# Accelerator
## 1. Features
__Detailed description coming soon...__

### 1.1 Responsive Image- Library
This extension comes with a customizable library for responsive images which can be used in your own extensions to generate responsive images.
#### 1.1.1 Basic Usage
You can either use it only with a FileReference- Uid
```
<f:cObject typoscriptObjectPath="lib.txAccelerator.responsiveImage" data="{page.txAcceleratorTeaserImage.uid}" />
```
__OR__

with a direct path to the source image
```
<f:cObject typoscriptObjectPath="lib.txAccelerator.responsiveImage" data="EXT:rkw_related/Resources/Public/Images/Logo.png"/>
```
__OR__

by defining all params according to your needs
```
<f:cObject typoscriptObjectPath="lib.txAccelerator.responsiveImage" data="{file: {page.txAcceleratorTeaserImage.uid}, treatIdAsReference: 1, title: 'Titel', additionalAttributes: 'class=\"test\"'}" />
```
#### 1.1.2 Example for extended usage: Responsive images from media-field in pages
You can include the Lib into your own TypoScript and customize it according to your needs.

##### TypoScript
```
lib.txMyExtension {

    keyvisual {

        article = FLUIDTEMPLATE
        article {

            file = {$plugin.tx_myExtension.view.partialRootPath}/FluidTemplateLibs/Keyvisual/Article.html

            // load images from media-field of current page
            dataProcessing {
                10 = TYPO3\CMS\Frontend\DataProcessing\FilesProcessor
                10 {
                    references {
                        table = pages
                        fieldName = media
                    }
                    as = images
                }
            }

            // inherit all settings from responsive image lib
            settings < lib.txAccelerator.responsiveImage.settings
            settings {

                // add class-tag
                additionalAttributes = class="article-image"

                // remove desktop-breakpoint because we only need 900px as maximum width
                breakpoint {
                    desktop >
                }

                // override maxWidth for tablet breakpoint  (default: 1024px)
                maxWidth {
                    tablet = 900
                }

                // set all relevant cropVariants to customized one
                cropVariant {
                    tablet = articleDesktop
                    mobile2 = articleDesktop
                    mobile = articleDesktop
                    fallback = articleDesktop
                }
            }
        }
    }
}
```
##### FluidTemplateLibs/KeyVisual/Article.html
```
<html xmlns:f="http://typo3.org/ns/TYPO3/CMS/Fluid/ViewHelpers" data-namespace-typo3-fluid="true">

    <f:if condition="{images.0}">
        <f:switch expression="{images.0.type}">
            <f:case value="2">
                <f:render section="Image" arguments="{file: images.0}" />
            </f:case>
            <f:case value="4">
                <f:render section="Video" arguments="{file: images.0}" />
            </f:case>
            <f:defaultCase>
                <!-- Nothing -->
            </f:defaultCase>
        </f:switch>
    </f:if>

    <!-- ======================================================================== -->

    <f:section name="Image">
        <f:cObject typoscriptObjectPath="lib.txAccelerator.responsiveImage" data="{file: '{file.uid}', treatIdAsReference: 1, settings: settings}" />
    </f:section>

    <f:section name="Video">
        <f:media class="article-video" file="{file}" width="2000" alt="{file.alternative}" title="{file.title}" additionalConfig="{controls: '0', loop: '1', autoplay: '1', modestbranding:'1', no-cookie: '1'}" />
    </f:section>
</html>
```
##### Call via Partial
```
<html xmlns:f="http://typo3.org/ns/TYPO3/CMS/Fluid/ViewHelpers" data-namespace-typo3-fluid="true">

    <!-- article image or video -->
    <f:cObject typoscriptObjectPath="lib.txMyExtension.keyvisual.article" data="{data}" />

</html>
```

#### 1.1.3 Example for extended usage: Responsive images from media-field in pages with inheritance
You can include the Lib into your own TypoScript and customize it according to your needs.

