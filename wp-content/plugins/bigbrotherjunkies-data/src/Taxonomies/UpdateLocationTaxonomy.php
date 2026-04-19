<?php

namespace BigBrotherJunkies\Data\Taxonomies;

/**
 * Registers the `update_location` taxonomy on the `live-feed-updates` CPT
 * and seeds a set of starter terms on plugin activation.
 */
class UpdateLocationTaxonomy
{
    private const TAXONOMY = 'update_location';
    private const POST_TYPE = 'live-feed-updates';

    private const STARTER_TERMS = [
        'HoH Bathroom', 'HoH Room', 'Backyard', 'Hammock',
        'Kitchen', 'Living Room', 'Have-Not Room', 'Storage',
        'Pergola', 'Bathroom', 'Diary Room',
    ];

    /**
     * Hook into WordPress.
     */
    public function init(): void
    {
        add_action('init', [$this, 'register'], 11);
        register_activation_hook(BBJD_FILE, [$this, 'seedTerms']);
    }

    /**
     * Register the taxonomy.
     */
    public function register(): void
    {
        register_taxonomy(self::TAXONOMY, self::POST_TYPE, [
            'hierarchical'        => false,
            'labels'              => [
                'name'          => 'Update Locations',
                'singular_name' => 'Update Location',
                'menu_name'     => 'Update Locations',
                'add_new_item'  => 'Add New Update Location',
                'search_items'  => 'Search Update Locations',
            ],
            'show_ui'             => true,
            'show_admin_column'   => true,
            'show_in_rest'        => true,
            'show_in_graphql'     => true,
            'graphql_single_name' => 'updateLocation',
            'graphql_plural_name' => 'updateLocations',
            'rewrite'             => ['slug' => 'update-location'],
        ]);
    }

    /**
     * Seed starter terms (idempotent — runs on plugin activation).
     */
    public function seedTerms(): void
    {
        // Ensure the taxonomy is registered before inserting terms,
        // otherwise wp_insert_term() will fail.
        if (!taxonomy_exists(self::TAXONOMY)) {
            $this->register();
        }

        foreach (self::STARTER_TERMS as $name) {
            if (!term_exists($name, self::TAXONOMY)) {
                wp_insert_term($name, self::TAXONOMY);
            }
        }
    }
}
