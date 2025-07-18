<?php
/**
 * The template for displaying all pages
 *
 * This is the template that displays all pages by default.
 * Please note that this is the WordPress construct of pages
 * and that other 'pages' on your WordPress site may use a
 * different template.
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package bigbrotherjunkies
 */

get_header();
?>

<div class="bodyContainer">
	<!-- Main   -->
	<div class="mainBody">
		<div class="widgetContain boxShadowsft">
			<div class="widgetHeader">
        <div class="titleBar"></div>
          <h2 class="widgetTitle">Register To Big Brother Junkies</h2>        
      </div>
			<div class="widgetBody">

      <?php echo do_shortcode( "[profilepress-login id='1']")?>

			
			</div>
		</div>

	
	</div>

	<!-- SideBar   -->
  <div class="sideBar">

    <!-- HouseStatus   -->
    <div class="widgetContain">
      <div class="widgetHeader">
        <div class="titleBar"></div>
          <?php if (is_user_logged_in()): 
              $currentUser = wp_get_current_user();
              //echo '<pre>',print_r($currentUser,1),'</pre>';
          ?>
          <h2 class="widgetTitle">Stats and Crap</h2>  

          <a href="/user-dashboard">User Dashboard</a>
          <?php else: ?>  
          <h2 class="widgetTitle">Welcome, Visitor!</h2>  
          <?php endif; ?> 
      </div>
      <div class="widgetBody">Here is a small account area that will have some quick links to anything account related. 
        Such items are possibly new posts since last visit, link to edit profile, your comment count, your comment ratio, 
        your membership status (premium, etc)
      </div>
    </div>
  
    <!-- HouseStatus   -->
    <div class="widgetContain">
      <div class="widgetHeader">
        <div class="titleBar"></div>
          <h2 class="widgetTitle">House Status</h2>        
      </div>
      <div class="widgetBody">dsfsdfsdd
      </div>
    </div>

  </div>
</div>

<?php
get_sidebar();
get_footer();
