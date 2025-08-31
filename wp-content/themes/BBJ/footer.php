
  </section>
  
  <!-- Site footer -->
<footer class="border-t-[3px] border-primary500 bg-primary500/30 text-[15px] leading-6 py-[15px] dark:bg-slate-900 dark:text-gray-200">
  <div class="mx-auto max-w-screen-2xl px-2">
    <div class="flex flex-col md:flex-row">
      <!-- Col 1: Logo + about -->
      <div class="w-full p-5">
        <a href="<?php echo esc_url( site_url() ); ?>" class="inline-block">
          <img
            src="<?php echo esc_url( get_theme_file_uri('/images/bbjlogo2020.png') ); ?>"
            alt="<?php echo esc_attr( get_bloginfo('description') ); ?>"
            class="max-w-[180px] h-auto"
            loading="lazy"
            decoding="async"
          />
        </a>
        <p class="mt-3">
          Big Brother Junkies was created in 2011 to provide commentary with some snark about the CBS show Big Brother. We have covered every US season since Big Brother 13 and you can browse the archives or the player database to get all the old information you want about cast members and seasons.
        </p>
      </div>

      <!-- Col 2: Menu + Privacy Manager -->
      <div class="w-full p-5">
        <h4 class="font-mainHead text-[20px] font-semibold text-primary500">Site Links</h4>

        <div class="group">
          <?php
          wp_nav_menu([
            'theme_location' => 'bbj-footer-menu',
            // classes applied to the <ul> element:
            'menu_class'     => 'pl-2 m-0 list-none space-y-1 [&_li]:list-none [&_a]:font-semibold [&_a]:text-primary500 hover:[&_a]:text-second500',
            'container'      => false,
          ]);
          ?>
          <button
            id="pmLink"
            class="invisible group-hover:visible cursor-pointer bg-transparent border-0 text-inherit hover:text-gray-500 focus:visible"
            type="button"
          >
            Privacy Manager
          </button>
        </div>
      </div>

      <!-- Col 3: Socials -->
      <div class="w-full p-5">
        <h4 class="font-mainHead text-[20px] font-semibold text-primary500">Socials</h4>

        <div class="flex gap-2 text-2xl mt-1">
          <div>
            <a href="https://www.facebook.com/bigbrotherjunkies" target="_blank" rel="noopener noreferrer"
               class="text-primary500 hover:text-second500">
              <i class="fa-brands fa-facebook"></i>
            </a>
          </div>
          <div>
            <a href="https://www.instagram.com/bigbrotherjunky/" target="_blank" rel="noopener noreferrer"
               class="text-primary500 hover:text-second500">
              <i class="fa-brands fa-instagram"></i>
            </a>
          </div>
          <div>
            <a href="https://twitter.com/BigBrotherBBJ" target="_blank" rel="noopener noreferrer"
               class="text-primary500 hover:text-second500">
              <i class="fa-brands fa-twitter"></i>
            </a>
          </div>
        </div>
      </div>
    </div>

    <div class="mx-auto max-w-screen-2xl text-center text-xs py-2">
      © <?php echo date('Y'); ?> JunkyNet Media, LLC. All Rights Reserved
    </div>
  </div>
</footer>


  <!-- Pre Footer -->
<?php wp_footer(); ?>
  <!-- Post Footer -->

<?php if (!premiumCheck()): ?>
<script type="text/javascript">
  window._taboola = window._taboola || [];
  _taboola.push({flush: true});
</script>
<?php endif; ?>
</body>
</html>