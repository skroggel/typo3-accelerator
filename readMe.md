# Accelerator
## Features
Speed up your TYPO3 installation:
* add Critical CSS (Above The Fold) inline *
* minify the HTML of your website
* use subdomains as CDN for your static contents (images, files, ...) to speed up the loading time of your website
* manage proxy-caching (e.g with Varnish) via page-properties
* can be used with Hetzner Varnish for Managed Servers
* reduce database size when storing JSON-arrays with persisted objects to the database.

# 1. HTML Minifier
## 1.1 Description

This function removes unnecessary breaks and spaces from the HTML code. This significantly reduces the size of the HTML code.

## 1.2 Settings
IMPORTANT: Since TYPO3 v10 the configuration is no longer possible via TypoScript because it is now implemented as Middleware.
It is now possible to configure it via your site-configuration (YAML) instead.
```
accelerator:
  htmlMinifier:
    enable: true
    excludePids: ''
    includePageTypes: '0'
  variants:
    -
      htmlMinifier:
        enable: false
      condition: 'applicationContext == "Development/Local"'
```
* **enable** activates the HTML Minify
* **excludePids** excludes the PIDs defined in this comma-separated list
* **includePageTypes** includes the pageTypes defined in this comma-separated list

Please note: The variants only work with the enable-attribute

**For a default-setup it is sufficient to set**
```
accelerator:
  htmlMinifier:
    enable: true
```

# 2. Pseudo-CDN
## 2.1 Description

With the CDN functionality it is possible to reduce the loading time of the website considerably by loading static content from subdomains of the respective website.
This is not a real CDN, but a "Pseudo-CDN", since no external servers are used.
It uses sub-domains of the given domain to deliver static contents and therefore it needs
1. a corresponding DNS-configuration
2. a wildcard TLS-certificate
to work properly. How this is done is not part of this documentation - but you can ask me any time :-)

Example without Pseudo-CDN
```
<picture >
    <source srcset="https://www.example.de/fileadmin/_processed_/e/e/csm_20191112-Unternehmensberatung-Desktop_20772b022d.jpg" media="(min-width: 1025px)">
    <source srcset="https://www.example.de/fileadmin/_processed_/a/2/csm_20191112-Unternehmensberatung-Tablet_e748abd11d.jpg" media="(min-width:769px)">
    <source srcset="https://www.example.de/fileadmin/_processed_/a/2/csm_20191112-Unternehmensberatung-Tablet_a7c14e847a.jpg" media="(min-width:481px)">
    <source srcset="https://www.rkwde/fileadmin/_processed_/4/9/csm_20191112-Unternehmensberatung-Mobile_3c8697c74b.jpg" media="(min-width:321px)">
    <source srcset="https://www.example.de/fileadmin/_processed_/4/9/csm_20191112-Unternehmensberatung-Mobile_f329b9da89.jpg" media="(min-width:0px)">
    <img src="data:image/gif;base64,R0lGODlhAQABAAAAACwAAAAAAQABAAA=" alt="Ihre Unternehmensberatung ">
</picture>
```
Example with active Pseudo-CDN
```
<picture >
    <source srcset="https://static1.example.de/fileadmin/_processed_/e/e/csm_20191112-Unternehmensberatung-Desktop_20772b022d.jpg" media="(min-width: 1025px)">
    <source srcset="https://static1.example.de/fileadmin/_processed_/a/2/csm_20191112-Unternehmensberatung-Tablet_e748abd11d.jpg" media="(min-width:769px)">
    <source srcset="https://static1.example.de/fileadmin/_processed_/a/2/csm_20191112-Unternehmensberatung-Tablet_a7c14e847a.jpg" media="(min-width:481px)">
    <source srcset="https://static2.rkwde/fileadmin/_processed_/4/9/csm_20191112-Unternehmensberatung-Mobile_3c8697c74b.jpg" media="(min-width:321px)">
    <source srcset="https://static2.example.de/fileadmin/_processed_/4/9/csm_20191112-Unternehmensberatung-Mobile_f329b9da89.jpg" media="(min-width:0px)">
    <img src="data:image/gif;base64,R0lGODlhAQABAAAAACwAAAAAAQABAAA=" alt="Ihre Unternehmensberatung ">
</picture>
```
## 2.2 Settings
IMPORTANT: Since TYPO3 v10 the configuration is no longer possible via TypoScript because it is now implemented as Middleware.
It is now possible to configure the PseudoCdn via your site-configuration (YAML) instead.
Important: the DNS has to be configured accordingly and a Wildcard-TLS-certificate has to be installed before activating this functionality
```
accelerator:
  pseudoCdn:
    enable: true
    maxConnectionsPerDomain: 4
    maxSubdomains: 100
    search: '/(href="|src="|srcset="|url\(\')\/?((uploads\/media|uploads\/pics|typo3temp\/compressor|typo3temp\/GB|typo3conf\/ext|fileadmin)([^"\']+))/i'
    ignoreIfContains: '/\.css|\.js|\.mp4|\.pdf|\?noCdn=1/'
  variants:
    -
      pseudoCdn:
        enable: false
      condition: 'applicationContext == "Development/Local"'
```
* **enable** activates the Pseudo-CDN
* **maxConnectionsPerDomain** defines how many resources are loaded from a subdomain.
* **maxSubdomains** defines how many sudomains there should be. If the value is set to 10 the subdomains static1.example.com to static10.example.com are used.
* **search** allows to override the regular expression for searching/replacing paths to static content
* **ignoreIfContains** allows to specify exclusion criteria for the pseudoCDN. Especially JS files should be excluded here (cross-domain issues)

