<?php
// 投稿タイプ tintcal 登録
add_action('init', function () {
  register_post_type('jcalendar', [
    'labels' => [
      'name' => 'カレンダー一覧',
      'singular_name' => 'カレンダー',
      'add_new' => '新規追加',
      'add_new_item' => 'カレンダーを新規追加',
      'edit_item' => 'カレンダーを編集',
      'new_item' => '新規カレンダー',
      'view_item' => 'カレンダーを見る',
      'search_items' => 'カレンダーを検索',
      'not_found' => 'カレンダーが見つかりませんでした',
      'not_found_in_trash' => 'ゴミ箱にカレンダーは見つかりませんでした',
      'all_items' => 'カレンダー一覧',
      'menu_name' => 'カレンダー一覧',
      'name_admin_bar' => 'カレンダー',
    ],
    'public' => true,
    'has_archive' => false,
    'show_in_menu' => false, // J-Calendar管理メニューで制御するためfalse
    'menu_icon' => 'dashicons-calendar',
    'supports' => ['title', 'custom-fields'], // ★ 'custom-fields' を追加
    'show_in_rest' => true,
    'capability_type' => 'jcalendar',
    'map_meta_cap'    => true,
    // ▼▼▼ この capabilities 配列を丸ごと追加 ▼▼▼
    'capabilities' => [
        // Meta capabilities
        'edit_post'              => 'edit_jcalendar',
        'read_post'              => 'read_jcalendar',
        'delete_post'            => 'delete_jcalendar',
        // Primitive capabilities
        'edit_posts'             => 'edit_jcalendars',
        'edit_others_posts'      => 'edit_others_jcalendars',
        'publish_posts'          => 'publish_jcalendars',
        'read_private_posts'     => 'read_private_jcalendars',
        'create_posts'           => 'edit_jcalendars', // 新規作成の権限
        'delete_posts'           => 'delete_jcalendars',
        'delete_private_posts'   => 'delete_private_jcalendars',
        'delete_published_posts' => 'delete_published_jcalendars',
        'delete_others_posts'    => 'delete_others_jcalendars',
        'edit_private_posts'     => 'edit_private_jcalendars',
        'edit_published_posts'   => 'edit_published_jcalendars',
    ],
    // ▲▲▲ ここまで追加 ▲▲▲
  ]);

  // ブロックエディター対応のためのメタデータ登録
  $meta_fields = [
    '_jcalendar_start_day', '_jcalendar_header_color', '_jcal_show_header_weekend_color',
    '_jcalendar_enable_holidays', '_jcalendar_holiday_color', '_jcalendar_show_sunday_color',
    '_jcalendar_sunday_color', '_jcalendar_show_saturday_color', '_jcalendar_saturday_color',
    '_jcalendar_show_legend', '_jcalendar_show_today_button', '_jcalendar_visible_categories'
  ];

  foreach ($meta_fields as $meta_key) {
    register_post_meta('jcalendar', $meta_key, [
        'show_in_rest' => true,
        'single' => true,
        'type' => ($meta_key === '_jcalendar_visible_categories') ? 'array' : 'string',
        'sanitize_callback' => 'sanitize_text_field'
    ]);
  }

});

// タイトル下にプレビューカレンダーを表示する
add_action('edit_form_after_title', function($post) {
    if ($post->post_type === 'jcalendar') {
        echo '<div id="jcal-individual-preview" style="margin:2em 0;padding:1em;border:1px solid #ccc;background:#f9f9f9;">';
        echo '<h2>プレビューカレンダー</h2>';
        echo '<div id="jcalendar-preview-admin" class="jcalendar-instance">';
        echo jcalendar_render_calendar_base_html($post->ID);
        echo '</div>';
        echo '</div>';
    }
}, 10);
