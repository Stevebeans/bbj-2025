<?php
namespace BigBrotherJunkies\Data\BrandSafety;

/**
 * Shared quarantine-directory guard.
 *
 * Backfill's dry-run report writer and MediaUploader's local-media mover
 * both write into wp-content/bbj-quarantine, and neither may ever leave it
 * web-accessible. Guard files (.htaccess denying all + a silencing
 * index.php) are written whenever they're missing — not only when the
 * directory itself is freshly created — so it doesn't matter which caller
 * happens to create the directory first; the other caller's own guard-write
 * used to be skipped by an `if (!file_exists($dir))` check that only ever
 * fired for whichever caller ran first.
 */
class QuarantineGuard
{
    /** Ensures the base quarantine dir exists and is guarded. Returns its path. */
    public static function ensureBaseDir(): string
    {
        $dir = WP_CONTENT_DIR . '/bbj-quarantine';
        self::ensure($dir);
        return $dir;
    }

    /**
     * Ensures a subdirectory under the quarantine dir exists and is guarded
     * (belt and suspenders — the parent .htaccess already denies everything
     * under it, but a missing subdir guard is cheap insurance). Returns its
     * path.
     */
    public static function ensureSubDir(string $name): string
    {
        $dir = self::ensureBaseDir() . '/' . trim($name, '/');
        self::ensure($dir);
        return $dir;
    }

    private static function ensure(string $dir): void
    {
        wp_mkdir_p($dir);

        $htaccess = $dir . '/.htaccess';
        if (!file_exists($htaccess)) {
            file_put_contents($htaccess, "Deny from all\n");
        }

        $index = $dir . '/index.php';
        if (!file_exists($index)) {
            file_put_contents($index, '<?php // Silence is golden');
        }
    }
}
