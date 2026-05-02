<?php

namespace BigBrotherJunkies\Data\Admin\Pages;

/**
 * Admin page for importing seasons and players
 */
class ImportPage
{
    public const MENU_SLUG = 'bbj-import';

    /**
     * Render the import page
     */
    public function render(): void
    {
        $activeTab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'seasons';

        ?>
        <div class="wrap">
            <h1>BBJ Data Import</h1>

            <nav class="nav-tab-wrapper">
                <a href="?page=<?php echo self::MENU_SLUG; ?>&tab=seasons"
                   class="nav-tab <?php echo $activeTab === 'seasons' ? 'nav-tab-active' : ''; ?>">
                    Seasons
                </a>
                <a href="?page=<?php echo self::MENU_SLUG; ?>&tab=players"
                   class="nav-tab <?php echo $activeTab === 'players' ? 'nav-tab-active' : ''; ?>">
                    Players
                </a>
            </nav>

            <div class="tab-content" style="margin-top: 20px;">
                <?php
                if ($activeTab === 'players') {
                    $this->renderPlayersTab();
                } else {
                    $this->renderSeasonsTab();
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render the seasons import tab
     */
    private function renderSeasonsTab(): void
    {
        $message = '';
        $messageType = '';

        if (isset($_POST['import_seasons']) && check_admin_referer('bbj_import_seasons')) {
            $result = $this->importSeasonsFromCsv();
            $message = $result['message'];
            $messageType = $result['success'] ? 'success' : 'error';
        }

        global $wpdb;
        $table = $wpdb->prefix . 'bbj_seasons';
        $existingSeasons = $wpdb->get_results(
            "SELECT id, season_number, full_name, abbreviation FROM {$table} ORDER BY CAST(season_number AS UNSIGNED)",
            ARRAY_A
        );

        if ($message): ?>
            <div class="notice notice-<?php echo esc_attr($messageType); ?> is-dismissible">
                <p><?php echo wp_kses_post($message); ?></p>
            </div>
        <?php endif; ?>

        <div class="card" style="max-width: 800px; padding: 20px;">
            <h2>Import Seasons</h2>
            <p>This will import seasons from the CSV file. Existing seasons will be skipped.</p>

            <h3>Existing Seasons (<?php echo count($existingSeasons); ?>)</h3>
            <table class="widefat striped" style="margin-bottom: 20px;">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Number</th>
                        <th>Name</th>
                        <th>Abbreviation</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($existingSeasons)): ?>
                        <tr><td colspan="4">No seasons found</td></tr>
                    <?php else: ?>
                        <?php foreach ($existingSeasons as $season): ?>
                            <tr>
                                <td><?php echo esc_html($season['id']); ?></td>
                                <td><?php echo esc_html($season['season_number']); ?></td>
                                <td><?php echo esc_html($season['full_name']); ?></td>
                                <td><?php echo esc_html($season['abbreviation']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php
            $csvSeasons = $this->readSeasonsCsv();
            $existingNumbers = array_column($existingSeasons, 'season_number');
            $toImport = array_filter($csvSeasons, function($s) use ($existingNumbers) {
                return !in_array($s['season_number'], $existingNumbers);
            });
            ?>

            <h3>Seasons to Import</h3>
            <?php if (empty($toImport)): ?>
                <p><strong>All seasons already imported!</strong></p>
            <?php else: ?>
                <table class="widefat striped" style="margin-bottom: 20px;">
                    <thead>
                        <tr>
                            <th>Number</th>
                            <th>Name</th>
                            <th>Abbreviation</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($toImport as $season): ?>
                            <tr>
                                <td><?php echo esc_html($season['season_number']); ?></td>
                                <td><?php echo esc_html($season['full_name']); ?></td>
                                <td><?php echo esc_html($season['abbreviation']); ?></td>
                                <td><?php echo esc_html($season['start_date']); ?></td>
                                <td><?php echo esc_html($season['end_date']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <form method="post">
                    <?php wp_nonce_field('bbj_import_seasons'); ?>
                    <button type="submit" name="import_seasons" class="button button-primary">
                        Import <?php echo count($toImport); ?> Seasons
                    </button>
                </form>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render the players import tab
     */
    private function renderPlayersTab(): void
    {
        $message = '';
        $messageType = '';

        if (isset($_POST['import_players']) && check_admin_referer('bbj_import_players')) {
            $result = $this->importPlayersFromCsv();
            $message = $result['message'];
            $messageType = $result['success'] ? 'success' : 'error';
        }

        if (isset($_POST['update_stats']) && check_admin_referer('bbj_update_stats')) {
            $result = $this->updateStatsFromCsv();
            $message = $result['message'];
            $messageType = $result['success'] ? 'success' : 'error';
        }

        global $wpdb;
        $playersTable = $wpdb->prefix . 'bbj_players';
        $linkTable = $wpdb->prefix . 'bbj_v2_player_season';
        $seasonsTable = $wpdb->prefix . 'bbj_seasons';

        // Get counts
        $playerCount = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$playersTable}");
        $linkCount = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$linkTable}");

        // Get players from CSV
        $csvPlayers = $this->readPlayersCsv();
        $csvPlayerCount = count($csvPlayers);

        // Get unique players in CSV (by name)
        $uniqueCsvPlayers = [];
        foreach ($csvPlayers as $p) {
            $key = strtolower($p['first_name'] . '|' . $p['last_name']);
            if (!isset($uniqueCsvPlayers[$key])) {
                $uniqueCsvPlayers[$key] = $p;
            }
        }

        // Get existing players (for matching)
        $existingPlayers = $wpdb->get_results(
            "SELECT id, first_name, last_name FROM {$playersTable}",
            ARRAY_A
        );
        $existingPlayerKeys = [];
        foreach ($existingPlayers as $p) {
            $key = strtolower($p['first_name'] . '|' . $p['last_name']);
            $existingPlayerKeys[$key] = $p['id'];
        }

        // Calculate new vs existing
        $newPlayers = 0;
        $existingMatches = 0;
        foreach ($uniqueCsvPlayers as $key => $p) {
            if (isset($existingPlayerKeys[$key])) {
                $existingMatches++;
            } else {
                $newPlayers++;
            }
        }

        // Get season links from CSV
        $seasonLinks = count($csvPlayers); // Each row is a player-season link

        // Get existing season IDs
        $existingSeasons = $wpdb->get_results(
            "SELECT id, season_number FROM {$seasonsTable}",
            ARRAY_A
        );
        $seasonMap = [];
        foreach ($existingSeasons as $s) {
            $seasonMap[$s['season_number']] = $s['id'];
        }

        if ($message): ?>
            <div class="notice notice-<?php echo esc_attr($messageType); ?> is-dismissible">
                <p><?php echo wp_kses_post($message); ?></p>
            </div>
        <?php endif; ?>

        <div class="card" style="max-width: 900px; padding: 20px;">
            <h2>Import Players</h2>
            <p>This will import players and link them to seasons. Players are matched by <strong>first name + last name</strong>.</p>
            <p><strong>Returnees:</strong> If a player already exists, they will be linked to additional seasons without creating duplicates.</p>

            <h3>Current Database</h3>
            <table class="widefat striped" style="margin-bottom: 20px; max-width: 400px;">
                <tr>
                    <td>Players in database:</td>
                    <td><strong><?php echo $playerCount; ?></strong></td>
                </tr>
                <tr>
                    <td>Player-season links:</td>
                    <td><strong><?php echo $linkCount; ?></strong></td>
                </tr>
                <tr>
                    <td>Seasons available:</td>
                    <td><strong><?php echo count($existingSeasons); ?></strong></td>
                </tr>
            </table>

            <h3>CSV Data Summary</h3>
            <table class="widefat striped" style="margin-bottom: 20px; max-width: 400px;">
                <tr>
                    <td>Total rows in CSV:</td>
                    <td><strong><?php echo $csvPlayerCount; ?></strong></td>
                </tr>
                <tr>
                    <td>Unique players:</td>
                    <td><strong><?php echo count($uniqueCsvPlayers); ?></strong></td>
                </tr>
                <tr>
                    <td style="color: green;">New players to create:</td>
                    <td><strong style="color: green;"><?php echo $newPlayers; ?></strong></td>
                </tr>
                <tr>
                    <td style="color: blue;">Existing players (returnees):</td>
                    <td><strong style="color: blue;"><?php echo $existingMatches; ?></strong></td>
                </tr>
            </table>

            <?php if (empty($existingSeasons)): ?>
                <div class="notice notice-warning" style="margin: 20px 0;">
                    <p><strong>Warning:</strong> No seasons found in database. Please import seasons first!</p>
                </div>
            <?php elseif ($csvPlayerCount === 0): ?>
                <div class="notice notice-warning" style="margin: 20px 0;">
                    <p><strong>Warning:</strong> Could not read players CSV file.</p>
                </div>
            <?php else: ?>
                <h3>Preview (First 20 rows)</h3>
                <div style="overflow-x: auto;">
                    <table class="widefat striped" style="margin-bottom: 20px; font-size: 12px;">
                        <thead>
                            <tr>
                                <th>Season</th>
                                <th>Name</th>
                                <th>DOB</th>
                                <th>Gender</th>
                                <th>HoH</th>
                                <th>PoV</th>
                                <th>Nom</th>
                                <th>Votes</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $preview = array_slice($csvPlayers, 0, 20);
                            foreach ($preview as $p):
                                $key = strtolower($p['first_name'] . '|' . $p['last_name']);
                                $isExisting = isset($existingPlayerKeys[$key]);
                                $hasSeasonId = isset($seasonMap[$p['season_number']]);
                            ?>
                                <tr>
                                    <td>BB<?php echo esc_html($p['season_number']); ?></td>
                                    <td><?php echo esc_html($p['first_name'] . ' ' . $p['last_name']); ?></td>
                                    <td><?php echo esc_html($p['date_of_birth']); ?></td>
                                    <td><?php echo esc_html($p['player_gender']); ?></td>
                                    <td><?php echo esc_html($p['total_hoh']); ?></td>
                                    <td><?php echo esc_html($p['total_pov']); ?></td>
                                    <td><?php echo esc_html($p['total_nom']); ?></td>
                                    <td><?php echo esc_html($p['votes_received']); ?></td>
                                    <td>
                                        <?php if (!$hasSeasonId): ?>
                                            <span style="color: red;">No season</span>
                                        <?php elseif ($isExisting): ?>
                                            <span style="color: blue;">Existing</span>
                                        <?php else: ?>
                                            <span style="color: green;">New</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <form method="post">
                    <?php wp_nonce_field('bbj_import_players'); ?>
                    <button type="submit" name="import_players" class="button button-primary button-large">
                        Import Players (<?php echo $csvPlayerCount; ?> rows)
                    </button>
                    <p class="description" style="margin-top: 10px;">
                        This will create <?php echo $newPlayers; ?> new players and <?php echo $csvPlayerCount; ?> season links.
                    </p>
                </form>
            <?php endif; ?>
        </div>

        <div class="card" style="max-width: 900px; padding: 20px; margin-top: 20px;">
            <h2>Update Stats from Master CSV</h2>
            <p>Updates <strong>season stats</strong> (HoH, PoV, Nom, misc, votes, finish place, evicted date) and <strong>player data</strong> (DOB, gender, occupation, nickname) from <code>all_players_master.csv</code>.</p>
            <p>Only updates existing player-season links — never creates new rows. Empty CSV values are skipped (won't overwrite existing data with blanks).</p>

            <?php
            $masterCsv = $this->readMasterCsv();
            if (empty($masterCsv)): ?>
                <div class="notice notice-warning" style="margin: 10px 0;">
                    <p><strong>Warning:</strong> Could not read <code>all_players_master.csv</code>.</p>
                </div>
            <?php else: ?>
                <p>Master CSV rows: <strong><?php echo count($masterCsv); ?></strong> | Existing player-season links: <strong><?php echo $linkCount; ?></strong></p>

                <form method="post">
                    <?php wp_nonce_field('bbj_update_stats'); ?>
                    <button type="submit" name="update_stats" class="button button-secondary button-large">
                        Update Stats from Master CSV
                    </button>
                </form>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Read seasons from CSV file
     */
    private function readSeasonsCsv(): array
    {
        $csvPath = $this->getCsvPath('big_brother_us_seasons.csv');

        if (!$csvPath || !file_exists($csvPath)) {
            return [];
        }

        $seasons = [];
        $handle = fopen($csvPath, 'r');
        if ($handle === false) return [];

        fgetcsv($handle); // Skip header

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) >= 5) {
                $seasons[] = [
                    'start_date' => $row[0] ?? '',
                    'end_date' => $row[1] ?? '',
                    'season_number' => $row[2] ?? '',
                    'full_name' => $row[3] ?? '',
                    'abbreviation' => $row[4] ?? '',
                    'winner' => $row[5] ?? '',
                    'runner_up' => $row[6] ?? '',
                    'afp' => $row[7] ?? '',
                ];
            }
        }

        fclose($handle);
        return $seasons;
    }

    /**
     * Read players from CSV file (players.md format)
     * Format: season_number,first_name,last_name,hometown_city,hometown_state,finish_place,evicted_date
     */
    private function readPlayersCsv(): array
    {
        $csvPath = $this->getCsvPath('players.md');

        if (!$csvPath || !file_exists($csvPath)) {
            return [];
        }

        // Load geo lookup
        $geoLookup = $this->loadGeoLookup();

        $players = [];
        $handle = fopen($csvPath, 'r');
        if ($handle === false) return [];

        while (($row = fgetcsv($handle)) !== false) {
            // Skip header rows (there may be multiple from ChatGPT chunks)
            if ($row[0] === 'season_number') {
                continue;
            }

            if (count($row) >= 5) {
                $city = trim($row[3] ?? '');
                $state = trim($row[4] ?? '');
                $geoKey = $city . '|' . $state;
                $geo = $geoLookup[$geoKey] ?? ['lat' => null, 'lng' => null];

                $players[] = [
                    'season_number' => $row[0] ?? '',
                    'first_name' => trim($row[1] ?? ''),
                    'last_name' => trim($row[2] ?? ''),
                    'hometown_city' => $city,
                    'hometown_state' => $state,
                    'hometown_lat' => $geo['lat'],
                    'hometown_lng' => $geo['lng'],
                    'finish_place' => $row[5] ?? '',
                    'evicted_date' => $row[6] ?? '',
                    // These will be empty for now - can be enriched later
                    'date_of_birth' => '',
                    'player_gender' => '',
                    'occupation' => '',
                    'facebook' => '',
                    'instagram' => '',
                    'twitter' => '',
                    'tiktok' => '',
                    'total_hoh' => 0,
                    'total_pov' => 0,
                    'total_nom' => 0,
                    'total_misc' => 0,
                    'votes_received' => 0,
                    'nickname' => '',
                ];
            }
        }

        fclose($handle);
        return $players;
    }

    /**
     * Load geo coordinates lookup from cities_with_coords.csv
     */
    private function loadGeoLookup(): array
    {
        $csvPath = $this->getCsvPath('cities_with_coords.csv');

        if (!$csvPath || !file_exists($csvPath)) {
            return [];
        }

        $lookup = [];
        $handle = fopen($csvPath, 'r');
        if ($handle === false) return [];

        fgetcsv($handle); // Skip header

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) >= 4) {
                $key = trim($row[0]) . '|' . trim($row[1]);
                $lookup[$key] = [
                    'lat' => (float) $row[2],
                    'lng' => (float) $row[3],
                ];
            }
        }

        fclose($handle);
        return $lookup;
    }

    /**
     * Get CSV file path
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
     * Import seasons from CSV
     */
    private function importSeasonsFromCsv(): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'bbj_seasons';

        $csvSeasons = $this->readSeasonsCsv();
        if (empty($csvSeasons)) {
            return ['success' => false, 'message' => 'Could not read CSV file.'];
        }

        $existingNumbers = $wpdb->get_col("SELECT season_number FROM {$table}");

        $imported = 0;
        $skipped = 0;
        $failed = 0;
        $errors = [];

        foreach ($csvSeasons as $season) {
            if (in_array($season['season_number'], $existingNumbers)) {
                $skipped++;
                continue;
            }

            $slug = sanitize_title($season['abbreviation']);
            $postId = wp_insert_post([
                'post_title' => $season['full_name'],
                'post_name' => $slug,
                'post_type' => 'bigbrother-seasons',
                'post_status' => 'publish',
                'post_content' => '',
            ], true);

            if (is_wp_error($postId)) {
                $failed++;
                $errors[] = "Season {$season['season_number']}: " . $postId->get_error_message();
                continue;
            }

            $inserted = $wpdb->insert(
                $table,
                [
                    'id' => $postId,
                    'post_id' => $postId,
                    'season_number' => $season['season_number'],
                    'full_name' => $season['full_name'],
                    'abbreviation' => $season['abbreviation'],
                    'start_date' => $season['start_date'] ?: null,
                    'end_date' => $season['end_date'] ?: null,
                ],
                ['%d', '%d', '%s', '%s', '%s', '%s', '%s']
            );

            if (!$inserted) {
                wp_delete_post($postId, true);
                $failed++;
                $errors[] = "Season {$season['season_number']}: " . $wpdb->last_error;
                continue;
            }

            $imported++;
        }

        $message = "Import complete: <strong>{$imported} imported</strong>, {$skipped} skipped, {$failed} failed.";
        if (!empty($errors)) {
            $message .= '<br><br>Errors:<br>' . implode('<br>', $errors);
        }

        return ['success' => $failed === 0, 'message' => $message];
    }

    /**
     * Read master CSV with full stats (all_players_master.csv)
     */
    private function readMasterCsv(): array
    {
        $csvPath = $this->getCsvPath('all_players_master.csv');

        if (!$csvPath || !file_exists($csvPath)) {
            return [];
        }

        $players = [];
        $handle = fopen($csvPath, 'r');
        if ($handle === false) return [];

        $header = fgetcsv($handle);

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 18) continue;

            $players[] = [
                'season_number'  => trim($row[0]),
                'first_name'     => trim($row[1]),
                'last_name'      => trim($row[2]),
                'date_of_birth'  => trim($row[3]),
                'player_gender'  => trim($row[4]),
                'occupation'     => trim($row[5]),
                'facebook'       => trim($row[6]),
                'instagram'      => trim($row[7]),
                'twitter'        => trim($row[8]),
                'tiktok'         => trim($row[9]),
                'total_hoh'      => trim($row[10]),
                'total_pov'      => trim($row[11]),
                'total_nom'      => trim($row[12]),
                'total_misc'     => trim($row[13]),
                'votes_received' => trim($row[14]),
                'nickname'       => trim($row[15]),
                'finish_place'   => trim($row[16]),
                'evicted_date'   => trim($row[17]),
            ];
        }

        fclose($handle);
        return $players;
    }

    /**
     * Update player stats and player data from master CSV
     */
    private function updateStatsFromCsv(): array
    {
        global $wpdb;
        $playersTable = $wpdb->prefix . 'bbj_players';
        $linkTable = $wpdb->prefix . 'bbj_v2_player_season';
        $seasonsTable = $wpdb->prefix . 'bbj_seasons';

        $masterRows = $this->readMasterCsv();
        if (empty($masterRows)) {
            return ['success' => false, 'message' => 'Could not read master CSV file.'];
        }

        // Build season map: season_number => season_id
        $seasons = $wpdb->get_results("SELECT id, season_number FROM {$seasonsTable}", ARRAY_A);
        $seasonMap = [];
        foreach ($seasons as $s) {
            $seasonMap[$s['season_number']] = (int) $s['id'];
        }

        // Build player map: first_name|last_name => player_id
        $existingPlayers = $wpdb->get_results("SELECT id, first_name, last_name FROM {$playersTable}", ARRAY_A);
        $playerMap = [];
        foreach ($existingPlayers as $p) {
            $key = strtolower(trim($p['first_name']) . '|' . trim($p['last_name']));
            $playerMap[$key] = (int) $p['id'];
        }

        $statsUpdated = 0;
        $playersUpdated = 0;
        $skipped = 0;
        $errors = [];
        $playersDone = []; // Track which player IDs we've already updated player-level data for

        foreach ($masterRows as $row) {
            $seasonId = $seasonMap[$row['season_number']] ?? null;
            $playerKey = strtolower(trim($row['first_name']) . '|' . trim($row['last_name']));
            $playerId = $playerMap[$playerKey] ?? null;

            if (!$seasonId || !$playerId) {
                $skipped++;
                continue;
            }

            // UPDATE season stats in wp_bbj_v2_player_season
            $statsData = [
                'bbj_total_hoh'      => (int) $row['total_hoh'],
                'bbj_total_pov'      => (int) $row['total_pov'],
                'bbj_total_nom'      => (int) $row['total_nom'],
                'bbj_total_misc'     => (int) $row['total_misc'],
                'bbj_votes_received' => (int) $row['votes_received'],
            ];
            $statsFormats = ['%d', '%d', '%d', '%d', '%d'];

            if (!empty($row['finish_place'])) {
                $statsData['finish_place'] = (int) $row['finish_place'];
                $statsFormats[] = '%d';
            }

            if (!empty($row['evicted_date'])) {
                $statsData['bbj_evicted_date'] = $row['evicted_date'];
                $statsFormats[] = '%s';
            }

            $updated = $wpdb->update(
                $linkTable,
                $statsData,
                ['bbj_player' => $playerId, 'bbj_season' => $seasonId],
                $statsFormats,
                ['%d', '%d']
            );

            if ($updated !== false) {
                $statsUpdated++;
            } else {
                $errors[] = "{$row['first_name']} {$row['last_name']} BB{$row['season_number']}: {$wpdb->last_error}";
            }

            // UPDATE player-level data in wp_bbj_players (once per player)
            if (!isset($playersDone[$playerId])) {
                $playerData = [];
                $playerFormats = [];

                if (!empty($row['date_of_birth'])) {
                    $playerData['date_of_birth'] = $row['date_of_birth'];
                    $playerFormats[] = '%s';
                }
                if (!empty($row['player_gender'])) {
                    $playerData['player_gender'] = $row['player_gender'];
                    $playerFormats[] = '%s';
                }
                if (!empty($row['occupation'])) {
                    $playerData['occupation'] = $row['occupation'];
                    $playerFormats[] = '%s';
                }
                if (!empty($row['nickname'])) {
                    $playerData['official_nickname'] = $row['nickname'];
                    $playerFormats[] = '%s';
                }

                if (!empty($playerData)) {
                    $pUpdated = $wpdb->update(
                        $playersTable,
                        $playerData,
                        ['id' => $playerId],
                        $playerFormats,
                        ['%d']
                    );

                    if ($pUpdated !== false) {
                        $playersUpdated++;
                    } else {
                        $errors[] = "Player data for {$row['first_name']} {$row['last_name']}: {$wpdb->last_error}";
                    }
                }

                $playersDone[$playerId] = true;
            }
        }

        $message = "Stats update complete:<br>";
        $message .= "- <strong>{$statsUpdated}</strong> season-stat rows updated<br>";
        $message .= "- <strong>{$playersUpdated}</strong> players updated (DOB/gender/occupation/nickname)<br>";
        $message .= "- <strong>{$skipped}</strong> rows skipped (no matching player or season)";

        if (!empty($errors)) {
            $first20 = array_slice($errors, 0, 20);
            $message .= '<br><br>' . count($errors) . ' errors (showing first 20):<br>' . implode('<br>', $first20);
        }

        return ['success' => empty($errors), 'message' => $message];
    }

    /**
     * Import players from CSV
     */
    private function importPlayersFromCsv(): array
    {
        global $wpdb;
        $playersTable = $wpdb->prefix . 'bbj_players';
        $linkTable = $wpdb->prefix . 'bbj_v2_player_season';
        $seasonsTable = $wpdb->prefix . 'bbj_seasons';

        $csvPlayers = $this->readPlayersCsv();
        if (empty($csvPlayers)) {
            return ['success' => false, 'message' => 'Could not read CSV file.'];
        }

        // Build season map (season_number => id)
        $seasons = $wpdb->get_results("SELECT id, season_number FROM {$seasonsTable}", ARRAY_A);
        $seasonMap = [];
        foreach ($seasons as $s) {
            $seasonMap[$s['season_number']] = (int) $s['id'];
        }

        // Build existing players map (first_name|last_name => id)
        $existingPlayers = $wpdb->get_results("SELECT id, first_name, last_name FROM {$playersTable}", ARRAY_A);
        $playerMap = [];
        foreach ($existingPlayers as $p) {
            $key = strtolower(trim($p['first_name']) . '|' . trim($p['last_name']));
            $playerMap[$key] = (int) $p['id'];
        }

        // Build existing links set (player_id|season_id)
        $existingLinks = $wpdb->get_results("SELECT bbj_player, bbj_season FROM {$linkTable}", ARRAY_A);
        $linkSet = [];
        foreach ($existingLinks as $l) {
            $linkSet[$l['bbj_player'] . '|' . $l['bbj_season']] = true;
        }

        $playersCreated = 0;
        $linksCreated = 0;
        $linksSkipped = 0;
        $errors = [];

        foreach ($csvPlayers as $row) {
            $seasonId = $seasonMap[$row['season_number']] ?? null;
            if (!$seasonId) {
                $errors[] = "No season found for BB{$row['season_number']}";
                continue;
            }

            $playerKey = strtolower(trim($row['first_name']) . '|' . trim($row['last_name']));
            $playerId = $playerMap[$playerKey] ?? null;

            // Create player if doesn't exist
            if (!$playerId) {
                $fullName = trim($row['first_name'] . ' ' . $row['last_name']);

                // Create WordPress post first
                $postId = wp_insert_post([
                    'post_title' => $fullName,
                    'post_name' => sanitize_title($fullName),
                    'post_type' => 'bigbrother-players',
                    'post_status' => 'publish',
                    'post_content' => '',
                ], true);

                if (is_wp_error($postId)) {
                    $errors[] = "Failed to create post for {$fullName}: " . $postId->get_error_message();
                    continue;
                }

                // Insert into players table with post_id and hometown
                $inserted = $wpdb->insert(
                    $playersTable,
                    [
                        'id' => $postId,
                        'first_name' => $row['first_name'],
                        'last_name' => $row['last_name'],
                        'date_of_birth' => $row['date_of_birth'] ?: null,
                        'player_gender' => $row['player_gender'],
                        'occupation' => $row['occupation'],
                        'facebook' => $row['facebook'],
                        'instagram' => $row['instagram'],
                        'twitter' => $row['twitter'],
                        'tiktok' => $row['tiktok'],
                        'official_nickname' => $row['nickname'] ?: '',
                        'hometown_city' => $row['hometown_city'] ?: null,
                        'hometown_state' => $row['hometown_state'] ?: null,
                        'hometown_lat' => $row['hometown_lat'],
                        'hometown_lng' => $row['hometown_lng'],
                        'post_id' => $postId,
                    ],
                    ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%d']
                );

                if (!$inserted) {
                    // Rollback: delete the post
                    wp_delete_post($postId, true);
                    $errors[] = "Failed to create player: {$fullName} - {$wpdb->last_error}";
                    continue;
                }

                $playerId = $postId;
                $playerMap[$playerKey] = $playerId;
                $playersCreated++;
            }

            // Check if link already exists
            $linkKey = $playerId . '|' . $seasonId;
            if (isset($linkSet[$linkKey])) {
                $linksSkipped++;
                continue;
            }

            // Create player-season link with finish_place and evicted_date
            $finishPlace = !empty($row['finish_place']) ? (int) $row['finish_place'] : null;
            $evictedDate = !empty($row['evicted_date']) ? $row['evicted_date'] : null;

            $linkInserted = $wpdb->insert(
                $linkTable,
                [
                    'bbj_player' => $playerId,
                    'bbj_season' => $seasonId,
                    'bbj_total_hoh' => $row['total_hoh'],
                    'bbj_total_pov' => $row['total_pov'],
                    'bbj_total_nom' => $row['total_nom'],
                    'bbj_total_misc' => $row['total_misc'],
                    'bbj_votes_received' => $row['votes_received'],
                    'finish_place' => $finishPlace,
                    'bbj_evicted_date' => $evictedDate,
                    'current_evicted' => 1, // Mark all imported players as evicted (historical)
                    'post_id' => $playerId, // Player post_id for MetaBox compatibility
                ],
                ['%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%d', '%d']
            );

            if ($linkInserted) {
                $linksCreated++;
                $linkSet[$linkKey] = true;
            } else {
                $errors[] = "Failed to link {$row['first_name']} {$row['last_name']} to BB{$row['season_number']}";
            }
        }

        $message = "Import complete:<br>";
        $message .= "- <strong>{$playersCreated}</strong> new players created<br>";
        $message .= "- <strong>{$linksCreated}</strong> season links created<br>";
        $message .= "- <strong>{$linksSkipped}</strong> links skipped (already exist)";

        if (!empty($errors)) {
            $first20 = array_slice($errors, 0, 20);
            $message .= '<br><br>' . count($errors) . ' errors (showing first 20):<br>' . implode('<br>', $first20);
        }

        return ['success' => empty($errors), 'message' => $message];
    }
}
