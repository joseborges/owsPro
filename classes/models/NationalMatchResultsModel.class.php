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
 * Provides results current user's national team.
 */
class NationalMatchResultsModel implements IModel {
	private $_db;
	private $_i18n;
	private $_websoccer;

	public function __construct($db, $i18n, $websoccer) {
		$this->_db = $db;
		$this->_i18n = $i18n;
		$this->_websoccer = $websoccer;
	}

	/**
	 * (non-PHPdoc)
	 * @see IModel::renderView()
	 */
	public function renderView() {
		return getConfig("nationalteams_enabled");
	}

	/**
	 * (non-PHPdoc)
	 * @see IModel::getTemplateParameters()
	 */
	public function getTemplateParameters() {

		// get team info
		$teamId = NationalteamsDataService::getNationalTeamManagedByCurrentUser($this->_websoccer, $this->_db);
		if (!$teamId) {
			throw new Exception(getMessage("nationalteams_user_requires_team"));
		}

		$matchesCount = NationalteamsDataService::countSimulatedMatches($this->_websoccer, $this->_db, $teamId);

		// setup paginator
		$eps = 5;
		$paginator = new Paginator($matchesCount, $eps, $this->_websoccer);
		$paginator->addParameter("block", "national-match-results");

		$matches = array();
		if ($matchesCount) {
			$matches = NationalteamsDataService::getSimulatedMatches($this->_websoccer, $this->_db, $teamId,
					$paginator->getFirstIndex(), $eps);
		}

		return array("paginator" => $paginator, "matches" => $matches);
	}

}

?>