##### TypoScript
```
lib.txmyExtension {

    keyvisual {

        publication = COA
        publication {

            10 = FILES
            10 {

                references {
                    table = pages
                    data = levelfield: -1, media, slide
                }

                renderObj = COA
                renderObj {

                    5 = LOAD_REGISTER
                    5 {
                        imageIdList.cObject = TEXT
                        imageIdList.cObject.data = register:imageIdList
                        imageIdList.cObject.wrap = |,{file:current:uid_local}
                        imageIdList.cObject.wrap.insertData = 1
                    }
                }
            }

            20 = FLUIDTEMPLATE
            20 {

                file = {$plugin.tx_myExtension.view.partialRootPath}/FluidTemplateLibs/Keyvisual/Publication.html

                // load images from media-field with inheritance
                dataProcessing {
                    10 = TYPO3\CMS\Frontend\DataProcessing\FilesProcessor
                    10 {

                        files.data = register:imageIdList
                        as = images
                    }
                }

                // get all settings from responsive image lib
                settings < lib.txAccelerator.responsiveImage.settings
                settings {

                    // add class-tag
                    additionalAttributes = class="publications-article__picture"

                    // remove desktop-breakpoint because we only need 900px as maximum width
                    breakpoint {
                        desktop >
                    }

                    // override maxWidth for tablet breakpoint  (default: 1024px)
                    maxWidth {
                        tablet = 900
                    }

                    // set all relevant cropVariants to customized one
                    cropVariant {
                        tablet = articleDesktop
                        mobile2 = articleDesktop
                        mobile = articleDesktop
                        fallback = articleDesktop
                    }
                }
            }

            90 = RESTORE_REGISTER
        }
    }
}
```
##### FluidTemplateLibs/KeyVisual/Publication.html
```
<html xmlns:f="http://typo3.org/ns/TYPO3/CMS/Fluid/ViewHelpers" data-namespace-typo3-fluid="true">

   <f:cObject typoscriptObjectPath="lib.txAccelerator.responsiveImage" data="{file: '{images.0.uid}', treatIdAsReference: 0, settings: settings}" />

</html>
```
##### Call via Partial
```
<html xmlns:f="http://typo3.org/ns/TYPO3/CMS/Fluid/ViewHelpers" data-namespace-typo3-fluid="true">

    <!-- article image or video -->
    <f:cObject typoscriptObjectPath="lib.txMyExtension.keyvisual.publication" data="{data}" />

</html>
```

## 1.1.2. Configuration
* Per default the lib uses the following breakpoints defined via TypoScript:
```
# cat=plugin.tx_accelerator//a; type=integer; label=Breakpoint for desktop
desktop = 1024

# cat=plugin.tx_accelerator//a; type=integer; label=Breakpoint for tablet
tablet = 768

# cat=plugin.tx_accelerator//a; type=integer; label=Second breakpoint for mobile
mobile2 = 640

# cat=plugin.tx_accelerator//a; type=integer; label=First breakpoint for mobile
mobile = 320
```
* Based on the defined breakpoints it generates the following image-set:
```
* min-width: 1024px AND min-resolution: 192dpi
* min-width: 1024px
* min-width: 768px AND min-resolution: 192dpi
* min-width: 768px
* min-width: 640px AND min-resolution: 192dpi
* min-width: 640px
* min-width: 320px AND min-resolution: 192dpi
* min-width: 320px
* min-width: 0px AND min-resolution: 192dpi
* min-width: 0px (Fallback)
```
* You can configure the usage of CropVariants per breakpoint and also set your own breakpoints and maxWidths via TypoScript

## 1.2. Pseudo-CDN
### 1.2.1 Description

With the CDN functionality it is possible to reduce the loading time of the website considerably by loading static content from subdomains of the respective website.
This is not a real CDN, but a Pseudo-CDN, since no external servers are used.

