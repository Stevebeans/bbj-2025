<?php
/**
 * Ad placeholder component.
 *
 * Visual placeholder matching the design mockup (dimensions + metadata).
 * Use this everywhere an ad slot is planned but not yet wired to the real
 * bbjd_ad() system. When we're ready, swap the get_template_part call for
 * template-parts/components/ad-slot with the equivalent slot name.
 *
 * Placeholders in use are tracked in docs/ad-slots.md.
 *
 * Args (via get_template_part):
 *   - slot    (string, required)  Slot name to reserve for later wiring
 *   - size    (string, required)  "970x90", "300x250", "320x50", etc.
 *   - note    (string, optional)  One-line context like "Below nav · above content · eager-load"
 *   - wrapper (string, optional)  Extra classes on the outer container
 */

if (!defined('ABSPATH')) {
    exit;
}

$args    = $args ?? [];
$slot    = isset($args['slot'])    ? (string) $args['slot']    : '';
$size    = isset($args['size'])    ? (string) $args['size']    : '';
$note    = isset($args['note'])    ? (string) $args['note']    : '';
$wrapper = isset($args['wrapper']) ? (string) $args['wrapper'] : '';

if ($slot === '' || $size === '') {
    return;
}

// Parse "970x90" into CSS aspect-ratio + max width so the box stays the
// right shape at all viewport widths without hardcoding pixel heights.
$dims = array_map('intval', explode('x', strtolower($size)));
$w = $dims[0] ?? 0;
$h = $dims[1] ?? 0;
if ($w <= 0 || $h <= 0) {
    return;
}
$pretty = $w . ' × ' . $h . ' ' . strtoupper(trim(preg_replace('/^\d+x\d+/', '', $size))) ?: $w . ' × ' . $h;
?>
<div class="bbj-ad-placeholder <?php echo esc_attr($wrapper); ?>"
     data-ad-slot="<?php echo esc_attr($slot); ?>"
     data-ad-size="<?php echo esc_attr($size); ?>"
     role="complementary"
     aria-label="<?php echo esc_attr(sprintf(__('%s ad placeholder', 'bbj-v2-theme'), $size)); ?>">
    <div class="mx-auto max-w-screen-xl px-2 py-4">
        <div class="bg-stone-100 dark:bg-gray-800 border border-dashed border-stone-300 dark:border-gray-600 rounded flex flex-col items-center justify-center text-center"
             style="max-width: <?php echo (int) $w; ?>px; margin-left: auto; margin-right: auto; aspect-ratio: <?php echo (int) $w; ?> / <?php echo (int) $h; ?>;">
            <div class="font-osw uppercase tracking-wider text-primary-500 dark:text-primary-400 text-lg md:text-2xl">
                <?php echo esc_html($w); ?> × <?php echo esc_html($h); ?>
                <?php
                $rest = trim(preg_replace('/^\d+x\d+\s*/i', '', $size));
                if ($rest !== '') {
                    echo ' ' . esc_html(strtoupper($rest));
                }
                ?>
            </div>
            <?php if ($note !== '') : ?>
                <div class="mt-1 text-xs md:text-sm uppercase tracking-widest text-gray-500 dark:text-gray-400">
                    <?php echo esc_html($note); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
