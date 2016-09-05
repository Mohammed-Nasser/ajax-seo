<?php

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
    // Avoid XSS attacks with CSP https://w3c.github.io/webappsec-csp/
    // Omit Referrer https://w3c.github.io/webappsec-referrer-policy/
    // Firefox OS app suggestion https://developer.mozilla.org/en-US/Apps/CSP
    header('Content-Security-Policy: script-src' . ($debug ? null : " 'unsafe-inline'") . ($cdn_host ? " $cdn_host" : " 'self'") . (ga ? ' www.google-analytics.com' : null) . '; referrer no-referrer');
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
$metadata .= "\n<meta name=viewport content=\"width=device-width,initial-scale=1\">";

// Early handshake DNS https://w3c.github.io/resource-hints/#dns-prefetch
if ($cdn_host) $metadata .= "\n<link rel=dns-prefetch href=$cdn_scheme$cdn_host/>";
// // Early handshake DNS, TCP and TLS https://w3c.github.io/resource-hints/#preconnect
// if ($cdn_host) $metadata .= "\n<link rel=preconnect href=$cdn_scheme$cdn_host/>";

// Resource hints http://w3c.github.io/resource-hints/
// Fetch and cache API in background when everything is downloaded https://html.spec.whatwg.org/#link-type-prefetch
if ($conn && $result) $metadata .= "\n<link rel=\"prefetch prerender\" href=$path/api" . ($url === '/' ? '' : $url) . '>';

// Manifest for a web application https://w3c.github.io/manifest/
$metadata .= "\n<link rel=manifest href=$path/manifest.json>";

// SVG favicon https://github.com/whatwg/html/issues/110
$metadata .= "\n<link rel=mask-icon href=$path/icon.svg color=#0b62bb>";
// Favicon 16x16 4-bit 16 color favicon.ico in website root http://zoompf.com/2012/04/instagram-and-optimizing-favicons
// 16px used on all browsers https://github.com/audreyr/favicon-cheat-sheet, http://realfavicongenerator.net/faq#.Vpasouh96Hs
if (!empty($path)) $metadata .= "\n<link rel=\"shortcut icon\" href=$path/favicon.png>";

// Copyright license
$metadata .= "\n<link rel=license href=$path/LICENSE>";

