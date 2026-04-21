<?php
/**
 * Reusable "Coming in Sprint A" inline stub used for inactive tabs
 * and for unfinished sections inside the Info tab.
 * Receives $args['label'] — string, the section / tab name.
 */

if (!defined('ABSPATH')) {
    exit;
}

$label = isset($args['label']) ? (string) $args['label'] : 'This section';
?>

<div class="p-6 bg-stone-50 dark:bg-slate-900/40 border border-dashed border-stone-300 dark:border-slate-700">
    <p class="font-semibold text-stone-700 dark:text-slate-300"><?php echo esc_html($label); ?></p>
    <p class="text-sm text-stone-500 dark:text-slate-500 mt-1">
        This section ships in Sprint A.
    </p>
</div>
