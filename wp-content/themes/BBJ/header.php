<!DOCTYPE html>
<html <?php language_attributes(); ?>> 

<?php
  $addFreeExperience = false;
  $bbjAdCheck = "regular";
  $bbjUpdater = "visitor";
  
  global $bbj_is_admin;
  
  $current_season_id = get_option( 'bbj_v2_current_season', '' );


  if ( is_user_logged_in() ) {
    if ( current_user_can("administrator") || current_user_can("editor") ) {
      $bbjAdCheck = "premium";
      $bbjUpdater = "updater";
      $addFreeExperience = true;
    }
  }

  $logIn = is_user_logged_in();
  $currentUser = wp_get_current_user();
  $bbj_user_exists = ( $currentUser instanceof WP_User && $currentUser->exists() );
?>

<head>
  <meta charset="<?php bloginfo("charset"); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name='ir-site-verification-token' value='2012599910'>

  <?php bbj_echo_ad('in_header'); ?>
  <?php bbj_echo_code('in_header_misc'); ?>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Caveat:wght@400;500;600&family=Open+Sans&family=Oswald&family=Roboto&family=Yanone+Kaffeesatz&display=swap" rel="stylesheet">
  <?php wp_head(); ?>

  <?php 
  $current_date_time_pst = new DateTime('now', new DateTimeZone('America/Los_Angeles'));
  $latest_unix = bbj_get_latest_unix();
  ?>
</head>

<body <?php body_class(); ?>>

