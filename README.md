# Mendeley

A MediaWiki Extension to work with the Mendeley API

# Setup 

* Create new application at https://dev.mendeley.com/myapps.html
* Set `$wgMendeleyConsumerKey` to app `ID` and `MendeleyConsumerSecret` to app `SECRET` values
* Set `$wgMendeleyRedirectUrl` to the app redirect URL

That's all!

# Setup for private spaces

If you want to be able to fetch data from private groups & resources on Mendeley you'll need to
do some extra configurations in addition to the above:

* Navigate to https://mendeley-show-me-access-tokens.herokuapp.com/ and login with your Mendeley credentials
* Set `$wgMendeleyToken` to the `Access token` value
* Set `$wgMendeleyRefreshToken` to the `Refresh token` value

The authorization code flow token has a lifetime of 1 hour, it'll be automatically renewed as soon as it expires,
however, you can also use `maintenance/refreshToken.php` to force the token refresh.

**Important:** Memcached or Redis is required for this! Ensure you have one of these installed on your server
and configured as main cache type, eg:

```
$wgMemCachedServers = [ '127.0.0.1:11211' ];
$wgMainCacheType = CACHE_MEMCACHED;
```

# Usage

Use `mendeley` parser function to fetch Mendeley data:
```
{{#mendeley:doi=10.1103/PhysRevA.20.1521|parameter=title}}
```

Or `Special:MendeleyImport` special page to import groups of documents into your wiki

# Import configuration

By default, the extension will import documents as `Article` template with no fields and
name imported pages after the documents IDs on the Mendeley DB. This can be altered for your needs:

* `$wgMendeleyTemplate` - name of the template to use for imported pages
* `$wgMendeleyTemplateFields` - mapping scheme between template and Mendeley fields, see Mendeley fields names at
https://api.mendeley.com/apidocs/docs#!/documents/getDocuments, the format is `mendeley_field => template_field`
* `$wgMendeleyPageFormula` - formula for imported pages titles, mendeley fields tokens will be substituted,
eg: `Reference:<title>` will put imported page into a `Reference` namespace with a `title` field as page name

Example of fields mapping:
```
$wgMendeleyTemplateFields = [
	'type' => 'Type',
	'title' => 'Title',
	'abstract' => 'Abstract',
	'accessed' => 'Accessed',
	'authors' => '@Authors',
	'source' => 'Source',
	'volume' => 'Volume',
	'websites' => '@Websites',
	'identifiers/doi' => 'Doi',
	'keywords' => 'Keywords',
];
```

Not that some fields on the Mendeley are lists, use `@` char in front of template field name to split field values,
list delimiter can be configured via `$wgMendeleyTemplateFieldsMapDelimiter`, it's `;` by default.

You can also import groups via `maintenance/importGroup.php` script:

```
php maintenance/importGroup.php --group_id XXX
```

Please see more at https://www.mediawiki.org/wiki/Extension:Mendeley