Please note: The variants only work with the enable-attribute

**For a default-setup it is sufficient to set**
```
accelerator:
  pseudoCdn:
    enable: true
```

# 3. Inline Critical CSS (Above-The-Fold)
## 3.1 Description
To increase the loading speed of your website, so-called critical CSS (above the fold) can be stored in a separate file.
This critical CSS is then written inline into the HTML of the website, while the rest of the CSS (which is included via page.includeCSS) is added in such a way that it does not block the rendering of the page (as is otherwise usual).

The critical CSS can be specified per
* **url-path** (filesForPath): You can define regular expressions defining the url-path for your critical CSS
* **page-uid** (filesForPage): You can define lists of pids for your critical CSS
* **frontend-layout** (filesForLayout): We use the fields 'backend_layout' and 'backend_layout_next_level' from the pages-table here.

Important:
* The definintion-sets take action in the above order. So, if you define a rule by url-path it takes precedence over a rule by page-uid or frontend-layout.
* The rules for filesForPath and filesForPage are processed in the order of their definition. If a rule in these sets matches, the rules later on in this set are ignored.

If no critical CSS is specified, the CSS files are included normally.

## 3.2 Settings
IMPORTANT: Since TYPO3 v10 the configuration is no longer possible via TypoScript because it is now implemented as Middleware.
It is now possible to configure it via your site-configuration (YAML) instead.
```
accelerator:
  criticalCss:
    enable: true
    layoutField: backend_layout
    layoutFieldNextLevel: backend_layout_next_level
    filesForPath:
      '^/products/.+$':
        -
          EXT:accelerator/Tests/Integration/ContentProcessing/CriticalCssTest/Fixtures/Frontend/Files/criticalOne.css
    filesForPages:
      '2,3,4':
        -
          EXT:accelerator/Tests/Integration/ContentProcessing/CriticalCssTest/Fixtures/Frontend/Files/criticalOne.css
    filesForLayout:
      home:
        -
          EXT:accelerator/Tests/Integration/ContentProcessing/CriticalCssTest/Fixtures/Frontend/Files/criticalOne.css
        -
          EXT:accelerator/Tests/Integration/ContentProcessing/CriticalCssTest/Fixtures/Frontend/Files/criticalTwo.css
    filesToRemoveWhenActive:
      -
        EXT:accelerator/Tests/Integration/ContentProcessing/CriticalCssTest/Fixtures/Frontend/Files/Global/removeOne.css
      -
        EXT:accelerator/Tests/Integration/ContentProcessing/CriticalCssTest/Fixtures/Frontend/Files/Global/removeTwo.css
  variants:
    -
      criticalCss:
        enable: false
      condition: 'applicationContext == "Development/Local"'
```
* **enable** activates the critical CSS inclusion
* **layoutField** sets the field in page-properties which is used to determine the defined layout of the current page for including the correct css-files. The default value is "backend_layout". If you don't use another field you can omit this setting. **If you use your own property, make sure it is added as rootline-field.**
* **layoutFieldNextLevel** sets the field in page-properties which is used to determine the defined layout of the subpages for including the correct css-files. The default value is "backend_layout_next_level". If you don't use another field you can omit this setting. **If you use your own property, make sure it is added as rootline-field.**
* **filesForLayout** contains the keys and CSS-files that are to be included if the layout of the page matches the defined key. The keys are the values set in the defined layoutField- / layoutFieldNextLevel-property of the page ("pagets__"-prefix is removed). If there is no match, no file will be included. The example above would include the files criticalOne.css and criticalTwo.css on a page on which the backendLayout-property is set to "pagets__home".
* **filesToRemoveWhenActive** defines files that will be removed from page.includeCss if criticalCSS is activated and working on the current page