<header>
  <script>
    if (localStorage.getItem('color-theme') === 'dark' || (!('color-theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        document.documentElement.classList.add('dark');
    } else {
        document.documentElement.classList.remove('dark')
    }
  </script>
 <nav class="bg-white rounded dark:bg-gray-900 drop-shadow z-50 relative">
    <div class="flex max-w-screen-xl justify-between mx-auto px-2 py-1 text-[10px] md:text-sm text-gray-500 dark:text-gray-400">
      <div class="text-xs">
      <?php
        if ($bbj_is_admin) { ?>
          <a href="/wp-admin/admin.php?page=bbj-v2-edit-season&season_id=<?php echo $current_season_id; ?>">Edit Season</a> |
        <?php
        }
        ?> 
        <a href="/contact/" class="hidden md:inline">Contact |</a>  
        <a href="/privacy-policy/"  class="hidden md:inline">Privacy |</a> 
      Follow: <a href="https://www.facebook.com/bigbrotherjunkies" target="_blank"><i class="fa-brands fa-facebook ml-1"></i></a>
      <a href="https://www.instagram.com/bigbrotherjunky/" target="_blank"><i class="fa-brands fa-instagram ml-1"></i></a>
      <!-- <a href="https://x.com/BigBrotherBBJ" target="_blank"><i class="fa-brands fa-square-x-twitter ml-2"></i></a> -->
      </div>
      <div class="text-right text-xs hidden md:block">
        <span class="text-gray-500 dark:text-gray-400">Current BB Time: </span>
        <?php echo esc_html( $current_date_time_pst->format('D, M jS h:i A') ); ?>
        <span class="text-gray-500 dark:text-gray-400"> | </span>
        Last updated:
            <time class="updated"
                  datetime="<?php echo date('c', $latest_unix); ?>"
                  itemprop="dateModified">
              <?php echo human_time_diff( $latest_unix, current_time('timestamp') ) . ' ago'; ?>
            </time>
      </div>
      <!-- Mobile Date/Time -->
      <div class="text-xs md:hidden">
        <span class="text-gray-500 dark:text-gray-400">BB Time: </span>
        <?php echo esc_html( $current_date_time_pst->format('h:i A') ); ?>
        <span class="text-gray-500 dark:text-gray-400"> | </span>
        Last updated:
            <time class="updated"
                  datetime="<?php echo date('c', $latest_unix); ?>"
                  itemprop="dateModified">
              <?php echo human_time_diff( $latest_unix, current_time('timestamp') ) . ' ago'; ?>
            </time>
      </div>
    </div>

    <!-- Primary Header Bar -->
    <div class="flex flex-wrap items-center justify-between mx-auto px-2 py-1 md:p-2 w-full max-w-screen-xl border-t border-slate-200 dark:border-slate-500">
      <div class="block shrink-0">
        <h1>
          <a href="<?= esc_url( site_url() ) ?>">
            <img src="<?= esc_url( BBJ_IMAGES . "/bbjlogo2020.png" ) ?>" alt="<?= esc_attr( get_bloginfo("description") ) ?>" class=" w-48 md:w-auto h-auto">
          </a>
          <span class="clip-rect-1 clip-path-inset-50 h-1 m-0 overflow-hidden p-0 absolute w-1 word-wrap-normal">Big Brother Junkies</span>
        </h1>
      </div>

      <div class="hidden lg:flex grow">
        <?php get_template_part("template-parts/search-bar"); ?>
      </div>

      <div class="flex items-center md:order-2 shrink-0">
        <button id="theme-toggle" type="button" class="text-gray-500 mr-2 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-200 dark:focus:ring-gray-700 rounded-lg text-sm p-1.5">
          <svg id="theme-toggle-dark-icon" class="hidden w-5 h-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"></path></svg>
          <svg id="theme-toggle-light-icon" class="hidden w-5 h-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" fill-rule="evenodd" clip-rule="evenodd"></path></svg>
        </button>

        <?php if ( $bbj_user_exists ): ?>
          <button type="button" class="flex mr-3 text-sm bg-gray-800 rounded-full md:mr-0 focus:ring-4 focus:ring-gray-300 dark:focus:ring-gray-600" id="user-menu-button" aria-expanded="false" data-dropdown-toggle="user-dropdown" data-dropdown-placement="bottom">
            <span class="sr-only">Open user menu</span>
            <img class="w-8 h-8 rounded-full" src="<?php echo esc_url( get_avatar_url( $currentUser->ID ) ); ?>" alt="<?php echo esc_attr( $currentUser->display_name ); ?>">
          </button>

          <div class="z-40 hidden my-4 text-base list-none bg-white divide-y divide-gray-100 rounded shadow dark:bg-gray-700 dark:divide-gray-600" id="user-dropdown">
            <div class="px-4 py-3">
              <span class="block text-sm text-gray-900 dark:text-white"><?php echo esc_html( $currentUser->display_name ); ?></span>
              <span class="block text-sm font-medium text-gray-500 truncate dark:text-gray-400"><?php echo esc_html( $currentUser->user_email ); ?></span>
            </div>
            <ul class="py-1" aria-labelledby="user-menu-button">
              <li><a href="/user-dashboard" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:hover:bg-gray-600 dark:text-gray-200 dark:hover:text-white">Settings</a></li>
              <li><a href="<?php echo esc_url( wp_logout_url() ); ?>" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:hover:bg-gray-600 dark:text-gray-200 dark:hover:text-white">Sign out</a></li>
            </ul>
          </div>
        <?php else: ?>
          <img class="bg-white" src="<?php echo esc_url( BBJ_IMAGES . '/bbjlogomobile.png' ); ?>" alt="<?php echo esc_attr( get_bloginfo('description') ); ?>">
        <?php endif; ?>

        <button data-collapse-toggle="mobile-menu-2" type="button" class="inline-flex items-center p-2 ml-1 text-sm text-gray-500 rounded-lg lg:hidden hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-gray-200 dark:text-gray-400 dark:hover:bg-gray-700 dark:focus:ring-gray-600" aria-controls="mobile-menu-2" aria-expanded="false">
          <span class="sr-only">Open main menu</span>
          <svg class="w-6 h-6" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 10a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 15a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"></path></svg>
        </button>
      </div>
    </div>
    <div class="w-full bg-primary500">
      <!-- Desktop Menu -->
      <div class="max-w-screen-xl mx-auto hidden md:block ">
        <div class="flex items-center justify-between px-2 py-1">
          <div class="text-xs lg:text-sm">
            <a href="https://paramountplus.qflm.net/c/161260/3116112/3065" target="_blank" class="v2-highlight-text">Watch Feeds</a> 
            <span class="inline-flex items-center gap-1 rounded-full bg-red-600 px-2 py-0.5 text-xs font-semibold ml-1 text-white">
              <span class="h-2 w-2 rounded-full bg-white animate-pulse"></span>
              LIVE
            </span>
          </div>
          <div>
            <?php           
              wp_nav_menu([
                "theme_location" => "bbj-main-menu",
                "items_wrap" =>
                  '<ul id="%1$s" class="%2$s desktop-nav px-0.5">%3$s' .
                  ( is_user_logged_in()
                    ? '<li><a href="/user-dashboard">Settings</a></li><li><a href="' . esc_url( wp_logout_url() ) . '">Log Out</a></li>'
                    : '<li><a href="/log-in">Log In</a></li><li><a href="/registration">Register</a></li>'
                  ) .
                  "</ul>",
                "container" => "",
                "menu_class" => "lg:flex py-0.5 hidden text-xs lg:text-sm",
              ]);
           ?>
          </div>
          <div class="text-xs lg:text-sm">
            <a href="/become-supporter/" class="v2-highlight-text md:ml-2">Go Ad Free</a>
          </div>
          
        </div>
      </div>

      <!-- Mobile Menu -->
      <div class="w-full md:hidden">
        <div class="flex items-center justify-between px-2 py-1">
          <div class="">
            <a href="https://paramountplus.qflm.net/c/161260/3116112/3065" target="_blank" class="v2-highlight-text">Watch Feeds</a> 
            <span class="inline-flex items-center gap-1 rounded-full bg-red-600 px-2 py-0.5 text-xs font-semibold ml-1 text-white">
              <span class="h-2 w-2 rounded-full bg-white animate-pulse"></span>
              LIVE
            </span>
          </div>
          <div class="">
            <a href="/become-supporter/" class="v2-highlight-text md:ml-2">Go Ad Free</a>
          </div>
        </div>
        <div class="container mx-auto hidden lg:flex" id="mobile-menu-2">
          <div class="md:hidden"><?php get_template_part("template-parts/search-bar"); ?></div>
          <ul id="bbj-main-menu" class="menu list-none p-0">
            <li class="nav-class-li"><a href="<?= esc_url( site_url() ) ?>" class="nav-class-a">afdsdafads</a></li>
            <?php 
            
            wp_nav_menu([
              "theme_location" => "bbj-main-menu",
              "items_wrap" =>
                '<ul id="%1$s" class="%2$s nav-class-li">%3$s' .
                ( is_user_logged_in()
                  ? '<li><a href="/user-dashboard">Settings</a></li><li><a href="' . esc_url( wp_logout_url() ) . '">Log Out</a></li>'
                  : '<li><a href="/log-in">Log In</a></li><li><a href="/registration">Register</a></li>'
                ) .
                "</ul>",
              "container" => "",
              "menu_class" => "flex flex-col md:flex-row py-0.5 px-1 lg:hidden",
            ]);
            ?>
          </ul>
        </div>
      </div>
    </div>
  </nav>
</header>

<section id="main-body" class="bg-slate-200 dark:bg-slate-700">
  <?php echo do_shortcode( '[bbj_spoiler_bar]' ); ?>  
  <?php bbj_echo_ad('below_header'); ?>

  <div id="user-role" data-role="<?= esc_attr($bbjAdCheck) ?>"></div>

  <?php if ( feedUpdater() ): ?>
    <div id="feed-update-box"></div>
  <?php endif; ?>