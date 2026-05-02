<?php

namespace BigBrotherJunkies\Data\Taxonomies;

/**
 * Registers the `update_type` taxonomy on the `live-feed-updates` CPT
 * and seeds a set of starter terms on plugin activation.
 */
class UpdateTypeTaxonomy
{
    private const TAXONOMY = 'update_type';
    private const POST_TYPE = 'live-feed-updates';

    private const STARTER_TERMS = [
        'Drama', 'Ceremony', 'Strategy', 'Competition',
        'Alliance', 'Eviction', 'Punishment', 'Reward', 'Showmance',
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
                'name'          => 'Update Types',
                'singular_name' => 'Update Type',
                'menu_name'     => 'Update Types',
                'add_new_item'  => 'Add New Update Type',
                'search_items'  => 'Search Update Types',
            ],
            'show_ui'             => true,
            'show_admin_column'   => true,
            'show_in_rest'        => true,
            'show_in_graphql'     => true,
            'graphql_single_name' => 'updateType',
            'graphql_plural_name' => 'updateTypes',
            'rewrite'             => ['slug' => 'update-type'],
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
