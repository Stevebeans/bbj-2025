

<div class="sideBar">

    <!-- HouseStatus   -->
    <div class="widgetContain">
      <div class="widgetHeader">
        <div class="titleBar"></div>
          <?php if (is_user_logged_in()):

            global $current_user;
            $currentUser = wp_get_current_user();

            //echo '<pre>',print_r($currentUser,1),'</pre>';
            ?>
          <h2 class="widgetTitle">Welcome, <?php echo $currentUser->display_name; ?></h2>  


          <div class="widgetBody">

          
            <div class="user-avatar-sidebar"><?php echo get_avatar($currentUser->ID); ?></div>
            <div class="sidebar-button-contain">
              
              <div class="sidebar-button"><a href="<?php echo site_url(); ?>/dashboard">Edit Profile</a></div>
              <div class="sidebar-button"><a href="<?php echo site_url(); ?>/my-profile">View Profile</a></div>
            </div>
          </div>

          <?php
          else:
             ?>  
          <h2 class="widgetTitle">Welcome, Visitor!</h2>  
          <div class="widgetBody">

      
          <div class="sidebar-button-contain">            
            <div class="sidebar-button"><a href="<?php echo site_url(); ?>/log-in">Login</a></div>
            <div class="sidebar-button"><a href="<?php echo site_url(); ?>/registration">Sign Up</a></div>
          </div>
          </div>
          <?php
          endif; ?> 
      </div>
    </div>


  
    <!-- HouseStatus   -->
    <div class="widgetContain">
      <div class="widgetHeader">
        <div class="titleBar"></div>
          <h2 class="widgetTitle">House Status</h2>        
      </div>
      <div class="widgetBody">

        <?php get_template_part("template-parts/spoiler-box"); ?>
      
        
      </div>
    </div>

          <?php get_template_part("template-parts/google-flex"); ?>

  </div>