# Homepage Critical CSS Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Eliminate render-blocking CSS on the homepage's critical rendering path by inlining the exact CSS needed for the fixed header + hero (everything visible without scrolling), and loading the site's five sitewide stylesheets asynchronously instead of blocking on them — homepage only, other pages get the async-loading benefit but no inlined critical block yet.

**Architecture:** `includes/head.php` currently loads 5 stylesheets via plain `<link rel="stylesheet">` tags (render-blocking, sitewide). This plan adds a homepage-only inline `<style>` block (pre-generated, see Task 1) immediately followed by those 5 stylesheets converted to the standard preload/onload-swap pattern (non-blocking everywhere, but the plan only ships the inlined critical content for `index.php`). A 6th preload/swap pair is added for the icon-font stylesheet that `vendors.css` currently pulls in via a blocking `@import`, and that `@import` is removed.

**Tech Stack:** Plain PHP includes, vanilla CSS, no build step in the deployed site. The critical CSS below was generated once, locally, using the `critical` npm package (Puppeteer-based, determines actual above-the-fold CSS from real rendering) against `index.php` at 390×844 and 1470×900 viewports — that generation step is already done; this plan only embeds its output and wires it up.

## Global Constraints

- This must not change behavior on any page other than `index.php`. `includes/head.php` is shared sitewide — the inlined critical CSS block must be gated behind a variable (`$critical_css_file`) that only `index.php` sets.
- The preload/onload-swap conversion of the 5 stylesheets (+1 new one for icons) in `includes/head.php` applies to **every page** — this part is not gated, since it's strictly non-worse than today's blocking behavior everywhere.
- Every stylesheet must still load correctly for visitors with JavaScript disabled, via a `<noscript>` fallback per stylesheet.
- `rev-slider-files/css/settings.css` is NOT part of this plan — it was already removed from `index.php` entirely in the earlier Revolution Slider removal work and is not referenced anywhere relevant here.
- No new build dependency ships to the deployed site. Node/`critical` was used once, locally, in a scratch directory outside the repo — nothing from that tooling is committed.

---

### Task 1: Add the inlined critical CSS and convert stylesheet loading to non-blocking

**Files:**
- Create: `includes/critical/home.css`
- Modify: `index.php:1-11` (add `$critical_css_file` before the closing `?>`)
- Modify: `includes/head.php:73-81` (the `<!-- GOOGLE WEB FONT -->` / `<!-- COMMON CSS -->` / `<!-- CUSTOM CSS -->` block, currently 5 plain `<link rel="stylesheet">` tags)

