<?php
/**
 * Admin shell — Season Info tab body.
 * Receives $args['season'] — season row object.
 * Receives $args['season_id'] — int.
 *
 * BasicInfo + Dates sections are live. Images / Winners / Roster render as
 * "Coming in Sprint A" stubs within this tab.
 */

if (!defined('ABSPATH')) {
    exit;
}

$season    = $args['season'];
$season_id = (int) $args['season_id'];

$full_name     = (string) ($season->full_name ?? '');
$season_number = (string) ($season->season_number ?? '');
$abbreviation  = (string) ($season->abbreviation ?? '');
$start_date    = (string) ($season->start_date ?? '');
$end_date      = (string) ($season->end_date ?? '');
?>

<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="space-y-6">
    <?php wp_nonce_field('edit_season_action', 'edit_season_nonce'); ?>
    <input type="hidden" name="action" value="bbj_v2_edit_season_info">
    <input type="hidden" name="season_id" value="<?php echo esc_attr($season_id); ?>">

    <!-- BasicInfo -->
    <fieldset class="p-5 border border-stone-200 dark:border-slate-700 bg-white dark:bg-slate-900/40">
        <legend class="px-2 font-osw text-lg uppercase tracking-wide text-primary-500 dark:text-secondary-500">
            Basic Info
        </legend>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-2">
            <div class="md:col-span-2">
                <label class="block text-sm font-semibold text-stone-700 dark:text-slate-300 mb-1" for="bbj-season-name">
                    Full name <span class="text-red-600">*</span>
                </label>
                <input type="text" id="bbj-season-name" name="season_name" required
                       value="<?php echo esc_attr($full_name); ?>"
                       class="w-full px-3 py-2 border border-stone-300 dark:border-slate-700 bg-white dark:bg-slate-900">
            </div>

            <div>
                <label class="block text-sm font-semibold text-stone-700 dark:text-slate-300 mb-1" for="bbj-season-number">
                    Season number <span class="text-red-600">*</span>
                </label>
                <input type="text" id="bbj-season-number" name="season_number" required
                       value="<?php echo esc_attr($season_number); ?>"
                       class="w-full px-3 py-2 border border-stone-300 dark:border-slate-700 bg-white dark:bg-slate-900">
            </div>

            <div>
                <label class="block text-sm font-semibold text-stone-700 dark:text-slate-300 mb-1" for="bbj-season-abbr">
                    Abbreviation
                </label>
                <input type="text" id="bbj-season-abbr" name="season_abbreviation"
                       value="<?php echo esc_attr($abbreviation); ?>"
                       placeholder="BB27"
                       class="w-full px-3 py-2 border border-stone-300 dark:border-slate-700 bg-white dark:bg-slate-900">
            </div>
        </div>
    </fieldset>

    <!-- Dates -->
    <fieldset class="p-5 border border-stone-200 dark:border-slate-700 bg-white dark:bg-slate-900/40">
        <legend class="px-2 font-osw text-lg uppercase tracking-wide text-primary-500 dark:text-secondary-500">
            Dates
        </legend>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-2">
            <div>
                <label class="block text-sm font-semibold text-stone-700 dark:text-slate-300 mb-1" for="bbj-season-start">
                    Start date
                </label>
                <input type="date" id="bbj-season-start" name="season_start_date"
                       value="<?php echo esc_attr($start_date); ?>"
                       class="w-full px-3 py-2 border border-stone-300 dark:border-slate-700 bg-white dark:bg-slate-900">
            </div>

            <div>
                <label class="block text-sm font-semibold text-stone-700 dark:text-slate-300 mb-1" for="bbj-season-end">
                    End date
                </label>
                <input type="date" id="bbj-season-end" name="season_end_date"
                       value="<?php echo esc_attr($end_date); ?>"
                       class="w-full px-3 py-2 border border-stone-300 dark:border-slate-700 bg-white dark:bg-slate-900">
            </div>
        </div>
    </fieldset>

    <!-- Images stub -->
    <?php get_template_part('template-parts/admin/partials/seasons-edit-stub', null, [
        'label' => 'Images (cover + banner)',
    ]); ?>

    <!-- Winners stub -->
    <?php get_template_part('template-parts/admin/partials/seasons-edit-stub', null, [
        'label' => 'Winners (Winner / Runner-up / AFP)',
    ]); ?>

    <!-- Roster stub -->
    <?php get_template_part('template-parts/admin/partials/seasons-edit-stub', null, [
        'label' => 'Roster (add / remove players)',
    ]); ?>

    <!-- Save -->
    <div class="flex items-center justify-end gap-3">
        <a href="<?php echo esc_url(add_query_arg('tab', 'seasons', home_url('/admin/'))); ?>"
           class="px-4 py-2 text-sm text-stone-600 dark:text-slate-400 hover:text-stone-800 dark:hover:text-slate-200">
            Back to list
        </a>
        <button type="submit"
                class="px-5 py-2 bg-primary-500 hover:bg-primary-600 text-white font-semibold text-sm transition-colors">
            Save Season
        </button>
    </div>

</form>
