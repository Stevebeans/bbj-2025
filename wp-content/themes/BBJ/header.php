<!DOCTYPE html>
<html <?php language_attributes(); ?>> 

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Caveat:wght@400;500;600&family=Open+Sans&family=Oswald&family=Roboto&family=Yanone+Kaffeesatz&display=swap" rel="stylesheet">

  <?php
  $addFreeExperience = false;

  $bbjAdCheck = "regular";
  $bbjUpdater = "visitor";
  if (is_user_logged_in()):
    if (current_user_can("administrator") || current_user_can("editor")):
      $bbjAdCheck = "premium";
      $bbjUpdater = "updater";
      $addFreeExperience = true;
    endif;
  endif;

?>
  <head>
    <meta charset="<?php bloginfo("charset"); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name='ir-site-verification-token' value='2012599910'>

    <!-- GDPR Stub File -->
<script>"use strict"; function _typeof(t) { return (_typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (t) { return typeof t } : function (t) { return t && "function" == typeof Symbol && t.constructor === Symbol && t !== Symbol.prototype ? "symbol" : typeof t })(t) } !function () { var t = function () { var t, e, o = [], n = window, r = n; for (; r;) { try { if (r.frames.__tcfapiLocator) { t = r; break } } catch (t) { } if (r === n.top) break; r = r.parent } t || (!function t() { var e = n.document, o = !!n.frames.__tcfapiLocator; if (!o) if (e.body) { var r = e.createElement("iframe"); r.style.cssText = "display:none", r.name = "__tcfapiLocator", e.body.appendChild(r) } else setTimeout(t, 5); return !o }(), n.__tcfapi = function () { for (var t = arguments.length, n = new Array(t), r = 0; r < t; r++)n[r] = arguments[r]; if (!n.length) return o; "setGdprApplies" === n[0] ? n.length > 3 && 2 === parseInt(n[1], 10) && "boolean" == typeof n[3] && (e = n[3], "function" == typeof n[2] && n[2]("set", !0)) : "ping" === n[0] ? "function" == typeof n[2] && n[2]({ gdprApplies: e, cmpLoaded: !1, cmpStatus: "stub" }) : o.push(n) }, n.addEventListener("message", (function (t) { var e = "string" == typeof t.data, o = {}; if (e) try { o = JSON.parse(t.data) } catch (t) { } else o = t.data; var n = "object" === _typeof(o) && null !== o ? o.__tcfapiCall : null; n && window.__tcfapi(n.command, n.version, (function (o, r) { var a = { __tcfapiReturn: { returnValue: o, success: r, callId: n.callId } }; t && t.source && t.source.postMessage && t.source.postMessage(e ? JSON.stringify(a) : a, "*") }), n.parameter) }), !1)) }; "undefined" != typeof module ? module.exports = t : t() }();
</script>

<!-- CCPA Stub File -->
<script>(function () { var e = false; var c = window; var t = document; function r() { if (!c.frames["__uspapiLocator"]) { if (t.body) { var a = t.body; var e = t.createElement("iframe"); e.style.cssText = "display:none"; e.name = "__uspapiLocator"; a.appendChild(e) } else { setTimeout(r, 5) } } } r(); function p() { var a = arguments; __uspapi.a = __uspapi.a || []; if (!a.length) { return __uspapi.a } else if (a[0] === "ping") { a[2]({ gdprAppliesGlobally: e, cmpLoaded: false }, true) } else { __uspapi.a.push([].slice.apply(a)) } } function l(t) { var r = typeof t.data === "string"; try { var a = r ? JSON.parse(t.data) : t.data; if (a.__cmpCall) { var n = a.__cmpCall; c.__uspapi(n.command, n.parameter, function (a, e) { var c = { __cmpReturn: { returnValue: a, success: e, callId: n.callId } }; t.source.postMessage(r ? JSON.stringify(c) : c, "*") }) } } catch (a) { } } if (typeof __uspapi !== "function") { c.__uspapi = p; __uspapi.msgHandler = l; c.addEventListener("message", l, false) } })();
</script>

