<?php

namespace BigBrotherJunkies\Data\Admin\Pages;

use BigBrotherJunkies\Data\Database\Migrator;
use BigBrotherJunkies\Data\Database\Schema;
use BigBrotherJunkies\Data\Api\AuthRoutes;
use BigBrotherJunkies\Data\Comments\CommentMigrator;
use BigBrotherJunkies\Data\Comments\CommentSchema;
use BigBrotherJunkies\Data\Billing\BillingMigrator;
use BigBrotherJunkies\Data\Billing\BillingSchema;
use BigBrotherJunkies\Data\BugReports\BugReportMigrator;
use BigBrotherJunkies\Data\BugReports\BugReportSchema;

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
        add_action('admin_post_bbjd_clear_registration_logs', [$this, 'handleClearRegistrationLogs']);
        add_action('admin_post_bbjd_create_comment_tables', [$this, 'handleCreateCommentTables']);
        add_action('admin_post_bbjd_create_billing_tables', [$this, 'handleCreateBillingTables']);
        add_action('admin_post_bbjd_create_bug_report_tables', [$this, 'handleCreateBugReportTables']);
        add_action('admin_post_bbjd_add_player_hometown_cols', [$this, 'handleAddPlayerHometownCols']);
        add_action('admin_post_bbjd_add_finish_place_col', [$this, 'handleAddFinishPlaceCol']);
        add_action('admin_post_bbjd_update_player_data', [$this, 'handleUpdatePlayerData']);
    }

    /**
     * Handle add player hometown columns
     */
    public function handleAddPlayerHometownCols(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('bbjd_add_player_hometown_cols');

        global $wpdb;
        $table = $wpdb->prefix . 'bbj_players';

        // Check if columns already exist
        $columns = $wpdb->get_col("SHOW COLUMNS FROM {$table}");
        $added = [];
        $skipped = [];

        if (!in_array('hometown_city', $columns)) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN hometown_city VARCHAR(100) DEFAULT NULL");
            $added[] = 'hometown_city';
        } else {
            $skipped[] = 'hometown_city';
        }

        if (!in_array('hometown_state', $columns)) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN hometown_state VARCHAR(50) DEFAULT NULL");
            $added[] = 'hometown_state';
        } else {
            $skipped[] = 'hometown_state';
        }

        if (!in_array('hometown_lat', $columns)) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN hometown_lat DECIMAL(10, 7) DEFAULT NULL");
            $added[] = 'hometown_lat';
        } else {
            $skipped[] = 'hometown_lat';
        }

        if (!in_array('hometown_lng', $columns)) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN hometown_lng DECIMAL(10, 7) DEFAULT NULL");
            $added[] = 'hometown_lng';
        } else {
            $skipped[] = 'hometown_lng';
        }

        wp_redirect(add_query_arg([
            'page' => self::MENU_SLUG,
            'message' => 'hometown_cols_added',
            'added' => implode(',', $added),
            'skipped' => implode(',', $skipped),
        ], admin_url('admin.php')));
        exit;
    }

    /**
     * Handle add finish_place column
     */
    public function handleAddFinishPlaceCol(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('bbjd_add_finish_place_col');

        global $wpdb;
        $table = $wpdb->prefix . 'bbj_v2_player_season';

        // Check if column already exists
        $columns = $wpdb->get_col("SHOW COLUMNS FROM {$table}");

        if (in_array('finish_place', $columns)) {
            wp_redirect(add_query_arg([
                'page' => self::MENU_SLUG,
                'message' => 'finish_place_exists',
            ], admin_url('admin.php')));
            exit;
        }

        $wpdb->query("ALTER TABLE {$table} ADD COLUMN finish_place TINYINT UNSIGNED DEFAULT NULL");

        wp_redirect(add_query_arg([
            'page' => self::MENU_SLUG,
            'message' => 'finish_place_added',
        ], admin_url('admin.php')));
        exit;
    }

    /**
     * Handle update player data from CSV
     * Updates existing players with hometown and finish_place data
     */
    public function handleUpdatePlayerData(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('bbjd_update_player_data');

        $result = $this->updatePlayersFromCsv();

        wp_redirect(add_query_arg([
            'page' => self::MENU_SLUG,
            'message' => 'player_data_updated',
            'players_updated' => $result['players_updated'],
            'seasons_updated' => $result['seasons_updated'],
            'errors' => $result['errors'],
        ], admin_url('admin.php')));
        exit;
    }

    /**
     * Name mapping: CSV names to DB names (for nickname/alias cases)
     * Format: 'csv_first|csv_last' => 'db_first|db_last'
     */
    private function getNameMapping(): array
    {
        return [
            // BB2
            'bill|miller' => 'bunky|miller',
            // BB3
            'gerald|lancaster' => 'gerry|lancaster',
            'lori|olson' => 'lori|olsen',
            // BB4
            'jack|owens' => 'jack|owens jr.',
            // BB5 & BB7
            'jason|wirey' => 'jase|wirey',
            'jennifer|dedmon' => 'nakomis|dedmon',
            // BB8 (Daniele was Donato before marriage to Dominic Briones)
            // 'daniele|donato' => 'daniele|briones', // Keep both - she's in DB as Briones for BB22
            // BB10
            'lorenza|martyn' => 'renny|martyn',
            // BB16
            'joanna|van pelt' => 'joey|van pelt',
            // BB20
            'joseph|mounduix' => 'jc|mounduix',
            'christopher|williams' => 'chris|williams',
            // BB23
            'frenchie|french' => 'brandon|french',
            // BB24
            'matthew|turner' => 'matt|turner',
        ];
    }

    /**
     * Update existing players from CSV data
     */
    private function updatePlayersFromCsv(): array
    {
        global $wpdb;
        $playersTable = $wpdb->prefix . 'bbj_players';
        $linkTable = $wpdb->prefix . 'bbj_v2_player_season';
        $seasonsTable = $wpdb->prefix . 'bbj_seasons';

        $csvPath = $this->getCsvPath('players.md');
        $geoPath = $this->getCsvPath('cities_with_coords.csv');

        if (!$csvPath || !file_exists($csvPath)) {
            return ['players_updated' => 0, 'seasons_updated' => 0, 'errors' => 1];
        }

        // Load geo lookup
        $geoLookup = [];
        if ($geoPath && file_exists($geoPath)) {
            $handle = fopen($geoPath, 'r');
            if ($handle) {
                fgetcsv($handle); // Skip header
                while (($row = fgetcsv($handle)) !== false) {
                    if (count($row) >= 4) {
                        $key = trim($row[0]) . '|' . trim($row[1]);
                        $geoLookup[$key] = [
                            'lat' => (float) $row[2],
                            'lng' => (float) $row[3],
                        ];
                    }
                }
                fclose($handle);
            }
        }

        // Build season map (season_number => id)
        $seasons = $wpdb->get_results("SELECT id, season_number FROM {$seasonsTable}", ARRAY_A);
        $seasonMap = [];
        foreach ($seasons as $s) {
            $seasonMap[$s['season_number']] = (int) $s['id'];
        }

        // Build player map (first|last => id) and track player hometown updates
        $existingPlayers = $wpdb->get_results("SELECT id, first_name, last_name FROM {$playersTable}", ARRAY_A);
        $playerMap = [];
        foreach ($existingPlayers as $p) {
            $key = strtolower(trim($p['first_name']) . '|' . trim($p['last_name']));
            $playerMap[$key] = (int) $p['id'];
        }

        // Get name mapping
        $nameMapping = $this->getNameMapping();

        // Track updates
        $playersUpdated = 0;
        $seasonsUpdated = 0;
        $playerHometownSet = []; // Track which players we've updated hometown for

        // Read CSV and process
        $handle = fopen($csvPath, 'r');
        if (!$handle) {
            return ['players_updated' => 0, 'seasons_updated' => 0, 'errors' => 1];
        }

        while (($row = fgetcsv($handle)) !== false) {
            // Skip header rows
            if ($row[0] === 'season_number') {
                continue;
            }

            if (count($row) < 5) {
                continue;
            }

            $seasonNumber = trim($row[0]);
            $firstName = trim($row[1]);
            $lastName = trim($row[2]);
            $hometownCity = trim($row[3] ?? '');
            $hometownState = trim($row[4] ?? '');
            $finishPlace = !empty($row[5]) ? (int) $row[5] : null;
            $evictedDate = !empty($row[6]) ? trim($row[6]) : null;

            // Create lookup key
            $csvKey = strtolower($firstName . '|' . $lastName);

            // Check if we need to map to a different name in DB
            $dbKey = $nameMapping[$csvKey] ?? $csvKey;

            // Find player ID
            $playerId = $playerMap[$dbKey] ?? null;

            if (!$playerId) {
                // Player not found - skip
                continue;
            }

            // Update player hometown (only once per player)
            if (!isset($playerHometownSet[$playerId]) && !empty($hometownCity)) {
                $geoKey = $hometownCity . '|' . $hometownState;
                $geo = $geoLookup[$geoKey] ?? ['lat' => null, 'lng' => null];

                $updated = $wpdb->update(
                    $playersTable,
                    [
                        'hometown_city' => $hometownCity,
                        'hometown_state' => $hometownState,
                        'hometown_lat' => $geo['lat'],
                        'hometown_lng' => $geo['lng'],
                    ],
                    ['id' => $playerId],
                    ['%s', '%s', '%f', '%f'],
                    ['%d']
                );

                if ($updated !== false) {
                    $playersUpdated++;
                    $playerHometownSet[$playerId] = true;
                }
            }

            // Update player_season record with finish_place and evicted_date
            $seasonId = $seasonMap[$seasonNumber] ?? null;
            if ($seasonId) {
                $updateData = [];
                $updateFormat = [];

                if ($finishPlace !== null) {
                    $updateData['finish_place'] = $finishPlace;
                    $updateFormat[] = '%d';
                }

                if ($evictedDate !== null) {
                    $updateData['bbj_evicted_date'] = $evictedDate;
                    $updateFormat[] = '%s';
                }

                if (!empty($updateData)) {
                    $updated = $wpdb->update(
                        $linkTable,
                        $updateData,
                        [
                            'bbj_player' => $playerId,
                            'bbj_season' => $seasonId,
                        ],
                        $updateFormat,
                        ['%d', '%d']
                    );

                    if ($updated !== false && $updated > 0) {
                        $seasonsUpdated++;
                    }
                }
            }
        }

        fclose($handle);

        return [
            'players_updated' => $playersUpdated,
            'seasons_updated' => $seasonsUpdated,
            'errors' => 0,
        ];
    }

    /**
     * Get CSV file path (dev fallback)
     */
    private function getCsvPath(string $filename): ?string
    {
        // Production path
        $prodPath = WP_CONTENT_DIR . '/uploads/bbj-import/' . $filename;
        if (file_exists($prodPath)) {
            return $prodPath;
        }

        // Development fallback
        $devPath = 'C:/xampp/htdocs/bbj-app/.claude/data/' . $filename;
        if (file_exists($devPath)) {
            return $devPath;
        }

        return null;
    }

    /**
     * Handle clear registration logs action
     */
    public function handleClearRegistrationLogs(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('bbjd_clear_registration_logs');

        AuthRoutes::clearRegistrationLogs();

        wp_redirect(add_query_arg([
            'page' => self::MENU_SLUG,
            'message' => 'logs_cleared',
        ], admin_url('admin.php')));
        exit;
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
     * Handle create comment tables action
     */
    public function handleCreateCommentTables(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('bbjd_create_comment_tables');

        $results = CommentMigrator::migrate();

        $success = !in_array(false, $results, true);
        $message = $success ? 'comment_tables_created' : 'comment_tables_error';

        wp_redirect(add_query_arg([
            'page' => self::MENU_SLUG,
            'message' => $message,
        ], admin_url('admin.php')));
        exit;
    }

    /**
     * Handle create billing tables action
     */
    public function handleCreateBillingTables(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('bbjd_create_billing_tables');

        $results = BillingMigrator::migrate();

        $success = !in_array(false, $results, true);
        $message = $success ? 'billing_tables_created' : 'billing_tables_error';

        wp_redirect(add_query_arg([
            'page' => self::MENU_SLUG,
            'message' => $message,
        ], admin_url('admin.php')));
        exit;
    }

    /**
     * Handle create bug report tables action
     */
    public function handleCreateBugReportTables(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('bbjd_create_bug_report_tables');

        $results = BugReportMigrator::migrate();

        $success = !in_array(false, $results, true);
        $message = $success ? 'bug_report_tables_created' : 'bug_report_tables_error';

        wp_redirect(add_query_arg([
            'page' => self::MENU_SLUG,
            'message' => $message,
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

        // Comment system tables
        $commentTablesStatus = CommentMigrator::getTablesStatus();
        $commentDbVersion = CommentMigrator::getCurrentVersion();
        $commentNeedsMigration = CommentMigrator::needsMigration();

        // Billing system tables
        $billingTablesStatus = BillingMigrator::getTablesStatus();
        $billingDbVersion = BillingMigrator::getCurrentVersion();
        $billingNeedsMigration = BillingMigrator::needsMigration();

        // Bug report system tables
        $bugReportTablesStatus = BugReportMigrator::getTablesStatus();
        $bugReportDbVersion = BugReportMigrator::getCurrentVersion();
        $bugReportNeedsMigration = BugReportMigrator::needsMigration();

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

                <!-- Comment System Database Section -->
                <div class="bbjd-bg-white bbjd-rounded-lg bbjd-shadow bbjd-p-6 bbjd-mb-6">
                    <h2 class="bbjd-text-xl bbjd-font-semibold bbjd-text-gray-800 bbjd-mb-4">
                        Comment System Database
                        <?php if ($commentNeedsMigration): ?>
                            <span class="bbjd-inline-flex bbjd-items-center bbjd-px-2.5 bbjd-py-0.5 bbjd-rounded-full bbjd-text-xs bbjd-font-medium bbjd-bg-yellow-100 bbjd-text-yellow-800 bbjd-ml-2">
                                Migration Available
                            </span>
                        <?php endif; ?>
                    </h2>

                    <p class="bbjd-text-sm bbjd-text-gray-600 bbjd-mb-4">
                        DB Version: <strong><?php echo esc_html($commentDbVersion); ?></strong>
                    </p>

                    <!-- Comment Table Status -->
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
                                    <?php foreach ($commentTablesStatus as $table => $status): ?>
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

                    <!-- Comment Tables Action -->
                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                        <?php wp_nonce_field('bbjd_create_comment_tables'); ?>
                        <input type="hidden" name="action" value="bbjd_create_comment_tables">
                        <button type="submit" class="bbjd-bg-primary500 bbjd-text-white bbjd-px-4 bbjd-py-2 bbjd-rounded bbjd-font-medium hover:bbjd-bg-primaryHard bbjd-transition-colors">
                            Create/Update Comment Tables
                        </button>
                    </form>
                </div>

                <!-- Billing System Database Section -->
                <div class="bbjd-bg-white bbjd-rounded-lg bbjd-shadow bbjd-p-6 bbjd-mb-6">
                    <h2 class="bbjd-text-xl bbjd-font-semibold bbjd-text-gray-800 bbjd-mb-4">
                        Billing System Database
                        <?php if ($billingNeedsMigration): ?>
                            <span class="bbjd-inline-flex bbjd-items-center bbjd-px-2.5 bbjd-py-0.5 bbjd-rounded-full bbjd-text-xs bbjd-font-medium bbjd-bg-yellow-100 bbjd-text-yellow-800 bbjd-ml-2">
                                Migration Available
                            </span>
                        <?php endif; ?>
                    </h2>

                    <p class="bbjd-text-sm bbjd-text-gray-600 bbjd-mb-4">
                        DB Version: <strong><?php echo esc_html($billingDbVersion); ?></strong>
                    </p>

                    <!-- Billing Table Status -->
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
                                    <?php foreach ($billingTablesStatus as $table => $status): ?>
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

                    <!-- Billing Tables Action -->
                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                        <?php wp_nonce_field('bbjd_create_billing_tables'); ?>
                        <input type="hidden" name="action" value="bbjd_create_billing_tables">
                        <button type="submit" class="bbjd-bg-primary500 bbjd-text-white bbjd-px-4 bbjd-py-2 bbjd-rounded bbjd-font-medium hover:bbjd-bg-primaryHard bbjd-transition-colors">
                            Create/Update Billing Tables
                        </button>
                    </form>
                </div>

                <!-- Bug Report System Database Section -->
                <div class="bbjd-bg-white bbjd-rounded-lg bbjd-shadow bbjd-p-6 bbjd-mb-6">
                    <h2 class="bbjd-text-xl bbjd-font-semibold bbjd-text-gray-800 bbjd-mb-4">
                        Bug Report System Database
                        <?php if ($bugReportNeedsMigration): ?>
                            <span class="bbjd-inline-flex bbjd-items-center bbjd-px-2.5 bbjd-py-0.5 bbjd-rounded-full bbjd-text-xs bbjd-font-medium bbjd-bg-yellow-100 bbjd-text-yellow-800 bbjd-ml-2">
                                Migration Available
                            </span>
                        <?php endif; ?>
                    </h2>

                    <p class="bbjd-text-sm bbjd-text-gray-600 bbjd-mb-4">
                        DB Version: <strong><?php echo esc_html($bugReportDbVersion); ?></strong>
                    </p>

                    <!-- Bug Report Table Status -->
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
                                    <?php foreach ($bugReportTablesStatus as $table => $status): ?>
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

                    <!-- Bug Report Tables Action -->
                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                        <?php wp_nonce_field('bbjd_create_bug_report_tables'); ?>
                        <input type="hidden" name="action" value="bbjd_create_bug_report_tables">
                        <button type="submit" class="bbjd-bg-primary500 bbjd-text-white bbjd-px-4 bbjd-py-2 bbjd-rounded bbjd-font-medium hover:bbjd-bg-primaryHard bbjd-transition-colors">
                            Create/Update Bug Report Tables
                        </button>
                    </form>
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
                <div class="bbjd-bg-white bbjd-rounded-lg bbjd-shadow bbjd-p-6 bbjd-mb-6">
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

                <!-- SQL Migrations -->
                <div class="bbjd-bg-white bbjd-rounded-lg bbjd-shadow bbjd-p-6 bbjd-mb-6">
                    <h2 class="bbjd-text-xl bbjd-font-semibold bbjd-text-gray-800 bbjd-mb-4">
                        SQL Migrations
                    </h2>
                    <p class="bbjd-text-gray-600 bbjd-mb-4">
                        Add missing columns to support player import with hometown/geo data:
                    </p>

                    <div class="bbjd-space-y-6">
                        <div class="bbjd-border bbjd-border-gray-200 bbjd-rounded bbjd-p-4">
                            <h3 class="bbjd-text-sm bbjd-font-medium bbjd-text-gray-700 bbjd-mb-2">Add hometown fields to players:</h3>
                            <pre class="bbjd-bg-gray-900 bbjd-text-green-400 bbjd-p-4 bbjd-rounded bbjd-text-sm bbjd-overflow-x-auto bbjd-mb-3"><code>ALTER TABLE wp_bbj_players
ADD COLUMN hometown_city VARCHAR(100) DEFAULT NULL,
ADD COLUMN hometown_state VARCHAR(50) DEFAULT NULL,
ADD COLUMN hometown_lat DECIMAL(10, 7) DEFAULT NULL,
ADD COLUMN hometown_lng DECIMAL(10, 7) DEFAULT NULL;</code></pre>
                            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                                <?php wp_nonce_field('bbjd_add_player_hometown_cols'); ?>
                                <input type="hidden" name="action" value="bbjd_add_player_hometown_cols">
                                <button type="submit" class="bbjd-bg-primary500 bbjd-text-white bbjd-px-4 bbjd-py-2 bbjd-rounded bbjd-font-medium hover:bbjd-bg-primaryHard bbjd-transition-colors">
                                    Run Migration
                                </button>
                            </form>
                        </div>

                        <div class="bbjd-border bbjd-border-gray-200 bbjd-rounded bbjd-p-4">
                            <h3 class="bbjd-text-sm bbjd-font-medium bbjd-text-gray-700 bbjd-mb-2">Add finish_place to player_season:</h3>
                            <pre class="bbjd-bg-gray-900 bbjd-text-green-400 bbjd-p-4 bbjd-rounded bbjd-text-sm bbjd-overflow-x-auto bbjd-mb-3"><code>ALTER TABLE wp_bbj_v2_player_season
ADD COLUMN finish_place TINYINT UNSIGNED DEFAULT NULL;</code></pre>
                            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                                <?php wp_nonce_field('bbjd_add_finish_place_col'); ?>
                                <input type="hidden" name="action" value="bbjd_add_finish_place_col">
                                <button type="submit" class="bbjd-bg-primary500 bbjd-text-white bbjd-px-4 bbjd-py-2 bbjd-rounded bbjd-font-medium hover:bbjd-bg-primaryHard bbjd-transition-colors">
                                    Run Migration
                                </button>
                            </form>
                        </div>

                        <div class="bbjd-border bbjd-border-gray-200 bbjd-rounded bbjd-p-4">
                            <h3 class="bbjd-text-sm bbjd-font-medium bbjd-text-gray-700 bbjd-mb-2">Update existing players with hometown &amp; finish_place data:</h3>
                            <pre class="bbjd-bg-gray-900 bbjd-text-green-400 bbjd-p-4 bbjd-rounded bbjd-text-sm bbjd-overflow-x-auto bbjd-mb-3"><code>-- Reads players.md and cities_with_coords.csv to update:
-- • Player hometown (city, state, lat/lng)
-- • Player season finish_place and evicted_date
-- • Handles name aliases (Bunky/Bill, Nakomis/Jennifer, etc.)</code></pre>
                            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                                <?php wp_nonce_field('bbjd_update_player_data'); ?>
                                <input type="hidden" name="action" value="bbjd_update_player_data">
                                <button type="submit" class="bbjd-bg-primary500 bbjd-text-white bbjd-px-4 bbjd-py-2 bbjd-rounded bbjd-font-medium hover:bbjd-bg-primaryHard bbjd-transition-colors">
                                    Run Migration
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Registration Logs -->
                <?php $this->renderRegistrationLogs(); ?>
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
            'logs_cleared' => ['success', 'Registration logs cleared successfully.'],
            'comment_tables_created' => ['success', 'Comment system tables created/updated successfully.'],
            'comment_tables_error' => ['error', 'Error creating comment system tables.'],
            'billing_tables_created' => ['success', 'Billing system tables created/updated successfully.'],
            'billing_tables_error' => ['error', 'Error creating billing system tables.'],
            'bug_report_tables_created' => ['success', 'Bug report system tables created/updated successfully.'],
            'bug_report_tables_error' => ['error', 'Error creating bug report system tables.'],
            'hometown_cols_added' => ['success', sprintf(
                'Hometown columns migration complete. Added: %s. Skipped (already exist): %s.',
                $_GET['added'] ?? 'none',
                $_GET['skipped'] ?? 'none'
            )],
            'finish_place_added' => ['success', 'finish_place column added to player_season table.'],
            'finish_place_exists' => ['success', 'finish_place column already exists - no changes made.'],
            'player_data_updated' => ['success', sprintf(
                'Player data updated! %d players updated with hometown, %d season records updated with finish_place/evicted_date. Errors: %d',
                intval($_GET['players_updated'] ?? 0),
                intval($_GET['seasons_updated'] ?? 0),
                intval($_GET['errors'] ?? 0)
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

    /**
     * Render registration logs section
     */
    private function renderRegistrationLogs(): void
    {
        $logs = AuthRoutes::getRegistrationLogs(50);
        ?>
        <div class="bbjd-bg-white bbjd-rounded-lg bbjd-shadow bbjd-p-6">
            <div class="bbjd-flex bbjd-justify-between bbjd-items-center bbjd-mb-4">
                <h2 class="bbjd-text-xl bbjd-font-semibold bbjd-text-gray-800">
                    Registration Logs
                </h2>
                <?php if (!empty($logs)): ?>
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>"
                      onsubmit="return confirm('Are you sure you want to clear all registration logs?');">
                    <?php wp_nonce_field('bbjd_clear_registration_logs'); ?>
                    <input type="hidden" name="action" value="bbjd_clear_registration_logs">
                    <button type="submit" class="bbjd-text-sm bbjd-text-red-600 hover:bbjd-text-red-800">
                        Clear Logs
                    </button>
                </form>
                <?php endif; ?>
            </div>

            <?php if (empty($logs)): ?>
                <p class="bbjd-text-gray-500 bbjd-text-sm">No registration attempts logged yet.</p>
            <?php else: ?>
                <div class="bbjd-overflow-x-auto">
                    <table class="bbjd-min-w-full bbjd-divide-y bbjd-divide-gray-200 bbjd-text-sm">
                        <thead class="bbjd-bg-gray-50">
                            <tr>
                                <th class="bbjd-px-3 bbjd-py-2 bbjd-text-left bbjd-text-xs bbjd-font-medium bbjd-text-gray-500 bbjd-uppercase">Time</th>
                                <th class="bbjd-px-3 bbjd-py-2 bbjd-text-left bbjd-text-xs bbjd-font-medium bbjd-text-gray-500 bbjd-uppercase">Username</th>
                                <th class="bbjd-px-3 bbjd-py-2 bbjd-text-left bbjd-text-xs bbjd-font-medium bbjd-text-gray-500 bbjd-uppercase">Email</th>
                                <th class="bbjd-px-3 bbjd-py-2 bbjd-text-left bbjd-text-xs bbjd-font-medium bbjd-text-gray-500 bbjd-uppercase">Method</th>
                                <th class="bbjd-px-3 bbjd-py-2 bbjd-text-left bbjd-text-xs bbjd-font-medium bbjd-text-gray-500 bbjd-uppercase">reCAPTCHA</th>
                                <th class="bbjd-px-3 bbjd-py-2 bbjd-text-left bbjd-text-xs bbjd-font-medium bbjd-text-gray-500 bbjd-uppercase">Status</th>
                                <th class="bbjd-px-3 bbjd-py-2 bbjd-text-left bbjd-text-xs bbjd-font-medium bbjd-text-gray-500 bbjd-uppercase">IP</th>
                            </tr>
                        </thead>
                        <tbody class="bbjd-bg-white bbjd-divide-y bbjd-divide-gray-200">
                            <?php foreach ($logs as $log): ?>
                            <tr>
                                <td class="bbjd-px-3 bbjd-py-2 bbjd-text-gray-500 bbjd-whitespace-nowrap">
                                    <?php echo esc_html($log['timestamp'] ?? '-'); ?>
                                </td>
                                <td class="bbjd-px-3 bbjd-py-2 bbjd-text-gray-900">
                                    <?php echo esc_html($log['username'] ?? '-'); ?>
                                </td>
                                <td class="bbjd-px-3 bbjd-py-2 bbjd-text-gray-900">
                                    <?php echo esc_html($log['email'] ?? '-'); ?>
                                </td>
                                <td class="bbjd-px-3 bbjd-py-2 bbjd-text-gray-500">
                                    <?php echo esc_html($log['method'] ?? '-'); ?>
                                </td>
                                <td class="bbjd-px-3 bbjd-py-2">
                                    <?php
                                    $score = $log['recaptcha_score'] ?? null;
                                    if ($score === null) {
                                        echo '<span class="bbjd-text-gray-400">N/A</span>';
                                    } else {
                                        $scoreColor = $score >= 0.7 ? 'bbjd-text-green-600' : ($score >= 0.5 ? 'bbjd-text-yellow-600' : 'bbjd-text-red-600');
                                        echo '<span class="' . $scoreColor . ' bbjd-font-mono">' . number_format($score, 2) . '</span>';
                                    }
                                    ?>
                                </td>
                                <td class="bbjd-px-3 bbjd-py-2">
                                    <?php
                                    $status = $log['status'] ?? 'unknown';
                                    $statusClass = $status === 'success'
                                        ? 'bbjd-bg-green-100 bbjd-text-green-800'
                                        : 'bbjd-bg-red-100 bbjd-text-red-800';
                                    ?>
                                    <span class="bbjd-inline-flex bbjd-items-center bbjd-px-2 bbjd-py-0.5 bbjd-rounded-full bbjd-text-xs bbjd-font-medium <?php echo $statusClass; ?>">
                                        <?php echo esc_html($status); ?>
                                    </span>
                                    <?php if (!empty($log['reason'])): ?>
                                        <span class="bbjd-text-xs bbjd-text-gray-400 bbjd-ml-1">(<?php echo esc_html($log['reason']); ?>)</span>
                                    <?php endif; ?>
                                </td>
                                <td class="bbjd-px-3 bbjd-py-2 bbjd-text-gray-500 bbjd-font-mono bbjd-text-xs">
                                    <?php echo esc_html($log['ip'] ?? '-'); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