echo "<!doctype html>
<html lang=en>
<head prefix=\"og: http://ogp.me/ns#\">
<meta charset=utf-8>
$metadata
" . ($debug ? '<link rel=stylesheet href=' . assets . "style.css>" : '<style>@viewport{width:device-width}body,html{height:100%}.button,button,html,label{-webkit-user-select:none;-moz-user-select:none;-ms-user-select:none;user-select:none}html{color:#333;font:62.45%/1.65 Cambria,Georgia,serif;text-rendering:optimizeSpeed;background-color:#f2f2f2;touch-action:manipulation;cursor:default}body,dd,dl,figure{margin:0}body{font-size:1.9em;overflow-wrap:break-word;word-wrap:break-word;-ms-hyphenate-limit-chars:6 3 2;hyphenate-limit-chars:6 3 2;-webkit-hyphens:auto;-ms-hyphens:auto;hyphens:auto}main{display:block;-webkit-user-select:text;-moz-user-select:text;-ms-user-select:text;user-select:text}dt,h1,h2,h3{line-height:1.07;font-weight:400;font-family:Times New Roman,serif}h1,h2{margin-top:.2em;margin-bottom:.2em}h1:first-line,h2:first-line{font-size:150%}h1{font-size:1.9em}dt,h2,h3{font-size:1.45em}h3{margin-top:.3em;margin-bottom:.3em}h4{margin-top:.4em;margin-bottom:.4em}cite,p,pre{margin-top:.8em;margin-bottom:.8em}p+p{margin-top:1.6em}small{font-size:70%}blockquote{margin:1.2em -3em 1.2em -3em;margin-right:-4.9vw;margin-left:-4.9vw;padding-left:3.05em;padding-left:calc(3.05em - .4em);padding-left:calc(4.95vw - .4em);padding-right:3em;padding-right:4.9vw;font-style:italic;background-color:#f6f6f6;border-left:.4em solid #aaa}blockquote:after,blockquote:before{content:"";display:table}blockquote>:not(div):not(span):not(code):first-child{padding-top:.4em}blockquote>:not(div):not(span):not(code):last-child{padding-bottom:.4em}blockquote code,blockquote pre{background-color:#eee}cite{display:block;color:#666}cite:before{content:"\2014\00A0";color:#999}br{word-spacing:0}hr{position:relative;margin:1em -3em;margin-right:-4.9vw;margin-left:-4.9vw;clear:both;border:0;border-top:1px solid #ddd}code,pre{font:80%/1.45 Consolas,Liberation Mono,Courier,monospace;white-space:pre-wrap;background-color:#f6f6f6;-moz-tab-size:4;tab-size:4}code{display:inline-block;padding:0 .4em .1em}pre{padding:.75em 1.05em}a,button,input,select,textarea{font-family:inherit;outline:0;pointer-events:auto}a img,abbr,iframe{border:0}a,img{-webkit-user-drag:none;user-drag:none}a[href]{color:#00d;text-decoration:none;-webkit-box-decoration-break:clone;box-decoration-break:clone}a[href]:focus,a[href]:hover{color:#02a;text-decoration:underline}a[href][target=_blank]{text-decoration:none;border-bottom:1px dotted}img{width:auto;max-width:100%;height:auto;vertical-align:top;object-fit:contain;-ms-interpolation-mode:nearest-neighbor;image-rendering:-webkit-optimize-contrast;image-rendering:-moz-crisp-edges;image-rendering:crisp-edges;image-rendering:pixelated}abbr{border-bottom:1px dotted #aaa}legend{display:table}label{display:inline-block;padding-top:.2em;padding-bottom:.2em;color:#666;line-height:1.2;vertical-align:middle}::selection{color:initial;text-shadow:none;background-color:rgba(255,255,60,.3)}.button,button,input,select,textarea{max-width:100%;padding:.6em .75em;margin:.15em 0;font-size:1em;line-height:1.25;box-sizing:border-box}button,input,select{vertical-align:middle}input,select,textarea{width:20em;background-color:#f6f6f6;border:1px solid #f6f6f6;box-shadow:1px 1px 0 rgba(0,0,0,.14)}[type=checkbox],[type=color],[type=file],[type=image],[type=radio]{width:auto;padding:0;border:0;box-sizing:content-box}textarea{overflow-x:hidden;overflow-y:scroll;min-height:4.95em;max-height:13em;vertical-align:top;word-wrap:break-word;resize:none}[required]:valid{border-color:#aaa}[required]:focus,input:focus,select:focus,textarea:focus{background-color:#fff;border-color:#00d}.button,button,select{overflow:hidden;display:inline-block;white-space:nowrap;word-wrap:normal;text-overflow:ellipsis;outline:0;cursor:pointer}.button,button{min-width:9.4em;margin-top:.55em;margin-bottom:.55em;color:#fff;text-align:center;background-color:#666;border:1px solid #666}[disabled]{color:#aaa;text-decoration:none;text-shadow:0 1px 1px #fff;border-color:#ddd;box-shadow:none;transition:none;pointer-events:none;cursor:default}.button[disabled],button[disabled],select[disabled]{background-color:rgba(179,179,179,.08);border-color:transparent}select:focus option{min-width:100%;max-width:0;background-color:#fff}option{min-width:100%;max-width:0}.button:active,button:active{background-color:#999;border-color:#999}.button{width:auto}a[href].button{color:#fff;text-decoration:none}[tabindex]{outline:0}.tab,.wrapper,html{width:100%;width:100vw}html{max-width:100%}div.noscroll{overflow:hidden}.wrapper{min-height:100vh}.status{opacity:0;position:fixed;position:-ms-device-fixed;position:device-fixed;z-index:999;left:0;width:0;height:3px;background-color:#60d778;box-shadow:0 0 0 1px rgba(255,255,255,.8);-webkit-transform:translateZ(0);-ms-transform:translateZ(0);transform:translateZ(0);-webkit-backface-visibility:hidden;backface-visibility:hidden;-webkit-perspective:1000;perspective:1000;will-change:transform;pointer-events:none}.status-start:before{content:"";display:block;width:10%;height:100%;float:right;background-image:linear-gradient(to right,rgba(96,215,120,0),#62fb82);box-shadow:2px -2px 5px 1px #62fb82}.status-start{opacity:1;width:70%;width:70vw;transition:opacity .2s,width 5s cubic-bezier(.2,1,.4,1)}.status-done{opacity:0;width:100%;width:100vw;transition-duration:.2s}.status-error{opacity:1;width:100%;width:100vw;background-color:#f79c87;transition:background .2s cubic-bezier(.2,1,.4,1)}.status-error:before{display:none}.tab{position:fixed;position:-ms-device-fixed;position:device-fixed;z-index:100;top:0;right:0;left:0;line-height:2.2;font-family:Segoe UI Historic,Segoe UI Symbol,sans-serif;text-align:center;white-space:nowrap;word-wrap:normal;backface-visibility:hidden}.footer,.header,.main{min-width:13.6em;max-width:47.45em;margin:auto;box-sizing:border-box}.header,.main{background-color:#fff}.header{position:relative;padding:.08em .4em}.header:after{content:"";position:absolute;left:0;right:0;height:1.5em;background-image:linear-gradient(#fff,rgba(255,255,255,.9) 35%,rgba(255,255,255,.8) 50%,rgba(255,255,255,0));pointer-events:none}.nav{width:100%;margin:.1em auto;font-size:1.05em;background-color:#f4f4f4;background-image:linear-gradient(#f8f8f8,rgba(248,249,250,0));border:1px solid #d9e0e2;border-bottom-color:#ccc;border-radius:.2em;box-shadow:0 1px 1px rgba(0,0,0,.1),inset 0 0 0 1px rgba(255,255,255,.5)}.nav .noscroll{overflow:hidden;display:inline-block;display:-ms-flexbox;display:-webkit-flex;display:flex}.footer a,.nav.nav a{color:inherit;text-decoration:none}.nav a{display:block;position:relative;-webkit-flex:1;-ms-flex:1;flex:1;-webkit-flex-basis:auto;flex-basis:auto;min-width:1px;padding:0 .63em;border:0 solid #d9e0e2;border-left-width:1px}.nav a:first-child{max-width:3.2em;padding:0;border-left:0}.nav a:first-of-type{border-radius:.2em 0 0 .2em}.nav a:last-of-type{border-radius:0 .2em .2em 0}.nav a:focus,.nav a:hover{background-color:#fff}.nav a.error,.nav a.focus,.nav.nav a.active{margin:-1px auto;line-height:2.25;border-color:transparent}.nav .active+a,.nav .error+a,.nav .focus+a{border-color:transparent}.nav a.focus{background-color:rgba(206,209,210,.4)}.nav a.active{z-index:1;color:#fff;background-color:#006cff}.nav a.error{background-color:#f7dad4}.nav a>span,[data-placeholder]{overflow:hidden;display:block;position:relative;top:-.06em;max-height:2.3em;text-overflow:ellipsis}.bar{width:auto;height:2.35em;padding:inherit;margin:inherit;background-color:transparent;border:inherit;box-shadow:inherit;all:unset;display:none;box-suppress:discard;position:relative;z-index:3;vertical-align:top}.nav .bar{display:inline-block;position:relative}.nav a[data-version]{line-height:initial;margin:initial}[data-version]:before{content:attr(data-version);position:absolute;z-index:4;right:50%;bottom:.65em;width:1.46em;height:1.46em;margin-right:-1.46em;color:#fff;text-shadow:1px 1px 0 red;font:700 .55em/1.4 Meiryo,Segoe UI,sans-serif;background-color:#f80;border-radius:100%;box-shadow:0 0 0 .17em #fff;pointer-events:none}.bar span,.bar span:after,.bar span:before{display:block;width:1.3em;height:.21em;background-color:#222;border-radius:.5em}.active .bar span,.active .bar span:after,.active .bar span:before{background-color:#fff}.bar span{position:relative;z-index:1;margin:1.07em .65em 1.07em .55em;transform:translateY(0);pointer-events:none}.bar span:before{content:"";position:absolute;right:0;width:.9em;-webkit-transform:translateY(-.42em);-ms-transform:translateY(-.42em);transform:translateY(-.42em)}.bar span:after{content:"";position:absolute;width:.5em;-webkit-transform:translateY(.42em);-ms-transform:translateY(.42em);transform:translateY(.42em)}.footer,.main{padding:0 3em;padding:0 4.9vw}.main{overflow:hidden;min-height:100vh;padding-top:3.8em;padding-bottom:6em;outline:1px solid #e1e1e1;will-change:contents}.main>div{contain:layout}.main :target{background-color:#ff0}.main a:target{text-decoration:none}.main a:target:hover{text-decoration:underline}.main :target:before{content:"";display:block;height:2.5em;margin-top:-2.5em;pointer-events:none;background-color:#fff}.error~.main{display:flex;justify-content:center;align-items:center;text-align:center;padding-bottom:8.5em}.pubdate{display:block;margin:.5em 0;color:#aaa;font-size:.7em;font-family:tahoma,sans-serif}.footer{overflow:hidden;height:2.7em;margin-top:-2.7em;line-height:2.7;text-overflow:ellipsis;white-space:nowrap;word-wrap:normal;border-top:1px solid #e1e1e1}.footer a{display:inline-block;color:#777}.footer a+a{margin-left:.6em}.footer a:focus,.footer a:hover{color:#222;text-decoration:underline}.nav [data-placeholder],[hidden],option[value=""]{display:none;box-suppress:discard}@media \0screen\,screen\9{.noscroll,.wrapper{height:100%}.main,.noscroll.noscroll{overflow:visible}.main{min-height:100%}.nav .noscroll{display:table;width:100%;table-layout:fixed}.nav a{display:table-cell;vertical-align:top}.nav .bar,[data-version]:before{display:none}.nav [data-placeholder]{display:inline-block}}@media (min-width:0\0) and (min-resolution:.001dpcm){.nav,.nav a:first-of-type,.nav a:last-of-type{border-radius:0}.nav .noscroll{display:table;width:100%;table-layout:fixed}.nav a{display:table-cell;vertical-align:top}.nav .bar,[data-version]:before{display:none}.nav [data-placeholder]{display:inline-block}}@media (-ms-high-contrast:active),(-ms-high-contrast:none){a{background-color:transparent}::-ms-clear{display:none;box-suppress:discard}:-ms-input-placeholder{color:inherit}select{-ms-user-select:none}select:focus::-ms-value{color:initial;background-color:initial}.nav:after,.nav:before{transition:width .35s cubic-bezier(.2,1,.4,1)}}@supports (-ms-accelerator:true){::-ms-clear{display:none;box-suppress:discard}::-webkit-input-placeholder{color:#333}[placeholder]:focus::-webkit-input-placeholder{opacity:.54}select:focus::-ms-value{color:initial;background-color:initial}}@media (-webkit-min-device-pixel-ratio:0){@supports (not (-ms-accelerator:true)){.nav,html,textarea{-webkit-overflow-scrolling:touch}html{-webkit-font-smoothing:antialiased;-webkit-text-size-adjust:100%}[tabindex],a,button,input,select,textarea{-webkit-tap-highlight-color:rgba(51,51,51,0)}a{-webkit-touch-callout:none}input{-webkit-hyphens:none}input[type=search]{-webkit-appearance:textfield}input[type=number]::-webkit-inner-spin-button,input[type=number]::-webkit-outer-spin-button,input[type=search]::-webkit-search-cancel-button{display:none}::-webkit-input-placeholder{color:#333;text-overflow:ellipsis!important;white-space:nowrap;-webkit-user-select:none;user-select:none}[placeholder]:focus::-webkit-input-placeholder{opacity:.54}.nav a>span,[data-placeholder]{text-overflow:clip;-webkit-mask:linear-gradient(to left,rgba(51,51,51,0),#333 1.5em,#333);mask:linear-gradient(to left,rgba(51,51,51,0),#333 1.5em,#333)}.nav a>span[dir],[dir] [data-placeholder]{-webkit-mask:linear-gradient(to right,rgba(51,51,51,0),#333 1.5em,#333);mask:linear-gradient(to right,rgba(51,51,51,0),#333 1.5em,#333)}}}@-moz-document url-prefix(){::-moz-selection{color:initial;text-shadow:none;background-color:rgba(255,255,60,.3)}label:active{background-color:transparent}button,input,select,textarea{background-image:none;border-radius:0}button::-moz-focus-inner,input::-moz-focus-inner{border:0;padding:0}::-moz-placeholder{color:#333}[placeholder]:not(:focus)::-moz-placeholder{opacity:1}}@media (max-width:720px){html{background-color:#fff}html.noscroll{overflow:hidden}blockquote{margin-left:-1.9em;margin-right:-1.9em;padding-left:calc(1.9em - .35em);padding-right:1.9em}hr{margin-right:-1.9em;margin-left:-1.9em}button,input,textarea{width:100%}[type=checkbox],[type=color],[type=image],[type=radio]{width:auto}.expand~.tab .header:after,.footer,.nav .bar,[data-version]:before{display:none;box-suppress:discard}.status{height:4px}.status:not(.expand)~.tab .header{transition:none}.tab{padding-bottom:.2em;text-align:left;text-align:start;background-image:linear-gradient(#fff,rgba(255,255,255,.9) 35%,rgba(255,255,255,.8) 50%,rgba(255,255,255,0));pointer-events:none}.tab>.bar{display:inline-block}.bar{pointer-events:auto;cursor:pointer}.bar span,.bar span:after,.bar span:before{transition:width .1s,background-color 1ms,transform .35s cubic-bezier(.2,1,.4,1);will-change:transform}.bar span{transition-duration:.15s}.bar:focus,.bar:hover{background-color:rgba(0,0,0,.07)}.expand~.tab .bar span{background-color:transparent}.expand~.tab .bar span:before{width:1.3em;-webkit-transform:rotate(45deg);-ms-transform:rotate(45deg);transform:rotate3d(0,0,1,45deg)}.expand~.tab .bar span:after{width:1.3em;-webkit-transform:rotate(-45deg);-ms-transform:rotate(-45deg);transform:rotate3d(0,0,1,-45deg)}.expand~.tab .nav:after,.expand~.tab .nav:before,.header{width:100%;max-width:76%;max-width:76vw;min-width:10em}.header{position:fixed;position:-ms-device-fixed;position:device-fixed;top:0;left:0;bottom:0;padding:0;background-color:#f6f6f6;border:0;-webkit-transform:translateX(-100%);-ms-transform:translateX(-100%);transform:translateX(-100%)}.expand~.tab .header{box-shadow:0 0 4em rgba(51,51,51,.3);box-shadow:0 0 4em rgba(51,51,51,.11),0 0 43vw rgba(51,51,51,.3);-webkit-transform:none;-ms-transform:none;transform:none;transition:transform .35s cubic-bezier(.2,1,.4,1)}.focusin,.handler,.nav [data-placeholder]{display:block}.expand~.handler,.expand~.tab .handler{position:initial;top:0;right:0;left:0;bottom:0;margin:0}.expand~.handler{position:fixed;position:-ms-device-fixed;position:device-fixed;z-index:99}.expand~.main{-webkit-filter:grayscale(100%);filter:grayscale(100%);transition:filter .35s cubic-bezier(.2,1,.4,1)}.nav,.nav a:first-of-type,.nav a:last-of-type{border-radius:0}.nav{contain:layout;overflow:auto;height:100%;margin:0;background:0 0;border:0;box-shadow:none;box-sizing:border-box;backface-visibility:hidden;pointer-events:auto}.nav .noscroll{display:block}.nav:after,.nav:before{content:"";position:fixed;position:-ms-device-fixed;position:device-fixed;left:0;z-index:2;height:2.35em;font-size:95%;will-change:transform;pointer-events:none}.nav:before{top:0;background-image:linear-gradient(#f8f8f8,rgba(248,248,248,.9) 50%,rgba(248,248,248,.8) 60%,rgba(248,248,248,0))}.nav:after{bottom:0;background-image:linear-gradient(rgba(248,248,248,0),rgba(248,248,248,.8) 50%,rgba(248,248,248,.9) 60%,#f8f8f8)}.nav.nav a{display:block;-webkit-flex:0;flex:0;padding:0 1.3em;float:none;border-color:rgba(125,138,138,.13);border-width:0;border-top-width:1px}.nav a[data-version]{line-height:2.25}.nav.nav a:first-child{max-width:100%;margin-top:2.25em;border-top:0}.nav a:last-of-type{margin-bottom:2.25em}.main{padding:2em 1.9em 3.2em;background-color:initial;outline:0}.error~.main{padding-bottom:6.7em}.main [id]:before{padding-top:1.8em;margin-top:-1.8em}}@media (max-width:480px){html{font-size:58%}h1{font-size:1.5em}h2{font-size:1.15em}h3{font-size:1.3em}dt,h3{font-size:1.3em}button{width:100%}.bar{font-size:102.4%}.nav span{top:0}}@media print{@page{margin:.5cm}.main,blockquote,code,html,pre{background-color:initial}.pubdate,a,cite,html{color:initial}h2,h3,p{orphans:3;widows:3}h2,h3{page-break-after:avoid}a{text-decoration:underline}a[target=_blank]{border-bottom:none}img{page-break-inside:avoid}.main,input,select,textarea{box-shadow:none}.main{padding-top:0;padding-bottom:0}.footer,.tab{display:none;box-suppress:discard}}</style>') . "
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
    <button class=bar id=bar tabindex=0 hidden><span></span></button>
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
    echo "\n" . ($debug ? '<script src="' . assets . "script.js#" . $safepath . '"></script>' : '<script>!function(e){"use strict";"function"==typeof define&&define.amd?define(["as"],e):"object"==typeof module&&module.exports?module.exports=e():window.as=e()}(function(){"use strict";var e,t,s,r=window,a=document,n=navigator,o=history,i=location,l={html:a.documentElement,wrapper:a.getElementById("wrapper"),bar:a.getElementById("bar"),collapse:a.getElementById("collapse"),focusin:a.getElementById("focusin"),focusout:a.getElementById("focusout"),reset:a.getElementById("reset"),nav:a.getElementById("nav"),status:a.getElementById("status"),output:a.getElementById("output")},c={classList:"classList"in l.html,click:"click"in l.html,dnt:"1"===n.doNotTrack||"1"===r.doNotTrack||"1"===n.msDoNotTrack,error:{e:null},eventListener:!!a.addEventListener,pointer:n.pointerEnabled?"pointerdown":n.maxTouchPoints>0||r.matchMedia&&r.matchMedia("(pointer: coarse)").matches||"ontouchstart"in r?"touchstart":"mousedown",valid:function(e){try{return e()}catch(t){return this.error.e=t,this.error}}},u={version:"5.1",viewportWidth:720,analytics:"' . ga . '",dnt:c.dnt,domain:"' . ga_domain . '",origin:function(){var e=a.currentScript||function(){var e=a.getElementsByTagName("script");return e[e.length-1]}(),t=e.src.split("#")[1]||"/ajax-seo";return"/"===t?i.origin:decodeURIComponent(a.URL).replace(new RegExp("("+t+")(.*)$"),"$1")}(),url:decodeURIComponent(a.URL),title:a.title,activeElement:function(){var e,t=a.querySelectorAll?a.querySelectorAll("[href]:not([target=_blank])"):[],s=decodeURIComponent(a.URL).toUpperCase();for(e=0;e<t.length;e+=1)if(t[e].href.toUpperCase()===s)return t[e];return null}(),error:void 0},d=r.console||{error:function(){}},v={};return c.eventListener?(!c.dnt&&u.analytics&&(v.analytics={listener:function(e){e=e===!0?"addEventListener":"removeEventListener",l.analytics[e]("load",v.analytics.load),l.analytics[e]("error",v.analytics.listener),l.analytics[e]("readystatechange",v.analytics.readystatechange),e||l.analytics.removeAttribute("id")},load:function(){ga("create",u.analytics,u.domain,{storage:"none",clientId:localStorage.gaClientId}),localStorage.gaClientId||ga(function(e){localStorage.gaClientId=e.get("clientId")}),ga("send","pageview"),v.analytics.listener()},readystatechange:function(){"complete"!==l.analytics.readyState&&"loaded"!==l.analytics.readyState||("function"==typeof ga?v.analytics.load():v.analytics.listener())},timestamp:+new Date+""},l.analytics=a.createElement("script"),l.analytics.src="//www.google-analytics.com/analytics.js",l.analytics.id=v.analytics.timestamp,a.body.appendChild(l.analytics),l.analytics=a.getElementById(v.analytics.timestamp),l.analytics&&v.analytics.listener(!0)),!c.classList&&Element.prototype&&Object.defineProperty(Element.prototype,"classList",{get:function(){function e(){return s.className.split(/\s+/)}function t(t){return function(r){var a=e(),n=a.indexOf(r);t(a,n,r),s.className=a.join(" ")}}var s=this;return{add:t(function(e,t,s){~t||e.push(s)}),remove:t(function(e,t){~t&&e.splice(t,1)}),item:function(t){return e()[t]||null},toggle:t(function(e,t,s){~t?e.splice(t,1):e.push(s)}),contains:function(t){return!!~e().indexOf(t)}}}}),l.wrapper&&l.bar&&l.collapse&&l.focusin&&l.focusout&&l.reset&&l.nav&&l.output?(l.nodeList=l.nav&&Array.from&&Array.from(l.nav.querySelectorAll("a"))||[].slice.call(l.nav.querySelectorAll("a")),c.touch="touchstart"===c.pointer,l.closest=function(e,t){if(!e||!t)return null;if(e.closest)return e.closest(t);for(var s=e.matches||e.webkitMatchesSelector||e.msMatchesSelector;e&&1===e.nodeType;){if(s.call(e,t))return e;e=e.parentNode}return null},l.anchor=function(e){return e?("A"!==e.tagName&&(e=l.closest(e,"a[href]")),e&&"A"===e.tagName&&e.href&&"_blank"!==e.target?e:null):null},v.nav={expand:function(){l.html.classList.add("noscroll"),l.status.classList.add("expand")},toggleReal:function(e){l.status.classList.contains("expand")?(e.preventDefault(),v.nav.preventPassFocus=!0,l.html.classList.remove("noscroll"),l.collapse.setAttribute("tabindex",0),setTimeout(function(){l.focusout.setAttribute("tabindex",0),l.collapse.focus(),l.status.classList.remove("expand")},10)):"touchstart"===e.type?(e.preventDefault(),v.nav.expand()):setTimeout(function(){a.activeElement!==e.target&&e.target.focus()},0)},focus:function(e){l.status.classList.contains("expand")||(e.target.blur(),l.nav.scrollTop=0,v.nav.expand(),l.focusin.setAttribute("tabindex",0),l.focusout.removeAttribute("tabindex"),setTimeout(function(){l.focusin.focus()},10))},disable:function(e){e.target.removeAttribute("tabindex")},collapse:function(e){var t,s=e&&("pointerdown"===e.type||"mousedown"===e.type);s&&e.target===l.nav&&l.nav.clientWidth<=e.clientX?e.preventDefault():s&&1!==e.which||(s&&(t=l.anchor(e.target),t&&t.click()),l.html.classList.remove("noscroll"),l.status.classList.remove("expand"),setTimeout(function(){v.nav.preventPassFocus=!0,l.focusout.setAttribute("tabindex",0)},10))},collapseTab:function(e){e.shiftKey||"Tab"!==e.key&&9!==e.keyCode||(v.nav.collapse(e),setTimeout(function(){l.collapse.setAttribute("tabindex",0),setTimeout(function(){l.collapse.focus()},10)},0))},keydown:function(e){e.target!==l.bar||"Enter"!==e.key&&13!==e.keyCode||v.nav.toggleReal(e),(e.target===l.focusout?e.shiftKey:!e.shiftKey)||"Tab"!==e.key&&9!==e.keyCode||(v.nav.collapse(e),e.target!==l.focusout||e.shiftKey||(l.collapse.setAttribute("tabindex",0),setTimeout(function(){l.collapse.focus()},10)))},passFocus:function(e){v.nav.preventPassFocus?delete v.nav.preventPassFocus:l.status.classList.contains("expand")||(l.focusout.focus(),v.nav.disable(e))},init:function(){var e=v.nav;(l.wrapper.offsetWidth<=u.viewportWidth?!e.events:e.events)&&(e.events=!e.events,e.listener=e.events?"addEventListener":"removeEventListener",l.bar[e.listener](c.pointer,e.toggleReal,!0),c.touch?l.nav[e.listener]("click",e.collapse,!0):(l.bar[e.listener]("focus",e.focus,!0),l.bar[e.listener]("keydown",e.keydown,!0),l.focusin[e.listener]("blur",e.disable,!0),l.nodeList&&l.nodeList[l.nodeList.length-1][e.listener]("keydown",e.collapseTab,!0),l.focusout[e.listener]("focus",e.expand,!0),l.focusout[e.listener]("blur",e.disable,!0),l.focusout[e.listener]("keydown",e.keydown,!0),l.collapse[e.listener]("focus",e.passFocus,!0),l.collapse[e.listener]("blur",e.disable,!0),l.reset[e.listener]("blur",e.disable,!0),l.nav[e.listener](c.pointer,e.collapse,!0)),l.reset[e.listener](c.pointer,e.collapse,!0))}},v.nav.init(),r.addEventListener("resize",function(){v.nav.timeoutScale&&clearTimeout(v.nav.timeoutScale),v.nav.timeoutScale=setTimeout(v.nav.init,100)},!0),o.pushState?(s={filter:function(e,t){return e?(e=decodeURIComponent(e).replace(/#.*$/,""),t?e:e.toLowerCase()):void 0},reset:function(){e&&clearTimeout(e),l.status&&l.status.classList.contains("status-start")&&l.status.classList.add("status-done")},click:function(e){if(e)if(c.click)e.click();else{var t=a.createEvent("MouseEvents");t.initEvent("click",!0,!0),e.dispatchEvent(t)}},nav:{nodeList:l.nodeList,activeElement:function(){if(s.nav.nodeList){var e;for(e=0;e<s.nav.nodeList.length;e+=1)if(s.filter(s.nav.nodeList[e].href)===u.url)return s.nav.nodeList[e]}return null}},update:function(e,r,n){if(e){!c.dnt&&u.analytics&&"function"==typeof ga&&ga("send","pageview",{page:u.url}),r?s.reset():t.abort(),s.nav.nodeList&&(l.focus=l.nav.querySelector(".focus"),l.active=l.nav.querySelector(".active"),l.error=l.nav.querySelector(".error"),l.focus&&l.focus.classList.remove("focus"),l.active&&l.active.classList.remove("active"),l.error&&l.error.classList.remove("error")),u.url=s.filter(a.URL),u.activeElement=n||s.nav.activeElement(),u.activeElement&&(u.activeElement.focus(),u.activeElement.classList.add(u.error?"error":"active"),u.error&&u.activeElement.classList.add("x-error")),u.error?(l.status.classList.add("error"),l.status.classList.add("status-error")):(l.status.classList.remove("error"),l.status.classList.remove("status-error")),a.title=u.title=e.title;var o=a.scrollingElement||l.html.scrollTop||a.body;o.scrollTop=0,l.output.innerHTML=e.content,i.hash&&i.replace(u.url+i.hash),delete s.inprogress}},retry:!1,popstate:function(e){var t,r=e.state;s.reset(),s.retry=!r,u.error=r&&r.error||!1,r||e.srcElement.location.pathname===e.target.location.pathname||(u.url=s.filter(a.URL),t=s.nav.activeElement(),s.click(t)),s.update(r,!1,t)},loadstart:function(){l.status&&(l.status.classList.remove("status-done"),l.status.classList.remove("status-start"),e&&clearTimeout(e),e=setTimeout(function(){l.status.classList.add("status-start")},0))},callback:function(e){u.error=e.error||!1,u.activeElement=s.nav.activeElement()||u.activeElement,o.replaceState(e,e.title,null),s.update(e,!0,u.activeElement)},load:function(){var e=this.response;e=c.valid(function(){return JSON.parse(e)}),s.callback(e===c.error?{error:!0,title:"Server error",content:"<h1>Whoops...</h1><p>Experienced server error. Try to <a class=x-error href="+u.url+">reload</a>"+(u.url===u.origin?"":" or head to <a href="+u.origin+">home page</a>")+"."}:e)},resetStatus:function(e){l.status&&(!l.status.classList.contains("status-error")||e&&u.error||l.status.classList.remove("status-error"),l.status.classList.contains("status-done")&&(l.status.classList.remove("status-start"),l.status.classList.remove("status-done")))},listener:function(e){if(e){var n=l.anchor(e.target),i=new RegExp("^"+u.origin+"($|#|/.{1,}).*","i"),c={};if(!n||!i.test(n.href.replace(/\/$/,"")))return;if(setTimeout(function(){n!==a.activeElement&&n.focus()},0),n.href.toLowerCase()===u.url.toLowerCase())return void e.preventDefault();if(u.url=n.href.toLowerCase().replace(/(\/)+(?=\1)/g,"").replace(/(^https?:(\/))/,"$1/").replace(/\/$/,""),c.attr=s.filter(u.url,!0),c.url=decodeURIComponent(a.URL),c.address=s.filter(c.url),c.attr===c.address&&u.url.indexOf("#")>-1)return void setTimeout(function(){o.replaceState({error:u.error,title:u.title,content:l.output.innerHTML},a.title,decodeURIComponent(u.url))},0);if(e.preventDefault(),v.nav.events&&l.status.classList.contains("expand")&&(v.nav.collapse(),l.reset.setAttribute("tabindex",0),setTimeout(function(){l.reset.focus()},10)),u.activeElement=n,u.activeNav=n.parentNode===l.nav,!s.retry&&u.activeNav&&(u.error=u.activeElement.classList.contains("x-error")),u.title=u.activeElement.textContent,u.error&&c.address===c.url?o.replaceState(null,u.title,u.url):u.url!==c.url&&o.pushState(null,u.title,u.url),!u.error&&!s.retry&&c.attr===c.address||u.activeNav&&u.activeElement.classList.contains("focus"))return;a.title=u.title,s.resetStatus(),s.nav.nodeList&&(u.error&&u.activeNav&&(u.activeElement.classList.remove("x-error"),u.activeElement.classList.remove("error")),l.focus=l.nav.querySelector(".focus"),l.focus&&l.focus.classList.remove("focus")),u.activeNav&&u.activeElement.classList.add("focus"),s.inprogress&&(t.abort(),r.stop?r.stop():a.execCommand&&a.execCommand("Stop",!1)),t.open("GET",u.origin+"/api"+c.attr.replace(new RegExp("^"+u.origin,"i"),"")),u.error&&t.setRequestHeader("If-Modified-Since","Sat, 1 Jan 2000 00:00:00 GMT"),s.inprogress=!0,t.send()}},init:function(){l.status&&l.status.addEventListener("transitionend",s.resetStatus,!0),setTimeout(function(){r.onpopstate=s.popstate},150),o.replaceState({error:u.error,title:u.title,content:l.output.innerHTML},u.title,u.url),t=new XMLHttpRequest,t.addEventListener("loadstart",s.loadstart,!0),t.addEventListener("load",s.load,!0),t.addEventListener("abort",s.reset,!0),l.html.addEventListener("click",s.listener,!0)}},u.analytics||delete u.analytics,u.domain||delete u.domain,s.init(),u.error=l.status&&l.status.classList.contains("status-error"),u):(u.error="Browser missing History API support",d.error(u.error,"http://caniuse.com/#feat=history"),u)):(u.error="Missing HTML Elements",d.error(u.error,"https://github.com/laukstein/ajax-seo"),u)):(u.error="Browser missing EventListener support",d.error(u.error,"http://caniuse.com/#feat=addeventlistener"),u)});</script>');
}
