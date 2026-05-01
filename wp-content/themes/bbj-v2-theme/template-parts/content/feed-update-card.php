<?php
/**
 * Feed update card — the rich live-blog-style row used by Latest Feeds.
 *
 * Expected args (via get_template_part):
 *   - post     (WP_Post)
 *   - type     (?array{name:string, slug:string})
 *   - location (?array{name:string, slug:string})
 *   - variant  ('rich'|'compact')  default 'rich'
 */

if (!defined('ABSPATH')) {
    exit;
}

$args      = $args ?? [];
$post      = $args['post'] ?? null;
$type      = $args['type'] ?? null;
$location  = $args['location'] ?? null;
$variant   = $args['variant'] ?? 'rich';
if (!$post instanceof WP_Post) {
    return;
}

$permalink = get_permalink($post->ID);
$time_12h  = get_the_date('g:i A', $post);
$relative  = human_time_diff(get_post_time('U', true, $post), current_time('timestamp', true)) . ' ago';

$type_slug = $type['slug'] ?? '';
$type_class_map = [
    'drama'        => 'bg-red-100 text-red-900',
    'ceremony'     => 'bg-green-100 text-green-900',
    'strategy'     => 'bg-slate-100 text-slate-800',
    'competition'  => 'bg-amber-100 text-amber-900',
    'alliance'     => 'bg-indigo-100 text-indigo-900',
    'eviction'     => 'bg-gray-700 text-white',
    'punishment'   => 'bg-purple-100 text-purple-900',
    'reward'       => 'bg-emerald-100 text-emerald-900',
    'showmance'    => 'bg-pink-100 text-pink-900',
];
$type_class = $type_class_map[$type_slug] ?? 'bg-gray-100 text-gray-900';

$dot_color_map = [
    'drama'        => 'bg-red-500',
    'ceremony'     => 'bg-green-500',
    'strategy'     => 'bg-slate-500',
    'competition'  => 'bg-amber-500',
    'alliance'     => 'bg-indigo-500',
    'eviction'     => 'bg-gray-700',
    'punishment'   => 'bg-purple-500',
    'reward'       => 'bg-emerald-500',
    'showmance'    => 'bg-pink-500',
];
$dot_class = $dot_color_map[$type_slug] ?? 'bg-gray-400';

$author  = get_the_author_meta('display_name', $post->post_author);
$replies = (int) get_comments_number($post->ID);
$comments_open = comments_open($post->ID);

if ($variant === 'compact') :
?>
<li class="flex gap-3 items-center py-2 border-b border-gray-200 dark:border-gray-700 last:border-b-0">
    <span class="text-xs font-osw text-gray-500 w-16 shrink-0" data-nosnippet><?php echo esc_html($time_12h); ?></span>
    <?php if ($type) : ?>
        <span class="hidden sm:inline-block text-[10px] font-osw uppercase tracking-wider px-1.5 py-0.5 rounded <?php echo esc_attr($type_class); ?>">
            <?php echo esc_html($type['name']); ?>
        </span>
    <?php endif; ?>
    <a href="<?php echo esc_url($permalink); ?>" class="text-sm font-semibold text-gray-900 dark:text-gray-100 hover:text-primary-500 dark:hover:text-secondary-500 truncate">
        <?php echo esc_html($post->post_title); ?>
    </a>
</li>
<?php return;
endif;
?>
<article class="flex gap-4 py-4">
    <div class="hidden sm:block w-20 shrink-0 text-right">
        <div class="font-osw text-sm text-gray-900 dark:text-gray-200"><?php echo esc_html($time_12h); ?></div>
        <div class="text-[11px] text-gray-500 dark:text-gray-400" data-nosnippet><?php echo esc_html($relative); ?></div>
    </div>

    <div class="relative flex-shrink-0">
        <span class="block w-3 h-3 rounded-full mt-1.5 <?php echo esc_attr($dot_class); ?>" aria-hidden="true"></span>
    </div>

    <div class="flex-1 min-w-0 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4 shadow-sm">
        <div class="flex flex-wrap gap-2 mb-2">
            <?php if ($type) : ?>
                <span class="text-[10px] font-osw uppercase tracking-wider px-2 py-0.5 rounded <?php echo esc_attr($type_class); ?>">
                    <?php echo esc_html($type['name']); ?>
                </span>
            <?php endif; ?>
            <?php if ($location) : ?>
                <span class="text-[10px] font-osw uppercase tracking-wider px-2 py-0.5 rounded border border-gray-300 text-gray-700 dark:border-gray-600 dark:text-gray-300">
                    <?php echo esc_html($location['name']); ?>
                </span>
            <?php endif; ?>
        </div>

        <h3 class="font-display text-lg md:text-xl leading-snug mb-2 text-gray-900 dark:text-gray-100">
            <a href="<?php echo esc_url($permalink); ?>" class="hover:text-primary-500 dark:hover:text-secondary-500">
                <?php echo esc_html($post->post_title); ?>
            </a>
        </h3>

        <?php
        $excerpt = $post->post_excerpt !== ''
            ? $post->post_excerpt
            : wp_trim_words(strip_shortcodes($post->post_content), 30, '…');
        ?>
        <?php if ($excerpt !== '') : ?>
            <p class="text-sm text-gray-700 dark:text-gray-300 mb-3"><?php echo esc_html($excerpt); ?></p>
        <?php endif; ?>

        <?php if (has_post_thumbnail($post)) : ?>
            <a href="<?php echo esc_url($permalink); ?>" class="relative block mb-3 rounded-lg overflow-hidden" aria-hidden="true" tabindex="-1">
                <?php echo get_the_post_thumbnail($post, 'large', [
                    'class'   => 'w-full h-auto object-cover block',
                    'loading' => 'lazy',
                    'alt'     => '',
                ]); ?>
                <span class="absolute top-2 right-2 text-[10px] font-osw uppercase tracking-wider px-2 py-1 rounded bg-gray-900/90 text-white">
                    <?php esc_html_e('Feed Cam', 'bbj-v2-theme'); ?>
                </span>
            </a>
        <?php endif; ?>

        <div class="flex flex-wrap gap-3 items-center text-xs text-gray-500 dark:text-gray-400">
            <?php if ($author !== '') : ?>
                <span class="font-osw">@<?php echo esc_html(sanitize_title($author)); ?></span>
            <?php endif; ?>
            <span>
                <?php if ($replies > 0) : ?>
                    <?php printf(esc_html(_n('%d reply', '%d replies', $replies, 'bbj-v2-theme')), $replies); ?>
                <?php else : ?>
                    <?php esc_html_e('No replies yet', 'bbj-v2-theme'); ?>
                <?php endif; ?>
            </span>
            <?php if ($comments_open) : ?>
                <a href="<?php echo esc_url($permalink . '#respond'); ?>" class="text-primary-500 dark:text-secondary-500 hover:underline">
                    <?php esc_html_e('Join the thread →', 'bbj-v2-theme'); ?>
                </a>
            <?php endif; ?>
        </div>
    </div>
</article>