<!-- GPP Stub File -->
<script>window.__gpp_addFrame=function(e){if(!window.frames[e])if(document.body){var t=document.createElement("iframe");t.style.cssText="display:none",t.name=e,document.body.appendChild(t)}else window.setTimeout(window.__gpp_addFrame,10,e)},window.__gpp_stub=function(){var e=arguments;if(__gpp.queue=__gpp.queue||[],__gpp.events=__gpp.events||[],!e.length||1==e.length&&"queue"==e[0])return __gpp.queue;if(1==e.length&&"events"==e[0])return __gpp.events;var t=e[0],p=e.length>1?e[1]:null,s=e.length>2?e[2]:null;if("ping"===t)p({gppVersion:"1.1",cmpStatus:"stub",cmpDisplayStatus:"hidden",signalStatus:"not ready",supportedAPIs:["2:tcfeuv2","5:tcfcav1","6:uspv1","7:usnatv1","8:uscav1","9:usvav1","10:uscov1","11:usutv1","12:usctv1"],cmpId:0,sectionList:[],applicableSections:[],gppString:"",parsedSections:{}},!0);else if("addEventListener"===t){"lastId"in __gpp||(__gpp.lastId=0),__gpp.lastId++;var n=__gpp.lastId;__gpp.events.push({id:n,callback:p,parameter:s}),p({eventName:"listenerRegistered",listenerId:n,data:!0,pingData:{gppVersion:"1.1",cmpStatus:"stub",cmpDisplayStatus:"hidden",signalStatus:"not ready",supportedAPIs:["2:tcfeuv2","5:tcfcav1","6:uspv1","7:usnatv1","8:uscav1","9:usvav1","10:uscov1","11:usutv1","12:usctv1"],cmpId:0,sectionList:[],applicableSections:[],gppString:"",parsedSections:{}}},!0)}else if("removeEventListener"===t){for(var a=!1,i=0;i<__gpp.events.length;i++)if(__gpp.events[i].id==s){__gpp.events.splice(i,1),a=!0;break}p({eventName:"listenerRemoved",listenerId:s,data:a,pingData:{gppVersion:"1.1",cmpStatus:"stub",cmpDisplayStatus:"hidden",signalStatus:"not ready",supportedAPIs:["2:tcfeuv2","5:tcfcav1","6:uspv1","7:usnatv1","8:uscav1","9:usvav1","10:uscov1","11:usutv1","12:usctv1"],cmpId:0,sectionList:[],applicableSections:[],gppString:"",parsedSections:{}}},!0)}else"hasSection"===t?p(!1,!0):"getSection"===t||"getField"===t?p(null,!0):__gpp.queue.push([].slice.apply(e))},window.__gpp_msghandler=function(e){var t="string"==typeof e.data;try{var p=t?JSON.parse(e.data):e.data}catch(e){p=null}if("object"==typeof p&&null!==p&&"__gppCall"in p){var s=p.__gppCall;window.__gpp(s.command,(function(p,n){var a={__gppReturn:{returnValue:p,success:n,callId:s.callId}};e.source.postMessage(t?JSON.stringify(a):a,"*")}),"parameter"in s?s.parameter:null,"version"in s?s.version:"1.1")}},"__gpp"in window&&"function"==typeof window.__gpp||(window.__gpp=window.__gpp_stub,window.addEventListener("message",window.__gpp_msghandler,!1),window.__gpp_addFrame("__gppLocator"));
</script>
<script>
    window._sp_queue = [];
    window._sp_ = {
        config: {
            accountId: 1638,
            baseEndpoint: "https://cdn.privacy-mgmt.com",
            usnat: {
                includeUspApi: true,
            },

            gdpr: {},
            events: {
                onMessageChoiceSelect: function () {
                    console.log("[event] onMessageChoiceSelect", arguments);
                },
                onMessageReady: function () {
                    console.log("[event] onMessageReady", arguments);
                },
                onMessageChoiceError: function () {
                    console.log("[event] onMessageChoiceError", arguments);
                },
                onPrivacyManagerAction: function () {
                    console.log("[event] onPrivacyManagerAction", arguments);
                },
                onPMCancel: function () {
                    console.log("[event] onPMCancel", arguments);
                },
                onMessageReceiveData: function () {
                    console.log("[event] onMessageReceiveData", arguments);
                },
                onSPPMObjectReady: function () {
                    console.log("[event] onSPPMObjectReady", arguments);
                },

                /* Function required for resurfacing links */
                onConsentReady: function (message_type, uuid, string, info) {
                    if (message_type == "usnat" && info.applies) {
                        /* code to insert the GPP footer link */
                        document.getElementById("pmLink").style.visibility = "visible";
                        document.getElementById("pmLink").innerHTML =
                            "Do Not Sell or Share My Personal Information";
                        document.getElementById("pmLink").onclick = function () {
                            /* Set GPP ID */
                            window._sp_.usnat.loadPrivacyManagerModal("1035372");
                        };
                    }
                    if (message_type == "gdpr" && info.applies) {
                        /* code to insert the GDPR footer link */
                        document.getElementById("pmLink").style.visibility = "visible";
                        document.getElementById("pmLink").innerHTML =
                            "Privacy Preferences";
                        document.getElementById("pmLink").onclick = function () {
                            /* Set GDPR ID */
                            window._sp_.gdpr.loadPrivacyManagerModal("899033");
                        };
                    }
                },
                onError: function () {
                    console.log("[event] onError", arguments);
                },
            },
        },
    };
