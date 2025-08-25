<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
// 管理メニューの追加
add_action('admin_menu', function () {

    // ▼▼▼ 先に、各メニューへのアクセス権限をチェックする ▼▼▼
    $can_see_common_settings = current_user_can('edit_posts'); // 権限チェックを簡素化
    $can_see_calendars = current_user_can('edit_posts');

    // ▼▼▼ アクセスできるサブメニューが一つもない場合は、何もせず処理を終了 ▼▼▼
    if (!$can_see_common_settings && !$can_see_calendars) {
        return;
    }

  // 親メニュー：J-Calendar 管理
  add_menu_page(
    esc_html__( 'カレンダー一覧', 'tintcal' ),
    'TintCal',
    'edit_posts',
    'tintcal-dashboard',
    'tintcal_dashboard_redirect',
    'dashicons-calendar-alt',
    99
  );
  /**
   * ダッシュボードメニューをクリックしたらカスタム投稿一覧へ飛ばす
   */
  function tintcal_dashboard_redirect() {
      wp_redirect( admin_url('edit.php?post_type=tintcal') );
      exit;
  }

  // 「カテゴリ・日付入力」（共通設定）
    if ($can_see_common_settings) { // ← 事前チェックした変数を使用
        global $tintcal_preference_hook;
        $tintcal_preference_hook = add_submenu_page(
            'tintcal-dashboard', esc_html__( 'カテゴリ・日付入力', 'tintcal' ), esc_html__( 'カテゴリ・日付入力', 'tintcal' ),
            'edit_posts',
            'tintcal-preference', 'tintcal_render_preference_page'
        );
    }
    
    // 「カレンダー一覧」と「新規追加」（カレンダー管理）
    if ($can_see_calendars) { // ← 事前チェックした変数を使用
        add_submenu_page(
            'tintcal-dashboard', esc_html__( 'カレンダー一覧', 'tintcal' ), esc_html__( 'カレンダー一覧', 'tintcal' ),
            'edit_posts',
            'edit.php?post_type=tintcal'
        );
        add_submenu_page(
            'tintcal-dashboard', esc_html__( '新規カレンダー追加', 'tintcal' ), esc_html__( '新規カレンダー追加', 'tintcal' ),
            'edit_posts',
            'post-new.php?post_type=tintcal'
        );
    }

    // Pro版案内ページ
    add_submenu_page(
        'tintcal-dashboard',
        esc_html__( 'TintCal Proのご案内', 'tintcal' ),
        esc_html__( 'TintCal Proのご案内', 'tintcal' ),
        'edit_posts',
        'tintcal-pro',
        'tintcal_render_pro_page'
    );

  // add_menu_page で自動生成される最初のサブメニューを削除
  remove_submenu_page('tintcal-dashboard', 'tintcal-dashboard');

});

/**
 * カレンダー投稿一覧の「編集」リンクを独自編集画面に差し替え
 */
add_filter('post_row_actions', function($actions, $post){
    if ($post->post_type === 'tintcal') {
        // 「表示」リンクを削除
        unset($actions['view']);
        // 一括管理設定へのリンクも追加したい場合は下記を追加
        $custom_url = admin_url('admin.php?page=tintcal-preference');
        $actions['edit_calendar'] = sprintf(
            '<a href="%s">' . esc_html__( '一括管理設定', 'tintcal' ) . '</a>',
            esc_url($custom_url)
        );
    }
    return $actions;
}, 10, 2);

/**
 * 'tintcal' 投稿タイプの編集画面でのみ「変更をプレビュー」ボタンを非表示にする
 */
add_action('admin_print_styles-post.php', 'tintcal_hide_preview_button', 10, 0);
add_action('admin_print_styles-post-new.php', 'tintcal_hide_preview_button', 10, 0);

function tintcal_hide_preview_button() {
    global $post_type;
    if ($post_type === 'tintcal') {
        $css = '#preview-action { display: none !important; }';
        wp_add_inline_style('wp-admin', $css);
    }
}

/**
 * Pro版案内ページのレンダリング
 */
function tintcal_render_pro_page() {
    $file = plugin_dir_path(__FILE__) . 'pro-view-tintcal.php';
    if (file_exists($file)) {
        include $file;
    }
}