**Interfaces:**
- Consumes: nothing (first task).
- Produces: the `$critical_css_file` variable convention — any future page wanting an inlined critical block sets this PHP variable (an absolute path via `__DIR__`) before including `includes/head.php`. Produces the preload/onload-swap markup pattern in `includes/head.php` that Task 2 needs to know about (specifically: the new `bootstrap-icons.min.css` preload pair added here, which makes Task 2's `@import` removal safe).

- [ ] **Step 1: Create `includes/critical/home.css` with this exact content**

This is the full, already-generated critical CSS for the homepage (covers fonts, Bootstrap base rules, the fixed header/nav, the hero section, the TripAdvisor badge, the cookie banner, and the preloader — everything visible above the fold at every viewport width). Font/image `url()` paths were rewritten to root-absolute (`/fonts/...`, `/css/...`, `/img/...`) to avoid the relative-path-breaks-on-rewritten-URLs bug class this codebase has hit before.

```css
@charset "UTF-8";@font-face{font-family:Montserrat;font-style:normal;font-weight:100 900;font-display:swap;src:url(/fonts/montserrat-v31-latin-variable.woff2) format("woff2-variations"),url(/fonts/montserrat-v31-latin-variable.woff2) format("woff2");unicode-range:U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD}@font-face{font-family:fontello;src:url(/css/fontello/font/fontello.eot?32974303);src:url(/css/fontello/font/fontello.eot?32974303#iefix) format("embedded-opentype"),url(/css/fontello/font/fontello.woff?32974303) format("woff"),url(/css/fontello/font/fontello.ttf?32974303) format("truetype"),url(/css/fontello/font/fontello.svg?32974303#fontello) format("svg");font-weight:400;font-style:normal}@font-face{font-family:icon_set_1;src:url(/css/fontello/font/icon_set_1.eot?55361665);src:url(/css/fontello/font/icon_set_1.eot?55361665#iefix) format("embedded-opentype"),url(/css/fontello/font/icon_set_1.woff?55361665) format("woff"),url(/css/fontello/font/icon_set_1.ttf?55361665) format("truetype"),url(/css/fontello/font/icon_set_1.svg?55361665#icon_set_1) format("svg");font-weight:400;font-style:normal}:root{--bs-blue:#0d6efd;--bs-indigo:#6610f2;--bs-purple:#6f42c1;--bs-pink:#d63384;--bs-red:#dc3545;--bs-orange:#fd7e14;--bs-yellow:#ffc107;--bs-green:#198754;--bs-teal:#20c997;--bs-cyan:#0dcaf0;--bs-black:#000;--bs-white:#fff;--bs-gray:#6c757d;--bs-gray-dark:#343a40;--bs-gray-100:#f8f9fa;--bs-gray-200:#e9ecef;--bs-gray-300:#dee2e6;--bs-gray-400:#ced4da;--bs-gray-500:#adb5bd;--bs-gray-600:#6c757d;--bs-gray-700:#495057;--bs-gray-800:#343a40;--bs-gray-900:#212529;--bs-primary:#0d6efd;--bs-secondary:#6c757d;--bs-success:#198754;--bs-info:#0dcaf0;--bs-warning:#ffc107;--bs-danger:#dc3545;--bs-light:#f8f9fa;--bs-dark:#212529;--bs-primary-rgb:13,110,253;--bs-secondary-rgb:108,117,125;--bs-success-rgb:25,135,84;--bs-info-rgb:13,202,240;--bs-warning-rgb:255,193,7;--bs-danger-rgb:220,53,69;--bs-light-rgb:248,249,250;--bs-dark-rgb:33,37,41;--bs-primary-text-emphasis:#052c65;--bs-secondary-text-emphasis:#2b2f32;--bs-success-text-emphasis:#0a3622;--bs-info-text-emphasis:#055160;--bs-warning-text-emphasis:#664d03;--bs-danger-text-emphasis:#58151c;--bs-light-text-emphasis:#495057;--bs-dark-text-emphasis:#495057;--bs-primary-bg-subtle:#cfe2ff;--bs-secondary-bg-subtle:#e2e3e5;--bs-success-bg-subtle:#d1e7dd;--bs-info-bg-subtle:#cff4fc;--bs-warning-bg-subtle:#fff3cd;--bs-danger-bg-subtle:#f8d7da;--bs-light-bg-subtle:#fcfcfd;--bs-dark-bg-subtle:#ced4da;--bs-primary-border-subtle:#9ec5fe;--bs-secondary-border-subtle:#c4c8cb;--bs-success-border-subtle:#a3cfbb;--bs-info-border-subtle:#9eeaf9;--bs-warning-border-subtle:#ffe69c;--bs-danger-border-subtle:#f1aeb5;--bs-light-border-subtle:#e9ecef;--bs-dark-border-subtle:#adb5bd;--bs-white-rgb:255,255,255;--bs-black-rgb:0,0,0;--bs-font-sans-serif:system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue","Noto Sans","Liberation Sans",Arial,sans-serif,"Apple Color Emoji","Segoe UI Emoji","Segoe UI Symbol","Noto Color Emoji";--bs-font-monospace:SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono","Courier New",monospace;--bs-gradient:linear-gradient(180deg, rgba(255, 255, 255, 0.15), rgba(255, 255, 255, 0));--bs-body-font-family:var(--bs-font-sans-serif);--bs-body-font-size:1rem;--bs-body-font-weight:400;--bs-body-line-height:1.5;--bs-body-color:#212529;--bs-body-color-rgb:33,37,41;--bs-body-bg:#fff;--bs-body-bg-rgb:255,255,255;--bs-emphasis-color:#000;--bs-emphasis-color-rgb:0,0,0;--bs-secondary-color:rgba(33, 37, 41, 0.75);--bs-secondary-color-rgb:33,37,41;--bs-secondary-bg:#e9ecef;--bs-secondary-bg-rgb:233,236,239;--bs-tertiary-color:rgba(33, 37, 41, 0.5);--bs-tertiary-color-rgb:33,37,41;--bs-tertiary-bg:#f8f9fa;--bs-tertiary-bg-rgb:248,249,250;--bs-heading-color:inherit;--bs-link-color:#0d6efd;--bs-link-color-rgb:13,110,253;--bs-link-decoration:underline;--bs-link-hover-color:#0a58ca;--bs-link-hover-color-rgb:10,88,202;--bs-code-color:#d63384;--bs-highlight-color:#212529;--bs-highlight-bg:#fff3cd;--bs-border-width:1px;--bs-border-style:solid;--bs-border-color:#dee2e6;--bs-border-color-translucent:rgba(0, 0, 0, 0.175);--bs-border-radius:0.375rem;--bs-border-radius-sm:0.25rem;--bs-border-radius-lg:0.5rem;--bs-border-radius-xl:1rem;--bs-border-radius-xxl:2rem;--bs-border-radius-2xl:var(--bs-border-radius-xxl);--bs-border-radius-pill:50rem;--bs-box-shadow:0 0.5rem 1rem rgba(0, 0, 0, 0.15);--bs-box-shadow-sm:0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);--bs-box-shadow-lg:0 1rem 3rem rgba(0, 0, 0, 0.175);--bs-box-shadow-inset:inset 0 1px 2px rgba(0, 0, 0, 0.075);--bs-focus-ring-width:0.25rem;--bs-focus-ring-opacity:0.25;--bs-focus-ring-color:rgba(13, 110, 253, 0.25);--bs-form-valid-color:#198754;--bs-form-valid-border-color:#198754;--bs-form-invalid-color:#dc3545;--bs-form-invalid-border-color:#dc3545}*,::after,::before{box-sizing:border-box}@media (prefers-reduced-motion:no-preference){:root{scroll-behavior:smooth}}body{margin:0;font-family:var(--bs-body-font-family);font-size:var(--bs-body-font-size);font-weight:var(--bs-body-font-weight);line-height:var(--bs-body-line-height);color:var(--bs-body-color);text-align:var(--bs-body-text-align);background-color:var(--bs-body-bg);-webkit-text-size-adjust:100%}h1,h2,h3{margin-top:0;margin-bottom:.5rem;font-weight:500;line-height:1.2;color:var(--bs-heading-color)}h1{font-size:calc(1.375rem + 1.5vw)}@media (min-width:1200px){h1{font-size:2.5rem}}h2{font-size:calc(1.325rem + .9vw)}@media (min-width:1200px){h2{font-size:2rem}}h3{font-size:calc(1.3rem + .6vw)}@media (min-width:1200px){h3{font-size:1.75rem}}p{margin-top:0;margin-bottom:1rem}ul{padding-left:2rem}ul{margin-top:0;margin-bottom:1rem}ul ul{margin-bottom:0}strong{font-weight:bolder}sup{position:relative;font-size:.75em;line-height:0;vertical-align:baseline}sup{top:-.5em}a{color:rgba(var(--bs-link-color-rgb),var(--bs-link-opacity,1));text-decoration:underline}img{vertical-align:middle}button{border-radius:0}button{margin:0;font-family:inherit;font-size:inherit;line-height:inherit}button{text-transform:none}[type=button],button{-webkit-appearance:button}::-moz-focus-inner{padding:0;border-style:none}::-webkit-datetime-edit-day-field,::-webkit-datetime-edit-fields-wrapper,::-webkit-datetime-edit-hour-field,::-webkit-datetime-edit-minute,::-webkit-datetime-edit-month-field,::-webkit-datetime-edit-text,::-webkit-datetime-edit-year-field{padding:0}::-webkit-inner-spin-button{height:auto}::-webkit-search-decoration{-webkit-appearance:none}::-webkit-color-swatch-wrapper{padding:0}::-webkit-file-upload-button{font:inherit;-webkit-appearance:button}::file-selector-button{font:inherit;-webkit-appearance:button}[hidden]{display:none!important}.img-fluid{max-width:100%;height:auto}.container{--bs-gutter-x:1.5rem;--bs-gutter-y:0;width:100%;padding-right:calc(var(--bs-gutter-x)*.5);padding-left:calc(var(--bs-gutter-x)*.5);margin-right:auto;margin-left:auto}@media (min-width:576px){.container{max-width:540px}}@media (min-width:768px){.container{max-width:720px}}@media (min-width:992px){.container{max-width:960px}}@media (min-width:1200px){.container{max-width:1140px}}@media (min-width:1400px){.container{max-width:1320px}}:root{--bs-breakpoint-xs:0;--bs-breakpoint-sm:576px;--bs-breakpoint-md:768px;--bs-breakpoint-lg:992px;--bs-breakpoint-xl:1200px;--bs-breakpoint-xxl:1400px}.row{--bs-gutter-x:1.5rem;--bs-gutter-y:0;display:flex;flex-wrap:wrap;margin-top:calc(-1*var(--bs-gutter-y));margin-right:calc(-.5*var(--bs-gutter-x));margin-left:calc(-.5*var(--bs-gutter-x))}.row>*{flex-shrink:0;width:100%;max-width:100%;padding-right:calc(var(--bs-gutter-x)*.5);padding-left:calc(var(--bs-gutter-x)*.5);margin-top:var(--bs-gutter-y)}.col-3{flex:0 0 auto;width:25%}.col-6{flex:0 0 auto;width:50%}.col-9{flex:0 0 auto;width:75%}@media (min-width:768px){.col-md-6{flex:0 0 auto;width:50%}.d-md-none{display:none!important}}@media (min-width:992px){.col-lg-6{flex:0 0 auto;width:50%}}.visually-hidden{width:1px!important;height:1px!important;padding:0!important;margin:-1px!important;overflow:hidden!important;clip:rect(0,0,0,0)!important;white-space:nowrap!important;border:0!important}.visually-hidden:not(caption){position:absolute!important}.text-center{text-align:center!important}.text-white{--bs-text-opacity:1;color:rgba(var(--bs-white-rgb),var(--bs-text-opacity))!important}body,html{-webkit-font-smoothing:antialiased;-moz-font-smoothing:antialiased;-o-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale}body{background:#f9f9f9;font-size:14px;line-height:1.5;font-family:Montserrat,Arial,sans-serif;color:#2a2a2a}h1,h2,h3{color:#2a2a2a}h3{font-size:22px}h1,h2,h3{margin-top:20px;margin-bottom:10px}.main_title{text-align:center;font-size:16px;margin-bottom:30px}.main_title h2{text-transform:uppercase;font-weight:700;letter-spacing:-1px;font-size:30px;margin-bottom:0;margin-top:0}.main_title p{font-weight:400;font-size:20px;color:#555}h2 span{color:#e04f67}a{color:#e14d67;text-decoration:none;outline:0}p{margin:0 0 20px}strong{font-weight:600}.btn_1,a.btn_1{border:none;font-family:inherit;font-size:inherit;color:#fff;background:#008489;padding:7px 20px;display:inline-block;outline:0;font-size:13px;-webkit-border-radius:3px;-moz-border-radius:3px;border-radius:3px;font-weight:600}#logo{margin-top:10px}header{width:100%;position:fixed;left:0;top:0;z-index:99999;padding:10px 0}header #logo .logo_sticky{display:none}nav{margin-top:20px!important;position:relative}#top_line{color:#fff;height:28px;font-size:12px;border-bottom:1px solid rgba(255,255,255,.2);font-size:11px;visibility:visible;opacity:1;margin-bottom:5px;position:relative;z-index:999999}ul#top_links{list-style:none;margin:0;padding:0;float:right}ul#top_links li{display:inline-block;border-left:1px solid rgba(255,255,255,.3);margin-right:5px;padding-left:8px;position:relative;font-weight:600}ul#top_links li:first-child{border-left:none;padding-left:0}ul#top_links a{color:#fff}.main-menu{position:relative;z-index:9;width:auto}.main-menu ul,.main-menu ul li,.main-menu ul li a{position:relative;margin-bottom:0;margin:0;padding:0}.main-menu ul li a{display:block;line-height:20px;padding:10px}.main-menu>ul>li>a{color:#fff;padding:0 8px 15px;font-size:14px;font-weight:600}.layer{position:fixed;top:0;left:0;width:100%;min-width:100%;z-index:100;min-height:100%;background-color:#000;z-index:99;background-color:rgba(0,0,0,.8);opacity:0;visibility:hidden}#close_in,#header_menu,.cmn-toggle-switch{display:none}@media (min-width:992px) and (max-width:1200px){.main-menu>ul>li>a{padding:0 5px 15px}}@media only screen and (min-width:992px){.main-menu{width:auto}.main-menu a{white-space:nowrap}.main-menu ul li{display:inline-block}.main-menu ul ul{position:absolute;border-top:2px solid #e04f67;z-index:1;visibility:hidden;left:3px;top:100%;margin:0;display:block;padding:0;background:#fff;min-width:230px;-webkit-box-shadow:0 6px 12px rgba(0,0,0,.175);box-shadow:0 6px 12px rgba(0,0,0,.175);-webkit-transform:translateY(20px);-ms-transform:translateY(20px);transform:translateY(20px);opacity:0;-webkit-border-radius:0 0 5px 5px;-moz-border-radius:0 0 5px 5px;border-radius:0 0 5px 5px}.main-menu ul ul li{display:block;height:auto;padding:0}.main-menu ul ul li a{font-size:13px;color:#444;display:block;font-weight:500}.main-menu ul ul:before{bottom:100%;left:15%;border:solid transparent;content:" ";height:0;width:0;position:absolute;border-bottom-color:#e04f67;border-width:7px;margin-left:-7px}}@media only screen and (max-width:991px){#header_menu{text-align:center;padding:25px 15px 10px;position:relative;display:block}.main-menu ul li{border-top:none;border-bottom:1px solid #ededed;color:#fff}.main-menu ul li a{padding:10px 15px!important}.main-menu a,.main-menu li{display:block;color:#333!important}.main-menu li{position:relative}.main-menu ul>li{padding-bottom:0}.main-menu ul>li i{float:right;font-size:16px}.main-menu ul li.submenu ul{font-size:13px;border-left:1px solid #ededed;margin:0 0 15px 25px}.main-menu ul li.submenu ul li{font-size:13px;border:0}.main-menu{overflow:auto;transform:translateX(-105%);top:0;left:0;bottom:0;width:55%;height:100%;position:fixed;background-color:#fff;z-index:999999;-webkit-box-shadow:1px 0 5px 0 rgba(50,50,50,.55);-moz-box-shadow:1px 0 5px 0 rgba(50,50,50,.55);box-shadow:1px 0 5px 0 rgba(50,50,50,.55)}.main-menu .show-submenu+ul{display:none;visibility:hidden}.cmn-toggle-switch{position:relative;display:block;overflow:visible;position:absolute;top:0;right:15px;margin:0;padding:0;width:30px;height:30px;font-size:0;text-indent:-9999px;-webkit-appearance:none;-moz-appearance:none;appearance:none;box-shadow:none;border:none}.cmn-toggle-switch span{display:block;position:absolute;top:10px;left:0;right:0;height:2px;background:#fff}.cmn-toggle-switch span::after,.cmn-toggle-switch span::before{position:absolute;display:block;left:0;width:100%;height:2px;background-color:#fff;content:""}.cmn-toggle-switch span::before{top:-10px}.cmn-toggle-switch span::after{bottom:-10px}}@media only screen and (max-width:480px){.main-menu{width:100%}a#close_in{display:block;position:absolute;right:15px;top:10px;width:20px;height:20px}#close_in i{color:#555!important;font-size:16px}}#toTop{position:fixed;right:0;opacity:0;visibility:hidden;bottom:25px;margin:0 25px 0 0;z-index:9999;transform:scale(.7);width:46px;height:46px;background-color:rgba(0,0,0,.6);opacity:1;border-radius:50%;text-align:center;font-size:21px;color:#fff}#toTop:after{content:"";font-family:fontello;position:relative;display:block;top:50%;-webkit-transform:translateY(-55%);transform:translateY(-55%)}.margin_60{padding-top:60px;padding-bottom:60px}.tour_container{background-color:#fff;-webkit-box-shadow:0 0 15px 0 rgba(0,0,0,.1);-moz-box-shadow:0 0 15px 0 rgba(0,0,0,.1);box-shadow:0 0 15px 0 rgba(0,0,0,.1);margin:0;margin-bottom:30px;-webkit-border-radius:5px;-moz-border-radius:5px;border-radius:5px;position:relative}.img_container{position:relative;overflow:hidden;-webkit-border-top-left-radius:5px;-webkit-border-top-right-radius:5px;-moz-border-radius-topleft:5px;-moz-border-radius-topright:5px;border-top-left-radius:5px;border-top-right-radius:5px}.tour_container .tour_title{padding:15px 15px 10px;position:relative}.tour_container .tour_title h3{margin:0 0 3px;font-size:14px;text-transform:uppercase}.img_container img{-webkit-transform:scale(1.2);transform:scale(1.2);-webkit-backface-visibility:hidden}.short_info{position:absolute;left:0;bottom:0;background:-webkit-linear-gradient(top,transparent,#000);background:linear-gradient(to bottom,transparent,#000);width:100%;padding:45px 10px 8px 5px;color:#fff;font-size:13px;font-weight:500;line-height:1}.short_info i{font-size:25px;display:inline-block;vertical-align:middle;font-weight:400;font-style:normal;padding:0;margin:0}.short_info .price{float:right;font-size:28px;font-weight:700;display:inline-block}.short_info .price sup{font-size:18px;position:relative;top:-5px}#preloader{position:fixed;top:0;left:0;right:0;width:100%;height:100%;bottom:0;background-color:#fff;z-index:999999999}.sk-spinner-wave.sk-spinner{margin:-15px 0 0-25px;position:absolute;left:50%;top:50%;width:50px;height:30px;text-align:center;font-size:10px}.sk-spinner-wave div{background-color:#ccc;height:100%;width:6px;display:inline-block;-webkit-animation:1.2s ease-in-out infinite sk-waveStretchDelay;animation:1.2s ease-in-out infinite sk-waveStretchDelay}.sk-spinner-wave .sk-rect2{-webkit-animation-delay:-1.1s;animation-delay:-1.1s}.sk-spinner-wave .sk-rect3{-webkit-animation-delay:-1s;animation-delay:-1s}.sk-spinner-wave .sk-rect4{-webkit-animation-delay:-.9s;animation-delay:-.9s}.sk-spinner-wave .sk-rect5{-webkit-animation-delay:-.8s;animation-delay:-.8s}@-webkit-keyframes sk-waveStretchDelay{0%,100%,40%{-webkit-transform:scaleY(.4);transform:scaleY(.4)}20%{-webkit-transform:scaleY(1);transform:scaleY(1)}}@keyframes sk-waveStretchDelay{0%,100%,40%{-webkit-transform:scaleY(.4);transform:scaleY(.4)}20%{-webkit-transform:scaleY(1);transform:scaleY(1)}}.badge_save{position:absolute;top:0;right:0;width:65px;height:77px;color:#fff;text-align:center;text-transform:uppercase;background:url(/img/badge_save.png);font-size:11px;line-height:12px;padding-top:32px}.badge_save strong{display:block;font-size:14px;font-weight:700}main{background-color:#f9f9f9;z-index:2;position:relative}@media (max-width:991px){nav{margin-top:10px!important}header #logo img.logo_normal,header #logo img.logo_sticky{width:auto;height:30px}}@media (max-width:767px){#top_line{display:none}.main_title{font-size:14px}.main_title h2{font-size:24px}.main_title p{font-size:16px}.margin_60{padding-top:30px;padding-bottom:30px}}[class^=icon-]:before{font-family:fontello;font-style:normal;font-weight:400;speak:none;display:inline-block;text-decoration:inherit;width:1em;margin-right:.2em;text-align:center;font-variant:normal;text-transform:none;line-height:1em;margin-left:.2em}.icon-phone:before{content:""}.icon-up-open:before{content:""}.icon-down-open-mini:before{content:""}[class*=icon_set_1_]:before,[class^=icon_set_1_]:before{font-family:icon_set_1;font-style:normal;font-weight:400;speak:none;display:inline-block;text-decoration:inherit;width:1em;margin-right:.2em;text-align:center;font-variant:normal;text-transform:none;line-height:1em;margin-left:.2em}.icon_set_1_icon-15:before{content:"/"}.icon_set_1_icon-23:before{content:"6"}.icon_set_1_icon-28:before{content:"<"}.icon_set_1_icon-77:before{content:"m"}@-webkit-keyframes zoomIn{from{opacity:0;-webkit-transform:scale3d(.3,.3,.3);transform:scale3d(.3,.3,.3)}50%{opacity:1}}@keyframes zoomIn{from{opacity:0;-webkit-transform:scale3d(.3,.3,.3);transform:scale3d(.3,.3,.3)}50%{opacity:1}}.zoomIn{-webkit-animation-name:zoomIn;animation-name:zoomIn}html{scroll-behavior:smooth}.hero-wrap{position:relative;overflow:hidden;height:clamp(260px,78vw,340px)}@media (min-width:576px){.hero-wrap{height:340px}}@media (min-width:768px){.hero-wrap{height:420px}}@media (min-width:992px){.hero-wrap{height:520px}}@media (min-width:1200px){.hero-wrap{height:600px}}.hero-bg{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;object-position:center center;animation:20s ease-in-out infinite alternate hero-zoom}.hero-overlay{position:absolute;inset:0;width:100%;height:100%;z-index:10;background-color:rgba(0,0,0,.35)}@keyframes hero-zoom{from{transform:scale(1)}to{transform:scale(1.08)}}@media (prefers-reduced-motion:reduce){.hero-bg{animation:none}}.hero-content{position:absolute;inset:0;z-index:20;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:0 20px}.hero-title{font-family:Montserrat,sans-serif;font-weight:700;font-size:70px;line-height:1;color:#fff;margin:0 0 18px}.hero-subtitle{font-family:Montserrat,sans-serif;font-weight:400;font-size:13px;letter-spacing:4px;color:#fff;margin:0 0 28px}a.btn_1.hero-cta{letter-spacing:3px;background:0 0;color:#fff;border:1px solid rgba(255,255,255,.5)}.badge_tripadvisor{position:absolute;top:30px;left:30px;width:220px;z-index:2}.badge_tripadvisor img{width:100%;height:auto;display:block}@media (max-width:767px){.hero-content{transform:translateY(-10px)}.hero-title{font-size:36px;margin-bottom:12px}.hero-subtitle{font-size:12px;letter-spacing:2px;margin-bottom:18px;max-width:280px}.badge_tripadvisor{top:24px;left:24px;width:176px}}.img_container .badge_save{top:12px;right:12px}.cookie-consent-banner{position:fixed;left:0;right:0;bottom:0;z-index:100000;background:#222;color:#fff;box-shadow:0-2px 10px rgba(0,0,0,.15);padding:14px 0}.cookie-consent-inner{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px}.cookie-consent-text{margin:0;font-size:14px;flex:1 1 320px}.cookie-consent-text a{color:#fff;text-decoration:underline}.cookie-consent-actions{display:flex;gap:10px;flex-shrink:0}button.btn_1.outline{color:#555;background:0 0;border:2px solid #555;padding:5px 18px}.normal_price_list{text-decoration:line-through;margin-left:5px;color:#999;font-size:.9em;display:inline-block}
```

- [ ] **Step 2: Set `$critical_css_file` in `index.php`**

Find the closing `?>` of the PHP block at the top of `index.php` (currently line 11, right after the `$page_og` array closes):

```php
$page_og = [
  'title'       => 'Stampstour - Discover Chile',
  'description' => 'Daily tours to Valparaíso, Maipo Wine Valley, Andes & Santiago. Book your curated experience with Stampstour!',
  'url'         => 'https://stampstour.com/',
  'image'       => 'https://stampstour.com/img/Tours/portada.jpg',
];
?>
```

Add the new variable right before that `?>`:

```php
$page_og = [
  'title'       => 'Stampstour - Discover Chile',
  'description' => 'Daily tours to Valparaíso, Maipo Wine Valley, Andes & Santiago. Book your curated experience with Stampstour!',
  'url'         => 'https://stampstour.com/',
  'image'       => 'https://stampstour.com/img/Tours/portada.jpg',
];
$critical_css_file = __DIR__ . '/includes/critical/home.css';
?>
```

- [ ] **Step 3: Inline the critical CSS and convert the 5 stylesheets (+1 for icons) to non-blocking in `includes/head.php`**

Find this block (currently lines 73-81, the last lines of the file):

```html
<!-- GOOGLE WEB FONT (self-hosted) -->
<link rel="preconnect" href="https://cdn.openwidget.com">
<link href="/fonts/fonts.css" rel="stylesheet"/>
<!-- COMMON CSS -->
<link href="/css/bootstrap.min.css" rel="stylesheet"/>
<link href="/css/style.css" rel="stylesheet"/>
<link href="/css/vendors.css" rel="stylesheet"/>
<!-- CUSTOM CSS -->
<link href="/css/custom.css" rel="stylesheet"/>
```

Replace it with:

```html
<!-- Homepage-only inlined critical CSS (covers the fixed header/nav + hero -
     everything visible without scrolling). Generated once via the `critical`
     npm package against a local rendering of index.php at 390x844 and
     1470x900 viewports - it is a static snapshot, not auto-regenerated. If
     the header or hero markup changes meaningfully, regenerate with:
       npx critical <homepage-url> --dimensions 390x844 --dimensions 1470x900
     and replace includes/critical/home.css with the output (root-absolute
     any fonts/css/img url() paths it produces). -->
<?php if (!empty($critical_css_file) && is_file($critical_css_file)): ?>
<style><?= file_get_contents($critical_css_file) ?></style>
<?php endif; ?>

<!-- GOOGLE WEB FONT (self-hosted) -->
<link rel="preconnect" href="https://cdn.openwidget.com">
<link rel="preload" href="/fonts/fonts.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link href="/fonts/fonts.css" rel="stylesheet"></noscript>
<!-- COMMON CSS -->
<link rel="preload" href="/css/bootstrap.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link href="/css/bootstrap.min.css" rel="stylesheet"></noscript>
<link rel="preload" href="/css/style.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link href="/css/style.css" rel="stylesheet"></noscript>
<link rel="preload" href="/css/vendors.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link href="/css/vendors.css" rel="stylesheet"></noscript>
<link rel="preload" href="/css/bs-icon-font/bootstrap-icons.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link href="/css/bs-icon-font/bootstrap-icons.min.css" rel="stylesheet"></noscript>
<!-- CUSTOM CSS -->
<link rel="preload" href="/css/custom.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link href="/css/custom.css" rel="stylesheet"></noscript>
```

Note the new `bootstrap-icons.min.css` preload pair — `vendors.css` currently loads this via a blocking `@import`; Task 2 removes that `@import` now that it has its own proper (non-blocking) link.

- [ ] **Step 4: Lint and verify**

```bash
php -l index.php
php -l includes/head.php
grep -c "rel=\"stylesheet\"" includes/head.php
```

Expected: both files report `No syntax errors detected`. The grep should return `0` for plain blocking `rel="stylesheet"` links in the CSS block (they're now all either `rel="preload"` or inside `<noscript>`, and `grep -c` counts matching lines — a `<noscript><link ... rel="stylesheet">` line will still match this grep, so expect the count to be `6`, one per `<noscript>` fallback, not `0`. Confirm it's exactly `6` and that none of them are outside a `<noscript>` tag by inspecting the file directly.)

- [ ] **Step 5: Commit**

```bash
git add includes/critical/home.css index.php includes/head.php
git commit -m "Add homepage critical CSS and load sitewide stylesheets non-blocking"
```

---

### Task 2: Fix the `vendors.css` `@import` bug

**Files:**
- Modify: `css/vendors.css:1`

**Interfaces:**
- Consumes: Task 1's new `bootstrap-icons.min.css` preload/noscript pair in `includes/head.php` — that must exist before this task removes the `@import`, otherwise the icon font stops loading entirely. Task 1 must be complete first.
- Produces: nothing further downstream.

- [ ] **Step 1: Remove the `@import` from `css/vendors.css`**

The file's first line currently starts with:

```css
@charset "UTF-8";@import url("bs-icon-font/bootstrap-icons.min.css");@font-face{font-family:fontello;...
```

Remove exactly this substring: `@import url("bs-icon-font/bootstrap-icons.min.css");` — leaving `@charset "UTF-8";` immediately followed by the `@font-face{font-family:fontello;...` that came after the `@import`. Nothing else on this line or in the file changes.

- [ ] **Step 2: Verify the icon font still loads**

```bash
php -S localhost:8899 &
sleep 1
curl -s http://localhost:8899/index.php | grep -c "bs-icon-font/bootstrap-icons.min.css"
kill %1
```

Expected: `1` (the single preload `<link>` Task 1 added — confirms the stylesheet is still referenced somewhere, just no longer via `@import`).

- [ ] **Step 3: Commit**

```bash
git add css/vendors.css
git commit -m "Remove blocking @import from vendors.css (icon font now loads via its own preload link)"
```

---

### Task 3: Local visual verification

**Files:**
- None modified — this task only verifies. If a check fails, fix `includes/critical/home.css` or the markup from Task 1 in place, then re-verify.

**Interfaces:**
- Consumes: the working `index.php` / `includes/head.php` / `includes/critical/home.css` / `css/vendors.css` from Tasks 1-2.
- Produces: visual confirmation only.

- [ ] **Step 1: Start a local PHP server**

```bash
cd /Users/miguelmontero/Documents/superpowers/STAMP
php -S localhost:8899 > /tmp/php-server.log 2>&1 &
sleep 1
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8899/index.php
```

Expected: `200`.

- [ ] **Step 2: Capture screenshots at the same width set used for the hero work, using the reliable CDP/iframe method**

`--window-size` was found this session to silently clamp to a 500px width floor below that size — use a same-origin iframe at a fixed CSS width, or Chrome DevTools Protocol `Emulation.setDeviceMetricsOverride`, for any width you need to test below 500px. Widths: `375, 576, 650, 768, 880, 992, 1100, 1200, 1470, 1920`.

- [ ] **Step 3: Confirm no visible flash between first paint and full load**

For each width: capture one screenshot as early as possible after navigation starts (before the deferred stylesheets finish swapping in) and one after full load. The header (logo, nav, phone number) and hero (image, overlay, "Discover Chile" text, CTA button) should look the same in both — same fonts, same layout, same colors. This is the actual signal that the critical CSS in `includes/critical/home.css` covers everything needed; if something visibly changes size, font, or position between the two captures, that element's styling is missing from the critical CSS and needs to be added (regenerate per the comment in Task 1 Step 3, or add the specific missing rule directly).

- [ ] **Step 4: Confirm the JS-disabled fallback works**

Run headless Chrome with `--disable-javascript` (this both stops the `onload` swap handlers from ever firing AND makes the browser render `<noscript>` content, matching a real JS-disabled visitor) and take a screenshot:

```bash
"/Applications/Google Chrome.app/Contents/MacOS/Google Chrome" --headless --disable-gpu --disable-javascript --screenshot=/tmp/noscript-check.png --window-size=1000,900 http://localhost:8899/index.php
```

View the resulting screenshot. Confirm the page is fully styled (header, hero, fonts, colors all present) — not a blank or unstyled page. This confirms the `<noscript>` fallback `<link>` tags from Task 1 actually work.

- [ ] **Step 5: Spot-check one other page is unaffected**

Load a tour page (e.g. `http://localhost:8899/maipo-valley-wine-tour-santiago.php`) and confirm it still renders fully styled — it gets the non-blocking stylesheet loading from Task 1's `head.php` changes (applies sitewide) but no inlined critical block (since it doesn't set `$critical_css_file`), which is expected and fine.

