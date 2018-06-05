<?php
/**
 * @copyright Copyright (c) 2018, AMIV an der ETH
 *
 * @author Sandro Lutz <code@temparus.ch>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

if ( !defined( 'MEDIAWIKI' )) {
	die('This is a MediaWiki extension, and must be run from within MediaWiki.');
}

class AmivAuthenticationDB {

  private const table = 'amiv_users';
  private static $instance;

  private $dbr;
  private $dbw;

  private function __construct() {
    $this->dbr = wfGetDB(DB_SLAVE);
    $this->dbw = wfGetDB(DB_MASTER);
  }

  public static function getInstance() {
    if (!self::$instance) {
      self::$instance = new AmivAuthenticationDB();
    }
    return self::$instance;
  }

  /**
   * Get user id of local account linked to the given API user id
   * @param string $apiUserId
   * @return int mediawiki user id
   */
  public function getLocalUserId($apiUserId) {
    $row = $this->dbr->selectRow(
      self::table,
      '*',
      array('external_id' => $apiUserId)
    );
    if ($row) {
      return $row->internal_id;
    }
    return null;
  }

  /**
   * Get id of API user linked to the given local user id
   * @param string $localUserId
   * @return int API user id
   */
  public function getApiUserId($localUserId) {
    $row = $this->dbr->selectRow(
      self::table,
      '*',
      array('internal_id' => $localUserId)
    );
    if ($row) {
      return $row->external_id;
    }
    return null;
  }

  /**
   * Create or update a new entry to link an API user with a local user account
   * @param int mediawiki user id
   * @param string API user id
   */
  public function createOrUpdateEntry($localUserId, $apiUserId) {
    $this->dbw->replace(
      self::table,
      ['internal_id', 'external_id'],
      ['internal_id' => $localUserId, 'external_id' => $apiUserId],
      __METHOD__
    );
  }
}
