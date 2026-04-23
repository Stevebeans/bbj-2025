<?php
/**
 * Admin shell — Feed Updates pane.
 *
 * Single-screen UI for live-feed shifts: quick-post form at top,
 * scannable list of the 50 most recent updates below. Hydrates the
 * list server-side so the page is readable even if JS fails to load.
 * All mutations after initial render happen via fetch in admin-feed-updates.js.
 */

if (!defined('ABSPATH')) {
    exit;
}

// 50 most recent feed updates
$recent = get_posts([
    'post_type'   => 'live-feed-updates',
    'post_status' => 'publish',
    'numberposts' => 50,
    'orderby'     => 'date',
    'order'       => 'DESC',
]);

// Update type taxonomy terms for the category select
$terms = get_terms([
    'taxonomy'   => 'update_type',
    'hide_empty' => false,
    'orderby'    => 'name',
    'order'      => 'ASC',
]);
if (is_wp_error($terms)) {
    $terms = [];
}

// Social config — so we can disable checkboxes when not configured
$social_options = get_option('bbjd_social_settings', []);
$bluesky_configured = !empty($social_options['bluesky_handle']) && !empty($social_options['bluesky_app_password']);
$facebook_configured = !empty($social_options['facebook_page_token']);

// Preload per-row data the JS will need when editing (term id, raw values)
if (!function_exists('bbj_v2_feed_pane_row_data')) {
    function bbj_v2_feed_pane_row_data(\WP_Post $post): array
    {
        $term_ids = wp_get_object_terms($post->ID, 'update_type', ['fields' => 'ids']);
        if (is_wp_error($term_ids)) {
            $term_ids = [];
        }
        $first_term_id = !empty($term_ids) ? (int) $term_ids[0] : 0;

        $term_name = '';
        if ($first_term_id > 0) {
            $t = get_term($first_term_id, 'update_type');
            if ($t && !is_wp_error($t)) {
                $term_name = (string) $t->name;
            }
        }

        global $wpdb;
        $vote_table = $wpdb->prefix . 'bbj_feed_ratings';
        $total_votes = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(rating), 0) FROM {$vote_table} WHERE update_id = %d",
            $post->ID
        ));

        $social_results = get_post_meta($post->ID, '_social_posting_results', true);
        $thumb = get_the_post_thumbnail_url($post->ID, 'thumbnail') ?: '';

        return [
            'title'       => html_entity_decode(get_the_title($post), ENT_QUOTES, 'UTF-8'),
            'content'     => $post->post_content,
            'term_id'     => $first_term_id,
            'term_name'   => $term_name,
            'time_ago'    => human_time_diff(get_post_timestamp($post), current_time('timestamp')) . ' ago',
            'votes'       => $total_votes,
            'social'      => is_array($social_results) ? $social_results : null,
            'thumb'       => $thumb,
        ];
    }
}
?>

