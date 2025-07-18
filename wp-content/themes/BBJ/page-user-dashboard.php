<?php get_header(); ?>
<?php $current_feed_update_count = get_user_meta(get_current_user_id(), 'feed_update_count', true);
 ?>

<div class="bbj-container-inner">


  <div class="bbj-inner-content-container">
    <div class="bbj-content-container">
        <h1 class="font-mainHead text-2xl text-primary500">User Dashboard</h1>
          <div class="h-[6px] bg-second500 w-[100px] mb-4"></div>
      
    
      
          <?php if ( is_user_logged_in() ): ?>  
            
            <?php if (premiumCheck()): ?>
              <div class="w-full my-2 bg-yellow-50 p-4 rounded-lg border-2 border-yellow-300 relative">
                <div class="absolute top-2 left-2"><i class="fa-solid fa-heart text-red-500"></i></div>
                <div class="absolute top-2 right-2"><i class="fa-solid fa-heart text-red-500"></i></div>
                <div class="text-lg text-center font-bold">Thank you for being a supporter!</div>
                <div class="text-sm">As ad-blockers become more and more prominent and pre-installed in browsers, it becomes harder and harder to pay the massive server bills for keeping this site running.
                  Rather than beg for donations every year, I decided to offer a premium membership for those who want to support the site and get some extra perks in return.
                  <br><br>
                  As a supporter, I also want you to have personal access to my email address so you can contact me directly with any questions or concerns you may have.  Please just keep the subject line something like "BBJ Supporter" so I can answer it asap. My personal email is <a href="mailto:stevebeans@gmail.com" class="text-blue-500">stevebeans@gmail.com</a>
                  <br><br>
                  Please don't give it out, I get enough spam as is <i class="fa-regular fa-face-smile"></i>
                </div>
              </div>
            <?php endif; ?>

            <form method="post">
              <div class="border p-4 shadow-md rounded-md">
                <div class="font-bold text-lg">User / Supporter Settings</div>
                <div class="text-sm">Supporters Only 

                <?php echo !premiumCheck() ? '<a href="/become-supporter">Become a supporter today</a>!' : '-- Thanks for being a supporter' ?>

                <div class="border-t border-gray-400 my-3 "></div>
                <div class="mb-2 flex items-center">
                  <div class="w-52"><label for="update-count" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white w-52">Feed Update Count</label></div>
                  <div class="w-full"><select name="feed_update_count" id="feed_update_count" class="border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500">
                  <option value="10" <?php echo ($current_feed_update_count == '10') ? 'selected' : ''; ?>>10</option>
                  <option value="20" <?php echo ($current_feed_update_count == '20') ? 'selected' : ''; ?>>20</option>
                  <option value="30" <?php echo ($current_feed_update_count == '30') ? 'selected' : ''; ?>>30</option>
                  <option value="50" <?php echo ($current_feed_update_count == '50') ? 'selected' : ''; ?>>50</option>

                  </select>
                  </div>       
                </div>
                <div class="w-full py-3 border-b border-gray-200">This option will be how many feed updates you are shown on the live feed update threads (and more to come soon)</div>
                
                <input type="hidden" name="user_id" value="<?php echo get_current_user_id(); ?>" />

                </div>
              </div>

              <div class="border p-4 shadow-md rounded-md mt-6">
                <div class="font-bold text-lg">Profile Settings</div>
                

                
                <div class="border-t border-gray-400 my-3 "></div>
                <div class="mb-2 items-center grid grid-cols-2 md:grid-cols-4">
                  <div class=""><label for="display_name" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white w-52">Display Name</label></div>
                  <div class="">
                    <input type="text" name="display_name" id="display_name" class="border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500" value="<?php echo get_the_author_meta('display_name', get_current_user_id()); ?>" />
                  </div>
                
                </div>
              </div>

              <div class="border p-4 shadow-md rounded-md mt-6">
                <div class="font-bold text-lg">Avatar Settings</div>
                <div class="text-center mb-4">
                  <?php $avatar_url = get_avatar_url(get_current_user_id(), ["size" => 32]); ?>
                  <img src="<?= $avatar_url ?>"class="rounded-lg  h-24 w-24 mr-2" alt="Author Avatar">
              </div>

                <input type="hidden" id="avatar_nonce" name="avatar_nonce" value="<?php echo wp_create_nonce('avatar_upload_nonce'); ?>" />
                <input type="file" id="avatar-upload" name="avatar-upload">
                <br />
                <button id="avatar-upload-button" class="button button-primary new-bbj-btn">Upload Avatar</button>
                
            </div>

            <script>
    jQuery(document).ready(function($) {
        $('#avatar-upload-button').on('click', function(e) {
            e.preventDefault();
            
            var file_data = $('#avatar-upload').prop('files')[0];
            var nonce = $('#avatar_nonce').val();
            var form_data = new FormData();
            form_data.append('file', file_data);
            form_data.append('action', 'upload_avatar');
            form_data.append('security', nonce);

            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'post',
                contentType: false,
                processData: false,
                data: form_data,
                success: function(response) {
                    if (response.success) {
                        location.reload(); // Reload page to see new avatar
                    } else {
                        alert(response.data.message); 
                    }
                }
            });
        });
    });
</script>




                <input type="submit" value="Save Changes" class="bbj-btn mt-4 ">
            </form>


          <?php else: ?>

          <div class="border p-4 shadow-md rounded-md">
            <div class="text-lg">You must be logged in to access this page</div>

            <div class="">

              <div class="my-4"><a href="/log-in" class="btn btn-primary">Log In</a></div>

              <div class="my-4"><a href="/registration" class="btn btn-primary">Register</a></div> 
              
                  

            </div>

          </div>
            <?php endif; ?>
    </div>

    <div>
        <?php get_template_part("template-parts/sidebar-default"); ?>
    </div>
  </div>




</div>


<?php get_footer(); ?>
