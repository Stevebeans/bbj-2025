<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="lg:sticky lg:top-24">
    <?php get_template_part('template-parts/components/ad-placeholder', null, [
        'slot'        => 'homepage_sidebar_sticky',
        'size'        => '300x600',
        'mobile_size' => '300x250',
        'note'        => __('Sticky · desktop half-page / mobile MPU', 'bbj-v2-theme'),
    ]); ?>
</div>
