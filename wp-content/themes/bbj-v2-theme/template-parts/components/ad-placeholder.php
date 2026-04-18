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
 *   - slot        (string, required)  Slot name to reserve for later wiring
 *   - size        (string, required)  Desktop size: "970x90", "300x250", etc.
 *   - mobile_size (string, optional)  Under-md size, e.g. "320x50". If set,
 *                                     the desktop box is hidden < md and the
 *                                     mobile box is hidden ≥ md. CLS stays 0
 *                                     because each box reserves its own space.
 *   - note        (string, optional)  One-line context
 *   - wrapper     (string, optional)  Extra classes on the outer container
 */

if (!defined('ABSPATH')) {
    exit;
}

$args        = $args ?? [];
$slot        = isset($args['slot'])        ? (string) $args['slot']        : '';
$size        = isset($args['size'])        ? (string) $args['size']        : '';
$mobile_size = isset($args['mobile_size']) ? (string) $args['mobile_size'] : '';
$note        = isset($args['note'])        ? (string) $args['note']        : '';
$wrapper     = isset($args['wrapper'])     ? (string) $args['wrapper']     : '';

if ($slot === '' || $size === '') {
    return;
}

/**
 * Parse "970x90" or "320x50 MOBILE" into ints + a rest-of-string label.
 *
 * @return array{0:int,1:int,2:string}|null
 */
$parse = static function (string $raw): ?array {
    $parts = explode('x', strtolower($raw));
    $w = isset($parts[0]) ? (int) $parts[0] : 0;
    $h = isset($parts[1]) ? (int) $parts[1] : 0;
    if ($w <= 0 || $h <= 0) return null;
    $rest = trim(preg_replace('/^\d+x\d+\s*/i', '', $raw));
    return [$w, $h, $rest];
};

$desktop = $parse($size);
if ($desktop === null) return;
$mobile = $mobile_size !== '' ? $parse($mobile_size) : null;

$render_box = static function (array $dims, string $note, string $visibility_class): void {
    [$w, $h, $rest] = $dims;
    ?>
    <div class="<?php echo esc_attr($visibility_class); ?> mx-auto max-w-screen-xl px-2 py-4">
        <div class="bg-stone-100 dark:bg-gray-800 border border-dashed border-stone-300 dark:border-gray-600 rounded flex flex-col items-center justify-center text-center"
             style="max-width: <?php echo (int) $w; ?>px; margin-left: auto; margin-right: auto; aspect-ratio: <?php echo (int) $w; ?> / <?php echo (int) $h; ?>;">
            <div class="font-osw uppercase tracking-wider text-primary-500 dark:text-primary-400 text-lg md:text-2xl">
                <?php echo esc_html($w . ' × ' . $h); ?>
                <?php if ($rest !== '') : ?>
                    <?php echo ' ' . esc_html(strtoupper($rest)); ?>
                <?php endif; ?>
            </div>
            <?php if ($note !== '') : ?>
                <div class="mt-1 text-xs md:text-sm uppercase tracking-widest text-gray-500 dark:text-gray-400">
                    <?php echo esc_html($note); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
};
?>
<div class="bbj-ad-placeholder <?php echo esc_attr($wrapper); ?>"
     data-ad-slot="<?php echo esc_attr($slot); ?>"
     data-ad-size="<?php echo esc_attr($size); ?>"
     <?php if ($mobile_size !== '') : ?>data-ad-size-mobile="<?php echo esc_attr($mobile_size); ?>"<?php endif; ?>
     role="complementary"
     aria-label="<?php echo esc_attr(sprintf(__('%s ad placeholder', 'bbj-v2-theme'), $size)); ?>">
    <?php
    if ($mobile !== null) {
        $render_box($mobile,  $note, 'block md:hidden');
        $render_box($desktop, $note, 'hidden md:block');
    } else {
        $render_box($desktop, $note, 'block');
    }
    ?>
</div>
