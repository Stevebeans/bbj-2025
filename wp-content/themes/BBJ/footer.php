
  </section>
  
  <!-- Site footer -->
  <footer class="site-footer">
    <div class="footer-contain">
      <div class="footer-col"><a href="<?php echo site_url(); ?>"><img src="<?php echo get_theme_file_uri("/images/bbjlogo2020.png"); ?>" alt="<?php echo get_bloginfo("description"); ?>"></a>
        <p>Big Brother Junkies was created in 2011 to provide commentary with some snark about the CBS show Big Brother. We have covered every US season since Big Brother 13 and you can browse the archives or the player database to get all the old information you want about cast members and seasons. </p>
      </div>
      <div class="footer-col"><h4>Site Links</h4>
      

      <?php wp_nav_menu([
        "theme_location" => "bbj-footer-menu",
        "menu_class" => "footer-menu",
      ]); ?>
  <button id="pmLink">Privacy Manager</button>

            <style>
                #pmLink {
                    visibility: hidden;
                    text-decoration: none;
                    cursor: pointer;
                    background: transparent;
                    border: none;
                }

                #pmLink:hover {
                    visibility: visible;
                    color: grey;
                }
            </style>

      
          
      </div>
      <div class="footer-col"><h4>Socials</h4>
      
        <div class="footer-socials">
          <div><a href="https://www.facebook.com/bigbrotherjunkies" target="_blank"><i class="fa-brands fa-facebook"></i></a></div>
          <div><a href="https://www.instagram.com/bigbrotherjunky/" target="_blank"><i class="fa-brands fa-instagram"></i></a></div>
          <div><a href="https://twitter.com/BigBrotherBBJ" target="_blank"><i class="fa-brands fa-twitter"></i></a></div>
        </div>
    
      </div>
    </div>

    <div class="fine-print">© 2024 JunkyNet Media, LLC. All Rights Reserved</div>
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