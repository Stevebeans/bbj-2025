<?php
/**
 * Admin shell — Seasons pane (list view).
 */

if (!defined('ABSPATH')) {
    exit;
}

$seasons           = bbj_v2_get_seasons('season_number', 'DESC');
$current_season_id = (int) get_option('bbj_v2_current_season', 0);

// Notices from query args
$notice_html = '';
if (!empty($_GET['error']) && $_GET['error'] === 'not_found') {
    $notice_html = '<div class="mb-4 p-3 bg-red-50 text-red-800 border border-red-200 dark:bg-red-900/20 dark:text-red-200 dark:border-red-800">That season doesn\'t exist.</div>';
}
?>

<section class="p-6 bg-white border border-stone-200 dark:bg-gray-900 dark:border-slate-800">

    <div class="flex items-start justify-between gap-4 mb-6">
        <div>
            <h1 class="font-mainHead text-3xl text-primary-500 dark:text-secondary-500">Seasons</h1>
            <p class="text-sm text-stone-600 dark:text-slate-400 mt-1">
                All Big Brother seasons tracked on the site.
            </p>
        </div>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('bbj_v2_create_season_action', 'bbj_v2_create_season_nonce'); ?>
            <input type="hidden" name="action" value="bbj_v2_create_season">
            <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 bg-primary-500 hover:bg-primary-600 text-white font-semibold text-sm transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Add Season
            </button>
        </form>
    </div>

    <?php echo $notice_html; ?>

    <?php if (empty($seasons)): ?>
        <div class="p-10 text-center border border-dashed border-stone-300 dark:border-slate-700">
            <p class="text-stone-600 dark:text-slate-400 mb-4">No seasons yet — add your first one.</p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="inline">
                <?php wp_nonce_field('bbj_v2_create_season_action', 'bbj_v2_create_season_nonce'); ?>
                <input type="hidden" name="action" value="bbj_v2_create_season">
                <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 bg-primary-500 hover:bg-primary-600 text-white font-semibold text-sm transition-colors">
                    Add Season
                </button>
            </form>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-stone-50 dark:bg-slate-800/40 text-stone-600 dark:text-slate-400 uppercase text-xs tracking-wide">
                    <tr>
                        <th class="px-4 py-2 text-left">Name</th>
                        <th class="px-4 py-2 text-left">#</th>
                        <th class="px-4 py-2 text-left">Dates</th>
                        <th class="px-4 py-2 text-left">Winner</th>
                        <th class="px-4 py-2 text-left">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($seasons as $season):
                        get_template_part('template-parts/admin/partials/seasons-list-row', null, [
                            'season'            => $season,
                            'current_season_id' => $current_season_id,
                        ]);
                    endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

</section>