<section class="p-6 bg-white border border-stone-200 dark:bg-gray-900 dark:border-slate-800">

    <h1 class="font-mainHead text-3xl text-primary-500 dark:text-secondary-500 mb-2">
        Feed Updates
    </h1>
    <p class="text-sm text-stone-600 dark:text-slate-400 mb-6">
        Post and manage live-feed updates. New updates appear at the top of the list instantly.
    </p>

    <!-- Quick-post form -->
    <h2 class="font-osw text-lg uppercase tracking-wide text-primary-500 dark:text-secondary-500 mb-2">
        Quick Post
    </h2>
    <form id="bbj-feed-form" class="mb-8 space-y-3" enctype="multipart/form-data" novalidate>
        <div>
            <label class="block text-sm font-semibold text-stone-700 dark:text-slate-300 mb-1" for="bbj-feed-headline">
                Headline <span class="text-accent-red">*</span>
            </label>
            <input type="text" id="bbj-feed-headline" name="title" required
                   class="w-full px-3 py-2 border border-stone-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm"
                   placeholder="e.g. Jag wins HOH">
            <p class="hidden text-xs text-accent-red mt-1" data-headline-error>Headline required.</p>
        </div>

        <div>
            <label class="block text-sm font-semibold text-stone-700 dark:text-slate-300 mb-1" for="bbj-feed-details">
                Details <span class="text-stone-400 text-xs font-normal">(optional)</span>
            </label>
            <textarea id="bbj-feed-details" name="details" rows="3"
                      class="w-full px-3 py-2 border border-stone-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm"
                      placeholder="Extra context — who, where, what was said"></textarea>
        </div>

        <div>
            <label class="block text-sm font-semibold text-stone-700 dark:text-slate-300 mb-1" for="bbj-feed-image">
                Image <span class="text-stone-400 text-xs font-normal">(optional)</span>
            </label>
            <input type="file" id="bbj-feed-image" name="image" accept="image/*"
                   class="block w-full text-sm text-stone-700 dark:text-slate-300">
            <div class="hidden mt-2" data-image-preview>
                <img alt="" class="h-20 w-auto border border-stone-200 dark:border-slate-700">
                <button type="button" data-image-clear
                        class="ml-2 text-xs text-accent-red hover:underline">Remove</button>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-4">
            <div>
                <label class="block text-sm font-semibold text-stone-700 dark:text-slate-300 mb-1" for="bbj-feed-category">
                    Category
                </label>
                <select id="bbj-feed-category" name="update_type"
                        class="px-3 py-2 border border-stone-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm min-w-[180px]">
                    <option value="">(none)</option>
                    <?php foreach ($terms as $t): ?>
                        <option value="<?php echo (int) $t->term_id; ?>"><?php echo esc_html($t->name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <span class="block text-sm font-semibold text-stone-700 dark:text-slate-300 mb-1">Mode</span>
                <label class="inline-flex items-center gap-1.5 mr-3 text-sm">
                    <input type="radio" name="mode" value="feed" checked> Feed
                </label>
                <label class="inline-flex items-center gap-1.5 text-sm">
                    <input type="radio" name="mode" value="show"> Show
                </label>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-4 pt-1">
            <label class="inline-flex items-center gap-1.5 text-sm <?php echo $bluesky_configured ? '' : 'opacity-50'; ?>">
                <input type="checkbox" name="post_to_bluesky" value="1"
                       <?php disabled(!$bluesky_configured); ?>>
                Bluesky<?php if (!$bluesky_configured): ?> <span class="text-xs text-stone-400">(not configured)</span><?php endif; ?>
            </label>
            <label class="inline-flex items-center gap-1.5 text-sm <?php echo $facebook_configured ? '' : 'opacity-50'; ?>">
                <input type="checkbox" name="post_to_facebook" value="1"
                       <?php disabled(!$facebook_configured); ?>>
                Facebook<?php if (!$facebook_configured): ?> <span class="text-xs text-stone-400">(not configured)</span><?php endif; ?>
            </label>
            <button type="submit"
                    class="ml-auto px-5 py-2 bg-primary-500 hover:bg-primary-600 text-white font-semibold text-sm transition-colors">
                Post Update
            </button>
        </div>
    </form>

    <!-- Recent Updates list -->
    <h2 class="font-osw text-lg uppercase tracking-wide text-primary-500 dark:text-secondary-500 mb-2">
        Recent Updates (<?php echo count($recent); ?>)
    </h2>
    <ul id="bbj-feed-list" class="divide-y divide-stone-200 dark:divide-slate-800 border border-stone-200 dark:border-slate-800">
        <?php foreach ($recent as $post):
            $row = bbj_v2_feed_pane_row_data($post);
        ?>
            <li class="p-3" data-id="<?php echo (int) $post->ID; ?>"
                data-title="<?php echo esc_attr($row['title']); ?>"
                data-content="<?php echo esc_attr($row['content']); ?>"
                data-term-id="<?php echo (int) $row['term_id']; ?>"
                data-term-name="<?php echo esc_attr($row['term_name']); ?>"
                data-mode="display">

                <!-- Display subtree -->
                <div class="flex items-center gap-3" data-row-display>
                    <?php if ($row['thumb']): ?>
                        <img src="<?php echo esc_url($row['thumb']); ?>" alt=""
                             class="h-8 w-8 object-cover border border-stone-200 dark:border-slate-700 shrink-0">
                    <?php else: ?>
                        <div class="h-8 w-8 shrink-0"></div>
                    <?php endif; ?>

                    <span class="flex-1 truncate text-sm text-stone-800 dark:text-slate-200 font-medium"
                          title="<?php echo esc_attr($row['title']); ?>">
                        <?php echo esc_html($row['title']); ?>
                    </span>

                    <?php if ($row['term_name'] !== ''): ?>
                        <span class="px-1.5 py-0.5 text-xs font-mono bg-stone-100 text-stone-600 border border-stone-200 dark:bg-slate-800 dark:text-slate-400">
                            <?php echo esc_html($row['term_name']); ?>
                        </span>
                    <?php endif; ?>

                    <span class="text-xs text-stone-500 dark:text-slate-500 shrink-0" data-nosnippet>
                        <?php echo esc_html($row['time_ago']); ?>
                    </span>

                    <span class="text-xs text-stone-600 dark:text-slate-400 shrink-0" title="Votes">
                        &#9650;<?php echo (int) $row['votes']; ?>
                    </span>

                    <span class="text-xs shrink-0" data-social>
                        <?php if (is_array($row['social'])):
                            $bs = $row['social']['bluesky'] ?? null;
                            $fb = $row['social']['facebook'] ?? null;
                            if (is_array($bs) && !empty($bs['posted'])) echo '<span class="text-green-600" title="Posted to Bluesky">✓BS</span> ';
                            elseif (is_array($bs) && !empty($bs['error'])) echo '<span class="text-accent-red" title="' . esc_attr($bs['error']) . '">✗BS</span> ';
                            if (is_array($fb) && !empty($fb['posted'])) echo '<span class="text-green-600" title="Posted to Facebook">✓FB</span>';
                            elseif (is_array($fb) && !empty($fb['error'])) echo '<span class="text-accent-red" title="' . esc_attr($fb['error']) . '">✗FB</span>';
                        endif; ?>
                    </span>

                    <span class="flex items-center gap-2 shrink-0" data-row-actions>
                        <button type="button" data-action="edit"
                                class="text-xs text-primary-500 hover:underline">Edit</button>
                        <button type="button" data-action="delete"
                                class="text-xs text-accent-red hover:underline">Delete</button>
                    </span>
                </div>

                <!-- Edit subtree (hidden until Edit clicked) -->
                <div class="hidden space-y-2" data-row-edit>
                    <div class="flex gap-2">
                        <input type="text" data-edit-title value="<?php echo esc_attr($row['title']); ?>"
                               class="flex-1 px-2 py-1 border border-stone-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm">
                        <select data-edit-category
                                class="px-2 py-1 border border-stone-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm">
                            <option value="">(none)</option>
                            <?php foreach ($terms as $t): ?>
                                <option value="<?php echo (int) $t->term_id; ?>"
                                    <?php selected((int) $t->term_id === (int) $row['term_id']); ?>>
                                    <?php echo esc_html($t->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" data-action="save"
                                class="px-3 py-1 bg-primary-500 hover:bg-primary-600 text-white text-xs font-semibold">Save</button>
                        <button type="button" data-action="cancel"
                                class="px-2 py-1 text-xs text-stone-500 hover:underline">Cancel</button>
                    </div>
                    <textarea data-edit-details rows="3"
                              class="w-full px-2 py-1 border border-stone-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm"><?php echo esc_textarea($row['content']); ?></textarea>
                </div>

                <!-- Delete confirm (hidden until Delete clicked) -->
                <div class="hidden mt-2 text-sm" data-row-confirm>
                    <span class="text-stone-700 dark:text-slate-300 mr-2">Delete this update?</span>
                    <button type="button" data-action="confirm-delete"
                            class="px-2 py-0.5 bg-accent-red hover:opacity-90 text-white text-xs font-semibold">Confirm</button>
                    <button type="button" data-action="cancel-delete"
                            class="px-2 py-0.5 text-xs text-stone-500 hover:underline">Cancel</button>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>

    <!-- Toast container (right-aligned, fixed) -->
    <div id="bbj-feed-toasts" class="fixed top-4 right-4 flex flex-col gap-2 z-50 pointer-events-none"></div>

</section>

<script>
    window.BBJ_FEED = {
        restRoot: <?php echo wp_json_encode(esc_url_raw(rest_url('bbjd/v1/feed-updates'))); ?>,
        nonce:    <?php echo wp_json_encode(wp_create_nonce('wp_rest')); ?>,
        social: {
            bluesky:  <?php echo $bluesky_configured ? 'true' : 'false'; ?>,
            facebook: <?php echo $facebook_configured ? 'true' : 'false'; ?>
        }
    };
</script>
