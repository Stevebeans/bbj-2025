<?php
get_header(); ?>

<div class="body-regular" id="post-<?php the_ID(); ?>">

   <article>  
    <div class="widgetContain boxShadowsft">
        <div class="widgetHeader">
          <div class="titleBar"></div>
          <?php custom_breadcrumbs(); ?>
                       
        </div>
        <div class="widgetBody">
          <div class="entry-content">


          
            <div class="filter-contain">
              <div id="search-bar">
                <i class="icon fa fa-search search-icon"></i>
                <input type="text" name='searchBar' id='searchBar' placeholder='Search Players'>
              </div>

              <div id="gender-filer">
                <select name="gender_filter" id="gender_filter">
                  <option value="both" selected disabled>Gender</option>
                  <option value="both">All</option>
                  <option value="male">Male</option>
                  <option value="female">Female</option>
                </select>
              </div>
            </div>

            <div class="post-content">
              
              <div id="spinner">Loading....</div>
              <div id="player-table"></div>
              
            </div>

           
          </div>
        </div>
    </div>
  </article>


  
  <?php get_template_part("template-parts/sidebar-pages"); ?>


  
</div>
<div class="post-bottom">
    <div class="comment-section">
      <?php if (comments_open()):
        comments_template();
      endif; ?>
    </div>

    <aside class="comment-aside">
      Comment Stuff
    </aside>
  </div>
<?php get_footer();
