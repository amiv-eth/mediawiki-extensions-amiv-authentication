# AMIV Authentication (MediaWiki Extension)

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
$wgAmivAuthenticationUserGroups = ['<wiki-group-id'];          // Groups which are allowed to use this tool
$wgAmivAuthenticationSysopGroups = ['<admin-group-id>'];       // Groups with will be granted `sysop` rights
$wgAmivAuthenticationAdditionalGroups = ['<board-group-id>'];  // Groups which get directly assigned to mediawiki users
```

Please note that the group arrays have to contain the group id!

You need also to specify the following in your settings:

```php
$wgWhitelistRead = ['Special:AmivAuthentication'];
```

Please note that the entry above is localized. For an instance set to german, it would be `Spezial:AmivAuthentication`.

## Development

You have to install `docker` and `docker-compose` in your development environment.

Comment the line 20 of `docker-compose.yml` and `cd` into your project directory.

```bash
sudo docker-compose up -d
```

Open your web browser and go to `localhost:8080`. Follow the setup dialog and place the downloaded `LocalSettings.php` into your project directory.

Enable line 20 of `docker-compose.yml` again.

```bash
sudo docker-compose up -d
```

Whenever some files should be updated on the running instance, execute the following command:

```bash
sudo docker-compose restart
```

## Troubleshooting

If the authentication does not work, but the app is loaded (e.g. OAuth redirect works), you might have to do a database update to create the custom table used by the extension. Execute the following command in the root directory of the wiki:

```bash
php maintenance/update.php
```

## License

Copyright (C) 2018 AMIV an der ETH, Sandro Lutz

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU Affero General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU Affero General Public License for more details.

You should have received a copy of the GNU Affero General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
