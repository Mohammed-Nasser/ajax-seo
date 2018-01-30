<?php

if (empty($_GET['api'])) $toMinify = true;

include 'content/config.php';
include 'content/connect.php';
include 'content/cache.php'; cache::url();

$description = null;
$result      = false;

if ($conn) {
    $title     = $title_error     = 'Whoops...';
    $pagetitle = $pagetitle_error = 'Page not found';
    // Old Webkit breaks the layout without </p>
    $content   = $content_error   = '<p>This page hasn\'t been found. Try to <a class=x-error href=' . $path . $url . '>reload</a>' . ($ishome ? null : ' or head to <a href=' . $path . '>home page</a>') . '.</p>';

    $stmt = $mysqli->prepare('SELECT title, headline, description, content, created, modified FROM `' . table . '` WHERE url=? LIMIT 1');
    $stmt->bind_param('s', $urldb);
    $stmt->execute();
    $stmt->bind_result($title, $headline, $description, $content, $created, $modified);

    while ($stmt->fetch()) {
        $result    = true;
        // SEO page title improvement for the root page
        $pagetitle = $ishome ? (!empty($title) ? $title : title) : $title;
        $created   = strtotime($created);
        $modified  = strtotime($modified);
        $modified  = max($created, $modified);
        $content = "<h1 dir=auto>$title</h1>" . (isset($headline) ? "\n<h2 itemprop=headline dir=auto>$headline</h2>" : null) .
            "\n<meta itemprop=datePublished content=" . date('Y-m-d\TH:i\Z', $created) . '><time class=pubdate itemprop=dateModified datetime=' . date('Y-m-d\TH:i\Z', $modified) . '>' .
            ($created >= $modified ? 'Posted' : 'Updated') . date(' M j, Y', $modified) . "</time>\n" . string($content);
    }

    $stmt->free_result();
    $stmt->close();

    if (!$result) {
        // URL does not exist
        http_response_code(404);

        $title     = $title_error;
        $content   = "<h1 dir=auto>$title</h1>\n" . $content_error;
        $pagetitle = $pagetitle_error;
    }

    if (isset($_GET['api'])) {
        // API
        include 'content/api.php';
        exit;
    }
}

if (empty($_GET['api'])) {
    // Secure with CSP https://w3c.github.io/webappsec-csp/
    $nonce = base64_encode(openssl_random_pseudo_bytes(16));
    header("Content-Security-Policy: base-uri 'none'" .
        "; default-src 'none'" .
        "; connect-src 'self'" .
        "; frame-ancestors 'none'" .
        "; form-action 'none'" .
        "; img-src 'self'" . ($cdn_host ? " $cdn_host" : null) .
            (ga ? ' www.google-analytics.com' : null) .
        "; manifest-src 'self'" .
        "; prefetch-src 'self'" .
        "; script-src " . ($cdn_host ? " $cdn_host" : " 'self'") .
            " 'strict-dynamic' 'unsafe-inline' 'nonce-$nonce'" .
            (ga ? " www.google-analytics.com" : null) .
        "; style-src '" . ($debug ? 'self' : 'unsafe-inline') . "'");
    // Omit Referrer https://w3c.github.io/webappsec-referrer-policy/
    header('Referrer-Policy: no-referrer');
}

// Max 160 character title http://blogs.msdn.com/b/ie/archive/2012/05/14/sharing-links-from-ie10-on-windows-8.aspx
$metadata  = "<title>$pagetitle</title>";

// Open Graph protocol http://ogp.me
$metadata .= "\n<meta property=og:title content=\"$pagetitle\">";
// Max 253 character description http://blogs.msdn.com/b/ie/archive/2012/05/14/sharing-links-from-ie10-on-windows-8.aspx
if ($description) $metadata .= "\n<meta property=og:description name=description content=\"$description\">";
// Twitter Cards https://dev.twitter.com/cards/overview, https://cards-dev.twitter.com/validator
$metadata .= "\n<meta property=twitter:card content=summary>";

