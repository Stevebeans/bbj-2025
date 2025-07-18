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

      <?php  the_content(); ?>


      <?php  $mainQuery = new WP_Query(array(
      'posts_per_page'  => -1,
      'post_type' => 'bigbrother-players',
      ));
      
      $results = array();
      $season_array = array();
      $full_array = array();
      

      while($mainQuery->have_posts()) {
        $mainQuery->the_post();

        $seasons = rwmb_meta('bb_seasons_played');

        echo rwmb_meta('first_name');
        echo rwmb_meta('last_name');
        echo '<br><br>';
  
        //echo '<pre>',print_r($seasons,1),'</pre>';

        // global $wpdb;
        // $sql = 'SELECT * FROM wp_bbj_seasons
        // WHERE 
       

         // .. ADDED:
		 unset( $played_seasons );
		 $player_id = get_the_ID();
		 $first_name = rwmb_meta('first_name');
		 $last_naem = rwmb_meta('last_name');
		 // .. END ADDED:


		 
		 foreach ($seasons as $season): 
		  // .. ADDED:
        $season_id = $season[ 'pick_seasons' ];
		  // .. END ADDED:

      echo '<pre>',print_r($season,1),'</pre>';

          $sql = 'SELECT full_name FROM wp_bbj_seasons
          WHERE ID = "'. $season['pick_seasons'] . '"';
          $results = $wpdb->get_results($sql);
		
		  // .. ADDED:
		  $played_seasons[ $season_id ] = $results[0]->full_name;
		  // .. END ADDED:
         ?>

          <?php //echo '<pre>',print_r($season['pick_seasons'],1),'</pre>';  ?>
          <?php //echo '<pre>',print_r($results ,1),'</pre>';?>
          <?php 
                echo '----';
                echo $results[0]->full_name;
                echo '<br><br>';
          

          //array_push($season_array, $season['pick_seasons'])
          ?>
          
          
          <?php 
          
          // if ($season['pick_seasons']) {
          //   array_push($season_array, $season['pick_seasons']);
          // } 
          
          ?>


          <?php //echo '<pre>',print_r($season,1),'</pre>';?>
          <?php //echo '<pre>',print_r($results ,1),'</pre>';?>
          <?php 

          ?>
        
         <?php endforeach; 

		// .. ADDED:
		// (throw all seasons into this player's array)
		$player[ $player_id ][ 'first_name' ] = $first_name;
		$player[ $player_id ][ 'last_name' ] = $last_name;
		$player[ $player_id ][ 'seasons_played' ] = $played_seasons;
		// .. END ADDED:

            echo $results[0]->fullname;
          //echo '<pre>',print_r($season_array,1),'</pre>';
         ?>


      <?php 
    array_push($full_array, array(
      'first_name'    => rwmb_meta('first_name'),
      'last_name'     => rwmb_meta('last_name'),
      'Seasons'       => $seasonName
      )     
    );  
    
    }; 
    
    echo '<pre>',print_r($player,1),'</pre>';
    
    //echo '<pre>',print_r($full_array,1),'</pre>';
    
    ?>
			<?php // echo '<pre>',print_r($results,1),'</pre>';?>
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
