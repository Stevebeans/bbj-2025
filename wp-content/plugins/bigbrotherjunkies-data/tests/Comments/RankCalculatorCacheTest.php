<?php

// Global-namespace WP stubs (no WP loaded in tests). Guarded so other suites can coexist.
namespace {
if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}
 if (!function_exists('get_user_meta')) {
    function get_user_meta($userId, $key = '', $single = false) { return $GLOBALS['__bbj_user_meta'][$userId][$key] ?? ''; }
}
if (!function_exists('update_user_meta')) {
    function update_user_meta($userId, $key, $value) { $GLOBALS['__bbj_user_meta'][$userId][$key] = $value; return true; }
}
if (!function_exists('delete_user_meta')) {
    function delete_user_meta($userId, $key) { unset($GLOBALS['__bbj_user_meta'][$userId][$key]); return true; }
}
if (!function_exists('get_userdata')) {
    function get_userdata($userId) { $u = new \WP_User(); $u->ID = (int) $userId; $u->roles = ['subscriber']; return $u; }
}
if (!function_exists('user_can')) {
    function user_can($user, $cap) { return false; }
}
if (!function_exists('wp_cache_get')) {
    function wp_cache_get($key, $group = '') { return $GLOBALS['__bbj_cache'][$group][$key] ?? false; }
}
if (!function_exists('wp_cache_set')) {
    function wp_cache_set($key, $data, $group = '', $expire = 0) {
        $GLOBALS['__bbj_cache'][$group][$key] = $data;
        $GLOBALS['__bbj_cache_ttl'][$group][$key] = $expire;
        return true;
    }
}
if (!function_exists('wp_cache_delete')) {
    function wp_cache_delete($key, $group = '') { unset($GLOBALS['__bbj_cache'][$group][$key]); return true; }
}
}

namespace BigBrotherJunkies\Data\Tests\Comments {

use BigBrotherJunkies\Data\Comments\RankCalculator;
use PHPUnit\Framework\TestCase;

/**
 * Fake $wpdb that answers the two getUserStats queries and counts how often
 * the database was actually asked.
 */
class CountingWpdb
{
    public string $comments = 'wp_comments';
    public string $prefix = 'wp_';
    public int $statsQueries = 0;
    public int $totalComments = 12;
    public int $upvotes = 30;

    public function prepare(string $sql, ...$args): string { return $sql; }
    public function get_var(string $sql) { $this->statsQueries++; return $this->totalComments; }
    public function get_row(string $sql, $output = null): array
    {
        $this->statsQueries++;
        return ['upvotes' => $this->upvotes, 'downvotes' => 0];
    }
}

class RankCalculatorCacheTest extends TestCase
{
    private CountingWpdb $db;

    protected function setUp(): void
    {
        global $wpdb;
        $wpdb = $this->db = new CountingWpdb();
        $GLOBALS['__bbj_cache'] = [];
        $GLOBALS['__bbj_cache_ttl'] = [];
        $GLOBALS['__bbj_user_meta'] = [];
        RankCalculator::resetMemo();
    }

    public function test_same_user_in_one_request_hits_the_database_once(): void
    {
        RankCalculator::calculateRank(606);
        RankCalculator::calculateRank(606);
        RankCalculator::calculateRank(606);

        // getUserStats = 2 queries (count + votes). Three calls must not triple it.
        $this->assertSame(2, $this->db->statsQueries);
    }

    public function test_rank_is_served_from_object_cache_across_requests(): void
    {
        $first = RankCalculator::calculateRank(606);
        $this->assertSame(2, $this->db->statsQueries);
        $this->assertSame(600, $GLOBALS['__bbj_cache_ttl']['bbjd-rank']['rank_606']);

        // New request: memo is gone, persistent cache remains.
        RankCalculator::resetMemo();
        $second = RankCalculator::calculateRank(606);

        $this->assertSame(2, $this->db->statsQueries, 'cache hit must not touch the DB');
        $this->assertSame($first, $second);
    }

    public function test_update_user_rank_recomputes_and_reprimes_cache(): void
    {
        $stale = RankCalculator::calculateRank(606);
        $this->assertSame('bronze', $stale['key']);

        // Member crosses into silver; a vote/comment write calls updateUserRank.
        $this->db->totalComments = 80;
        $this->db->upvotes = 200;
        $fresh = RankCalculator::updateUserRank(606);
        $this->assertSame('silver', $fresh['key']);

        // Later request sees the new rank without recomputing.
        RankCalculator::resetMemo();
        $queriesBefore = $this->db->statsQueries;
        $this->assertSame('silver', RankCalculator::calculateRank(606)['key']);
        $this->assertSame($queriesBefore, $this->db->statsQueries);
    }

    public function test_special_rank_change_busts_cache(): void
    {
        $this->assertSame('bronze', RankCalculator::calculateRank(606)['key']);

        RankCalculator::setSpecialRank(606, 'vip');
        RankCalculator::resetMemo();

        $this->assertSame('vip', RankCalculator::calculateRank(606)['key']);
    }
}

}