- [ ] **Step 6: Lint and tag-balance check**

```bash
php -l index.php
php -l includes/head.php
grep -c "<div" index.php
grep -c "</div>" index.php
```

Expected: no syntax errors; `<div`/`</div>` counts match (confirms Task 1's edits didn't disturb any existing markup).

- [ ] **Step 7: Stop the local server**

```bash
pkill -f "php -S localhost:8899"
```

- [ ] **Step 8: If any check failed, fix and re-verify**

Repeat Steps 1-7 for at least the affected width(s) after any fix. Do not proceed to Task 4 until every check in Steps 3-6 passes.

- [ ] **Step 9: Commit (only if Step 8 required a fix)**

```bash
git add includes/critical/home.css includes/head.php index.php
git commit -m "Fix critical CSS gap found during visual verification"
```

If no fix was needed, skip this step.

---

### Task 4: Deploy and confirm production

**Files:**
- None modified — this task pushes already-committed changes and confirms the live site.

**Interfaces:**
- Consumes: the commits from Tasks 1-3.
- Produces: nothing further — final task in the plan.

- [ ] **Step 1: Push to origin**

```bash
git push
```

- [ ] **Step 2: Remind the user to deploy**

State clearly that pushing to `origin/main` does not deploy automatically — the user needs to `git pull` on the cPanel server to see this live.

- [ ] **Step 3: Once deployed, re-run PageSpeed Insights as a sanity check (not a blocking gate)**

Optional follow-up. Compare against the last recorded baseline (mobile: 41/100, FCP 5.3s, LCP 9.9s; desktop: 67/100, LCP 1.7s) and note the FCP change specifically — that's what this plan directly targets. A correlated LCP improvement is likely but not guaranteed to be large; full LCP improvement was never this plan's goal (see the design spec's Risks section).
