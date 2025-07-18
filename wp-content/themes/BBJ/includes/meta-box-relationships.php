<? add_action( 'mb_relationships_init', 'bbj_relationship' );


function bbj_relationship() {
    MB_Relationships_API::register( [
        'id'   => 'player-to-season',
        'from' => [
            'object_type' => 'post',
            'post_type'   => 'bigbrother-players',
            'field'   => [
              'query_args'  => [
                'orderby'   =>  'title',
                'order' => 'DESC'
              ],
            ],
            'meta_box'    => [
                'title' => 'Select Season',
            ],
        ],
        'to'   => [
            'object_type' => 'post',
            'post_type'   => 'bigbrother-seasons',
            'field'   => [
              'query_args'  => [
                'orderby'   =>  'title',
              ],
            ],
            'meta_box'    => [
                'title' => 'Select Player',
            ],
        ],
    ] );
}