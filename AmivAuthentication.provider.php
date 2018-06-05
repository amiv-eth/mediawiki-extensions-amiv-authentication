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

class AmivAuthenticationProvider
    extends AbstractPasswordPrimaryAuthenticationProvider
{
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
        global $wgAmivAuthenticationAdditionalGroups, $wgAmivAuthenticationUserGroups, $wgAmivAuthenticationSysopGroups;

        $req = AuthenticationRequest::getRequestByClass($reqs, PasswordAuthenticationRequest::class);
        if (!$req || $req->username === null || $req->password === null) {
            return AuthenticationResponse::newAbstain();
        }

        $username = $req->username;
        $pass = rawurlencode($req->password);
        list($httpcode, $session) = ApiUtil::post('sessions?embedded={"user":1}', 'username=' .$username .'&password=' .$pass);

        if ($httpcode === 201) {
            $apiUser = $session->user;
            try {
                $user = ApiSync::syncUser($apiUser);
            } catch (\Exception $e) {
            }

            if ($user) {
                $_SESSION['api_session_id'] = $session->_id;
		        $_SESSION['api_session_token'] = $session->token;
                return AuthenticationResponse::newPass($user->getName());
            } else {
                // Remove newly created session as it is not used anymore
                ApiUtil::delete('sessions/' .$session->_id, $session->_etag, $session->token);
            }
        }

        $canonicalUsername = User::getCanonicalName($username, 'usable');
        $userObject = User::newFromName($canonicalUsername);
        if ($userObject && !in_array("sysop", $userObject->getGroups())) {
            return AuthenticationResponse::newFail(wfMessage('wrongpassword'));
        }

        // just abstain so local accounts can still be authenticated
        return AuthenticationResponse::newAbstain();
    }

    public function postAuthentication($user, AuthenticationResponse $response) {
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
        global $wgAmivAuthenticationDisablePasswordReset;
        if ($wgAmivAuthenticationDisablePasswordReset) {
            return \StatusValue::newFatal('password change not allowed');
        }
        return \StatusValue::newGood();
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
    }
}