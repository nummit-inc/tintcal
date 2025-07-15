<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
// 管理メニューの追加
add_action('admin_menu', function () {

    // ▼▼▼ 先に、各メニューへのアクセス権限をチェックする ▼▼▼
    $can_see_common_settings = jcalendar_current_user_can_access('manage_common_settings');
    $can_see_calendars = jcalendar_current_user_can_access('manage_calendars');
    $can_see_config = current_user_can('manage_options');

    // ▼▼▼ アクセスできるサブメニューが一つもない場合は、何もせず処理を終了 ▼▼▼
    if (!$can_see_common_settings && !$can_see_calendars && !$can_see_config) {
        return;
    }

  // 親メニュー：J-Calendar 管理
  add_menu_page(
    'カレンダー一覧',
    'TintCal',
    'edit_posts',
    'jcalendar-dashboard',
    'jcalendar_dashboard_redirect',
    'dashicons-calendar-alt',
    99
  );
  /**
   * ダッシュボードメニューをクリックしたらカスタム投稿一覧へ飛ばす
   */
  function jcalendar_dashboard_redirect() {
      wp_redirect( admin_url('edit.php?post_type=jcalendar') );
      exit;
  }

  // 「カテゴリ・日付入力」（共通設定）
    if ($can_see_common_settings) { // ← 事前チェックした変数を使用
        global $jcal_preference_hook;
        $jcal_preference_hook = add_submenu_page(
            'jcalendar-dashboard', 'カテゴリ・日付入力', 'カテゴリ・日付入力',
            'edit_posts',
            'jcalendar-preference', 'jcalendar_render_preference_page'
        );
    }
    
    // 「カレンダー一覧」と「新規追加」（カレンダー管理）
    if ($can_see_calendars) { // ← 事前チェックした変数を使用
        add_submenu_page(
            'jcalendar-dashboard', 'カレンダー一覧', 'カレンダー一覧',
            'edit_jcalendars',
            'edit.php?post_type=jcalendar'
        );
        add_submenu_page(
            'jcalendar-dashboard', '新規カレンダー追加', '新規カレンダー追加',
            'edit_jcalendars',
            'post-new.php?post_type=jcalendar'
        );
    }

    // 「プラグイン設定」（管理者のみ）
    if ($can_see_config) { // ← 事前チェックした変数を使用
        global $jcal_config_hook;
        $jcal_config_hook = add_submenu_page(
            'jcalendar-dashboard', 'プラグイン設定', 'プラグイン設定',
            'manage_options',
            'jcalendar-config', 'jcalendar_render_config_page'
        );
    }

  // add_menu_page で自動生成される最初のサブメニューを削除
  remove_submenu_page('jcalendar-dashboard', 'jcalendar-dashboard');

});

/**
 * カレンダー投稿一覧の「編集」リンクを独自編集画面に差し替え
 */
add_filter('post_row_actions', function($actions, $post){
    if ($post->post_type === 'jcalendar') {
        // 「表示」リンクを削除
        unset($actions['view']);
        // 一括管理設定へのリンクも追加したい場合は下記を追加
        $custom_url = admin_url('admin.php?page=jcalendar-preference');
        $actions['edit_calendar'] = sprintf(
            '<a href="%s">一括管理設定</a>',
            esc_url($custom_url)
        );
    }
    return $actions;
}, 10, 2);

/**
 * 全体設定ページのレンダリング
 */
function jcalendar_render_config_page() {
  $file = plugin_dir_path(__FILE__) . 'config-view-jcalendar.php';
  if (! file_exists($file)) {
      wp_die('config-view-jcalendar.php が見つかりません');
  }
  include $file;
}


/**
 * 現在のユーザーが、指定されたTintCalの管理メニューにアクセスできるかチェックする
 *
 * @param string $cap チェックしたい権限のスラッグ (例: 'manage_common_settings')
 * @return bool アクセス可能なら true, 不可能なら false
 */
function jcalendar_current_user_can_access($cap) {
    // 管理者は常にOK
    if (current_user_can('manage_options')) {
        return true;
    }

    // 編集者でなければ、この先は常にNG
    if (!current_user_can('editor')) {
        return false;
    }
    
    // 編集者の場合、保存された設定をチェック
    $permissions = get_option('jcalendar_role_permissions', []);
    return !empty($permissions['editor'][$cap]);
}

/**
 * 'jcalendar' 投稿タイプの編集画面でのみ「変更をプレビュー」ボタンを非表示にする
 */
add_action('admin_head-post.php', 'jcalendar_hide_preview_button');
add_action('admin_head-post-new.php', 'jcalendar_hide_preview_button');

function jcalendar_hide_preview_button() {
    global $post_type;
    if ($post_type === 'jcalendar') {
        echo '<style>#preview-action { display: none !important; }</style>';
    }
}