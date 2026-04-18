<?php
/**
 * Auth modal: trigger helper, modal loader, reset-link handler.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Emit a button that opens the auth modal.
 * Returns empty output when the user is already logged in.
 *
 * @param string $label  Visible label ("Log In", "Sign up", "Log in to comment", …).
 * @param string $view   Initial view: login | register | forgot.
 * @param string $class  Extra CSS classes to apply to the button.
 */
function bbj_v2_auth_trigger(string $label, string $view = 'login', string $class = ''): void
{
    if (is_user_logged_in()) {
        return;
    }
    $allowed = ['login', 'register', 'forgot'];
    if (!in_array($view, $allowed, true)) {
        $view = 'login';
    }
    printf(
        '<button type="button" class="%s" data-bbj-auth-open="%s">%s</button>',
        esc_attr(trim($class)),
        esc_attr($view),
        esc_html($label)
    );
}

/**
 * Detect a password-reset landing URL and stash its key/login on the body
 * as a class so the modal JS can auto-open to view-reset.
 *
 * Reset emails link to: https://site.com/?bbj_rp=1&key=...&login=...
 */
add_filter('body_class', function (array $classes): array {
    if (!empty($_GET['bbj_rp']) && !empty($_GET['key']) && !empty($_GET['login'])) {
        $classes[] = 'bbj-reset-password-landing';
    }
    return $classes;
});

/**
 * Emit the reset key/login as JSON so the modal JS can read them on
 * first render. Runs inside <body>, before wp_footer().
 */
add_action('wp_body_open', function (): void {
    if (empty($_GET['bbj_rp']) || empty($_GET['key']) || empty($_GET['login'])) {
        return;
    }
    $payload = wp_json_encode([
        'key'   => sanitize_text_field(wp_unslash($_GET['key'])),
        'login' => sanitize_text_field(wp_unslash($_GET['login'])),
    ]);
    echo '<script id="bbj-reset-payload" type="application/json">' . $payload . '</script>';
});

/**
 * Expose the bbjd Google OAuth client ID as a data attribute on <html>
 * so auth-google.js can read it without another network request.
 *
 * Filter registered at file load so it's present before header.php renders
 * (language_attributes() on <html> fires before wp_head / wp_enqueue_scripts).
 */
add_filter('language_attributes', function (string $output): string {
    if (is_user_logged_in()) {
        return $output;
    }
    $client_id = defined('BBJD_GOOGLE_CLIENT_ID') ? (string) BBJD_GOOGLE_CLIENT_ID : (string) get_option('bbjd_google_client_id', '');
    if ($client_id === '') {
        return $output;
    }
    return $output . ' data-bbj-google-client="' . esc_attr($client_id) . '"';
});
