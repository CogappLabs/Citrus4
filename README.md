🍊 Citrus for Craft 4
=====
A Craft CMS 4 plugin for purging and banning Varnish caches when elements are saved.

It supports:

🍊 Banning/Purging via section bindings<br>
🍊 Admin or HTTP banning<br>
🍊 On-demand banning and purging<br>
🍊 Multilingual sites<br>
🍊 Multiple Varnish hosts

Installation
---
Citrus 4 should be installed using composer.

1. Add the repository to your composer JSON file

```
// composer.json
...
"repositories": [
	...
	{
		"type": "path",
		"url": "https://github.com/dentsucreativeuk/Citrus4.git"
	}
]
```

2. Add the package

```
composer require dentsucreativeuk/citrus @dev
```

3. Enable the Citrus plugin in Craft (Settings > Plugins).
4. [Override default configuration if necessary](https://github.com/njpanderson/Citrus#configuration).
5. [Create optional bindings if needed](https://github.com/njpanderson/Citrus#bindings).

Configuration
---
To configure Citrus, create a new `citrus.php` config file in your config folder, and override settings as needed. The following settings are the default:

| Option | Default |
| --- | --- |
| `varnishHosts` | `[]` |
| `varnishUrl` | (Current site base URL) |
| `varnishHostName` | (Current hostname) |
| `purgeEnabled` | (Dependent on `HTTP_X_VARNISH` header) |
| `purgeRelated` | `false` |
| `logAll` | `0` (Only log important messages) |
| `bansSupported` | `false` |
| `adminIp` | (Empty) |
| `adminPort` | (Empty) |
| `adminSecret` | (Empty) |
| `banQueryHeader` | `Ban-Query-Full` |
| `adminCookieName` | `CitrusAdmin` |

The `varnishUrl` setting can also be an array if you are running a multi language site, e.g:

```php
'varnishUrl' => array(
    'no' => 'http://your-varnish-server.com/no/',
    'en' => 'http://your-varnish-server.com/en/',
),
```

#### varnishHosts
Used to configure multiple Varnish hosts. See below for options.

#### varnishUrl
The url to your Varnish server. Usually this is your site url, but it could be different if you don't purge through a private connection, or if you use the IP directly to bypass CloudFlare or similar services. If your site url and the varnish url is different, make sure you handle this in your VCL file.

#### varnishHostName
If the Varnish server cannot be directly referenced via its host name, Citrus has an option to explicitly provide the host name in a second setting. For example:

```php
	'varnishUrl' => 'http://123.123.123.120',
	'varnishHostName' => 'myawesomewebsite.com',
```

All HTTP based requests will be sent to the server by its IP but a `Host` header will also define the host name required.

#### purgeEnabled
Enables or disables purging on Entry editing within the Citrus plugin. On-demand purging is always available.

#### purgeRelated
Enables or disables purging of related urls when an element is saved. This should normally be enabled to make sure that all relevant urls are updated, but could be disabled on high traffic websites to make sure the cache stays as warm as possible.

#### logAll
When set to `1` some additional logging is forced even if devMode is disabled. Useful for debugging in production environments without having to enable devMode.

#### purgeUrlMap
A lookup map for purging additional urls that needs it when a given url is purged.

#### bansSupported
If either the admin socket or HTTP banning can be performed on the Varnish hosts, set this option to `true`. Otherwise use `false` and no banning related options will appear. **Important:** changing this option after ban bindings have been added can lead loss of binding settings.

#### adminIP, adminPort & adminSecret
If using the admin socket method for adding bans, the relevant details for the Varnish host IP, admin port and secret file (usually found in `/etc/varnish`) will need to be entered here. If one of these three options is omitted and `bansSupported` is `true`, Citrus will fall back to the HTTP method for banning.

#### banQueryHeader
Defines the header to use for HTTP banning. See "Configuring Varnish" for more information.

#### adminCookieName
When logging into the admin, Citrus will set a recognisable cookie which can be optionally used within the Varnish VCL to disable the cache for administrative users on the front end. The name of the cookie can be set with this variable.

So long as you're already passing the admin area with Varnish, an example syntax for preventing caching with this cookie would be as follows:

```
# Disable cache for authenticated users
if (req.http.Cookie ~ "CitrusAdmin=1") {
	return (pass);
}
```

If this feature is not required, then set the name to `false`.

Defining multiple Varnish hosts
---
If the CMS is configured behind more than one Varnish host, purge and ban commands can be duplicated to each host required. This can be done by defining the **`varnishHosts`** option.

An example setup for a multilingual, multi-Varnish system could look as follows:

```php
	'varnishHosts' => [
		'main' => [
			'url' => [
				'en_gb' => 'http://123.123.123.121/',
				'it' => 'http://123.123.123.121/it/',
				'ja' => 'http://123.123.123.121/ja/',
			],
			'hostName' => 'myhostname.com',
			'adminIP' => '123.123.123.121',
			'adminPort' => '6082',
			'adminSecret' => '<secret_key>',
		],
		'failover1' => [
			'url' => [
				'en_gb' => 'http://123.123.123.122/',
				'it' => 'http://123.123.123.122/it/',
				'ja' => 'http://123.123.123.122/ja/',
			],
			'hostName' => 'myhostname.com',
			'adminIP' => '123.123.123.122',
			'adminPort' => '6082',
			'adminSecret' => '<secret_key>',
		],
	]
```

It's up to you what name you give each host, which is defined as the array key for each array within `varnishHosts`. The following options can be given for each host:

| Option | See in main config... |
| --- | --- |
| `url` | `varnishUrl` |
| `hostName` | `varnishHostName` |
| `adminIp` | `adminIp` |
| `adminPort` | `adminPort` |
| `adminSecret` | `adminSecret` |

### Dynamic hosts

If the number of hosts is not fixed or need to be computed at run time, `varnishHosts` may be defined as a closure:


```php
	'varnishHosts' => function() {
		return [
			'main' => [
				'url' => [
					'en_gb' => 'http://123.123.123.121/'
				],
				// ...
			],
			'failover1' => [
				// ...
			],
		];
	}
```

So long as the return value matches what Citrus would expect in its regular array form, the function may do whatever work it needs to in order to compute an array of hosts.


How Citrus works
---
When an element is saved, the plugin collects the urls *that it thinks need to be updated*, and creates a new task that sends purge requests to the Varnish server for each url.

If `purgeRelated` is enabled, the urls for all related elements are also purged. If the saved element is an entry, all directly related entry urls, all related category urls, and all urls for elements related through an entries Matrix blocks, is purged. If the saved element is an asset, all urls for elements related to that asset, either directly or through a Matrix block, is purged. And so on. The element types taken into account is
entries, categories, matrix blocks and assets.

**Warning:** Enabling `purgeRelated` can slow down saving of entries — especially if there are multiple locales in a site as well as large amounts of relational fields. Use this setting with care!

Additionally, if the element is an entry, any bindings configured for the section and entry type being edited will also be performed.

The plugin also adds a new element action to entries and categories for purging individual elements manually. When doing this, related elements are not purged, only the selected elements.

Bindings
---
Bindings are premade purge or ban requests which will be sent to your Varnish hosts whenever an entry within the section and entry type is either created or edited.

To create a binding within the "Bindings" navigation of the "Citrus" plugin, first choose the section you wish to edit and then click "Edit bindings". You will be taken to a screen showing all of the **entry types** available to that section, and the bindings can be set within each type.

Click the "Add a row" button to add a new binding, then choose the type of binding, then enter the appropriate query required and click on "Save bindings" to save all of the bindings at once. As with the on-demand banning, query template keys can be used within bans.

The binding types available are as follows:

| Type | Example / Description |
| ---  | --- |
| `PURGE` | `/my/uri?query=1` <br> A simple purge request will be sent to the Varnish hosts for the URI defined. URIs should start with a forward slash. |
| `BAN` | `^.*\.jpe?g` <br> A ban query will be sent to the Varnish hosts. The ban query in this case will be prefixed with the active hostname, which means all that is required to be entered is a regular expression for the URI(s) to ban. |
| `FULLBAN` | `req.http.host == ${hostName} && req.url ~ \?.+ ` <br> A full ban query will be sent to the Varnish hosts. The entire query can be entered here. |

Setting cache headers in your templates
---
Unless overridden within the VCL configuration, Varnish uses cache headers sent by Craft to determine if/how to cache a request. You can configure this in your webserver, but it can be more flexible to configure this within the templates:

```twig
// _layout.twig
{% if expiryTime is not defined %}{% set expiryTime = '+1 week' %}{% endif %}

{% if expiryTime != '0' %}
	{% set expires = now | date_modify(expiryTime) %}
	{% header "Cache-Control: max-age=" ~ (expires.timestamp - now.timestamp) %}
	{% header "Pragma: cache" %}
	{% header "Expires: " ~ expires.rfc1123() %}
{% endif %}
```

The expiry time can then be overridden within individual templates like so:

```twig
// article.twig
{% extends "_layout" %}
{% set expiryTime = '+60 mins' %}
```

Configuring Varnish
---
If not using the admin port method for banning, a small addition will need to be made to the VCL configuration for Varnish in order to enable bans over HTTP.

Citrus sends bans over HTTP by adding a new HTTP Header named "Ban-Query-Full" by default. This header can then be used within the VCL code to instantiate a single ban. The following example is how to handle this header:

```
if (req.method == "BAN") {
	if (!client.ip ~ purge) { # purge is the ACL defined at the begining
		# Not from an allowed IP? Then die with an error.
		return (synth(405, "This IP is not allowed to send PURGE/BAN requests."));
	}

	if (req.http.Ban-Query-Full) {
		ban(req.http.Ban-Query-Full);
		return (synth(200, "Ban added"));
	} else {
		return (synth(400, "Ban query sent without Ban-Query-Full headers."));
	}
}
```

This will enable Varnish to accept ban requests over HTTP from the Citrus plugin.
