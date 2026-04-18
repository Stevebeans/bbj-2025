<?php

namespace BigBrotherJunkies\Data\Admin\Pages;

use BigBrotherJunkies\Data\Email\EmailSchema;
use BigBrotherJunkies\Data\Email\EmailService;

class EmailListsPage
{
    public const MENU_SLUG = 'bbjd-mailing';

    public function handleActions(): void
    {
        add_action('admin_post_bbjd_import_mailpoet', [$this, 'handleImportMailPoet']);
        add_action('admin_post_bbjd_subscriber_action', [$this, 'handleSubscriberAction']);
    }

    public function handleImportMailPoet(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        check_admin_referer('bbjd_import_mailpoet');

        global $wpdb;

        $mailpoetTable = $wpdb->prefix . 'mailpoet_subscribers';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$mailpoetTable}'") !== $mailpoetTable) {
            wp_redirect(add_query_arg([
                'page' => self::MENU_SLUG,
                'import_error' => 'no_mailpoet',
            ], admin_url('admin.php')));
            exit;
        }

        $subscribers = $wpdb->get_col(
            "SELECT email FROM {$mailpoetTable} WHERE status = 'subscribed' AND email != ''"
        );

        $subTable = EmailSchema::table(EmailSchema::TABLE_SUBSCRIBERS);
        $listTable = EmailSchema::table(EmailSchema::TABLE_LISTS);
        $lsTable = EmailSchema::table(EmailSchema::TABLE_LIST_SUBSCRIBERS);

        // Get the post-notifications list ID
        $listId = (int) $wpdb->get_var(
            "SELECT id FROM {$listTable} WHERE slug = 'post-notifications' LIMIT 1"
        );

        if (!$listId) {
            wp_redirect(add_query_arg([
                'page' => self::MENU_SLUG,
                'import_error' => 'no_list',
            ], admin_url('admin.php')));
            exit;
        }

        // Also match by WP user email for user_id linking
        $wpUsers = [];
        foreach (get_users(['fields' => ['ID', 'user_email']]) as $u) {
            $wpUsers[strtolower($u->user_email)] = (int) $u->ID;
        }

        $now = current_time('mysql');
        $imported = 0;
        $skipped = 0;

        foreach ($subscribers as $email) {
            $email = strtolower(trim($email));
            if (!is_email($email)) {
                $skipped++;
                continue;
            }

            // Check if already exists
            $existingId = $wpdb->get_var(
                $wpdb->prepare("SELECT id FROM {$subTable} WHERE email = %s", $email)
            );

            if ($existingId) {
                // Just ensure they're on the list
                $wpdb->replace($lsTable, [
                    'list_id' => $listId,
                    'subscriber_id' => (int) $existingId,
                ]);
                $skipped++;
                continue;
            }

            // Insert as pre-confirmed
            $wpdb->insert($subTable, [
                'email' => $email,
                'user_id' => $wpUsers[$email] ?? null,
                'status' => 'subscribed',
                'source' => 'mailpoet-import',
                'subscribed_at' => $now,
                'confirmed_at' => $now,
                'created_at' => $now,
            ]);

            $subId = (int) $wpdb->insert_id;
            if ($subId) {
                $wpdb->insert($lsTable, [
                    'list_id' => $listId,
                    'subscriber_id' => $subId,
                ]);
                $imported++;
            }
        }

        wp_redirect(add_query_arg([
            'page' => self::MENU_SLUG,
            'import_done' => 1,
            'imported' => $imported,
            'skipped' => $skipped,
        ], admin_url('admin.php')));
        exit;
    }

    public function handleSubscriberAction(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        check_admin_referer('bbjd_subscriber_action');

        global $wpdb;

        $subId = intval($_POST['subscriber_id'] ?? 0);
        $action = sanitize_text_field($_POST['sub_action'] ?? '');
        $listSlug = sanitize_text_field($_POST['list_slug'] ?? '');

        if (!$subId || !$action) {
            wp_die('Missing parameters');
        }

        $subTable = EmailSchema::table(EmailSchema::TABLE_SUBSCRIBERS);
        $lsTable = EmailSchema::table(EmailSchema::TABLE_LIST_SUBSCRIBERS);
        $listTable = EmailSchema::table(EmailSchema::TABLE_LISTS);
        $sendsTable = EmailSchema::table(EmailSchema::TABLE_SENDS);
        $now = current_time('mysql');
        $msg = 'done';

        switch ($action) {
            case 'remove_from_list':
                $listId = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$listTable} WHERE slug = %s", $listSlug
                ));
                if ($listId) {
                    $wpdb->delete($lsTable, [
                        'subscriber_id' => $subId,
                        'list_id' => (int) $listId,
                    ]);
                }
                $msg = 'removed';
                break;

            case 'delete':
                $wpdb->delete($lsTable, ['subscriber_id' => $subId]);
                $wpdb->delete($sendsTable, ['subscriber_id' => $subId]);
                $wpdb->delete($subTable, ['id' => $subId]);
                $msg = 'deleted';
                break;

            case 'unsubscribe':
                $wpdb->update($subTable, [
                    'status' => 'unsubscribed',
                    'unsubscribed_at' => $now,
                ], ['id' => $subId]);
                $msg = 'unsubscribed';
                break;

            case 'resubscribe':
                $wpdb->update($subTable, [
                    'status' => 'subscribed',
                    'subscribed_at' => $now,
                    'unsubscribed_at' => null,
                ], ['id' => $subId]);
                $msg = 'resubscribed';
                break;
        }

        wp_redirect(add_query_arg([
            'page' => self::MENU_SLUG,
            'list' => $listSlug,
            'message' => $msg,
        ], admin_url('admin.php')));
        exit;
    }

    public function render(): void
    {
        global $wpdb;

        $listTable = EmailSchema::table(EmailSchema::TABLE_LISTS);
        $listSubTable = EmailSchema::table(EmailSchema::TABLE_LIST_SUBSCRIBERS);
        $subTable = EmailSchema::table(EmailSchema::TABLE_SUBSCRIBERS);

        // Check if viewing a specific list
        $viewList = sanitize_text_field($_GET['list'] ?? '');

        if ($viewList) {
            $this->renderListDetail($viewList);
            return;
        }

        // Get all lists with subscriber counts
        $lists = $wpdb->get_results(
            "SELECT l.*,
                    COUNT(CASE WHEN s.status = 'subscribed' THEN 1 END) as active_count,
                    COUNT(ls.subscriber_id) as total_count
             FROM {$listTable} l
             LEFT JOIN {$listSubTable} ls ON l.id = ls.list_id
             LEFT JOIN {$subTable} s ON ls.subscriber_id = s.id
             GROUP BY l.id
             ORDER BY l.created_at ASC"
        );
        $importDone = !empty($_GET['import_done']);
        $importError = sanitize_text_field($_GET['import_error'] ?? '');

        // Check if MailPoet table exists for showing import button
        $mailpoetTable = $wpdb->prefix . 'mailpoet_subscribers';
        $hasMailPoet = $wpdb->get_var("SHOW TABLES LIKE '{$mailpoetTable}'") === $mailpoetTable;
        $mailpoetCount = $hasMailPoet
            ? (int) $wpdb->get_var("SELECT COUNT(*) FROM {$mailpoetTable} WHERE status = 'subscribed' AND email != ''")
            : 0;
        ?>
        <div class="bbjd-admin">
            <div class="bbjd-p-6 bbjd-max-w-6xl">
                <h1 class="bbjd-text-3xl bbjd-font-bold bbjd-text-primary500 bbjd-mb-6">
                    Mailing Lists
                </h1>

                <?php if ($importDone): ?>
                <div class="bbjd-bg-green-100 bbjd-border-l-4 bbjd-border-green-500 bbjd-text-green-700 bbjd-p-4 bbjd-mb-6 bbjd-rounded">
                    <p>Import complete: <strong><?php echo intval($_GET['imported'] ?? 0); ?></strong> imported, <strong><?php echo intval($_GET['skipped'] ?? 0); ?></strong> skipped.</p>
                </div>
                <?php endif; ?>

                <?php if ($importError === 'no_mailpoet'): ?>
                <div class="bbjd-bg-red-100 bbjd-border-l-4 bbjd-border-red-500 bbjd-text-red-700 bbjd-p-4 bbjd-mb-6 bbjd-rounded">
                    <p>MailPoet subscribers table not found.</p>
                </div>
                <?php endif; ?>

                <?php if ($hasMailPoet && $mailpoetCount > 0): ?>
                <div class="bbjd-bg-white bbjd-rounded-lg bbjd-shadow bbjd-p-6 bbjd-mb-6">
                    <div class="bbjd-flex bbjd-items-center bbjd-justify-between">
                        <div>
                            <h2 class="bbjd-text-lg bbjd-font-semibold bbjd-text-gray-800">Import from MailPoet</h2>
                            <p class="bbjd-text-sm bbjd-text-gray-600">
                                Found <strong><?php echo number_format($mailpoetCount); ?></strong> active MailPoet subscribers.
                                Import them into the post-notifications list.
                            </p>
                        </div>
                        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                            <?php wp_nonce_field('bbjd_import_mailpoet'); ?>
                            <input type="hidden" name="action" value="bbjd_import_mailpoet">
                            <button type="submit"
                                    class="bbjd-bg-primary500 bbjd-text-white bbjd-px-4 bbjd-py-2 bbjd-rounded bbjd-text-sm bbjd-font-medium hover:bbjd-bg-primaryHard bbjd-transition-colors bbjd-whitespace-nowrap"
                                    onclick="return confirm('Import <?php echo number_format($mailpoetCount); ?> subscribers into post-notifications list?');">
                                Import Subscribers
                            </button>
                        </form>
                    </div>
                </div>
                <?php endif; ?>

                <div class="bbjd-bg-white bbjd-rounded-lg bbjd-shadow bbjd-overflow-hidden">
                    <table class="bbjd-w-full bbjd-text-sm">
                        <thead>
                            <tr class="bbjd-bg-gray-50 bbjd-border-b">
                                <th class="bbjd-text-left bbjd-px-6 bbjd-py-3 bbjd-font-medium bbjd-text-gray-700">List</th>
                                <th class="bbjd-text-left bbjd-px-6 bbjd-py-3 bbjd-font-medium bbjd-text-gray-700">Slug</th>
                                <th class="bbjd-text-center bbjd-px-6 bbjd-py-3 bbjd-font-medium bbjd-text-gray-700">Active Subs</th>
                                <th class="bbjd-text-center bbjd-px-6 bbjd-py-3 bbjd-font-medium bbjd-text-gray-700">Total</th>
                                <th class="bbjd-text-center bbjd-px-6 bbjd-py-3 bbjd-font-medium bbjd-text-gray-700">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($lists)): ?>
                                <tr><td colspan="5" class="bbjd-px-6 bbjd-py-8 bbjd-text-center bbjd-text-gray-500">No lists found. Tables may need migration.</td></tr>
                            <?php else: ?>
                                <?php foreach ($lists as $list): ?>
                                <tr class="bbjd-border-b hover:bbjd-bg-gray-50">
                                    <td class="bbjd-px-6 bbjd-py-3">
                                        <a href="<?php echo esc_url(add_query_arg(['page' => self::MENU_SLUG, 'list' => $list->slug], admin_url('admin.php'))); ?>"
                                           class="bbjd-text-primary500 bbjd-font-medium hover:bbjd-underline">
                                            <?php echo esc_html($list->name); ?>
                                        </a>
                                        <?php if ($list->description): ?>
                                            <p class="bbjd-text-xs bbjd-text-gray-500 bbjd-mt-0.5"><?php echo esc_html($list->description); ?></p>
                                        <?php endif; ?>
                                    </td>
                                    <td class="bbjd-px-6 bbjd-py-3 bbjd-font-mono bbjd-text-xs bbjd-text-gray-600"><?php echo esc_html($list->slug); ?></td>
                                    <td class="bbjd-px-6 bbjd-py-3 bbjd-text-center bbjd-font-semibold"><?php echo intval($list->active_count); ?></td>
                                    <td class="bbjd-px-6 bbjd-py-3 bbjd-text-center bbjd-text-gray-600"><?php echo intval($list->total_count); ?></td>
                                    <td class="bbjd-px-6 bbjd-py-3 bbjd-text-center">
                                        <?php if ($list->is_active): ?>
                                            <span class="bbjd-inline-block bbjd-px-2 bbjd-py-0.5 bbjd-text-xs bbjd-rounded-full bbjd-bg-green-100 bbjd-text-green-700">Active</span>
                                        <?php else: ?>
                                            <span class="bbjd-inline-block bbjd-px-2 bbjd-py-0.5 bbjd-text-xs bbjd-rounded-full bbjd-bg-gray-100 bbjd-text-gray-600">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }

    private function renderListDetail(string $listSlug): void
    {
        global $wpdb;

        $page = intval($_GET['paged'] ?? 1);
        $perPage = 25;
        $offset = ($page - 1) * $perPage;
        $status = sanitize_text_field($_GET['status'] ?? '');
        $search = sanitize_text_field($_GET['s'] ?? '');
        $message = sanitize_text_field($_GET['message'] ?? '');

        $subTable = EmailSchema::table(EmailSchema::TABLE_SUBSCRIBERS);
        $lsTable = EmailSchema::table(EmailSchema::TABLE_LIST_SUBSCRIBERS);
        $listTable = EmailSchema::table(EmailSchema::TABLE_LISTS);
        $sendsTable = EmailSchema::table(EmailSchema::TABLE_SENDS);

        // Build WHERE
        $where = ["l.slug = %s"];
        $params = [$listSlug];

        if ($status) {
            $where[] = "s.status = %s";
            $params[] = $status;
        }
        if ($search) {
            $where[] = "s.email LIKE %s";
            $params[] = '%' . $wpdb->esc_like($search) . '%';
        }

        $whereClause = implode(' AND ', $where);

        $total = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*)
             FROM {$subTable} s
             INNER JOIN {$lsTable} ls ON ls.subscriber_id = s.id
             INNER JOIN {$listTable} l ON l.id = ls.list_id
             WHERE {$whereClause}",
            ...$params
        ));

        // Query with per-subscriber send stats
        $subscribers = $wpdb->get_results($wpdb->prepare(
            "SELECT s.id, s.email, s.user_id, s.status, s.source, s.subscribed_at, s.created_at,
                    COALESCE(stats.sends, 0) as sends,
                    COALESCE(stats.opens, 0) as opens,
                    COALESCE(stats.clicks, 0) as clicks,
                    stats.last_open
             FROM {$subTable} s
             INNER JOIN {$lsTable} ls ON ls.subscriber_id = s.id
             INNER JOIN {$listTable} l ON l.id = ls.list_id
             LEFT JOIN (
                SELECT subscriber_id,
                       COUNT(*) as sends,
                       SUM(CASE WHEN opened_at IS NOT NULL THEN 1 ELSE 0 END) as opens,
                       SUM(CASE WHEN clicked_at IS NOT NULL THEN 1 ELSE 0 END) as clicks,
                       MAX(opened_at) as last_open
                FROM {$sendsTable}
                GROUP BY subscriber_id
             ) stats ON stats.subscriber_id = s.id
             WHERE {$whereClause}
             ORDER BY s.created_at DESC
             LIMIT %d OFFSET %d",
            ...array_merge($params, [$perPage, $offset])
        ), ARRAY_A);

        $totalPages = (int) ceil($total / $perPage);
        $backUrl = add_query_arg(['page' => self::MENU_SLUG], admin_url('admin.php'));
        $baseUrl = add_query_arg(['page' => self::MENU_SLUG, 'list' => $listSlug], admin_url('admin.php'));
        ?>
        <div class="bbjd-admin">
            <div class="bbjd-p-6 bbjd-max-w-7xl">
                <div class="bbjd-mb-4">
                    <a href="<?php echo esc_url($backUrl); ?>" class="bbjd-text-primary500 bbjd-text-sm hover:bbjd-underline">&larr; Back to Lists</a>
                </div>

                <h1 class="bbjd-text-3xl bbjd-font-bold bbjd-text-primary500 bbjd-mb-2">
                    <?php echo esc_html(ucwords(str_replace('-', ' ', $listSlug))); ?>
                </h1>
                <p class="bbjd-text-gray-600 bbjd-mb-6"><?php echo number_format($total); ?> subscribers</p>

                <?php $this->renderDetailMessages($message); ?>

                <!-- Filters -->
                <div class="bbjd-flex bbjd-gap-3 bbjd-mb-4 bbjd-items-center">
                    <form method="get" class="bbjd-flex bbjd-gap-2 bbjd-items-center">
                        <input type="hidden" name="page" value="<?php echo esc_attr(self::MENU_SLUG); ?>">
                        <input type="hidden" name="list" value="<?php echo esc_attr($listSlug); ?>">
                        <input type="text" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search email..."
                               class="bbjd-px-3 bbjd-py-1.5 bbjd-border bbjd-border-gray-300 bbjd-rounded bbjd-text-sm" style="min-width:200px;">
                        <select name="status" class="bbjd-px-3 bbjd-py-1.5 bbjd-border bbjd-border-gray-300 bbjd-rounded bbjd-text-sm">
                            <option value="">All Statuses</option>
                            <option value="subscribed" <?php selected($status, 'subscribed'); ?>>Subscribed</option>
                            <option value="unconfirmed" <?php selected($status, 'unconfirmed'); ?>>Unconfirmed</option>
                            <option value="unsubscribed" <?php selected($status, 'unsubscribed'); ?>>Unsubscribed</option>
                            <option value="bounced" <?php selected($status, 'bounced'); ?>>Bounced</option>
                        </select>
                        <button type="submit" class="bbjd-bg-primary500 bbjd-text-white bbjd-px-3 bbjd-py-1.5 bbjd-rounded bbjd-text-sm">Filter</button>
                    </form>
                </div>

                <!-- Subscriber Table -->
                <div class="bbjd-bg-white bbjd-rounded-lg bbjd-shadow bbjd-overflow-hidden">
                    <table class="bbjd-w-full bbjd-text-sm">
                        <thead>
                            <tr class="bbjd-bg-gray-50 bbjd-border-b">
                                <th class="bbjd-text-left bbjd-px-4 bbjd-py-3 bbjd-font-medium bbjd-text-gray-700">Email</th>
                                <th class="bbjd-text-center bbjd-px-3 bbjd-py-3 bbjd-font-medium bbjd-text-gray-700">Status</th>
                                <th class="bbjd-text-left bbjd-px-3 bbjd-py-3 bbjd-font-medium bbjd-text-gray-700">Source</th>
                                <th class="bbjd-text-center bbjd-px-3 bbjd-py-3 bbjd-font-medium bbjd-text-gray-700">Sends</th>
                                <th class="bbjd-text-center bbjd-px-3 bbjd-py-3 bbjd-font-medium bbjd-text-gray-700">Opens</th>
                                <th class="bbjd-text-center bbjd-px-3 bbjd-py-3 bbjd-font-medium bbjd-text-gray-700">Clicks</th>
                                <th class="bbjd-text-left bbjd-px-3 bbjd-py-3 bbjd-font-medium bbjd-text-gray-700">Last Open</th>
                                <th class="bbjd-text-right bbjd-px-4 bbjd-py-3 bbjd-font-medium bbjd-text-gray-700">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($subscribers)): ?>
                                <tr><td colspan="8" class="bbjd-px-4 bbjd-py-8 bbjd-text-center bbjd-text-gray-500">No subscribers found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($subscribers as $sub): ?>
                                <tr class="bbjd-border-b hover:bbjd-bg-gray-50">
                                    <td class="bbjd-px-4 bbjd-py-3">
                                        <span class="bbjd-font-mono bbjd-text-xs"><?php echo esc_html($sub['email']); ?></span>
                                        <?php if ($sub['user_id']): ?>
                                            <span class="bbjd-text-xs bbjd-text-primary500 bbjd-ml-1" title="Linked WP User #<?php echo intval($sub['user_id']); ?>">WP</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="bbjd-px-3 bbjd-py-3 bbjd-text-center">
                                        <?php echo $this->statusBadge($sub['status']); ?>
                                    </td>
                                    <td class="bbjd-px-3 bbjd-py-3 bbjd-text-gray-600 bbjd-text-xs"><?php echo esc_html($sub['source'] ?? '-'); ?></td>
                                    <td class="bbjd-px-3 bbjd-py-3 bbjd-text-center bbjd-text-gray-600"><?php echo intval($sub['sends']); ?></td>
                                    <td class="bbjd-px-3 bbjd-py-3 bbjd-text-center">
                                        <?php if ($sub['sends'] > 0): ?>
                                            <span class="<?php echo $sub['opens'] > 0 ? 'bbjd-text-green-600 bbjd-font-medium' : 'bbjd-text-gray-400'; ?>">
                                                <?php echo intval($sub['opens']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="bbjd-text-gray-300">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="bbjd-px-3 bbjd-py-3 bbjd-text-center">
                                        <?php if ($sub['sends'] > 0): ?>
                                            <span class="<?php echo $sub['clicks'] > 0 ? 'bbjd-text-blue-600 bbjd-font-medium' : 'bbjd-text-gray-400'; ?>">
                                                <?php echo intval($sub['clicks']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="bbjd-text-gray-300">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="bbjd-px-3 bbjd-py-3 bbjd-text-gray-600 bbjd-text-xs">
                                        <?php echo $sub['last_open'] ? esc_html(date('M j, Y', strtotime($sub['last_open']))) : '-'; ?>
                                    </td>
                                    <td class="bbjd-px-4 bbjd-py-3 bbjd-text-right bbjd-whitespace-nowrap">
                                        <?php echo $this->actionButtons($sub, $listSlug); ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <div class="bbjd-flex bbjd-justify-center bbjd-gap-2 bbjd-mt-4">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="<?php echo esc_url(add_query_arg('paged', $i, $baseUrl)); ?>"
                           class="bbjd-px-3 bbjd-py-1 bbjd-rounded bbjd-text-sm <?php echo $i === $page ? 'bbjd-bg-primary500 bbjd-text-white' : 'bbjd-bg-gray-100 bbjd-text-gray-700 hover:bbjd-bg-gray-200'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    private function renderDetailMessages(string $message): void
    {
        $messages = [
            'removed' => ['green', 'Subscriber removed from list.'],
            'deleted' => ['green', 'Subscriber deleted from system.'],
            'unsubscribed' => ['green', 'Subscriber marked as unsubscribed.'],
            'resubscribed' => ['green', 'Subscriber re-activated.'],
        ];

        if (!isset($messages[$message])) return;

        [$color, $text] = $messages[$message];
        echo "<div class=\"bbjd-bg-{$color}-100 bbjd-border-l-4 bbjd-border-{$color}-500 bbjd-text-{$color}-700 bbjd-p-4 bbjd-mb-6 bbjd-rounded\"><p>{$text}</p></div>";
    }

    private function actionButtons(array $sub, string $listSlug): string
    {
        $nonce = wp_create_nonce('bbjd_subscriber_action');
        $actionUrl = admin_url('admin-post.php');
        $subId = intval($sub['id']);

        $form = function (string $action, string $label, string $color, string $confirm = '') use ($nonce, $actionUrl, $subId, $listSlug) {
            $onclick = $confirm ? " onclick=\"return confirm('{$confirm}');\"" : '';
            return "<form method='post' action='{$actionUrl}' class='bbjd-inline'>
                <input type='hidden' name='_wpnonce' value='{$nonce}'>
                <input type='hidden' name='action' value='bbjd_subscriber_action'>
                <input type='hidden' name='subscriber_id' value='{$subId}'>
                <input type='hidden' name='sub_action' value='{$action}'>
                <input type='hidden' name='list_slug' value='" . esc_attr($listSlug) . "'>
                <button type='submit' class='bbjd-text-xs bbjd-text-{$color} hover:bbjd-underline'{$onclick}>{$label}</button>
            </form>";
        };

        $buttons = [];

        if ($sub['status'] === 'subscribed') {
            $buttons[] = $form('unsubscribe', 'Unsub', 'amber-600', 'Unsubscribe this person?');
        } elseif ($sub['status'] === 'unsubscribed' || $sub['status'] === 'unconfirmed') {
            $buttons[] = $form('resubscribe', 'Re-sub', 'green-600', 'Re-subscribe this person?');
        }

        $buttons[] = $form('remove_from_list', 'Remove', 'red-500', 'Remove from this list?');
        $buttons[] = $form('delete', 'Delete', 'red-700', 'Permanently delete this subscriber and all their data?');

        return implode(' <span class="bbjd-text-gray-300">|</span> ', $buttons);
    }

    private function statusBadge(string $status): string
    {
        $colors = [
            'subscribed' => 'bbjd-bg-green-100 bbjd-text-green-700',
            'unconfirmed' => 'bbjd-bg-yellow-100 bbjd-text-yellow-700',
            'unsubscribed' => 'bbjd-bg-gray-100 bbjd-text-gray-600',
            'bounced' => 'bbjd-bg-red-100 bbjd-text-red-700',
        ];
        $colorClass = $colors[$status] ?? 'bbjd-bg-gray-100 bbjd-text-gray-600';
        return '<span class="bbjd-inline-block bbjd-px-2 bbjd-py-0.5 bbjd-text-xs bbjd-rounded-full ' . $colorClass . '">' . esc_html(ucfirst($status)) . '</span>';
    }
}
