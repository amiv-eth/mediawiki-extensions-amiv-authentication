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

/** SpecialPage used as OAuth2 redirect page */
class AmivAuthenticationSpecial extends SpecialPage {

	public function __construct() {
		global $wgAmivAuthenticationApiUrl, $wgAmivAuthenticationOAuthClientId;
		if (!$wgAmivAuthenticationApiUrl || !$wgAmivAuthenticationOAuthClientId) {
			return; // configuration is incomplete
		}

		parent::__construct('AmivAuthentication');
	}

	/** Called when the SpecialPage is requested. */
	public function execute($parameter) {
		global $wgOut, $wgRequest;

		$this->setHeaders();

		$state = $_SESSION['amiv.oauth_state'];

		if (!isset($_GET['access_token']) || !isset($_GET['state']) || 
			!$state || $_GET['state'] !== $state) {
				return $this->makeError('amivauthentication-bad-request');
		}
		$token = $_GET['access_token'];

		// Check if token is valid / the corresponding session exists
		list($httpcode, $session) = ApiUtil::get('sessions/' .$token .'?embedded={"user":1}', $token);

		if ($httpcode !== 200) {
			return $this->makeError('amivauthentication-invalid-token');
		}

		$_SESSION['api_session_id'] = $session->_id;
		$_SESSION['api_session_token'] = $session->token;
		$_SESSION['amiv.oauth_state'] = bin2hex(random_bytes(32));

		$apiUser = $session->user;
		return $this->login($apiUser, $session);
	}

	/** Performs action to log the given $apiUser in. */
	private function login($apiUser, $session) {
		try {
			$user = ApiSync::syncUser($apiUser);
		} catch (\Exception $e) {
		}

		if (!$user) {
			return $this->makeError('amivauthentication-no-permission');
		}

		$_SESSION['api_session_id'] = $session->_id;
		$_SESSION['api_session_token'] = $session->token;
		$user->setCookies(null, null, true);

		return $this->makeSuccess();
	}

	/** Called when the user has failed to log in */
	private function makeError(string $messageKey) {
		global $wgOut;

		$wgOut->showErrorPage('amivauthentication-error-title', $messageKey);
		return false;
	}

	/** Called when the user has successfully logged in */
	private function makeSuccess() {
		global $wgOut;

		if($_GET['returnto']) {
			$title = Title::newFromText($_GET['returnto']);
		} else {
			$title = Title::newMainPage();
		}
		$wgOut->redirect($title->getFullUrl());
		return true;
	}
}
