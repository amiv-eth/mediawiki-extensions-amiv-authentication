# amiv Authentication (MediaWiki Extension)

Extension to authenticate using amiv API

## Usage

Enable the extension with the following line in your `LocalSettings.php` file:

```php
wfLoadExtension('AmivAuthentication');
$wgAmivAuthenticationApiUrl = '<API-Domain>';
$wgAmivAuthenticationApiKey = '<api-key>'; // requires read permissions for `users`, `groups` and `groupmemberships`
$wgAmivAuthenticationDisablePasswordReset = true;
$wgAmivAuthenticationOAuthAutoRedirect = false;
$wgAmivAuthenticationOAuthRedirectProtocol = 'https';
$wgAmivAuthenticationOAuthClientId = '<oauth2-client-id>';
$wgAmivAuthenticationUserGroups = ['Wiki'];           // Groups which are allowed to use this tool
$wgAmivAuthenticationSysopGroups = ['admin'];         // Groups with will be granted `sysop` rights
$wgAmivAuthenticationAdditionalGroups = ['Vorstand']; // Groups which get directly assigned to mediawiki users
```

You need also to specify the following in your settings:

```php
$wgWhitelistRead = ['Special:AmivAuthentication'];
```