// Optimize smart device viewport (initial-scale=1 to enable zoom-in, maximum-scale=1 to disable zoom) https://developer.chrome.com/multidevice/webview/pixelperfect#viewport http://io13-high-dpi.appspot.com/#11
// Avoid tap 350ms delay https://webkit.org/blog/5610/more-responsive-tapping-on-ios/
// Render fullscreen (out of "safe-area") with "viewport-fit=cover" http://stephenradford.me/removing-the-white-bars-in-safari-on-iphone-x/
//      spec https://drafts.csswg.org/css-round-display/#viewport-fit-descriptor
$metadata .= "\n<meta name=viewport content=\"width=device-width,initial-scale=1\">";

// Early handshake DNS https://w3c.github.io/resource-hints/#dns-prefetch
if ($cdn_host) $metadata .= "\n<link rel=dns-prefetch href=$cdn_scheme$cdn_host/>";
// // Early handshake DNS, TCP and TLS https://w3c.github.io/resource-hints/#preconnect
// if ($cdn_host) $metadata .= "\n<link rel=preconnect href=$cdn_scheme$cdn_host/>";

// Resource hints http://w3c.github.io/resource-hints/
// Fetch and cache API in background when everything is downloaded https://html.spec.whatwg.org/#link-type-prefetch
if ($conn && $result) $metadata .= "\n<link rel=\"prefetch prerender\" href=$path/api" . ($url === '/' ? '' : $url) . '>';

// Webapp Manifest https://w3c.github.io/manifest/
$metadata .= "\n<link rel=manifest href=$path/manifest.webmanifest>";

// SVG favicon https://github.com/whatwg/html/issues/110
$metadata .= "\n<link rel=mask-icon href=$path/icon.svg>";
// Favicon 16x16 4-bit 16 color favicon.ico in website root http://zoompf.com/2012/04/instagram-and-optimizing-favicons
// 16px used on all browsers https://github.com/audreyr/favicon-cheat-sheet, http://realfavicongenerator.net/faq#.Vpasouh96Hs
if (!empty($path)) $metadata .= "\n<link rel=icon href=$path/favicon.png>";

// Copyright license
$metadata .= "\n<link rel=license href=$path/LICENSE>";

echo "<!doctype html>
<html lang=en>
<head prefix=\"og: http://ogp.me/ns#\">
<meta charset=utf-8>
$metadata
" . ($debug ? '<link rel=stylesheet href=' . assets . "style.css>" : '<style>' . (file_get_contents('assets/style.min.css')) . '</style>') . "
<!--[if lt IE 9]><script src=//cdnjs.cloudflare.com/ajax/libs/html5shiv/3.7.3/html5shiv.min.js></script><![endif]-->
<body itemscope itemtype=http://schema.org/WebPage>
<div class=noscroll>
<div class=wrapper id=wrapper>$note";

if ($conn) {
    // Head nav
    if ($stmt = $mysqli->prepare('SELECT url, title FROM `' . table . '` WHERE permit=1 ORDER BY `order` ASC')) {
        $stmt->execute();
        $stmt->bind_result($data_url, $data_metatitle);

        echo "\n<div id=status class=" . ($result ? 'status' : '"status error status-error"') . ' role=progressbar></div>
<div class=tab>
    <button class=bar id=bar aria-controls=nav aria-label="Menu bar" tabindex=0 hidden><span></span></button>
    <span class=focusin id=focusin hidden></span>
    <header class=header>
        <nav class=nav id=nav>
            <div class=noscroll>';

        function is_rtl($string) {
            // Check if there RTL characters (Arabic, Persian, Hebrew) https://gist.github.com/khal3d/4648574
            // RTL languages http://www.w3.org/International/questions/qa-scripts#which
            return (bool) preg_match('/[\x{0590}-\x{05ff}\x{0600}-\x{06ff}]/u', $string);
        }

        while ($stmt->fetch()) {
            $home = !strlen($data_url);

            echo "\n            <a" . ($data_url === $urldb ? ' class=active' : null) .
                ' href="' . ($home ? $safepath : "$path/$data_url") . '"' . ($home ? ' data-version=5' : null) . '>' .
                ($home ? '<span><span class=bar><span></span></span>' : null) .
                '<span' . (is_rtl($data_metatitle) ? ' dir=auto' : '') . ($home ? ' data-placeholder' : null) .
                ">$data_metatitle</span>" . ($home ? '</span>' : null) . '</a>';
        }

        echo "\n            <div class=handler id=focusout hidden></div>
            <div class=handler id=collapse hidden></div>
            </div>
        </nav>
    </header>
</div>
<div class=handler id=reset hidden></div>";

        $stmt->free_result();
        $stmt->close();
    }
    $mysqli->close();
}