Example without Pseudo-CDN
```
<picture >
    <source srcset="https://www.rkw.de/fileadmin/_processed_/e/e/csm_20191112-Unternehmensberatung-Desktop_20772b022d.jpg" media="(min-width: 1025px)">
    <source srcset="https://www.rkw.de/fileadmin/_processed_/a/2/csm_20191112-Unternehmensberatung-Tablet_e748abd11d.jpg" media="(min-width:769px)">
    <source srcset="https://www.rkw.de/fileadmin/_processed_/a/2/csm_20191112-Unternehmensberatung-Tablet_a7c14e847a.jpg" media="(min-width:481px)">
    <source srcset="https://www.rkwde/fileadmin/_processed_/4/9/csm_20191112-Unternehmensberatung-Mobile_3c8697c74b.jpg" media="(min-width:321px)">
    <source srcset="https://www.rkw.de/fileadmin/_processed_/4/9/csm_20191112-Unternehmensberatung-Mobile_f329b9da89.jpg" media="(min-width:0px)">
    <img src="data:image/gif;base64,R0lGODlhAQABAAAAACwAAAAAAQABAAA=" alt="Ihre Unternehmensberatung ">
</picture>
```
Example with Pseudo-CDN
```
<picture >
    <source srcset="https://static1.rkw.de/fileadmin/_processed_/e/e/csm_20191112-Unternehmensberatung-Desktop_20772b022d.jpg" media="(min-width: 1025px)">
    <source srcset="https://static1.rkw.de/fileadmin/_processed_/a/2/csm_20191112-Unternehmensberatung-Tablet_e748abd11d.jpg" media="(min-width:769px)">
    <source srcset="https://static1.rkw.de/fileadmin/_processed_/a/2/csm_20191112-Unternehmensberatung-Tablet_a7c14e847a.jpg" media="(min-width:481px)">
    <source srcset="https://static2.rkwde/fileadmin/_processed_/4/9/csm_20191112-Unternehmensberatung-Mobile_3c8697c74b.jpg" media="(min-width:321px)">
    <source srcset="https://static2rkw.de/fileadmin/_processed_/4/9/csm_20191112-Unternehmensberatung-Mobile_f329b9da89.jpg" media="(min-width:0px)">
    <img src="data:image/gif;base64,R0lGODlhAQABAAAAACwAAAAAAQABAAA=" alt="Ihre Unternehmensberatung ">
</picture>
```
### 1.2.2 Settings
```
plugin.tx_accelerator {
    settings {
        cdn {

            # cat=plugin.tx_accelerator//a; type=boolean; label=Activate CDN
            enable = 0

            # cat=plugin.tx_accelerator//a; type=integer; label=Maximum number of connections per domain
            maxConnectionsPerDomain = 4

            # cat=plugin.tx_accelerator//a; type=integer; label=Maximum number of subdomains
            maxSubdomains = 100

            # cat=plugin.tx_accelerator//a; type=string; label=Ignore some files like CSS and JS because browser security stuff may cause problems
            ignoreIfContains = /\.css|\.js|\?noCdn=1/

            # cat=plugin.tx_accelerator//a; type=string; label=Regular expression for replacement
            search = /(href="|src="|srcset=")\/?((uploads\/media|uploads\/pics|typo3temp\/compressor|typo3temp\/GB|typo3conf\/ext|fileadmin)([^"]+))/i
        }
    }
}
```
* **enable** activates the Pseudo-CDN
* **maxConnectionsPerDomain** defines how many resources are loaded from a subdomain.
* **maxSubdomains** defines how many sudomains there should be. If the value is set to 10 the subdomains static1.example.com to static10.example.com are used.
* **search** allows to override the regular expression for searching/replacing paths to static content
* **ignoreIfContains** allows to specify exclusion criteria for the pseudoCDN. Especially JS files should be excluded here (cross-domain issues)

## 1.3 HTML Minify
### 1.3.1 Description

This function removes unnecessary breaks and spaces from the HTML code. This significantly reduces the size of the HTML code.

### 1.3.1 Settings
```
plugin.tx_accelerator {
    settings {
        htmlMinify {

            # cat=plugin.tx_accelerator//a; type=boolean; label=Activate HTML Minifier
            enable = 0

            # cat=plugin.tx_accelerator//a; type=string; label=Pids to exclude, comma-separated
            excludePids =

            # cat=plugin.tx_accelerator//a; type=string; label=Page types to include, comma-separated
            includePageTypes = 0
        }
    }
}
```
* **enable** activates the HTML Minify
* **excludePids** excludes the PIDs defined in this comma-separated list
* **includePageTypes** includes the pageTypes defined in this comma-separated list

