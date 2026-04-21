<?php
/**
 * Admin shell — Season Info tab body.
 * Receives $args['season'] — season row object.
 * Receives $args['season_id'] — int.
 *
 * Form shipping in Task 6. This file exists now so the tabs partial can include it.
 */

if (!defined('ABSPATH')) {
    exit;
}

get_template_part('template-parts/admin/partials/seasons-edit-stub', null, [
    'label' => 'Season Info (work-in-progress)',
]);
