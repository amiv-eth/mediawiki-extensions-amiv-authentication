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

class ApiSync {

    private const table = 'amiv_users';

    /**
     * Sync user information and group memberships with the given user
     *
     * @param dict $apiUser
     * @return \User local user if user has enough permissions; null otherwise
     */
    public static function syncUser($apiUser) {
        [$userGroups, $additionalGroups, $sysopGroups] = self::getAllowedApiGroups();

        $groupIds = array_merge([], array_keys($userGroups), array_keys($additionalGroups), array_keys($sysopGroups));
        $groupmemberships = self::getApiUserGroupmemberships($apiUser, $groupIds);

        // User is not allowed to access the wiki
        if (count($groupmemberships) === 0) return null;

        // Create user
        if ($apiUser->nethz && strlen($apiUser->nethz) > 0) {
            $name = $apiUser->nethz;
        } else {
            $name = $apiUser->email;
        }
        $user = ApiSync::getOrCreateUser($apiUser->_id, User::getCanonicalName($name, 'usable'));

        // sync user information
        $user->setRealName($apiUser->firstname .' ' .$apiUser->lastname);
        $user->setEmail($apiUser->email);
        $user->confirmEmail();
        $user->setPassword(null);
        $user->saveSettings();

        // Update group memberships
        $groupsAdded = [];
        foreach ($groupmemberships as $item) {
            if (isset($additionalGroups[$item->group])) {
                $groupName = $additionalGroups[$item->group]->name;
                $user->addGroup($groupName);
                $groupsAdded[] = $groupName;
            } else if (isset($sysopGroups[$item->group])) {
                $user->addGroup('sysop');
                $groupsAdded[] = 'sysop';
            }
        }
        $localGroups = $user->getGroups();
        foreach ($localGroups as $group) {
            if (!in_array($group, $groupsAdded)) {
              var_dump($group);
              $user->removeGroup($group);
            }
        }
        return $user;
    }

    private static function getAllowedApiGroups() {
        global $wgAmivAuthenticationAdditionalGroups, $wgAmivAuthenticationSysopGroups, $wgAmivAuthenticationUserGroups;
        $groupNames = array_merge([], $wgAmivAuthenticationAdditionalGroups, $wgAmivAuthenticationSysopGroups, $wgAmivAuthenticationUserGroups);
        $additionalGroups = [];
        $sysopGroups = [];
        $userGroups = [];

        list($httpcode, $response) = ApiUtil::get('groups?where={"name":{"$in":' .json_encode($groupNames) .'}}');
        if ($httpcode == 200) {

            foreach($response->_items as $group) {
                if (in_array($group->name, $wgAmivAuthenticationAdditionalGroups)) {
                    $additionalGroups[$group->_id] = $group;
                } else if (in_array($group->name, $wgAmivAuthenticationSysopGroups)) {
                    $sysopGroups[$group->_id] = $group;
                } else if (in_array($group->name, $wgAmivAuthenticationUserGroups)) {
                    $userGroups[$group->_id] = $group;
                }
            }
        }
        return [$userGroups, $additionalGroups, $sysopGroups];
    }

    private static function getApiUserGroupmemberships($apiUser, $groupIds) {
        list($httpcode, $response) = ApiUtil::get('groupmemberships?where={"user":"' .$apiUser->_id .'","group":{"$in":' .json_encode($groupIds) .'}}');
        if ($httpcode == 200) {
            return $response->_items;
        }
        return [];
    }

    /** Get or create a local user based on the API user id or on the given name */
    private static function getOrCreateUser($apiUserId, $name) {
      $dbr = wfGetDB(DB_SLAVE);
      $row = $dbr->selectRow(
        self::table,
        '*',
        array('external_id' => $apiUserId)
      );

      if ($row) {
        // User already linked with a local account
        return User::newFromId($row->internal_id);
      }

      $user = User::newFromName($name, 'creatable');
      if (false === $user || $user->getId() != 0) {
        if (false === $user) {
          throw new MWException('Unable to create user.1');
        }
      }
      if (!$user->isLoggedIn()) { 
        // [New in MW 1.27] 
        // User does not exist, 
        // so we need to add them to the DB before changing fields.
        $user->addToDatabase(); 
      }

      // add link to api user
      $dbw = wfGetDB(DB_MASTER);
      $dbw->replace(
        self::table,
        ['internal_id', 'external_id'],
        ['internal_id' => $user->getId(), 'external_id' => $apiUserId],
        __METHOD__
      );
      return $user;
    }
}