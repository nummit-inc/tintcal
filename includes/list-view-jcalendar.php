<?php
// カラムの定義（ショートコード、カテゴリ数）
add_filter('manage_jcalendar_posts_columns', function ($columns) {
  $columns['cb'] = '<input type="checkbox" />';
  $columns['title'] = 'カレンダー名';
  $columns['jcalendar_shortcode'] = 'ショートコード';
  $columns['category_count'] = 'カテゴリ数';
  $columns['date'] = '日付';
  return $columns;
});

// カラムの表示処理
add_action('manage_jcalendar_posts_custom_column', function ($column, $post_id) {
  if ($column === 'jcalendar_shortcode') {
    echo '[tintcal id="' . esc_attr($post_id) . '"]';
  } elseif ($column === 'category_count') {
    $categories = get_option('jcalendar_categories', []);
    echo is_array($categories) ? count($categories) : 0;
  }
}, 10, 2);
