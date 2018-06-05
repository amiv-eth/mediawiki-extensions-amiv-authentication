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

class AmivAuthenticationHooks {
	public static function onUserLoginForm(&$tpl) {
		global $wgRequest, $wgAmivAuthenticationApiUrl, $wgAmivAuthenticationOAuthAutoRedirect,
		  $wgAmivAuthenticationOAuthRedirectProtocol, $wgAmivAuthenticationOAuthClientId;

		if (!$wgAmivAuthenticationApiUrl || !$wgAmivAuthenticationOAuthClientId) {
			return; // configuration is incomplete
		}

		if (!isset($_SESSION['amiv.oauth_state'])) {
			$state = bin2hex(random_bytes(32));
			$_SESSION['amiv.oauth_state'] = $state;
		} else {
			$state = $_SESSION['amiv.oauth_state'];
		}

		$redirectUri = Skin::makeSpecialUrl('AmivAuthentication', 'returnto='.$wgRequest->getVal('returnto'), $wgAmivAuthenticationOAuthRedirectProtocol);
		$providerUrl = $wgAmivAuthenticationApiUrl .'/oauth?response_type=token&client_id=' 
			.urlencode($wgAmivAuthenticationOAuthClientId) .'&state=' .$state .'&redirect_uri=' .urlencode($redirectUri);

		// Add amiv SSO button before login form
		$header = $tpl->get('header');
		$header .= '<a class="mw-ui-button mw-ui-progressive dataporten-button" href="' . $providerUrl . '">Login with amiv SSO</a>';
		$tpl->set('header', $header);
		if (!isset($_GET['no_redirect']) && $wgAmivAuthenticationOAuthAutoRedirect) {
			header('Location: ' . $providerUrl);
			exit();
		}
  }

	public static function onUserLogout(&$user) {
		$sessionId = $_SESSION['api_session_id'];
		$token = $_SESSION['api_session_token'];
		list($httpcode, $session) = ApiUtil::get('sessions/' .$sessionId, $token);
		if ($httpcode == 200) {
			ApiUtil::delete('sessions/' .$session->_id, $session->_etag, $token);
		}
		unset($_SESSION['api_session_id']);
		unset($_SESSION['api_session_token']);
		return true;
  }

	public static function onLoadExtensionSchemaUpdates(DatabaseUpdater $updater) {
		$updater->addExtensionTable('amiv_users',	__DIR__ . '/sql/users.sql');
		$updater->doUpdates();
		return true;
	}
}
