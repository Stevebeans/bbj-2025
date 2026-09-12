<?php

namespace BigBrotherJunkies\Data\Taxonomies;

/**
 * Registers the `bbj_show` taxonomy on the `live-feed-updates` CPT.
 *
 * The invariant every Big Brother surface relies on: a post with NO
 * `bbj_show` term is a Big Brother update. There is intentionally no "Big
 * Brother" term — legacy content and future BB updates satisfy the
 * invariant for free by carrying no term at all.
 */
class ShowTaxonomy
{
    private const TAXONOMY = 'bbj_show';
    private const POST_TYPE = 'live-feed-updates';

    private const STARTER_TERMS = ['Survivor'];

    /**
     * Hook into WordPress.
     */
    public function init(): void
    {
        add_action('init', [$this, 'register'], 11);
        add_action('init', [$this, 'maybeSeedTerms'], 12);
    }

    /**
     * Register the taxonomy.
     */
    public function register(): void
    {
        register_taxonomy(self::TAXONOMY, self::POST_TYPE, [
            'hierarchical'        => false,
            'labels'              => [
                'name'          => 'Shows',
                'singular_name' => 'Show',
                'menu_name'     => 'Shows',
                'add_new_item'  => 'Add New Show',
                'search_items'  => 'Search Shows',
            ],
            'show_ui'             => true,
            'show_admin_column'   => true,
            'show_in_rest'        => true,
            'show_in_graphql'     => true,
            'graphql_single_name' => 'bbjShow',
            'graphql_plural_name' => 'bbjShows',
            'rewrite'             => ['slug' => 'show'],
        ]);
    }

    /**
     * Seed starter terms (idempotent — option-guarded since the plugin is
     * already active and its activation hook won't refire).
     */
    public function maybeSeedTerms(): void
    {
        if (get_option('bbjd_show_tax_seeded')) {
            return;
        }
        $this->seedTerms();
        update_option('bbjd_show_tax_seeded', 1);
    }

    /**
     * Seed starter terms.
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
