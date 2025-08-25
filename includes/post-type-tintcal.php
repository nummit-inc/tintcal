<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
// 投稿タイプ tintcal 登録
add_action('init', function () {
  register_post_type('tintcal', [
    'labels' => [
      'name'                  => esc_html__( 'カレンダー一覧', 'tintcal' ),
      'singular_name'         => esc_html__( 'カレンダー', 'tintcal' ),
      'add_new'               => esc_html__( '新規追加', 'tintcal' ),
      'add_new_item'          => esc_html__( 'カレンダーを新規追加', 'tintcal' ),
      'edit_item'             => esc_html__( 'カレンダーを編集', 'tintcal' ),
      'new_item'              => esc_html__( '新規カレンダー', 'tintcal' ),
      'view_item'             => esc_html__( 'カレンダーを見る', 'tintcal' ),
      'search_items'          => esc_html__( 'カレンダーを検索', 'tintcal' ),
      'not_found'             => esc_html__( 'カレンダーが見つかりませんでした', 'tintcal' ),
      'not_found_in_trash'    => esc_html__( 'ゴミ箱にカレンダーは見つかりませんでした', 'tintcal' ),
      'all_items'             => esc_html__( 'カレンダー一覧', 'tintcal' ),
      'menu_name'             => esc_html__( 'カレンダー一覧', 'tintcal' ),
      'name_admin_bar'        => esc_html__( 'カレンダー', 'tintcal' ),
    ],
    'public' => true,
    'has_archive' => false,
    'show_in_menu' => false, // J-Calendar管理メニューで制御するためfalse
    'menu_icon' => 'dashicons-calendar',
    'supports' => ['title', 'custom-fields'], // ★ 'custom-fields' を追加
    'show_in_rest' => true,
    'capability_type' => 'tintcal',
    'map_meta_cap'    => true,
    // ▼▼▼ この capabilities 配列を丸ごと追加 ▼▼▼
    'capabilities' => [
        // Meta capabilities
        'edit_post'              => 'edit_tintcal',
        'read_post'              => 'read_tintcal',
        'delete_post'            => 'delete_tintcal',
        // Primitive capabilities
        'edit_posts'             => 'edit_tintcals',
        'edit_others_posts'      => 'edit_others_tintcals',
        'publish_posts'          => 'publish_tintcals',
        'read_private_posts'     => 'read_private_tintcals',
        'create_posts'           => 'edit_tintcals', // 新規作成の権限
        'delete_posts'           => 'delete_tintcals',
        // 'delete_private_posts'   => 'delete_private_tintcals',
        'delete_published_posts' => 'delete_published_tintcals',
        'delete_others_posts'    => 'delete_others_tintcals',
        // 'edit_private_posts'     => 'edit_private_tintcals',
        'edit_published_posts'   => 'edit_published_tintcals',
    ],
    // ▲▲▲ ここまで追加 ▲▲▲
  ]);

  // ブロックエディター対応のためのメタデータ登録
  $meta_fields = [
    '_tintcal_start_day', '_tintcal_header_color', '_tintcal_show_header_weekend_color',
    '_tintcal_enable_holidays', '_tintcal_holiday_color', '_tintcal_show_sunday_color',
    '_tintcal_sunday_color', '_tintcal_show_saturday_color', '_tintcal_saturday_color',
    '_tintcal_show_legend', '_tintcal_show_today_button', '_tintcal_visible_categories'
  ];

  foreach ($meta_fields as $meta_key) {
    $meta_args = [
       'show_in_rest' => true,
       'single' => true,
       'type' => ($meta_key === '_tintcal_visible_categories') ? 'array' : 'string',
      //  'sanitize_callback' => 'sanitize_text_field'
    ];

    if ($meta_key === '_tintcal_visible_categories') {
        $meta_args['show_in_rest'] = [
            'schema' => [
                'type'  => 'array',
                'items' => [
                    'type' => 'string', // 配列の各要素が文字列であることを示す
                ],
            ],
        ];
    }
    register_post_meta('tintcal', $meta_key, $meta_args);
 }

});

// タイトル下にプレビューカレンダーを表示する
add_action('edit_form_after_title', function($post) {
    if ($post->post_type === 'tintcal') {
        echo '<div id="tintcal-individual-preview" style="margin:2em 0;padding:1em;border:1px solid #ccc;background:#f9f9f9;">';
        echo '<h2>' . esc_html__( 'プレビューカレンダー', 'tintcal' ) . '</h2>';
        echo '<div id="tintcal-preview-admin" class="tintcal-instance">';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- This function returns HTML which is already escaped internally.
        echo wp_kses_post(tintcal_render_calendar_base_html($post->ID));
        echo '</div>';
        echo '</div>';
    }
}, 10);
