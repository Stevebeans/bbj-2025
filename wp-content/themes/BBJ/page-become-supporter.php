<?php

/**
 * Template Name: Single Sidebar - Full
 */
get_header(); ?>

<div class="bbj-container-inner">
	<div class="my-2 flex w-full flex-col rounded-md bg-white lg:flex-row overflow-hidden">
		<div class="flex-grow">

      <div class="bg-primary500 w-full p-2">
        <h1 class="text-white text-2xl font-bold"><?php the_title(); ?></h1>
      </div>

      <div class="w-full p-2 border-r border-slate-200">
        <h2 class="text-xl font-bold my-2">Enjoy The Ad-Free Experience</h2>

        <div class="prose max-w-prose"><p>
        Hey there, Big Brother Junkies fan! We've got something special just for you. How about enjoying all the amazing content you love on our site without any ads getting in the way? For only $4.95 a month, you can become a supporter and get that smooth, ad-free experience you've always wanted.</p>

        <p>
We're passionate about bringing you the best Big Brother updates and insights, and your support helps us keep the site up and running. Plus, you'll be part of our amazing community, contributing directly to the site you love. So, what do you say? Join us as a supporter and let's make Big Brother Junkies even better, together!</p>
        </div>
        <?php if ( is_user_logged_in() ): ?>
        <div id="payment-options">
          <div class="flex">
            <div class="flex-1 bg-sky-200 border rounded border-blue-200 p-4 m-2 text-center cursor-pointer select-option" data-value="4.95" data-plan-type="1">
              <h3 class="text-lg font-bold">Monthly</h3>
              <p>$4.95/month *</p>
            </div>
            <div class="flex-1 bg-sky-200 border rounded border-blue-200 p-4 m-2 text-center cursor-pointer select-option" data-value="39"  data-plan-type="2">
              <h3 class="text-lg font-bold">Annual</h3>
              <p>$39/year *</p>
              <p class="text-green-500">Save 35%!</p>
            </div>
          </div>
          <div class="text-sm">* You will be re-billed automatically unless you cancel</div>

          <div class="mb-4 mt-6 border-b border-gray-200 dark:border-gray-700">
                  <ul class="flex flex-wrap -mb-px text-lg font-medium text-center" id="myTab" data-tabs-toggle="#myTabContent" role="tablist">
                      <li class="mr-2" role="presentation">
                          <button class="inline-block p-4 border-b-2 rounded-t-lg" id="profile-tab" data-tabs-target="#profile" type="button" role="tab" aria-controls="profile" aria-selected="false">Credit Card <i class="fa-solid fa-credit-card"></i></button>
                      </li>
                      <li class="mr-2" role="presentation">
                          <button class="inline-block p-4 border-b-2 border-transparent rounded-t-lg hover:text-gray-600 hover:border-gray-300 dark:hover:text-gray-300" id="dashboard-tab" data-tabs-target="#dashboard" type="button" role="tab" aria-controls="dashboard" aria-selected="false">PayPal <i class="fa-brands fa-cc-paypal"></i></button>
                      </li>
                  </ul>
              </div>
              <div id="bbj-payment-summary" class="my-4 p-4 bg-white border rounded shadow-md hidden">
                  <!-- The payment summary will be inserted here -->
              </div>
              <div id="myTabContent">
                  <div class="hidden p-4 rounded-lg bg-gray-50 dark:bg-gray-800" id="profile" role="tabpanel" aria-labelledby="profile-tab">
                      <p class="text-sm text-gray-500 dark:text-gray-400"><?php echo FrmFormsController::get_form_shortcode(["id" => 9]); ?></p>
                  </div>
                  <div class="hidden p-4 rounded-lg bg-gray-50 dark:bg-gray-800" id="dashboard" role="tabpanel" aria-labelledby="dashboard-tab">
                      <p class="text-sm text-gray-500 dark:text-gray-400"><?php echo FrmFormsController::get_form_shortcode(["id" => 14]); ?></p>
                  </div>
                  
              </div>
          </div>
          <?php else: ?>
          <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-800">
            <p class="text-sm text-gray-500 dark:text-gray-400">You must be logged in to become a supporter. <a href="/log-in">Login</a> or <a href="/registration">Register</a></p>
          </div>
          <?php endif; ?>
        </div>
    </div>

    <div class="w-full md:w-[320px]  flex-shrink-0">
		<?php get_template_part("template-parts/sidebar-default"); ?>
		</div>
  </div>
</div>

<?php get_footer();
