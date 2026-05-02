<?php
/**
 * Ad slot component.
 *
 * Args (via get_template_part/set_query_var):
 *   - slot    (string, required)  Slot name registered in bigbrotherjunkies-data
 *   - label   (string, optional)  Optional visible label (e.g., "Advertisement")
 *   - wrapper (string, optional)  Extra wrapper classes
 */

if (!defined('ABSPATH')) {
    exit;
}

$args    = $args ?? [];
$slot    = $args['slot']    ?? '';
$label   = $args['label']   ?? '';
$wrapper = $args['wrapper'] ?? '';

if ($slot === '' || !function_exists('bbjd_ad') || !bbj_v2_should_show_ads()) {
    return;
}
?>
<div class="bbj-ad-slot <?php echo esc_attr($wrapper); ?>" data-slot="<?php echo esc_attr($slot); ?>">
    <?php if ($label !== '') : ?>
        <div class="text-xs uppercase tracking-widest text-gray-500 text-center mb-1">
            <?php echo esc_html($label); ?>
        </div>
    <?php endif; ?>
    <?php bbjd_ad($slot); ?>
</div>
