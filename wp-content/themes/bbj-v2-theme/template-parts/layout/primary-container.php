<?php
/**
 * Primary container wrapper used across templates.
 * Opens when $args['state'] === 'open' (or absent), closes when 'close'.
 *
 * Usage:
 *   get_template_part('template-parts/layout/primary-container', null, ['state' => 'open']);
 *   ...content...
 *   get_template_part('template-parts/layout/primary-container', null, ['state' => 'close']);
 */

if (!defined('ABSPATH')) {
    exit;
}

$state = $args['state'] ?? 'open';

if ($state === 'close') : ?>
        </div>
    </div>
<?php else : ?>
    <div class="v2-primary-container">
        <div class="v2-primary-container-inner p-4 md:p-6">
<?php endif;
