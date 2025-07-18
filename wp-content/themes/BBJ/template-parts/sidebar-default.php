
<section class="flex-grow basis-[400px] p-2 relative shrink-0">

  <?php get_template_part("template-parts/socials"); ?>
  <?php get_template_part("template-parts/sidebar-newsletter"); ?>



  <?php if (!newPremiumCheck()): ?>
    <!-- Tag ID: bigbrotherjunkies_siderail_right_1 -->
    <div align="center" data-freestar-ad="__300x600" id="bigbrotherjunkies_siderail_right_1">
      <script data-cfasync="false" type="text/javascript">
        freestar.config.enabled_slots.push({ placementName: "bigbrotherjunkies_siderail_right_1", slotId: "bigbrotherjunkies_siderail_right_1" });
      </script>
    </div> 
  <?php endif; ?>


  <h3 class="font-mainHead text-2xl text-primary500 mt-6">Big Brother Stats</h3>
  
  <div class="h-[6px] bg-second500 w-[100px] mb-4"></div>
      


  <?= do_shortcode("[bbj_stats]") ?>


  <div class="text-xs">More stats to come!</div>

 
  
      <?php if (!newPremiumCheck()): ?> 
        <div class="sticky top-4 pt-4">
          <div class="">
            <!-- Tag ID: bigbrotherjunkies_siderail_right_2 -->
            <div align="center" data-freestar-ad="__300x600" id="bigbrotherjunkies_siderail_right_2">
            <script data-cfasync="false" type="text/javascript">
              freestar.config.enabled_slots.push({ placementName: "bigbrotherjunkies_siderail_right_2", slotId: "bigbrotherjunkies_siderail_right_2" });
            </script>
            </div>
          </div>
        </div>
      <?php endif; ?>
  

</section>