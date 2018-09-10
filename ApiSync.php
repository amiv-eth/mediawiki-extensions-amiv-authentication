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

    /**
     * Sync user information and group memberships with the given user
     *
     * @param dict $apiUser
     * @return \User local user if user has enough permissions; null otherwise
     */
    public static function syncUser($apiUser) {
        global $wgAmivAuthenticationUserGroups, $wgAmivAuthenticationAdditionalGroups, $wgAmivAuthenticationSysopGroups;
        $userGroups = $wgAmivAuthenticationUserGroups;
        $additionalGroups = $wgAmivAuthenticationAdditionalGroups;
        $sysopGroups = $wgAmivAuthenticationSysopGroups;

        $groupIds = array_merge([], $userGroups, array_keys($additionalGroups), $sysopGroups);
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
            if (in_array($item->group, $additionalGroups)) {
                list($httpcode, $response) = ApiUtil::get('groups/' .$item->group);
                if ($httpcode == 200) {
                    $groupName = $response->name;
                    $user->addGroup($groupName);
                    $groupsAdded[] = $groupName;
                }
            } else if (in_array($item->group, $sysopGroups)) {
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

    private static function getApiUserGroupmemberships($apiUser, $groupIds) {
        list($httpcode, $response) = ApiUtil::get('groupmemberships?where={"user":"' .$apiUser->_id .'","group":{"$in":' .json_encode($groupIds) .'}}');
        if ($httpcode == 200) {
            return $response->_items;
        }
        return [];
    }

    /** Get or create a local user based on the API user id or on the given name */
    private static function getOrCreateUser($apiUserId, $name) {
      $db = AmivAuthenticationDB::getInstance();
      $localUserId = $db->getLocalUserId($apiUserId);
      if ($localUserId) {
        // User already linked with a local account
        return User::newFromId($localUserId);
      }

      $user = User::newFromName($name, 'creatable');
      if (false === $user || $user->getId() != 0) {
        if (false === $user) {
          throw new MWException('Unable to create user.');
        }
      }
      if (!$user->isLoggedIn()) { 
        // [New in MW 1.27] 
        // User does not exist, 
        // so we need to add them to the DB before changing fields.
        $user->addToDatabase(); 
      }

      // add link to api user
      $db->createOrUpdateEntry($user->getId(), $apiUserId);
      return $user;
    }
}
