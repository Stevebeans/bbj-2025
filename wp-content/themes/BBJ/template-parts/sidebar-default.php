
<aside class="w-full lg:w-[310px] relative shrink-0 ml-4 space-y-4">

  <section class="v2-sidebar-container p-2">
    <?php get_template_part("template-parts/socials"); ?>
  </section>

  <section class="v2-sidebar-container p-2">
    <?php get_template_part("template-parts/sidebar-newsletter"); ?>
  </section>

  <section class="v2-sidebar-container-adbox">
    <h2 class="v2-ad-subheader">Advertisement</h2>
    <?php bbj_echo_ad( 'sidebar_top' ); ?>
  </section>

  <section class="v2-sidebar-container p-2">
    <h2 class="v2-primary-subheader mb-2">Hot Posts</h2>
    <?= do_shortcode("[bbj_hot_posts]") ?>
  </section>

  <!-- insert most popular posts section // perhaps top 10 posts with most comments in last 30 days--> 

  <section class="v2-sidebar-container-adbox sticky top-4">
    <h2 class="v2-ad-subheader">Advertisement</h2>
    <?php bbj_echo_ad( 'sidebar_bottom' ); ?>
  </section>
</aside>