echo "\n<main class=main itemprop=about itemscope itemtype=http://schema.org/Article>
<meta itemprop=mainEntityOfPage content=$uri>
<div id=output>
$content
</div>
</main>
<footer class=footer itemprop=breadcrumb>
    <a href=https://github.com/laukstein/ajax-seo>GitHub project</a>
    <a href=https://github.com/laukstein/ajax-seo/archive/master.zip>Download</a>
    <a href=https://github.com/laukstein/ajax-seo/issues>Issues</a>
</footer>
</div>
</div>";

if ($conn) {
    echo "\n" . ($debug ? '<script src="' . assets . "script.js#" . $safepath . '" nonce="' . $nonce . '"></script>' : '<script nonce="' . $nonce . '">"use strict";!function(t){var e,s,n,r=document,a=navigator,o=history,i=location,c={bar:r.getElementById("bar"),collapse:r.getElementById("collapse"),focusin:r.getElementById("focusin"),focusout:r.getElementById("focusout"),html:r.documentElement,nav:r.getElementById("nav"),output:r.getElementById("output"),reset:r.getElementById("reset"),status:r.getElementById("status"),wrapper:r.getElementById("wrapper")},l={classList:"classList"in c.html,click:"click"in c.html,dnt:"1"===a.doNotTrack||"1"===t.doNotTrack||"1"===a.msDoNotTrack,error:{e:null},eventListener:!!r.addEventListener,eventListenerOptions:function(){var t=!1;try{r.addEventListener&&addEventListener("test",null,{get passive(){t=!0}})}catch(e){}return t}(),pointer:a.pointerEnabled?"pointerdown":a.maxTouchPoints>0||(t.matchMedia?t.matchMedia("(pointer: coarse)").matches:"ontouchstart"in t)?"touchstart":"mousedown",valid:function(t){try{return t()}catch(e){return this.error.e=e,this.error}}},u={activeElement:function(){var t,e=r.querySelectorAll?r.querySelectorAll("[href]:not([target=_blank])"):[],s=decodeURIComponent(r.URL).toUpperCase();for(t=0;t<e.length;t+=1)if(e[t].href.toUpperCase()===s)return e[t];return null}(),analytics:"' . ga . '",dnt:!1,domain:"' . ga_domain . '",origin:function(){var t=r.currentScript||function(){var t=r.getElementsByTagName("script");return t[t.length-1]}(),e=t.src.split("#")[1]||"/ajax-seo";return"/"===e?i.origin:decodeURIComponent(r.URL).replace(new RegExp("("+e+")(.*)$"),"$1")}(),title:r.title,url:decodeURIComponent(r.URL),version:"5.4.0",viewportWidth:720},d=t.console||{error:function(){return arguments}},p={};if(!l.eventListener)return u.error="Browser missing EventListener support",d.error(u.error,"http://caniuse.com/#feat=addeventlistener"),u;if(u.analytics&&(!l.dnt||!u.dnt)){try{localStorage.localStorage="1",delete localStorage.localStorage}catch(v){t.localStorage&&delete t.localStorage,t.localStorage={}}p.analytics={listener:function(t){t=t===!0?"addEventListener":"removeEventListener",c.analytics[t]("load",p.analytics.load),c.analytics[t]("error",p.analytics.listener),c.analytics[t]("readystatechange",p.analytics.readystatechange),t||c.analytics.removeAttribute("id")},load:function(){"function"==typeof t.ga&&(ga("create",u.analytics,u.domain,{clientId:localStorage.gaClientId,storage:"none"}),localStorage.gaClientId||ga(function(t){localStorage.gaClientId=t.get("clientId")}),p.analytics.listener(),p.analytics.track())},readystatechange:function(){"complete"!==c.analytics.readyState&&"loaded"!==c.analytics.readyState||("function"==typeof t.ga?p.analytics.load():p.analytics.listener())},timestamp:+new Date+"",track:function(){"function"==typeof t.ga&&ga("send",{hitType:"pageview",title:r.title,page:location.pathname})}},c.analytics=r.createElement("script"),c.analytics.src="https://www.google-analytics.com/analytics.js",c.analytics.id=p.analytics.timestamp,r.body.appendChild(c.analytics),c.analytics=r.getElementById(p.analytics.timestamp),c.analytics&&p.analytics.listener(!0)}return!l.classList&&Element.prototype&&Object.defineProperty(Element.prototype,"classList",{get:function(){function t(){return s.className.split(/\s+/)}function e(e){return function(n){var r=t(),a=r.indexOf(n);e(r,a,n),s.className=r.join(" ")}}var s=this;return{add:e(function(t,e,s){~e||t.push(s)}),contains:function(e){return!!~t().indexOf(e)},item:function(e){return t()[e]||null},remove:e(function(t,e){~e&&t.splice(e,1)}),toggle:e(function(t,e,s){~e?t.splice(e,1):t.push(s)})}}}),c.wrapper&&c.bar&&c.collapse&&c.focusin&&c.focusout&&c.reset&&c.nav&&c.output?(c.nodeList=c.nav&&c.nav.querySelectorAll("a"),c.nodeList=c.nodeList&&(Array.from&&Array.from(c.nodeList)||[].slice.call(c.nodeList)),l.touch="touchstart"===l.pointer,c.closest=function(t,e){if(!t||!e)return null;if(t.closest)return t.closest(e);for(var s=t.matches||t.webkitMatchesSelector||t.msMatchesSelector;t&&1===t.nodeType;){if(s.call(t,e))return t;t=t.parentNode}return null},c.anchor=function(t){return t?("A"!==t.tagName&&(t=c.closest(t,"a[href]")),t&&"A"===t.tagName&&t.href&&"_blank"!==t.target?t:null):null},p.nav={expand:function(){c.html.classList.add("noscroll"),c.status.classList.add("expand")},toggleReal:function(t){c.status.classList.contains("expand")?("touchstart"===t.type&&l.eventListenerOptions||t.preventDefault(),p.nav.preventPassFocus=!0,c.html.classList.remove("noscroll"),c.collapse.setAttribute("tabindex",0),setTimeout(function(){c.focusout.setAttribute("tabindex",0),c.collapse.focus(),c.status.classList.remove("expand")},10)):"touchstart"===t.type?(l.eventListenerOptions||t.preventDefault(),p.nav.expand()):setTimeout(function(){r.activeElement!==t.target&&t.target.focus()},0)},focus:function(t){c.status.classList.contains("expand")||(t.target.blur(),c.nav.scrollTop=0,p.nav.expand(),c.focusin.setAttribute("tabindex",0),c.focusout.removeAttribute("tabindex"),setTimeout(function(){c.focusin.focus()},10))},disable:function(t){t.target.removeAttribute("tabindex")},collapse:function(t){var e,s=t&&("pointerdown"===t.type||"mousedown"===t.type);s&&t.target===c.nav&&c.nav.clientWidth<=t.clientX?t.preventDefault():s&&1!==t.which||(s&&(e=c.anchor(t.target),e&&e.click()),c.html.classList.remove("noscroll"),c.status.classList.remove("expand"),setTimeout(function(){p.nav.preventPassFocus=!0,c.focusout.setAttribute("tabindex",0)},10))},collapseTab:function(t){t.shiftKey||"Tab"!==t.key&&9!==t.keyCode||(p.nav.collapse(t),setTimeout(function(){c.collapse.setAttribute("tabindex",0),setTimeout(function(){c.collapse.focus()},10)},0))},keydown:function(t){t.target!==c.bar||"Enter"!==t.key&&13!==t.keyCode||p.nav.toggleReal(t),(t.target===c.focusout?t.shiftKey:!t.shiftKey)||"Tab"!==t.key&&9!==t.keyCode||(p.nav.collapse(t),t.target!==c.focusout||t.shiftKey||(c.collapse.setAttribute("tabindex",0),setTimeout(function(){c.collapse.focus()},10)))},passFocus:function(t){p.nav.preventPassFocus?delete p.nav.preventPassFocus:c.status.classList.contains("expand")||(c.focusout.focus(),p.nav.disable(t))},init:function(t){var e=p.nav;(t&&t!==l.pointer||(c.wrapper.offsetWidth<=u.viewportWidth?!e.events:e.events))&&(e.events=!e.events,e.listener=e.events?"addEventListener":"removeEventListener",e.options="touchstart"===l.pointer&&l.eventListenerOptions?{passive:!0}:!0,c.bar[e.listener](l.pointer,e.toggleReal,!0),e.events?c.focusout.setAttribute("tabindex",0):c.focusout.removeAttribute("tabindex"),l.touch?c.nav[e.listener]("click",e.collapse,!0):(c.bar[e.listener]("focus",e.focus,!0),c.bar[e.listener]("keydown",e.keydown,!0),c.focusin[e.listener]("blur",e.disable,!0),c.nodeList&&c.nodeList[c.nodeList.length-1][e.listener]("keydown",e.collapseTab,!0),c.focusout[e.listener]("focus",e.expand,!0),c.focusout[e.listener]("blur",e.disable,!0),c.focusout[e.listener]("keydown",e.keydown,!0),c.collapse[e.listener]("focus",e.passFocus,!0),c.collapse[e.listener]("blur",e.disable,!0),c.reset[e.listener]("blur",e.disable,!0),c.nav[e.listener](l.pointer,e.collapse,!0)),c.reset[e.listener](l.pointer,e.collapse,!0),t&&(l.pointer=t,l.touch="touchstart"===l.pointer,p.nav.init()))}},p.nav.init(),"pointerdown"!==l.pointer&&t.matchMedia&&t.matchMedia("(pointer: coarse)").addListener(function(t){p.nav.init(t.matches?"touchstart":"mousedown")}),t.addEventListener("resize",function(){p.nav.timeoutScale&&clearTimeout(p.nav.timeoutScale),p.nav.timeoutScale=setTimeout(p.nav.init,100)},!0),o.pushState?(n={callback:function(t){u.error=t.error||!1,u.activeElement=n.nav.activeElement()||u.activeElement,o.replaceState(t,t.title,null),n.update(t,!0,u.activeElement)},click:function(t){var e;t&&(l.click?t.click():(e=r.createEvent("MouseEvents"),e.initEvent("click",!0,!0),t.dispatchEvent(e)))},filter:function(t,e){return t?(t=decodeURIComponent(t).replace(/#.*$/,""),e?t:t.toLowerCase()):void 0},init:function(){c.status&&c.status.addEventListener("transitionend",n.resetStatus,!0),setTimeout(function(){t.onpopstate=n.popstate},150),o.replaceState({error:u.error,title:u.title,content:c.output.innerHTML},u.title,u.url),s=new XMLHttpRequest,s.addEventListener("loadstart",n.loadstart,!0),s.addEventListener("load",n.load,!0),s.addEventListener("abort",n.reset,!0),c.html.addEventListener("click",n.listener,!0)},listener:function(e){var a,i,l={};if(e){if(i=c.anchor(e.target),a=new RegExp("^"+u.origin+"($|#|/.{1,}).*","i"),!i||!a.test(i.href.replace(/\/$/,"")))return;if(setTimeout(function(){i!==r.activeElement&&i.focus()},0),i.href.toLowerCase()===u.url.toLowerCase())return void e.preventDefault();if(u.url=i.href.toLowerCase().replace(/(\/)+(?=\1)/g,"").replace(/(^https?:(\/))/,"$1/").replace(/\/$/,""),l.attr=n.filter(u.url,!0),l.url=decodeURIComponent(r.URL),l.address=n.filter(l.url),l.attr===l.address&&u.url.indexOf("#")>-1)return void setTimeout(function(){o.replaceState({error:u.error,title:u.title,content:c.output.innerHTML},r.title,decodeURIComponent(u.url))},0);if(e.preventDefault(),p.nav.events&&c.status.classList.contains("expand")&&(p.nav.collapse(),c.reset.setAttribute("tabindex",0),setTimeout(function(){c.reset.focus()},10)),u.activeElement=i,u.activeNav=i.parentNode===c.nav,!n.retry&&u.activeNav&&(u.error=u.activeElement.classList.contains("x-error")),u.title=u.activeElement.textContent,u.error&&l.address===l.url?o.replaceState(null,u.title,u.url):u.url!==l.url&&o.pushState(null,u.title,u.url),!u.error&&!n.retry&&l.attr===l.address||u.activeNav&&u.activeElement.classList.contains("focus"))return;r.title=u.title,n.resetStatus(),n.nav.nodeList&&(u.error&&u.activeNav&&(u.activeElement.classList.remove("x-error"),u.activeElement.classList.remove("error")),c.focus=c.nav.querySelector(".focus"),c.focus&&c.focus.classList.remove("focus")),u.activeNav&&u.activeElement.classList.add("focus"),n.inprogress&&(s.abort(),t.stop?t.stop():r.execCommand&&r.execCommand("Stop",!1)),s.open("GET",u.origin+"/api"+l.attr.replace(new RegExp("^"+u.origin,"i"),"")),u.error&&s.setRequestHeader("If-Modified-Since","Sat, 1 Jan 2000 00:00:00 GMT"),n.inprogress=!0,s.send()}},load:function(){var t=this.response;t=l.valid(function(){return JSON.parse(t)}),n.callback(t===l.error?{error:!0,title:"Server error",content:"<h1>Whoops...</h1><p>Experienced server error. Try to <a class=x-error href="+u.url+">reload</a>"+(u.url===u.origin?"":" or head to <a href="+u.origin+">home page</a>")+"."}:t)},loadstart:function(){c.status&&(c.status.classList.remove("status-done"),c.status.classList.remove("status-start"),e&&clearTimeout(e),e=setTimeout(function(){c.status.classList.add("status-start")},0))},nav:{activeElement:function(){var t;if(n.nav.nodeList)for(t=0;t<n.nav.nodeList.length;t+=1)if(n.filter(n.nav.nodeList[t].href)===u.url)return n.nav.nodeList[t];return null},nodeList:c.nodeList},popstate:function(t){var e,s=t.state;n.reset(),n.retry=!s,u.error=s&&s.error||!1,s||t.srcElement.location.pathname===t.target.location.pathname||(u.url=n.filter(r.URL),e=n.nav.activeElement(),n.click(e)),n.update(s,!1,e)},reset:function(){e&&clearTimeout(e),c.status&&c.status.classList.contains("status-start")&&c.status.classList.add("status-done")},resetStatus:function(t){c.status&&(!c.status.classList.contains("status-error")||t&&u.error||c.status.classList.remove("status-error"),c.status.classList.contains("status-done")&&(c.status.classList.remove("status-start"),c.status.classList.remove("status-done")))},retry:!1,update:function(t,e,a){if(t){e?n.reset():s.abort(),n.nav.nodeList&&(c.focus=c.nav.querySelector(".focus"),c.active=c.nav.querySelector(".active"),c.error=c.nav.querySelector(".error"),c.focus&&c.focus.classList.remove("focus"),c.active&&c.active.classList.remove("active"),c.error&&c.error.classList.remove("error")),u.url=n.filter(r.URL),u.activeElement=a||n.nav.activeElement(),u.activeElement&&(u.activeElement.focus(),u.activeElement.classList.add(u.error?"error":"active"),u.error&&u.activeElement.classList.add("x-error")),u.error?(c.status.classList.add("error"),c.status.classList.add("status-error")):(c.status.classList.remove("error"),c.status.classList.remove("status-error")),r.title=u.title=t.title;var o=r.scrollingElement||c.html.scrollTop||r.body;o.scrollTop=0,c.output.innerHTML=t.content,i.hash&&i.replace(u.url+i.hash),p.analytics&&p.analytics.track(),delete n.inprogress}}},u.analytics||delete u.analytics,u.domain||delete u.domain,n.init(),u.error=c.status&&c.status.classList.contains("status-error"),void(t.as=u)):(u.error="Browser missing History API support",d.error(u.error,"http://caniuse.com/#feat=history"),u)):(u.error="Missing HTML Elements",d.error(u.error,"https://github.com/laukstein/ajax-seo"),u)}(this);</script>');
}
