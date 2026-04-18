<?php
/**
 * Auth modal — shared chrome + slots for each view.
 * Hidden by default; JS adds .is-open on the .bbj-modal root.
 * Only rendered for logged-out users (saves ~2KB for authed users).
 */

if (!defined('ABSPATH')) {
    exit;
}
if (is_user_logged_in()) {
    return;
}
?>
<div id="bbj-auth-modal"
     class="bbj-modal"
     role="dialog"
     aria-modal="true"
     aria-hidden="true"
     aria-labelledby="bbj-auth-modal-title">
    <div class="bbj-modal-dialog" role="document">
        <div class="bbj-modal-header">
            <h2 id="bbj-auth-modal-title" class="bbj-modal-title" data-bbj-auth-title>
                <?php esc_html_e('Log In', 'bbj-v2-theme'); ?>
            </h2>
            <button type="button" class="bbj-modal-close" data-bbj-auth-close aria-label="<?php esc_attr_e('Close', 'bbj-v2-theme'); ?>">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="bbj-modal-body">
            <?php get_template_part('template-parts/auth/view-login'); ?>
            <?php get_template_part('template-parts/auth/view-register'); ?>
            <?php get_template_part('template-parts/auth/view-link'); ?>
            <?php get_template_part('template-parts/auth/view-forgot'); ?>
            <?php get_template_part('template-parts/auth/view-reset'); ?>
        </div>
    </div>
</div>
