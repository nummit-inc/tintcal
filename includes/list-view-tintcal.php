<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
// カラムの定義（ショートコード、カテゴリ数）
add_filter('manage_tintcal_posts_columns', function ($columns) {
  $columns['cb'] = '<input type="checkbox" />';
  $columns['title'] = esc_html__( 'カレンダー名', 'tintcal' );
  $columns['tintcal_shortcode'] = esc_html__( 'ショートコード', 'tintcal' );
  $columns['category_count'] = esc_html__( 'カテゴリ数', 'tintcal' );
  $columns['date'] = esc_html__( '日付', 'tintcal' );
  return $columns;
});

// カラムの表示処理
add_action('manage_tintcal_posts_custom_column', function ($column, $post_id) {
  if ($column === 'tintcal_shortcode') {
    echo '[tintcal id="' . esc_attr($post_id) . '"]';
  } elseif ($column === 'category_count') {
    $categories = get_option('tintcal_categories', []);
    $count = is_array($categories) ? min(1, count($categories)) : 0;
    echo (int) $count;
  }
}, 10, 2);
