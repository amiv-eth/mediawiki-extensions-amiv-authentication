<?php
/**
 * @copyright Copyright (c) 2016, AMIV an der ETH
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

use MediaWiki\Auth\AuthManager;
use MediaWiki\Auth\AbstractPasswordPrimaryAuthenticationProvider;
use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Auth\PasswordAuthenticationRequest;
use MediaWiki\Auth\AuthenticationResponse;

class AMIVAuthenticationProvider
    extends AbstractPasswordPrimaryAuthenticationProvider
{
    private $apiToken = "";
    private $apiGroupMemberships = [];

    public function __construct() {
        parent::__construct();
    }

    public function getAuthenticationRequests($action, array $options) {
        switch ($action) {
            case AuthManager::ACTION_LOGIN:
            case AuthManager::ACTION_CREATE:
                return [new PasswordAuthenticationRequest];
            default:
                // Other actions not supported!
                return [];
        }
    }

    public function beginPrimaryAuthentication(array $reqs) {
        global $wgAMIVAuthenticationAdditionalGroups, $wgAMIVAuthenticationUserGroups, $wgAMIVAuthenticationSysopGroups;

        $req = AuthenticationRequest::getRequestByClass($reqs, PasswordAuthenticationRequest::class);
        if (!$req || $req->username === null || $req->password === null) {
            return AuthenticationResponse::newAbstain();
        }

        $username = User::getCanonicalName($req->username, 'usable');
        $user = strtolower($username);
        $pass = rawurlencode($req->password);
        list($httpcode, $response) = APIUtil::post("sessions", "username=$user&password=$pass");
        if($httpcode === 201) {
            $this->apiToken = $response->token;
            // retrieve list of the users groups from AMIV API
            list($httpcode, $response) = APIUtil::get('groupmemberships?where={"user":"' .$response->user .'"}&embedded={"group":1}', $this->apiToken);
            if ($httpcode == 200) {
                $this->apiGroupMemberships = $response->_items;

                $valid = false;
                foreach ($this->apiGroupMemberships as $item) {
                    $group = $item->group;
                    if (in_array($group->name, $wgAMIVAuthenticationAdditionalGroups) ||
                        in_array($group->name, $wgAMIVAuthenticationSysopGroups) ||
                        in_array($group->name, $wgAMIVAuthenticationUserGroups))
                    {
                        $valid = true;
                    }
                }
                if ($valid) {
                    return AuthenticationResponse::newPass($username);
                }
            }
        }

        $userObject = User::newFromName($username);
        if ($user && !in_array("sysop", $userObject->getGroups())) {
            return AuthenticationResponse::newFail(wfMessage('wrongpassword'));
        }

        // just abstain so local accounts can still be authenticated
        return AuthenticationResponse::newAbstain();
    }

    public function postAuthentication($user, AuthenticationResponse $response) {
        if (($response->status == AuthenticationResponse::PASS) && ($user !== false) && ($user instanceof \User)) {
            $this->updateGroupMemberships($user);
        }
    }

    public function testUserCanAuthenticate($username) {
        $username = User::getCanonicalName($username, 'usable');
        if ($username === false) {
            return false;
        }       
        return true;
    }

    public function providerRevokeAccessForUser($username) {
        $username = User::getCanonicalName($username, 'usable');
        if ($username === false) {
            return;
        }
        $user = User::newFromName($username);
        if ($user) {
            // Reset the password on the local wiki user 
            // to prevent a former AMIV member from logging in.
            $user->setPassword(null);
            $user->setToken();
        }
    }

    public function testUserExists($username, $flags = User::READ_NORMAL) {
        $username = User::getCanonicalName($username, 'usable');
        if ($username === false) {
            return false;
        }
        return true;
    }

    public function providerAllowsPropertyChange($property) {
        return false;
    }

    public function providerAllowsAuthenticationDataChange(
        AuthenticationRequest $req, $checkData = true
    ) {
        return \StatusValue::newGood('ignored');
    }

    public function providerChangeAuthenticationData(AuthenticationRequest $req) {
    }

    public function accountCreationType() {
        return self::TYPE_NONE;
    }

    public function testForAccountCreation($user, $creator, array $reqs) {
        return \StatusValue::newGood();
    }

    public function beginPrimaryAccountCreation($user, $creator, array $reqs) {
        if ($this->accountCreationType() === self::TYPE_NONE) {
            throw new \BadMethodCallException('Shouldn\'t call this when accountCreationType() is NONE');
        }
        return AuthenticationResponse::newAbstain();
    }
    
    public function autoCreatedAccount($user, $source) {
        $this->updateGroupMemberships($user);
    }

    private function updateGroupMemberships($user) {
        global $wgAMIVAuthenticationSyssopGroups, $wgAMIVAuthenticationUserGroups, $wgAMIVAuthenticationAdditionalGroups;

        // Remove all group memberships
        foreach ($user->getGroupMemberships() as $item) {
            $item->delete();
        }

        foreach ($this->apiGroupMemberships as $item) {
            $group = $item->group;
            $validUser = false;

            if (in_array($group->name, $wgAMIVAuthenticationSyssopGroups)) {
                $user->addGroup("bureaucrat", $item->expiry);
                $user->addGroup("sysop", $item->expiry);
                $validUser = true;
            } else if (in_array($group->name, $wgAMIVAuthenticationAdditionalGroups) {
                $user->addGroup($group->name, $item->expiry);
                $validUser = true;
            } else if (in_array($group->name, $wgAMIVAuthenticationUserGroups) || $validUser) {
                $user->addGroup("user", $item->expiry);
            } 
        }

        // Set local password to null to prevent login if API is not accessible
        $user->setPassword(null);
    }
}