Please note: The variants only work with the enable-attribute.

If the pageType 1715339215 or the GET-Param no_critical_css=1 is used critical css is disabled.
This is helpful for rendering the critical css e.g. via NPM critical.

# 4. Proxy Caching e.g. with Varnish

**IMPORTANT: If you work with proxyCaching you definitivly need an extension
that handles the purging of its cache if changes happen to your content.
I strongly recommend sopsone-ch/varnish**

## 4.1 Description
This extension allows an extended setup with a proxy-cache like e.g. Varnish.
By default pages are excluded from proxy-caching if a frontend cookie is set. This is to prevent personal data from being cached and thus becoming visible to strangers.

Conversely, however, this means that proxy-caching is completely disabled for logged-in front-end users, so that they can no longer benefit from the performance improvement provided by a proxy-cache for the entire page.
To avoid this, this extension provides a field "Allow Proxy-Caching" in the page properties in the backend. This has the following options:

* **Inherit**: Inherits the settings from the page rootline
* **Deactivate**: Completely deactivates the ProxyCache for this page (and its subpages if applicable). This setting is useful e.g. for time-controlled plugins on the page
* **Activate**: Enables the ProxyCache explicitly even if a frontend cookie is set. This allows pages to be served from the Varnish cache even if a user is logged in. This should only be activated for pages that do not contain personal data.

The values of the fields are inherited down the page-tree and result in a HTTP-Header which is added:
```
X-Typo3-Proxycaching: 1
```

## 4.2 xkeys  sopsone-ch/varnish
The madj2k/t3-accelerator normally add a second HTTP-Header which contains a unique tag (HMAC-Key) for the whole website and another one for the current page.
They can be used for clearing the proxy-cache selectively based on tags.
```
xkey: 3ade06b8b96caba9c1717382f6dff9c7f049295e 2cbded6f9c51a25bc9f41b76e6834ea173066908
```
However, newer versions of sopsone-ch/varnish already implement xkey, so that if you activate it in sopsone-ch/varnish,
the madj2k/t3-accelerator will not send xkeys any more.

## 4.3 Hetzner Managed Server Varnish
Hetzner recently implemented an own Varnish-Setup for their Managed Servers.
They already use xkey with PURGE, but the header for the purge with xkey has to be `x-xkey-purge` in order to selectively purge the cache.
However sopsone-ch/varnish uses `xkey-purge` as header. The madj2k/t3-accelerator fixes this
if you set `proxyCachingMode` to `hetzner` in the extension-settings.

## 4.4 Example-Configuration for usage with Varnish Proxy-Cache
The proxy-caching setup only works if the appropriate settings are made in the proxy cache configuration.
Since proxy-cache configurations are very individual, only the relevant lines that control the behavior of the proxy-cache according to the above specifications are listed here.
The following configuration example assumes that ```madj2k/t3-accelerator``` is used together with ```opsone-ch/varnish```.

