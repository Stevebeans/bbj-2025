<?php
$ad_number = $args['ad_number'] ?? '51558'; // Default to '51558' if no number is passed

if (!premiumCheck() && function_exists('the_ad')): ?>
    <div class="mx-auto p-4 relative max-w-screen-xl">
        <?php the_ad($ad_number); ?>
        <div class="text-xs absolute bottom-0 right-0 bg-slate-50">Don't want ads? <a href="/become-supporter/" class=" text-primary500 hover:underline mt-4">Go premium here</a></div>
    </div>
<?php endif; ?>

    