<?php

namespace BigBrotherJunkies\Data\Admin\Pages;

use BigBrotherJunkies\Data\Database\Migrator;
use BigBrotherJunkies\Data\Database\Schema;

/**
 * Dev Tools admin page for database management
 */
class DevToolsPage
{
    public const MENU_SLUG = 'bbjd-dev-tools';

    /**
     * Handle AJAX actions
     */
    public function handleActions(): void
    {
        add_action('admin_post_bbjd_create_tables', [$this, 'handleCreateTables']);
        add_action('admin_post_bbjd_drop_tables', [$this, 'handleDropTables']);
        add_action('admin_post_bbjd_import_v2', [$this, 'handleImportV2']);
    }

    /**
     * Handle create tables action
     */
    public function handleCreateTables(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('bbjd_create_tables');

        $results = Migrator::migrate();

        $success = !in_array(false, $results, true);
        $message = $success ? 'tables_created' : 'tables_error';

        wp_redirect(add_query_arg([
            'page' => self::MENU_SLUG,
            'message' => $message,
        ], admin_url('admin.php')));
        exit;
    }

    /**
     * Handle drop tables action
     */
    public function handleDropTables(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('bbjd_drop_tables');

        $results = Migrator::dropAllTables();

        $success = !in_array(false, $results, true);
        $message = $success ? 'tables_dropped' : 'tables_drop_error';

        wp_redirect(add_query_arg([
            'page' => self::MENU_SLUG,
            'message' => $message,
        ], admin_url('admin.php')));
        exit;
    }

    /**
     * Handle import from bbj-v2 action
     */
    public function handleImportV2(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('bbjd_import_v2');

        $results = Migrator::importFromBbjV2();

        wp_redirect(add_query_arg([
            'page' => self::MENU_SLUG,
            'message' => 'import_complete',
            'slots' => $results['slots_imported'],
            'ads' => $results['ads_imported'],
        ], admin_url('admin.php')));
        exit;
    }