```
#
# Varnish file by Steffen Kroggel (developer@steffenkroggel.de)
# Version 1.0.5
# Date 2020/11/05
#

# Marker to tell the VCL compiler that this VCL has been adapted to the
# new 4.0 format.
vcl 4.0;
import std;
import xkey;
[...]

#========================================================
# Sub-routine when request is received
#========================================================
sub vcl_recv {
    # Happens before we check if we have this in cache already.
    #
    # Typically you clean up the request here, removing cookies you don't need,
    # rewriting the request, etc.

    [...]


    # Set X-Forwarded-For Header
    if (req.restarts == 0) {

        if (req.http.X-Forwarded-For) {
            set req.http.X-Forwarded-For = req.http.X-Forwarded-For + ", " + client.ip;
        } else {
            set req.http.X-Forwarded-For = client.ip;
        }
    }

    # Catch BAN Command for TYPO3 extension "Varnish"
    # This bans specific cache objects from cache
    if (
        (req.method == "BAN")
        || (req.method == "PURGE")
    ) {

        # Check if  IP is allowed to BAN/Purge
        if (req.http.X-Forwarded-For ~ "^127.0.0.0") {
            return(synth(405,"Not allowed. IP: " + req.http.X-Forwarded-For));
            #===
        }

        # Check if one single page of an instance is to be invalidated
        if (req.http.Varnish-Ban-TYPO3-Pid && req.http.Varnish-Ban-TYPO3-Sitename) {
            set req.http.n-gone = xkey.softpurge(req.http.Varnish-Ban-TYPO3-Sitename + "_" + req.http.Varnish-Ban-TYPO3-Pid);
            return (synth(200, "Softpurge. Invalidated " + req.http.n-gone + " objects with " + req.http.Varnish-Ban-TYPO3-Sitename + "_" + req.http.Varnish-Ban-TYPO3-Pid));
            #====

        # Check if all pages of an instance are to be invalidated
        } else if (req.http.Varnish-Ban-TYPO3-Sitename) {
            set req.http.n-gone = xkey.softpurge(req.http.Varnish-Ban-TYPO3-Sitename);
            return (synth(200, "Softpurge. Invalidated " + req.http.n-gone + " objects with " + req.http.Varnish-Ban-TYPO3-Sitename));
            #===

        # Fallback with minimum impact
        } else {
            ban("req.http.host == " + req.http.host + " && req.url == " + req.url);
            return(synth(200,"Ban. Banned " + req.http.host + req.url));
            #===
        }
    }


    [...]

    # Do not cache authorized content (login via htaccess)
    if (req.http.Authorization) {
        return (pass);
        #===
    }

    # Force lookup if the request is a no-cache request from the client (STRG + F5)
    if (req.http.Cache-Control ~ "no-cache") {
        return (pass);
        #===
    }

    # Do not cache image files, pdfs, xls, docs, zips, etc. This fills up the cache to fast
    # and it keeps WebP-optimization on apache side from working
    if (req.url ~ "(?i)\.(jpeg|jpg|png|gif|ico|webp|txt|pdf|gz|zip|doc|docx|ppt|pptx|xls|xlsx)$") {
        return (pass);
	    #===
    }

    # Do not cache TYPO3 BE User requests
    if (req.http.Cookie ~ "be_typo_user" || req.url ~ "^/typo3/") {
        return (pass);
        #===
    }

    # Do not cache non-cached pages or specific page types and params
    # We also ignore some RealUrl-coded params from extensions
    if (
        (req.url ~ "^/nc/?")
        || (req.url ~ "$/gitpull.php")
        || (req.url ~ "(\?|&)type=")
        || (req.url ~ "(\?|&)typeNum=")
        || (req.url ~ "(\?|&)no_cache=1")
        || (req.url ~ "(\?|&)no_varnish=1")
        || (req.url ~ "(\?|&)eID=")
        || (req.url ~ "(\?|&)cHash=")
        || (req.url ~ "/tx-[a-z-]+/")
        || (req.url ~ "/pagetype-[a-z-]+/")
        || (req.url ~ "^/phpmyadmin/?")
    ) {
        return (pass);
        #===
    }


    # unset grace-header from request
    unset req.http.grace;

    # Removes all cookies named __utm? (utma, utmb...) and __unam - tracking thing
    # Otherwise we might run into problems with caching
    set req.http.Cookie = regsuball(req.http.Cookie, "(^|(?<=; )) *__utm.=[^;]+;? *", "\1"); # Google Analytics
    set req.http.Cookie = regsuball(req.http.Cookie, "(^|(?<=; )) *__unam=[^;]+;? *", "\1"); # Google Analytics
    set req.http.Cookie = regsuball(req.http.Cookie, "(^|(?<=; )) *_et_coid=[^;]+;? *", "\1"); # eTracker
    set req.http.Cookie = regsuball(req.http.Cookie, "(^|(?<=; )) *isSdEnabled=[^;]+;? *", "\1"); # perso-net shit
    set req.http.Cookie = regsuball(req.http.Cookie, "(^|(?<=; )) *cookie_optin=[^;]+;? *", "\1"); # Cookie-Opt-In
    if (req.http.Cookie == "") {
        unset req.http.Cookie;
    }

    [...]
}

#========================================================
# Sub-routine after data from backend is received and before it is cached
#========================================================
sub vcl_backend_response {
    # Happens after we have read the response headers from the backend.
    #
    # Here you clean the response headers, removing silly Set-Cookie headers
    # and other mistakes your backend does.

    # Set TTL and grace
    set beresp.ttl = 1w;
    set beresp.grace = 3d;

    [...]

    # Only cache objects that are requested with frontend-cookies if ProxyCaching is set to 1
    if (
        (bereq.http.Cookie)
        && (! beresp.http.X-TYPO3-ProxyCaching == "1")
    ) {

        # Do not cache this object and do not keep decision
        set beresp.uncacheable = true;
        set beresp.ttl = 0s;
        set beresp.grace = 0s;
        return (deliver);
    }


    # Check for some things in the response-header that indicate that we should not cache
    # e.g. we do NOT cache contents that are about to set a cookie
    # or where ProxyCaching is set to 2
    if (
        (beresp.http.Set-Cookie)
        || (beresp.http.Vary == "*")
        || (beresp.http.Authorization)
        || (beresp.http.Pragma ~ "nocache")
        || (beresp.http.Cache-Control ~ "no-cache")
        || (beresp.http.X-TYPO3-ProxyCaching == "2")

        # TYPO3 uses "private" when INT-Scripts are used!
        # so we check for ProxyCaching variable in addition
        || (
            (beresp.http.Cache-Control ~ "private")
            && (! beresp.http.X-TYPO3-ProxyCaching == "1")
        )
    ) {

        # Do not cache this object and do not keep the decision
        set beresp.uncacheable = true;
        set beresp.ttl = 0s;
        set beresp.grace = 0s;
        return (deliver);
        #===
    }

    return (deliver);
    #===

}

#========================================================
# Sub-routine after object is loaded from cache
#========================================================
sub vcl_hit {

    [...]

    # Based on the already cached object we check if there is login sensitive data allowed on the cached pages
    # If so, we pass to backend if a cookie is set
    if (
        (! obj.http.X-TYPO3-ProxyCaching == "1")
        && (req.http.Cookie)
    ){
        return (pass);
        #===
    }

   [...]
}

#========================================================
# Sub-routine before delivering final data
#========================================================
sub vcl_deliver {

    # Happens when we have all the pieces we need, and are about to send the
    # response to the client.
    #
    # You can do accounting or modifying the final object here.

    [...]

    # Remove cache control if it isn't needed
    if (resp.http.X-TYPO3-ProxyCaching ~ "1") {
	    unset resp.http.cache-control;
    }

    [...]

    # Remove entries related to Varnish-Extension and Accelerator
    unset resp.http.xtag;
    unset resp.http.X-TYPO3-ProxyCaching;

    [...]
}

```
