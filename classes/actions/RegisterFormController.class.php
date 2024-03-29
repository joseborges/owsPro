<?php
/******************************************************

  This file is part of OpenWebSoccer-Sim.

  OpenWebSoccer-Sim is free software: you can redistribute it
  and/or modify it under the terms of the
  GNU Lesser General Public License
  as published by the Free Software Foundation, either version 3 of
  the License, or any later version.

  OpenWebSoccer-Sim is distributed in the hope that it will be
  useful, but WITHOUT ANY WARRANTY; without even the implied
  warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
  See the GNU Lesser General Public License for more details.

  You should have received a copy of the GNU Lesser General Public
  License along with OpenWebSoccer-Sim.
  If not, see <http://www.gnu.org/licenses/>.

******************************************************/

/**
 * Validates registation form and creates a new (disabled) user in DB.
 */
class RegisterFormController implements IActionController {
	private $_i18n;
	private $_websoccer;
	private $_db;

	public function __construct(I18n $i18n, WebSoccer $websoccer, DbConnection $db) {
		$this->_i18n = $i18n;
		$this->_websoccer = $websoccer;
		$this->_db = $db;
	}

	/**
	 * (non-PHPdoc)
	 * @see IActionController::executeAction()
	 */
	public function executeAction($parameters) {

		// registration enabled?
		if (!getConfig("allow_userregistration")) {
			throw new Exception(getMessage("registration_disabled"));
		}

		// illegal user name?
		$illegalUsernames = explode(",", strtolower(str_replace(", ", ",",getConfig("illegal_usernames"))));
		if (array_search(strtolower($parameters["nick"]), $illegalUsernames)) {
			throw new Exception(getMessage("registration_illegal_username"));
		}

		// repeated e-mail correct?
		if ($parameters["email"] !== $parameters["email_repeat"]) {
			throw new Exception(getMessage("registration_repeated_email_notmatching"));
		}

		// repeated password correct?
		if ($parameters["pswd"] !== $parameters["pswd_repeat"]) {
			throw new Exception(getMessage("registration_repeated_password_notmatching"));
		}

		// check captcha
		if (getConfig("register_use_captcha")
				&& strlen(getConfig("register_captcha_publickey"))
				&& strlen(getConfig("register_captcha_privatekey"))) {

			include_once(BASE_FOLDER . "/lib/recaptcha/recaptchalib.php");

			$captchaResponse = recaptcha_check_answer(getConfig("register_captcha_privatekey"),
                                $_SERVER["REMOTE_ADDR"],
                                $_POST["recaptcha_challenge_field"],
                                $_POST["recaptcha_response_field"]);
			if (!$captchaResponse->is_valid) {
				throw new Exception(getMessage("registration_invalidcaptcha"));
			}
		}

		$columns = "COUNT(*) AS hits";
		$fromTable = "user";

		// check maximum number of users
		$maxNumUsers = (int)getConfig("max_number_of_users");
		if ($maxNumUsers > 0) {
			$wherePart = "status = 1";
			$result = $this->_db->querySelect($columns, $fromTable, $wherePart);
			$rows = $result->fetch_array();
			$result->free();

			if ($rows["hits"] >= $maxNumUsers) {
				throw new Exception(getMessage("registration_max_number_users_exceeded"));
			}
		}

		// check if e-mail or user exists
		$wherePart = "UPPER(nick) = '%s' OR UPPER(email) = '%s'";
		$result = $this->_db->querySelect($columns, $fromTable, $wherePart, array(strtoupper($parameters["nick"]), strtoupper($parameters["email"])));
		$rows = $result->fetch_array();
		$result->free();
		if ($rows["hits"]) {
			throw new Exception(getMessage("registration_user_exists"));
		}

		$this->_createUser($parameters, $fromTable);

		$this->_websoccer->addFrontMessage(new FrontMessage(MESSAGE_TYPE_SUCCESS,getMessage("register-success_message_title"),
				getMessage("register-success_message_content")));

		return "register-success";
	}

	private function _createUser($parameters, $fromTable) {
		$dbcolumns = array();

		$dbcolumns["nick"] = $parameters["nick"];
		$dbcolumns["email"] = strtolower($parameters["email"]);
		$dbcolumns["passwort_salt"] = SecurityUtil::generatePasswordSalt();
		$dbcolumns["passwort"] = SecurityUtil::hashPassword($parameters["pswd"], $dbcolumns["passwort_salt"]);
		$dbcolumns["datum_anmeldung"] = getNowAsTimestamp();
		$dbcolumns["schluessel"] = str_replace("&", "_", SecurityUtil::generatePassword());
		$dbcolumns["status"] = 2;
		$dbcolumns["lang"] = $this->_i18n->getCurrentLanguage();

		if (getConfig("premium_initial_credit")) {
			$dbcolumns["premium_balance"] = getConfig("premium_initial_credit");
		}

		$this->_db->queryInsert($dbcolumns, $fromTable);

		// get user id
		$columns = "id";
		$wherePart = "email = '%s'";
		$result = $this->_db->querySelect($columns, $fromTable, $wherePart, $dbcolumns["email"]);
		$newuser = $result->fetch_array();
		$result->free();

		$querystr = "key=" . $dbcolumns["schluessel"] ."&userid=" . $newuser["id"];
		$tplparameters["activationlink"] = getInternalActionUrl("activate", $querystr, "activate-user", TRUE);

		// send e-mail
		EmailHelper::sendSystemEmailFromTemplate($this->_websoccer, $this->_i18n,
			$dbcolumns["email"],
			getMessage("activation_email_subject"),
			"useractivation",
			$tplparameters);

		// trigger plug-ins
		$event = new UserRegisteredEvent($this->_websoccer, $this->_db, $this->_i18n,
				$newuser["id"], $dbcolumns["nick"], $dbcolumns["email"]);
		PluginMediator::dispatchEvent($event);
	}
}

?>