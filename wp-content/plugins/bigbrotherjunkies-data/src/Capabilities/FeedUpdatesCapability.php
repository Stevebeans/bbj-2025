<?php

namespace BigBrotherJunkies\Data\Capabilities;

/**
 * Registers the `bbj_v2_edit_feed_updates` capability and seeds it on
 * the administrator role. The future native permissions grid in
 * Settings will grant/revoke this cap to other roles via
 * $role->add_cap() / $role->remove_cap(); this class does not need
 * to change when that lands.
 *
 * Uses a versioned one-shot (bbj_v2_caps_version_feed_updates option) so the
 * seeder runs on normal page loads after a deploy, not just on
 * plugin-activation click.
 */
class FeedUpdatesCapability
{
    public const CAP = 'bbj_v2_edit_feed_updates';
    public const VERSION_OPTION = 'bbj_v2_caps_version_feed_updates';
    public const CURRENT_VERSION = 1;

    /**
     * Hook into WordPress.
     */
    public function init(): void
    {
        add_action('init', [$this, 'maybeSeed'], 20);
    }

    /**
     * Seed the cap if not already seeded at the current version.
     * Idempotent — safe to run on every request.
     */
    public function maybeSeed(): void
    {
        $seeded = (int) get_option(self::VERSION_OPTION, 0);
        if ($seeded >= self::CURRENT_VERSION) {
            return;
        }

        $admin = get_role('administrator');
        if ($admin) {
            if (!$admin->has_cap(self::CAP)) {
                $admin->add_cap(self::CAP);
            }
            update_option(self::VERSION_OPTION, self::CURRENT_VERSION, false);
        }
    }
}
