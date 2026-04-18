<?php

namespace BigBrotherJunkies\Data\Admin\Pages;

class UserCleanupPage
{
    public const MENU_SLUG = 'bbjd-user-cleanup';

    private const BATCH_SIZE = 500;

    private const PROTECTED_ROLES = ['administrator', 'lifetime', 'helper', 'updater', 'second_in_command', 'commod', 'author', 'editor'];

    public function handleActions(): void
    {
        add_action('admin_post_bbjd_delete_spam_supporters', [$this, 'handleDeleteSpamSupporters']);
        add_action('admin_post_bbjd_delete_roleless_users', [$this, 'handleDeleteRolelessUsers']);
        add_action('admin_post_bbjd_demote_supporters', [$this, 'handleDemoteSupporters']);
    }

    public function handleDeleteSpamSupporters(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        check_admin_referer('bbjd_delete_spam_supporters');

        global $wpdb;

        // Get supporter user IDs from July 2024 who have 0 posts and 0 comments
        $userIds = $wpdb->get_col(
            "SELECT u.ID FROM {$wpdb->users} u
            JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
            WHERE um.meta_key = 'wp_capabilities'
            AND um.meta_value LIKE '%supporter%'
            AND um.meta_value NOT LIKE '%lifetime%'
            AND um.meta_value NOT LIKE '%administrator%'
            AND u.user_registered BETWEEN '2024-07-01' AND '2024-07-31'
            AND NOT EXISTS (SELECT 1 FROM {$wpdb->posts} WHERE post_author = u.ID)
            AND NOT EXISTS (SELECT 1 FROM {$wpdb->comments} WHERE user_id = u.ID)
            LIMIT " . self::BATCH_SIZE
        );

        $deleted = 0;
        require_once ABSPATH . 'wp-admin/includes/user.php';

        foreach ($userIds as $userId) {
            if (wp_delete_user($userId)) {
                $deleted++;
            }
        }

        $remaining = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->users} u
            JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
            WHERE um.meta_key = 'wp_capabilities'
            AND um.meta_value LIKE '%supporter%'
            AND um.meta_value NOT LIKE '%lifetime%'
            AND um.meta_value NOT LIKE '%administrator%'
            AND u.user_registered BETWEEN '2024-07-01' AND '2024-07-31'
            AND NOT EXISTS (SELECT 1 FROM {$wpdb->posts} WHERE post_author = u.ID)
            AND NOT EXISTS (SELECT 1 FROM {$wpdb->comments} WHERE user_id = u.ID)"
        );

        wp_redirect(add_query_arg([
            'page' => self::MENU_SLUG,
            'message' => 'spam_deleted',
            'deleted' => $deleted,
            'remaining' => $remaining,
        ], admin_url('admin.php')));
        exit;
    }

    public function handleDeleteRolelessUsers(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        check_admin_referer('bbjd_delete_roleless_users');

        global $wpdb;

        // Users with no wp_capabilities, no posts, no comments
        $userIds = $wpdb->get_col(
            "SELECT u.ID FROM {$wpdb->users} u
            WHERE NOT EXISTS (
                SELECT 1 FROM {$wpdb->usermeta} WHERE user_id = u.ID AND meta_key = 'wp_capabilities'
            )
            AND NOT EXISTS (SELECT 1 FROM {$wpdb->posts} WHERE post_author = u.ID)
            AND NOT EXISTS (SELECT 1 FROM {$wpdb->comments} WHERE user_id = u.ID)
            AND u.ID != 1
            LIMIT " . self::BATCH_SIZE
        );

        $deleted = 0;
        require_once ABSPATH . 'wp-admin/includes/user.php';

        foreach ($userIds as $userId) {
            if (wp_delete_user($userId)) {
                $deleted++;
            }
        }

        $remaining = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->users} u
            WHERE NOT EXISTS (
                SELECT 1 FROM {$wpdb->usermeta} WHERE user_id = u.ID AND meta_key = 'wp_capabilities'
            )
            AND NOT EXISTS (SELECT 1 FROM {$wpdb->posts} WHERE post_author = u.ID)
            AND NOT EXISTS (SELECT 1 FROM {$wpdb->comments} WHERE user_id = u.ID)
            AND u.ID != 1"
        );

        wp_redirect(add_query_arg([
            'page' => self::MENU_SLUG,
            'message' => 'roleless_deleted',
            'deleted' => $deleted,
            'remaining' => $remaining,
        ], admin_url('admin.php')));
        exit;
    }

    public function handleDemoteSupporters(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        check_admin_referer('bbjd_demote_supporters');

        global $wpdb;

        // Find users with ONLY supporter role (not lifetime, admin, etc.)
        $users = $wpdb->get_results(
            "SELECT u.ID, um.meta_value FROM {$wpdb->users} u
            JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
            WHERE um.meta_key = 'wp_capabilities'
            AND um.meta_value LIKE '%supporter%'
            AND um.meta_value NOT LIKE '%lifetime%'
            AND um.meta_value NOT LIKE '%administrator%'",
            ARRAY_A
        );

        $demoted = 0;
        foreach ($users as $row) {
            $caps = maybe_unserialize($row['meta_value']);
            if (!is_array($caps)) {
                continue;
            }

            // Skip if user has any protected role
            $hasProtected = false;
            foreach (self::PROTECTED_ROLES as $role) {
                if (isset($caps[$role])) {
                    $hasProtected = true;
                    break;
                }
            }
            if ($hasProtected) {
                continue;
            }

            // Remove supporter, ensure subscriber
            unset($caps['supporter']);
            $caps['subscriber'] = true;

            $wpdb->update(
                $wpdb->usermeta,
                ['meta_value' => serialize($caps)],
                ['user_id' => $row['ID'], 'meta_key' => 'wp_capabilities']
            );
            $demoted++;
        }

        wp_redirect(add_query_arg([
            'page' => self::MENU_SLUG,
            'message' => 'supporters_demoted',
            'demoted' => $demoted,
        ], admin_url('admin.php')));
        exit;
    }

    private function getStats(): array
    {
        global $wpdb;

        $totalUsers = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->users}");

        $activeUsers = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->users} u
            WHERE EXISTS (SELECT 1 FROM {$wpdb->posts} WHERE post_author = u.ID)
            OR EXISTS (SELECT 1 FROM {$wpdb->comments} WHERE user_id = u.ID)"
        );

        $spamSupporters = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->users} u
            JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
            WHERE um.meta_key = 'wp_capabilities'
            AND um.meta_value LIKE '%supporter%'
            AND um.meta_value NOT LIKE '%lifetime%'
            AND um.meta_value NOT LIKE '%administrator%'
            AND u.user_registered BETWEEN '2024-07-01' AND '2024-07-31'
            AND NOT EXISTS (SELECT 1 FROM {$wpdb->posts} WHERE post_author = u.ID)
            AND NOT EXISTS (SELECT 1 FROM {$wpdb->comments} WHERE user_id = u.ID)"
        );

        $rolelessUsers = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->users} u
            WHERE NOT EXISTS (
                SELECT 1 FROM {$wpdb->usermeta} WHERE user_id = u.ID AND meta_key = 'wp_capabilities'
            )
            AND NOT EXISTS (SELECT 1 FROM {$wpdb->posts} WHERE post_author = u.ID)
            AND NOT EXISTS (SELECT 1 FROM {$wpdb->comments} WHERE user_id = u.ID)
            AND u.ID != 1"
        );

        $allSupporters = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->usermeta}
            WHERE meta_key = 'wp_capabilities'
            AND meta_value LIKE '%supporter%'
            AND meta_value NOT LIKE '%lifetime%'
            AND meta_value NOT LIKE '%administrator%'"
        );

        $lifetimeUsers = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->usermeta}
            WHERE meta_key = 'wp_capabilities'
            AND meta_value LIKE '%lifetime%'"
        );

        // Role breakdown
        $roles = $wpdb->get_results(
            "SELECT
                CASE
                    WHEN meta_value LIKE '%administrator%' THEN 'administrator'
                    WHEN meta_value LIKE '%lifetime%' THEN 'lifetime'
                    WHEN meta_value LIKE '%supporter%' THEN 'supporter'
                    WHEN meta_value LIKE '%helper%' THEN 'helper'
                    WHEN meta_value LIKE '%updater%' THEN 'updater'
                    WHEN meta_value LIKE '%second_in_command%' THEN 'second_in_command'
                    WHEN meta_value LIKE '%subscriber%' THEN 'subscriber'
                    WHEN meta_value = 'a:0:{}' THEN 'empty_caps'
                    ELSE 'other'
                END as role_group,
                COUNT(*) as cnt
            FROM {$wpdb->usermeta}
            WHERE meta_key = 'wp_capabilities'
            GROUP BY role_group
            ORDER BY cnt DESC",
            ARRAY_A
        );

        return compact('totalUsers', 'activeUsers', 'spamSupporters', 'rolelessUsers', 'allSupporters', 'lifetimeUsers', 'roles');
    }

    public function render(): void
    {
        $stats = $this->getStats();
        $message = $_GET['message'] ?? '';
        ?>
        <div class="bbjd-admin">
            <div class="bbjd-p-6 bbjd-max-w-5xl">
                <h1 class="bbjd-text-3xl bbjd-font-bold bbjd-text-primary500 bbjd-mb-6">
                    User Cleanup
                </h1>

                <?php $this->renderMessages($message); ?>

                <!-- Overview Stats -->
                <div class="bbjd-grid bbjd-grid-cols-2 md:bbjd-grid-cols-4 bbjd-gap-4 bbjd-mb-6">
                    <div class="bbjd-bg-white bbjd-rounded-lg bbjd-shadow bbjd-p-4 bbjd-text-center">
                        <div class="bbjd-text-3xl bbjd-font-bold bbjd-text-gray-800"><?php echo number_format($stats['totalUsers']); ?></div>
                        <div class="bbjd-text-sm bbjd-text-gray-500">Total Users</div>
                    </div>
                    <div class="bbjd-bg-white bbjd-rounded-lg bbjd-shadow bbjd-p-4 bbjd-text-center">
                        <div class="bbjd-text-3xl bbjd-font-bold bbjd-text-green-600"><?php echo number_format($stats['activeUsers']); ?></div>
                        <div class="bbjd-text-sm bbjd-text-gray-500">Active (posted/commented)</div>
                    </div>
                    <div class="bbjd-bg-white bbjd-rounded-lg bbjd-shadow bbjd-p-4 bbjd-text-center">
                        <div class="bbjd-text-3xl bbjd-font-bold bbjd-text-blue-600"><?php echo number_format($stats['lifetimeUsers']); ?></div>
                        <div class="bbjd-text-sm bbjd-text-gray-500">Lifetime Members</div>
                    </div>
                    <div class="bbjd-bg-white bbjd-rounded-lg bbjd-shadow bbjd-p-4 bbjd-text-center">
                        <div class="bbjd-text-3xl bbjd-font-bold bbjd-text-red-600"><?php echo number_format($stats['spamSupporters'] + $stats['rolelessUsers']); ?></div>
                        <div class="bbjd-text-sm bbjd-text-gray-500">Spam / Junk Users</div>
                    </div>
                </div>

                <!-- Role Breakdown -->
                <div class="bbjd-bg-white bbjd-rounded-lg bbjd-shadow bbjd-p-6 bbjd-mb-6">
                    <h2 class="bbjd-text-xl bbjd-font-semibold bbjd-text-gray-800 bbjd-mb-4">Role Breakdown</h2>
                    <div class="bbjd-overflow-x-auto">
                        <table class="bbjd-min-w-full bbjd-divide-y bbjd-divide-gray-200">
                            <thead class="bbjd-bg-gray-50">
                                <tr>
                                    <th class="bbjd-px-4 bbjd-py-2 bbjd-text-left bbjd-text-xs bbjd-font-medium bbjd-text-gray-500 bbjd-uppercase">Role</th>
                                    <th class="bbjd-px-4 bbjd-py-2 bbjd-text-right bbjd-text-xs bbjd-font-medium bbjd-text-gray-500 bbjd-uppercase">Count</th>
                                </tr>
                            </thead>
                            <tbody class="bbjd-bg-white bbjd-divide-y bbjd-divide-gray-200">
                                <?php foreach ($stats['roles'] as $role): ?>
                                <tr>
                                    <td class="bbjd-px-4 bbjd-py-2 bbjd-text-sm bbjd-text-gray-900"><?php echo esc_html($role['role_group']); ?></td>
                                    <td class="bbjd-px-4 bbjd-py-2 bbjd-text-sm bbjd-text-gray-900 bbjd-text-right bbjd-font-mono"><?php echo number_format($role['cnt']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <tr class="bbjd-bg-gray-50">
                                    <td class="bbjd-px-4 bbjd-py-2 bbjd-text-sm bbjd-text-gray-500 bbjd-italic">No wp_capabilities (roleless)</td>
                                    <td class="bbjd-px-4 bbjd-py-2 bbjd-text-sm bbjd-text-gray-500 bbjd-text-right bbjd-font-mono">
                                        <?php echo number_format($stats['totalUsers'] - array_sum(array_column($stats['roles'], 'cnt'))); ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Action 1: July 2024 Spam Supporters -->
                <?php if ($stats['spamSupporters'] > 0): ?>
                <div class="bbjd-bg-white bbjd-rounded-lg bbjd-shadow bbjd-p-6 bbjd-mb-6 bbjd-border-l-4 bbjd-border-red-500">
                    <h2 class="bbjd-text-xl bbjd-font-semibold bbjd-text-gray-800 bbjd-mb-2">
                        July 2024 Spam Supporters
                    </h2>
                    <p class="bbjd-text-gray-600 bbjd-mb-4">
                        <strong class="bbjd-text-red-600"><?php echo number_format($stats['spamSupporters']); ?></strong> accounts created during the July 2024 exploit.
                        All have gibberish usernames, random @Gmail.com emails, supporter role, zero posts, zero comments.
                        Processed in batches of <?php echo self::BATCH_SIZE; ?>.
                    </p>
                    <div class="bbjd-bg-red-50 bbjd-border bbjd-border-red-200 bbjd-rounded bbjd-p-3 bbjd-mb-4">
                        <p class="bbjd-text-sm bbjd-text-red-700">
                            <strong>Safety:</strong> Only deletes users from July 2024 with supporter role, no posts, no comments.
                            Lifetime and admin users are excluded.
                        </p>
                    </div>
                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>"
                          >
                        <?php wp_nonce_field('bbjd_delete_spam_supporters'); ?>
                        <input type="hidden" name="action" value="bbjd_delete_spam_supporters">
                        <button type="submit" class="bbjd-bg-red-600 bbjd-text-white bbjd-px-6 bbjd-py-2 bbjd-rounded bbjd-font-medium hover:bbjd-bg-red-700 bbjd-transition-colors">
                            Delete Batch (up to <?php echo self::BATCH_SIZE; ?>)
                        </button>
                    </form>
                </div>
                <?php endif; ?>

                <!-- Action 2: Roleless Users -->
                <?php if ($stats['rolelessUsers'] > 0): ?>
                <div class="bbjd-bg-white bbjd-rounded-lg bbjd-shadow bbjd-p-6 bbjd-mb-6 bbjd-border-l-4 bbjd-border-orange-500">
                    <h2 class="bbjd-text-xl bbjd-font-semibold bbjd-text-gray-800 bbjd-mb-2">
                        Roleless Users (No wp_capabilities)
                    </h2>
                    <p class="bbjd-text-gray-600 bbjd-mb-4">
                        <strong class="bbjd-text-orange-600"><?php echo number_format($stats['rolelessUsers']); ?></strong> users have no WordPress role at all.
                        These are 2015-2020 era spam bot registrations (Russian emails, spammy usernames).
                        Processed in batches of <?php echo self::BATCH_SIZE; ?>.
                    </p>
                    <div class="bbjd-bg-orange-50 bbjd-border bbjd-border-orange-200 bbjd-rounded bbjd-p-3 bbjd-mb-4">
                        <p class="bbjd-text-sm bbjd-text-orange-700">
                            <strong>Safety:</strong> Only deletes users with no wp_capabilities, no posts, no comments. User ID 1 (admin) excluded.
                        </p>
                    </div>
                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>"
                          >
                        <?php wp_nonce_field('bbjd_delete_roleless_users'); ?>
                        <input type="hidden" name="action" value="bbjd_delete_roleless_users">
                        <button type="submit" class="bbjd-bg-orange-600 bbjd-text-white bbjd-px-6 bbjd-py-2 bbjd-rounded bbjd-font-medium hover:bbjd-bg-orange-700 bbjd-transition-colors">
                            Delete Batch (up to <?php echo self::BATCH_SIZE; ?>)
                        </button>
                    </form>
                </div>
                <?php endif; ?>

                <!-- Action 3: Demote Supporters -->
                <?php if ($stats['allSupporters'] > 0): ?>
                <div class="bbjd-bg-white bbjd-rounded-lg bbjd-shadow bbjd-p-6 bbjd-mb-6 bbjd-border-l-4 bbjd-border-yellow-500">
                    <h2 class="bbjd-text-xl bbjd-font-semibold bbjd-text-gray-800 bbjd-mb-2">
                        Demote Remaining Supporters
                    </h2>
                    <p class="bbjd-text-gray-600 bbjd-mb-4">
                        <strong class="bbjd-text-yellow-600"><?php echo number_format($stats['allSupporters']); ?></strong> users still have the supporter role
                        (excludes lifetime and admin). This will change their role to subscriber.
                    </p>
                    <div class="bbjd-bg-yellow-50 bbjd-border bbjd-border-yellow-200 bbjd-rounded bbjd-p-3 bbjd-mb-4">
                        <p class="bbjd-text-sm bbjd-text-yellow-700">
                            <strong>Safety:</strong> Removes "supporter" capability and ensures "subscriber" is set.
                            Skips users with lifetime, administrator, helper, updater, second_in_command, or other protected roles.
                        </p>
                    </div>
                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>"
                          >
                        <?php wp_nonce_field('bbjd_demote_supporters'); ?>
                        <input type="hidden" name="action" value="bbjd_demote_supporters">
                        <button type="submit" class="bbjd-bg-yellow-600 bbjd-text-white bbjd-px-6 bbjd-py-2 bbjd-rounded bbjd-font-medium hover:bbjd-bg-yellow-700 bbjd-transition-colors">
                            Demote All Supporters to Subscriber
                        </button>
                    </form>
                </div>
                <?php endif; ?>

                <!-- All Clean -->
                <?php if ($stats['spamSupporters'] === 0 && $stats['rolelessUsers'] === 0 && $stats['allSupporters'] === 0): ?>
                <div class="bbjd-bg-green-50 bbjd-border bbjd-border-green-200 bbjd-rounded-lg bbjd-p-6 bbjd-text-center">
                    <div class="bbjd-text-4xl bbjd-mb-2">&#10003;</div>
                    <h2 class="bbjd-text-xl bbjd-font-semibold bbjd-text-green-800 bbjd-mb-2">All Clean!</h2>
                    <p class="bbjd-text-green-700">No spam supporters, no roleless users, no supporters to demote.</p>
                </div>
                <?php endif; ?>

                <!-- Protected Users Preview -->
                <div class="bbjd-bg-white bbjd-rounded-lg bbjd-shadow bbjd-p-6">
                    <h2 class="bbjd-text-xl bbjd-font-semibold bbjd-text-gray-800 bbjd-mb-4">Protected Users</h2>
                    <p class="bbjd-text-sm bbjd-text-gray-500 bbjd-mb-4">These users will never be touched by cleanup actions.</p>
                    <?php $this->renderProtectedUsers(); ?>
                </div>
            </div>
        </div>
        <?php
    }

    private function renderProtectedUsers(): void
    {
        global $wpdb;

        $protectedRoleLikes = array_map(fn($r) => "um.meta_value LIKE '%{$r}%'", self::PROTECTED_ROLES);
        $protectedWhere = implode(' OR ', $protectedRoleLikes);

        $users = $wpdb->get_results(
            "SELECT u.ID, u.user_login, u.user_email, u.user_registered, um.meta_value as caps,
                (SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_author = u.ID) as posts,
                (SELECT COUNT(*) FROM {$wpdb->comments} WHERE user_id = u.ID) as comments
            FROM {$wpdb->users} u
            JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
            WHERE um.meta_key = 'wp_capabilities'
            AND ({$protectedWhere})
            ORDER BY u.user_registered ASC",
            ARRAY_A
        );

        if (empty($users)) {
            echo '<p class="bbjd-text-gray-500">No protected users found.</p>';
            return;
        }
        ?>
        <div class="bbjd-overflow-x-auto">
            <table class="bbjd-min-w-full bbjd-divide-y bbjd-divide-gray-200 bbjd-text-sm">
                <thead class="bbjd-bg-gray-50">
                    <tr>
                        <th class="bbjd-px-3 bbjd-py-2 bbjd-text-left bbjd-text-xs bbjd-font-medium bbjd-text-gray-500 bbjd-uppercase">ID</th>
                        <th class="bbjd-px-3 bbjd-py-2 bbjd-text-left bbjd-text-xs bbjd-font-medium bbjd-text-gray-500 bbjd-uppercase">Username</th>
                        <th class="bbjd-px-3 bbjd-py-2 bbjd-text-left bbjd-text-xs bbjd-font-medium bbjd-text-gray-500 bbjd-uppercase">Email</th>
                        <th class="bbjd-px-3 bbjd-py-2 bbjd-text-left bbjd-text-xs bbjd-font-medium bbjd-text-gray-500 bbjd-uppercase">Roles</th>
                        <th class="bbjd-px-3 bbjd-py-2 bbjd-text-right bbjd-text-xs bbjd-font-medium bbjd-text-gray-500 bbjd-uppercase">Posts</th>
                        <th class="bbjd-px-3 bbjd-py-2 bbjd-text-right bbjd-text-xs bbjd-font-medium bbjd-text-gray-500 bbjd-uppercase">Comments</th>
                    </tr>
                </thead>
                <tbody class="bbjd-bg-white bbjd-divide-y bbjd-divide-gray-200">
                    <?php foreach ($users as $user):
                        $caps = maybe_unserialize($user['caps']);
                        $roleNames = is_array($caps) ? implode(', ', array_keys($caps)) : 'unknown';
                    ?>
                    <tr>
                        <td class="bbjd-px-3 bbjd-py-2 bbjd-text-gray-500 bbjd-font-mono"><?php echo esc_html($user['ID']); ?></td>
                        <td class="bbjd-px-3 bbjd-py-2 bbjd-text-gray-900 bbjd-font-medium"><?php echo esc_html($user['user_login']); ?></td>
                        <td class="bbjd-px-3 bbjd-py-2 bbjd-text-gray-500"><?php echo esc_html($user['user_email']); ?></td>
                        <td class="bbjd-px-3 bbjd-py-2">
                            <?php
                            if (is_array($caps)) {
                                foreach (array_keys($caps) as $role) {
                                    $color = match ($role) {
                                        'administrator' => 'bbjd-bg-purple-100 bbjd-text-purple-800',
                                        'lifetime' => 'bbjd-bg-emerald-100 bbjd-text-emerald-800',
                                        'supporter' => 'bbjd-bg-blue-100 bbjd-text-blue-800',
                                        'helper' => 'bbjd-bg-cyan-100 bbjd-text-cyan-800',
                                        'updater' => 'bbjd-bg-indigo-100 bbjd-text-indigo-800',
                                        default => 'bbjd-bg-gray-100 bbjd-text-gray-800',
                                    };
                                    echo '<span class="bbjd-inline-flex bbjd-items-center bbjd-px-2 bbjd-py-0.5 bbjd-rounded-full bbjd-text-xs bbjd-font-medium ' . $color . ' bbjd-mr-1">' . esc_html($role) . '</span>';
                                }
                            }
                            ?>
                        </td>
                        <td class="bbjd-px-3 bbjd-py-2 bbjd-text-gray-900 bbjd-text-right bbjd-font-mono"><?php echo number_format($user['posts']); ?></td>
                        <td class="bbjd-px-3 bbjd-py-2 bbjd-text-gray-900 bbjd-text-right bbjd-font-mono"><?php echo number_format($user['comments']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    private function renderMessages(string $message): void
    {
        if (empty($message)) {
            return;
        }

        $messages = [
            'spam_deleted' => ['success', sprintf(
                'Deleted %s spam supporter accounts. %s remaining.',
                number_format(intval($_GET['deleted'] ?? 0)),
                number_format(intval($_GET['remaining'] ?? 0))
            )],
            'roleless_deleted' => ['success', sprintf(
                'Deleted %s roleless accounts. %s remaining.',
                number_format(intval($_GET['deleted'] ?? 0)),
                number_format(intval($_GET['remaining'] ?? 0))
            )],
            'supporters_demoted' => ['success', sprintf(
                'Demoted %s supporters to subscriber.',
                number_format(intval($_GET['demoted'] ?? 0))
            )],
        ];

        if (!isset($messages[$message])) {
            return;
        }

        [$type, $text] = $messages[$message];
        $bgColor = $type === 'success'
            ? 'bbjd-bg-green-100 bbjd-border-green-500 bbjd-text-green-700'
            : 'bbjd-bg-red-100 bbjd-border-red-500 bbjd-text-red-700';
        ?>
        <div class="<?php echo $bgColor; ?> bbjd-border-l-4 bbjd-p-4 bbjd-mb-6 bbjd-rounded">
            <p><?php echo esc_html($text); ?></p>
        </div>
        <?php
    }
}