    /**
     * Render the page
     */
    public function render(): void
    {
        $tablesStatus = Migrator::getTablesStatus();
        $dbVersion = Migrator::getCurrentVersion();
        $needsMigration = Migrator::needsMigration();

        // Check for messages
        $message = $_GET['message'] ?? '';
        ?>
        <div class="bbjd-admin">
            <div class="bbjd-p-6 bbjd-max-w-4xl">
                <h1 class="bbjd-text-3xl bbjd-font-bold bbjd-text-primary500 bbjd-mb-6">
                    Dev Tools
                </h1>

                <?php $this->renderMessages($message); ?>

                <!-- Database Section -->
                <div class="bbjd-bg-white bbjd-rounded-lg bbjd-shadow bbjd-p-6 bbjd-mb-6">
                    <h2 class="bbjd-text-xl bbjd-font-semibold bbjd-text-gray-800 bbjd-mb-4">
                        Database Management
                    </h2>

                    <!-- Table Status -->
                    <div class="bbjd-mb-6">
                        <h3 class="bbjd-text-lg bbjd-font-medium bbjd-text-gray-700 bbjd-mb-3">Table Status</h3>
                        <div class="bbjd-overflow-x-auto">
                            <table class="bbjd-min-w-full bbjd-divide-y bbjd-divide-gray-200">
                                <thead class="bbjd-bg-gray-50">
                                    <tr>
                                        <th class="bbjd-px-4 bbjd-py-2 bbjd-text-left bbjd-text-xs bbjd-font-medium bbjd-text-gray-500 bbjd-uppercase">Table</th>
                                        <th class="bbjd-px-4 bbjd-py-2 bbjd-text-left bbjd-text-xs bbjd-font-medium bbjd-text-gray-500 bbjd-uppercase">Status</th>
                                        <th class="bbjd-px-4 bbjd-py-2 bbjd-text-left bbjd-text-xs bbjd-font-medium bbjd-text-gray-500 bbjd-uppercase">Rows</th>
                                    </tr>
                                </thead>
                                <tbody class="bbjd-bg-white bbjd-divide-y bbjd-divide-gray-200">
                                    <?php foreach ($tablesStatus as $table => $status): ?>
                                    <tr>
                                        <td class="bbjd-px-4 bbjd-py-2 bbjd-text-sm bbjd-font-mono bbjd-text-gray-900">
                                            <?php echo esc_html($status['full_name']); ?>
                                        </td>
                                        <td class="bbjd-px-4 bbjd-py-2 bbjd-text-sm">
                                            <?php if ($status['exists']): ?>
                                                <span class="bbjd-inline-flex bbjd-items-center bbjd-px-2.5 bbjd-py-0.5 bbjd-rounded-full bbjd-text-xs bbjd-font-medium bbjd-bg-green-100 bbjd-text-green-800">
                                                    Exists
                                                </span>
                                            <?php else: ?>
                                                <span class="bbjd-inline-flex bbjd-items-center bbjd-px-2.5 bbjd-py-0.5 bbjd-rounded-full bbjd-text-xs bbjd-font-medium bbjd-bg-red-100 bbjd-text-red-800">
                                                    Missing
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="bbjd-px-4 bbjd-py-2 bbjd-text-sm bbjd-text-gray-500">
                                            <?php echo $status['rows'] >= 0 ? number_format($status['rows']) : '-'; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="bbjd-flex bbjd-flex-wrap bbjd-gap-4">
                        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                            <?php wp_nonce_field('bbjd_create_tables'); ?>
                            <input type="hidden" name="action" value="bbjd_create_tables">
                            <button type="submit" class="bbjd-bg-primary500 bbjd-text-white bbjd-px-4 bbjd-py-2 bbjd-rounded bbjd-font-medium hover:bbjd-bg-primaryHard bbjd-transition-colors">
                                Create/Update Tables
                            </button>
                        </form>

                        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>"
                              onsubmit="return confirm('Are you sure you want to DROP all Ad Manager tables? This cannot be undone!');">
                            <?php wp_nonce_field('bbjd_drop_tables'); ?>
                            <input type="hidden" name="action" value="bbjd_drop_tables">
                            <button type="submit" class="bbjd-bg-thirdColor bbjd-text-white bbjd-px-4 bbjd-py-2 bbjd-rounded bbjd-font-medium hover:bbjd-opacity-90 bbjd-transition-opacity">
                                Drop All Tables
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Migration Section -->
                <div class="bbjd-bg-white bbjd-rounded-lg bbjd-shadow bbjd-p-6 bbjd-mb-6">
                    <h2 class="bbjd-text-xl bbjd-font-semibold bbjd-text-gray-800 bbjd-mb-4">
                        Import from bbj-v2
                    </h2>
                    <p class="bbjd-text-gray-600 bbjd-mb-4">
                        Import existing ad slots and ad content from the bbj-v2 plugin. This will create slots and ads based on the current <code class="bbjd-bg-gray-100 bbjd-px-1 bbjd-rounded">bbj_ads</code> option.
                    </p>
                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                        <?php wp_nonce_field('bbjd_import_v2'); ?>
                        <input type="hidden" name="action" value="bbjd_import_v2">
                        <button type="submit" class="bbjd-bg-second500 bbjd-text-primaryHard bbjd-px-4 bbjd-py-2 bbjd-rounded bbjd-font-medium hover:bbjd-bg-secondSoft bbjd-transition-colors">
                            Import from bbj-v2
                        </button>
                    </form>
                </div>

                <!-- Debug Info -->
                <div class="bbjd-bg-white bbjd-rounded-lg bbjd-shadow bbjd-p-6">
                    <h2 class="bbjd-text-xl bbjd-font-semibold bbjd-text-gray-800 bbjd-mb-4">
                        Debug Information
                    </h2>
                    <dl class="bbjd-grid bbjd-grid-cols-1 md:bbjd-grid-cols-2 bbjd-gap-4">
                        <div>
                            <dt class="bbjd-text-sm bbjd-font-medium bbjd-text-gray-500">Plugin Version</dt>
                            <dd class="bbjd-mt-1 bbjd-text-sm bbjd-text-gray-900"><?php echo esc_html(BBJD_VERSION); ?></dd>
                        </div>
                        <div>
                            <dt class="bbjd-text-sm bbjd-font-medium bbjd-text-gray-500">Database Version</dt>
                            <dd class="bbjd-mt-1 bbjd-text-sm bbjd-text-gray-900"><?php echo esc_html($dbVersion); ?></dd>
                        </div>
                        <div>
                            <dt class="bbjd-text-sm bbjd-font-medium bbjd-text-gray-500">PHP Version</dt>
                            <dd class="bbjd-mt-1 bbjd-text-sm bbjd-text-gray-900"><?php echo esc_html(PHP_VERSION); ?></dd>
                        </div>
                        <div>
                            <dt class="bbjd-text-sm bbjd-font-medium bbjd-text-gray-500">MySQL Version</dt>
                            <dd class="bbjd-mt-1 bbjd-text-sm bbjd-text-gray-900"><?php global $wpdb; echo esc_html($wpdb->db_version()); ?></dd>
                        </div>
                        <div>
                            <dt class="bbjd-text-sm bbjd-font-medium bbjd-text-gray-500">Object Cache</dt>
                            <dd class="bbjd-mt-1 bbjd-text-sm bbjd-text-gray-900">
                                <?php echo wp_using_ext_object_cache() ? 'External (Redis/Memcached)' : 'Default (File)'; ?>
                            </dd>
                        </div>
                        <div>
                            <dt class="bbjd-text-sm bbjd-font-medium bbjd-text-gray-500">Migration Status</dt>
                            <dd class="bbjd-mt-1 bbjd-text-sm bbjd-text-gray-900">
                                <?php echo $needsMigration ? 'Migration needed' : 'Up to date'; ?>
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render status messages
     */
    private function renderMessages(string $message): void
    {
        if (empty($message)) {
            return;
        }

        $messages = [
            'tables_created' => ['success', 'Database tables created/updated successfully.'],
            'tables_error' => ['error', 'Error creating database tables.'],
            'tables_dropped' => ['success', 'All tables dropped successfully.'],
            'tables_drop_error' => ['error', 'Error dropping tables.'],
            'import_complete' => ['success', sprintf(
                'Import complete! Imported %d slots and %d ads.',
                intval($_GET['slots'] ?? 0),
                intval($_GET['ads'] ?? 0)
            )],
        ];

        if (!isset($messages[$message])) {
            return;
        }

        [$type, $text] = $messages[$message];
        $bgColor = $type === 'success' ? 'bbjd-bg-green-100 bbjd-border-green-500 bbjd-text-green-700' : 'bbjd-bg-red-100 bbjd-border-red-500 bbjd-text-red-700';
        ?>
        <div class="<?php echo $bgColor; ?> bbjd-border-l-4 bbjd-p-4 bbjd-mb-6 bbjd-rounded">
            <p><?php echo esc_html($text); ?></p>
        </div>
        <?php
    }
}