</script>
<script src="https://cdn.privacy-mgmt.com/unified/wrapperMessagingWithoutDetection.js" async></script>
    <?php wp_head(); ?>

    





     <?php
     $logIn = is_user_logged_in();

     if ($logIn):
       $currentUser = wp_get_current_user();
     endif;
     ?>

    <!-- Global site tag (gtag.js) - Google Analytics -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-1Q771W4ZV2"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());

      gtag('config', 'G-1Q771W4ZV2');
    </script>
    <script type="text/javascript">
    (function(c,l,a,r,i,t,y){
        c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};
        t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;
        y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);
    })(window, document, "clarity", "script", "sf3yr8knc6");
    </script>
    
   <?php if( function_exists('the_ad_placement') ) { the_ad_placement('header-code'); } ?>

   <meta name="ahrefs-site-verification" content="8026876afddfec6d7edc2fda6d5286f8e29ececb7ea8edaafb7f66ed478a7392">
    
     <?php if (!newPremiumCheck()): ?>
      <!-- PLACE THIS SECTION INSIDE OF YOUR HEAD TAGS -->
      <!-- Below is a recommended list of pre-connections, which allow the network to establish each connection quicker, speeding up response times and improving ad performance. -->
      <link rel="preconnect" href="https://a.pub.network/" crossorigin />
      <link rel="preconnect" href="https://b.pub.network/" crossorigin />
      <link rel="preconnect" href="https://c.pub.network/" crossorigin />
      <link rel="preconnect" href="https://d.pub.network/" crossorigin />
      <link rel="preconnect" href="https://c.amazon-adsystem.com" crossorigin />
      <link rel="preconnect" href="https://s.amazon-adsystem.com" crossorigin />
      <link rel="preconnect" href="https://btloader.com/" crossorigin />
      <link rel="preconnect" href="https://api.btloader.com/" crossorigin />
      <link rel="preconnect" href="https://cdn.confiant-integrations.net" crossorigin />
      <!-- Below is a link to a CSS file that accounts for Cumulative Layout Shift, a new Core Web Vitals subset that Google uses to help rank your site in search -->
      <!-- The file is intended to eliminate the layout shifts that are seen when ads load into the page. If you don't want to use this, simply remove this file -->
      <!-- To find out more about CLS, visit https://web.dev/vitals/ -->
      <link rel="stylesheet" href="https://a.pub.network/bigbrotherjunkies-com/cls.css">
      <script data-cfasync="false" type="text/javascript">
        var freestar = freestar || {};
        freestar.queue = freestar.queue || [];
        freestar.config = freestar.config || {};
        freestar.config.enabled_slots = [];
        freestar.initCallback = function () { (freestar.config.enabled_slots.length === 0) ? freestar.initCallbackCalled = false : freestar.newAdSlots(freestar.config.enabled_slots) }
      </script>
      <script src="https://a.pub.network/bigbrotherjunkies-com/pubfig.min.js" data-cfasync="false" async></script>
      <?php endif; ?>
  </head>
  <body <?php body_class(); ?> class="">

  <header> 
    
    <script>
    // On page load or when changing themes, best to add inline in `head` to avoid FOUC
    if (localStorage.getItem('color-theme') === 'dark' || (!('color-theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        document.documentElement.classList.add('dark');
    } else {
        document.documentElement.classList.remove('dark')
    }
</script>




    <nav class="bg-white rounded dark:bg-gray-900 drop-shadow z-50 relative">
      <div class="container flex flex-wrap items-center justify-between mx-auto px-2 py-1 md:p-2">
      
        <div class="hidden md:block shrink-0"><h1><a href="<?= site_url() ?>"><img src="<?= BBJ_IMAGES . "/bbjlogo2020.png" ?>" alt="<?= get_bloginfo("description") ?>" ></a> <span class="clip-rect-1 clip-path-inset-50 h-1 m-0 overflow-hidden
p-0 absolute w-1 word-wrap-normal">Big Brother Junkies</span></h1></div>        
        <div class="block md:hidden shrink-0"><h1><a href="<?= site_url() ?>"><img src="<?= BBJ_IMAGES . "/bbjlogomobile.png" ?>" alt="<?= get_bloginfo("description") ?>" ></a> <span class="clip-rect-1 clip-path-inset-50 h-1 m-0 overflow-hidden
p-0 absolute w-1 word-wrap-normal">Big Brother Junkies</span></h1></div> 

      <div class="hidden md:flex grow">
        <?php get_template_part("template-parts/search-bar"); ?>
      </div>

      <div class="flex items-center md:order-2 shrink-0">

        <div class="flex flex-col justify-center items-center mr-2">
          <div class="text-xs text-gray-500">Spoilers</div>
          <div class="mx-auto h-4 flex justify-center items-center"><i class="fa fa-toggle-off text-gray-500" id="toggleSpoiler"></i></div>
        </div>
          <button id="theme-toggle" type="button" class="text-gray-500 mr-2 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-200 dark:focus:ring-gray-700 rounded-lg text-sm p-1.5">
            <svg id="theme-toggle-dark-icon" class="hidden w-5 h-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"></path></svg>
            <svg id="theme-toggle-light-icon" class="hidden w-5 h-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" fill-rule="evenodd" clip-rule="evenodd"></path></svg>
          </button>

          <?php if (is_user_logged_in()): ?>
          <button type="button" class="flex mr-3 text-sm bg-gray-800 rounded-full md:mr-0 focus:ring-4 focus:ring-gray-300 dark:focus:ring-gray-600" id="user-menu-button" aria-expanded="false" data-dropdown-toggle="user-dropdown" data-dropdown-placement="bottom">
          <?php endif; ?>
            <span class="sr-only">Open user menu</span>
            <?php echo is_user_logged_in() ? '<img class="w-8 h-8 rounded-full" src="' . get_avatar_url($currentUser->ID) . '" alt="' . $currentUser->display_name . '">' : '<img class="bg-white" src="' . BBJ_IMAGES . '/bbjlogomobile.png" alt="' . get_bloginfo("description") . '" >'; ?>
          </button>
          <!-- Dropdown menu -->
          <div class="z-40 hidden my-4 text-base list-none bg-white divide-y divide-gray-100 rounded shadow dark:bg-gray-700 dark:divide-gray-600" id="user-dropdown">
            <div class="px-4 py-3">
              <span class="block text-sm text-gray-900 dark:text-white"><?= $currentUser->display_name ?></span>
              <span class="block text-sm font-medium text-gray-500 truncate dark:text-gray-400"><?= $currentUser->user_email ?></span>
            </div>
            <ul class="py-1" aria-labelledby="user-menu-button">
              <li>
                <a href="/user-dashboard" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:hover:bg-gray-600 dark:text-gray-200 dark:hover:text-white">Settings</a>
              </li>
              <li>
                <a href="<?= wp_logout_url() ?>" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:hover:bg-gray-600 dark:text-gray-200 dark:hover:text-white">Sign out</a>
              </li>
            </ul>
          </div>
          <button data-collapse-toggle="mobile-menu-2" type="button" class="inline-flex items-center p-2 ml-1 text-sm text-gray-500 rounded-lg lg:hidden hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-gray-200 dark:text-gray-400 dark:hover:bg-gray-700 dark:focus:ring-gray-600" aria-controls="mobile-menu-2" aria-expanded="false">
            <span class="sr-only">Open main menu</span>
            <svg class="w-6 h-6" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 10a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 15a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"></path></svg>
        </button>
        </div>
      </div> 
      <div class="flex flex-wrap items-center justify-between mx-auto bg-primary500 px-2 sm:px-4 py-1">
        <div class="container mx-auto hidden lg:flex" id="mobile-menu-2">   
        
          <div class="md:hidden"><?php get_template_part("template-parts/search-bar"); ?></div>        
          <ul id="bbj-main-menu" class="menu list-none p-0">
                <?php wp_nav_menu([
                  "theme_location" => "bbj-main-menu",
                  "items_wrap" =>
                    '<ul id="%1$s" class="%2$s nav-class-li">%3$s' .
                    (is_user_logged_in()
                      ? '<li><a href="/user-dashboard">Settings</a></li>  
                    <li><a href="' .
                        wp_logout_url() .
                        '">Log Out</a></li>'
                      : '
                      <li><a href="/log-in">Log In</a></li>
                      <li><a href="/registration">Register</a></li>
                      ') .
                    "</ul>",
                  "container" => "",
                  "menu_class" => "flex flex-col md:flex-row py-0.5 px-1",
                ]); ?>

          </ul>
        </div>
      </div>
    </nav>

</header>

<section id="main-body" class="bg-slate-200 dark:bg-slate-700">

  
      <?php get_template_part("template-parts/spoiler-bar"); ?>

      

      
      <?php // show_header_ad() ?>

      <?php if (!newPremiumCheck()): ?>
        <!-- Tag ID: bigbrotherjunkies_leaderboard_atf -->
        <div align="center" data-freestar-ad="__240x400 __336x280" id="bigbrotherjunkies_leaderboard_atf">
          <script data-cfasync="false" type="text/javascript">
            freestar.config.enabled_slots.push({ placementName: "bigbrotherjunkies_leaderboard_atf", slotId: "bigbrotherjunkies_leaderboard_atf" });
          </script>
        </div>
      <?php endif; ?>

      <!-- <div class=" max-w-6xl w-full  mx-auto my-2 border border-red-400 bg-red-200 p-4">
        <div class="text-center text-lg">Notice</div>
        <div class="text-sm">I will be away between Sunday, August 6th and Thursday, August 10th. <Br /> There should still be a daily post from me as well as one from Mel, and Jennifer will continue to do live updates in addition to hopefully a second person soon. You shouldn't notice much of a difference, but if you do, that's why. <br /> - Steve</div>
      </div> -->

    
      <div id="user-role" data-role="<?= $bbjAdCheck ?>"></div>
  <?php if (feedUpdater()): ?>
    <div id="feed-update-box"></div>
  <?php //get_template_part("template-parts/feed-updater", null, ["bbjUpdater" => $bbjUpdater]); ?>


<?php endif; ?>