##  1.4 Enforced optimized ImageRendering
Normally TYPO3 only calculates those images whose size does not fit, for example.
For all others, however, image optimization would not work.
Using an XClass, this extension forces ImageProcessing for all images in the Frontend,
thus enabling uniform optimization of the images.

Currently, this feature cannot yet be deactivated via TypoScript.

## 1.4 Extended ProxyCaching with Varnish
This extension allows an extended setup with Varnish.
By default pages are excluded from Varnish caching if a frontend cookie is set. This is to prevent personal data from being cached and thus becoming visible to strangers.

Conversely, however, this means that Varnish caching is completely disabled for logged-in front-end users, so that they can no longer benefit from the performance improvement provided by Varnish for the entire page. To avoid this, this extension provides a field "Allow Proxy-Caching" in the page properties in the backend. This has the following options:
* **Inherit**: Inherits the settings from the page rootline
* **Deactivate**: Completely deactivates the ProxyCache for this page (and its subpages if applicable). This setting is useful e.g. for time-controlled plugins on the page
* **Activate**: Enables the ProxyCache explicitly even if a frontend cookie is set. This allows pages to be served from the Varnish cache even if a user is logged in. This should only be activated for pages that do not contain personal data.

This setup only works if the appropriate settings are made in the Varnish configuration.
Since Varnish configurations are very individual, only the relevant lines that control the behavior of the Varnish according to the above specifications are listed here.
The following configuration example assumes that ```madj2k/accelerator``` is used together with ```opsone-ch/varnish```.

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

## 1.5 Enforced Inline CSS - NOT ACTIVE!!!!
Ideally, the CSS is output inline directly to the header of the website.
This saves reloading the CSS files and speeds up the rendering.
From TYPO3 9 upwards, it is possible to force inline output via TypoScript,
but this does not work for external CSS files (e.g. TypeKit) (see: https://docs.typo3.org/m/typo3/reference-typoscript/master/en-us/Setup/Page/Index.html#includecss-array).

Using an XClass, this extension forces the integration of all
specified CSS files as inline CSS and also considers external CSS files.

Currently, this feature cannot yet be deactivated via TypoScript.


## 1.6 Include Critical CSS (Above-The-Fold)
To increase the loading speed of your website, so-called critical CSS (above the fold) can be stored in a separate file.
This critical CSS is then written inline into the HTML of the website, while the rest of the CSS (which is included via page.includeCSS) is added in such a way that it does not block the rendering of the page (as is otherwise usual).
The critical CSS can be specified per frontend-layout. We use the fields 'tx_accelerator_fe_layout_next_level' and 'layout' here (layout-field takes precedence in current page).
If no critical CSS is specified for a layout, the CSS files are included normally.

The configuration is done via Typoscript.

Usage via TypoScript:
```
plugin.tx_accelerator {

    settings {
        criticalCss {

            // globally activate it
            enable = 1

            filesForLayout {

                // the key is the frontend-layout in which the following files are to be included
                0 {
                    // this keys here are only determining the order of the inclusion. Extension-keys can be used.
                    10 = EXT:accelerator/Tests/Integration/ContentProcessing/CriticalCssTest/Fixtures/Frontend/Files/criticalOne.css
                    20 = EXT:accelerator/Tests/Integration/ContentProcessing/CriticalCssTest/Fixtures/Frontend/Files/criticalTwo.css
                }
            }

            // here you can define CSS files that are included via page.includeCss that are to be removed when critical-CSS is included
            filesToRemoveWhenActive {
                10 = EXT:accelerator/Tests/Integration/ContentProcessing/CriticalCssTest/Fixtures/Frontend/Files/Global/removeOne.css
                20 = EXT:accelerator/Tests/Integration/ContentProcessing/CriticalCssTest/Fixtures/Frontend/Files/Global/removeTwo.css
            }
        }
	}
```
