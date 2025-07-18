<div class="post-content">
  <div class="feed-update-container">
    <div class="update-left">

      <div class="post-content">
          <div class="promobox">
    <b>Quick Links:</b> 
      <a href="/livefeeds/" target="_blank"><b>Watch Live Feeds</b></a> | 
      <a href="https://twitter.com/BigBrotherBBJ" target="_blank" class="promosprite twitter">Twitter</a> |
      <a href="http://www.facebook.com/bigbrotherjunkies" target="_blank"  class="promosprite facebook">Facebook</a> | 
      <A href="https://www.instagram.com/bigbrotherjunky/" target="_blank">Instagram</a> | 
      <a href="/donate">Donate</a>
    </div>

    <?php if (!premiumCheck()): ?>
    Tired of seeing ads?<br>
    <a href="/become-a-supporter/">Become a Big Brother Junkie supporter here
    </a>
    <?php endif; ?>

          <?php the_content(); ?>    
      </div>    
    </div>                    
      <?php get_template_part("template-parts/sidebar-spoiler-box"); ?>
  </div>         
</div>