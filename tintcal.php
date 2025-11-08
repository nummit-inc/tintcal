<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
/*
Plugin Name:      TintCal
Plugin URI:       https://tintcal.com
Description:      「定休日」や「イベント日」といった予定を自由に設定し、日付をカラフルに色分け。日本の祝日にも対応した、見た目がわかりやすいオリジナルカレンダーを作成できます。
Version:          2.2.2
Requires at least: 5.8
Requires PHP:     7.4
Author:           QuantaLumina
Author URI:       https://nummit.jp
License:          GPL v2 or later
License URI:      https://www.gnu.org/licenses/gpl-2.0.html
Text Domain:      tintcal
Domain Path:      /languages
*/


define('TINTCAL_VERSION', '2.2.2');
define('TINTCAL_FREE_MAX_CATEGORIES', 1); // Free version supports only 1 category



$tintcal_includes_path = plugin_dir_path(__FILE__) . 'includes/';

require_once $tintcal_includes_path . 'admin-menu-tintcal.php';
require_once $tintcal_includes_path . 'meta-box-tintcal.php';
require_once $tintcal_includes_path . 'save-meta-tintcal.php';
require_once $tintcal_includes_path . 'list-view-tintcal.php';
require_once $tintcal_includes_path . 'post-type-tintcal.php';
require_once $tintcal_includes_path . 'shortcode-frontend-tintcal.php';

// =============================
// WP_Filesystem Helper Functions
// =============================

/**
 * Initialize WP_Filesystem with fallback to direct file operations
 *
 * @return WP_Filesystem_Base|false Filesystem object or false on failure
 */
function tintcal_get_filesystem() {
    global $wp_filesystem;

    if (empty($wp_filesystem)) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        WP_Filesystem();
    }

    return $wp_filesystem;
}

/**
 * Read file contents using WP_Filesystem with fallback
 *
 * @param string $file_path Path to file
 * @return string|false File contents or false on failure
 */
function tintcal_read_file($file_path) {
    $filesystem = tintcal_get_filesystem();

    if ($filesystem && $filesystem->exists($file_path)) {
        return $filesystem->get_contents($file_path);
    }

    // Fallback to direct file access if WP_Filesystem fails
    if (file_exists($file_path) && is_readable($file_path)) {
        return file_get_contents($file_path);
    }

    return false;
}

/**
 * Write file contents using WP_Filesystem with fallback
 *
 * @param string $file_path Path to file
 * @param string $content Content to write
 * @return bool True on success, false on failure
 */
function tintcal_write_file($file_path, $content) {
    $filesystem = tintcal_get_filesystem();

    if ($filesystem) {
        return $filesystem->put_contents($file_path, $content, FS_CHMOD_FILE);
    }

    // Fallback to direct file access if WP_Filesystem fails
    $result = file_put_contents($file_path, $content);
    return $result !== false;
}

// =============================
// 管理画面用の通知・メニュー登録
// =============================

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is a display logic based on GET param, not processing form data.
if (isset($_GET['updated']) && $_GET['updated'] == 1) {
  add_action('admin_notices', function () {
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( '設定を保存しました。', 'tintcal' ) . '</p></div>';
  });
}

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is a display logic based on GET param, not processing form data.
if (isset($_GET['import']) && $_GET['import'] === 'success') {
  add_action('admin_notices', function () {
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'カテゴリと日付データをインポートしました。', 'tintcal' ) . '</p></div>';
  });
}

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is a display logic based on GET param, not processing form data.
if (isset($_GET['import']) && $_GET['import'] === 'error') {
  add_action('admin_notices', function () {
    echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'インポートファイルの形式が不正です。', 'tintcal' ) . '</p></div>';
  });
}

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is a display logic based on GET param, not processing form data.
if (isset($_GET['tintcal_updated']) && $_GET['tintcal_updated'] == 1) {
  add_action('admin_notices', function () {
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( '設定を保存しました。', 'tintcal' ) . '</p></div>';
  });
}

/**
 * プラグイン設定ページ（ライセンス・ロール権限）の保存・解除処理
 */
add_action('admin_init', function() {
    
    // --- ロール権限設定の保存処理 ---
    if (isset($_POST['action']) && $_POST['action'] === 'tintcal_save_role_settings') {
        check_admin_referer('tintcal_role_settings_nonce');
        if (!current_user_can('manage_options')) {
            wp_die( esc_html__( '権限がありません。', 'tintcal' ) );
        }
        $permissions = [];
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce check is performed above.
        if (isset($_POST['permissions']) && is_array($_POST['permissions'])) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $permissions = wp_unslash($_POST['permissions']);
        }

        if (!is_array($permissions)) {
            $permissions = [];
        }
        $sanitized_permissions = [];
        foreach ($permissions as $role => $caps) {
            $sanitized_role = sanitize_key($role); // キーは sanitize_key でサニタイズされている
            $sanitized_permissions[$sanitized_role] = [
                'manage_common_settings' => isset($caps['manage_common_settings']) ? (bool) $caps['manage_common_settings'] : false, // (bool) でキャスト
                'manage_calendars'       => isset($caps['manage_calendars']) ? (bool) $caps['manage_calendars'] : false, // (bool) でキャスト
            ];
        }
        update_option('tintcal_role_permissions', $sanitized_permissions);

        // WordPressの権限システムに実際のcapabilityを登録/削除する
        $role = get_role('editor');
        if ($role) {
            // カレンダー投稿タイプを管理するために必要なWordPressのcapabilityのリスト
            $tintcal_caps = [
                'edit_tintcal',
                'read_tintcal',
                'delete_tintcal',
                'edit_tintcals',
                'edit_others_tintcals',
                'publish_tintcals',
                'read_private_tintcals',
                'delete_tintcals',
                'delete_private_tintcals',
                'delete_published_tintcals',
                'delete_others_tintcals',
                'edit_private_tintcals',
                'edit_published_tintcals',
            ];

            // 「個別カレンダー編集の管理」のチェック状態に応じて権限を付与または削除
            if (!empty($sanitized_permissions['editor']['manage_calendars'])) {
                foreach ($tintcal_caps as $cap) {
                    $role->add_cap($cap);
                }
            } else {
                foreach ($tintcal_caps as $cap) {
                    $role->remove_cap($cap);
                }
            }
        }

        wp_safe_redirect(admin_url('admin.php?page=tintcal-config&tintcal_updated=1'));
        exit;
    }
});

/**
 * 表示用のカテゴリリストを取得・整理して返すヘルパー関数
 * - DBからカテゴリを取得
 * - order順にソート
 * @return array 整頓済みのカテゴリ配列
 */
function tintcal_get_displayable_categories() {
    // 1. DBからカテゴリを取得
    $categories = get_option('tintcal_categories', []);
    if (!is_array($categories)) {
        return [];
    }

    // 2. orderプロパティを基準にカテゴリを昇順で並び替える
    usort($categories, function($a, $b) {
        $order_a = isset($a['order']) ? (int)$a['order'] : 0;
        $order_b = isset($b['order']) ? (int)$b['order'] : 0;
        return $order_a <=> $order_b;
    });

    // 3. 最終的に整頓されたカテゴリリストを返す
    return $categories;
}

// =============================
// 管理画面 設定ページの描画
// =============================

/**
 * 個別カレンダー設定画面のレンダリングと保存処理
 */
function tintcal_render_preference_page() {
    
    // 権限チェックを修正
    if ( ! current_user_can('edit_posts') ) {
        wp_die( esc_html__( 'このページにアクセスする権限がありません。', 'tintcal' ) );
    }

    // --- [UUID slug migration] 既存カテゴリ全件にslugフィールド付与 ---
    $categories = get_option('tintcal_categories', []);
    $modified = false;
    foreach ($categories as &$cat) {
        if (empty($cat['slug'])) {
            // slug未設定なら生成（UUID v4簡易生成）
            $cat['slug'] = wp_generate_uuid4();
            $modified = true;
        }
    }
    unset($cat);
    if ($modified) {
        update_option('tintcal_categories', $categories);
    }

    // 設定保存処理
    if ( isset($_POST['tintcal_save_settings']) && check_admin_referer('tintcal_settings_nonce') ) {
        // カラー以外の設定は、ライセンス状況に関わらず常に保存する
        // Nonce と権限チェックは既にされているため、ここではサニタイズとスラッシュ除去に集中
        update_option('tintcal_start_day', isset($_POST['start_day']) ? sanitize_text_field(wp_unslash($_POST['start_day'])) : 'sunday');
        update_option('tintcal_enable_holidays', isset($_POST['enable_holidays']) ? 1 : 0); // この行は既に isset があるので OK
        update_option('tintcal_show_sunday_color', isset($_POST['show_sunday_color']) ? 1 : 0);
        update_option('tintcal_show_saturday_color', isset($_POST['show_saturday_color']) ? 1 : 0);
        update_option('tintcal_show_legend', isset($_POST['show_legend']) ? 1 : 0);
        update_option('tintcal_show_header_weekend_color', isset($_POST['tintcal_show_header_weekend_color']) ? 1 : 0);
        update_option('tintcal_show_today_button', isset($_POST['tintcal_show_today_button']) ? 1 : 0);

        // カラー設定を常に保存する
        update_option('tintcal_header_color', isset($_POST['header_color']) ? sanitize_hex_color(wp_unslash($_POST['header_color'])) : '#eeeeee');
        update_option('tintcal_holiday_color', isset($_POST['holiday_color']) ? sanitize_hex_color(wp_unslash($_POST['holiday_color'])) : '#ffdddd');
        update_option('tintcal_sunday_color', isset($_POST['sunday_color']) ? sanitize_hex_color(wp_unslash($_POST['sunday_color'])) : '#ffecec');
        update_option('tintcal_saturday_color', isset($_POST['saturday_color']) ? sanitize_hex_color(wp_unslash($_POST['saturday_color'])) : '#ecf5ff');
        
        add_settings_error('tintcal_messages', 'saved', esc_html__('設定を保存しました。', 'tintcal'), 'updated');
        // 保存後は同じ個別設定ページにリダイレクト
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce check is performed above.
        $active_tab = isset($_POST['active_tab']) ? sanitize_text_field(wp_unslash($_POST['active_tab'])) : '';
        wp_safe_redirect(admin_url('admin.php?page=tintcal-preference&updated=1' . (!empty($active_tab) ? '&hash=' . urlencode($active_tab) : '')));
        exit;
    }

    // 設定画面の表示
    global $tintcal_includes_path;
    include $tintcal_includes_path . 'preference-view-tintcal.php';
}

/**
 * 管理画面のJavaScriptに渡すための設定データを構築する共通関数
 *
 * @param int|null $post_id カレンダー投稿のID（新規作成や一括設定画面ではnull）
 * @return array JavaScriptに渡す設定データの連想配列
 */
function tintcal_build_js_data_for_admin($post_id = null) {
    $js_data = [];
    $setting_keys = [
        'startDay'               => ['type' => 'string', 'default' => 'sunday'],
        'headerColor'            => ['type' => 'string', 'default' => '#eeeeee'],
        'sundayColor'            => ['type' => 'string', 'default' => '#ffecec'],
        'saturdayColor'          => ['type' => 'string', 'default' => '#ecf5ff'],
        'holidayColor'           => ['type' => 'string', 'default' => '#ffdddd'],
        'showSundayColor'        => ['type' => 'bool',   'default' => 1],
        'showSaturdayColor'      => ['type' => 'bool',   'default' => 1],
        'enableHolidays'         => ['type' => 'bool',   'default' => 1],
        'showLegend'             => ['type' => 'bool',   'default' => 1],
        'showHeaderWeekendColor' => ['type' => 'bool',   'default' => 1, 'option_key' => 'tintcal_show_header_weekend_color'],
        'showTodayButton'        => ['type' => 'bool',   'default' => 1, 'option_key' => 'tintcal_show_today_button'],
    ];

    if ($post_id) {
        // 【編集画面の場合】
        foreach ($setting_keys as $js_key => $props) {
            // DB上のキー名を生成
            $option_key = isset($props['option_key']) ? $props['option_key'] : 'tintcal_' . strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $js_key));
            $meta_key   = '_' . $option_key;

            $meta_value = get_post_meta($post_id, $meta_key, true);
            
            // ▼▼▼ このロジックを管理画面側と完全に統一する ▼▼▼
            if ($meta_value !== '') {
                // 個別設定が保存されていれば、その値を使う
                $js_data[$js_key] = ($props['type'] === 'bool') ? (int)$meta_value : $meta_value;
            } else {
                // なければ、共通設定の値を使う
                $js_data[$js_key] = get_option($option_key, $props['default']);
            }
        }
        // _tintcal_visible_categories を取得し、配列であることを保証する
        $visible_slugs_from_meta = get_post_meta($post_id, '_tintcal_visible_categories', false); // falseに変更済み
        $visible_slugs_current = []; // まず空で初期化
        if (is_array($visible_slugs_from_meta)) {
            // 取得した配列がさらに配列を内包している場合があるので、すべてを1次元配列にフラット化する
            foreach ($visible_slugs_from_meta as $item) {
                if (is_array($item)) {
                    $visible_slugs_current = array_merge($visible_slugs_current, $item);
                } else {
                    $visible_slugs_current[] = $item;
                }
            }
        }

        $is_auto_draft = (get_post($post_id)->post_status === 'auto-draft');
        
        if ($is_auto_draft) {
            // 新規投稿の場合は、共通設定の「表示ON」カテゴリを初期値とする
            $all_categories = get_option('tintcal_categories', []);
            $visible_in_main_settings = array_filter($all_categories, function($cat) { return ($cat['visible'] ?? true); });
            $js_data['visibleCategories'] = array_values(wp_list_pluck($visible_in_main_settings, 'slug'));
        } else {
            // 既存投稿の場合は、DBの値を正とする
            $js_data['visibleCategories'] = $visible_slugs_current;
        }
    } else {
        // 【新規作成画面 or 一括設定画面の場合】
        foreach ($setting_keys as $js_key => $props) {
            $option_key = $props['option_key'] ?? 'tintcal_' . strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $js_key));
            $js_data[$js_key] = get_option($option_key, $props['default']);
        }
        $all_categories = get_option('tintcal_categories', []);
        $visible_in_main_settings = array_filter($all_categories, function($cat) { return $cat['visible'] ?? true; });
        $js_data['visibleCategories'] = array_values(wp_list_pluck($visible_in_main_settings, 'slug'));
    }

    // 全ての管理画面で共通して渡すデータ
    $js_data['pluginUrl']   = plugin_dir_url(__FILE__);
    $js_data['ajaxUrl']     = admin_url('admin-ajax.php');
    $js_data['nonce']       = wp_create_nonce('tintcal_settings_nonce');
    $js_data['categories'] = tintcal_get_displayable_categories();

    // JSON decode with error handling
    $assignments_raw = get_option('tintcal_date_categories', '{}');
    $assignments_decoded = json_decode($assignments_raw, true);
    $js_data['assignments'] = (json_last_error() === JSON_ERROR_NONE && is_array($assignments_decoded)) ? $assignments_decoded : [];
    
    // 祝日データを年ごとに読み込む（Transient APIでキャッシュ）
    $current_year = (int)date_i18n('Y');
    $years_to_load = [$current_year, $current_year + 1, $current_year + 2];
    $locale = 'ja';
    $upload_dir = wp_upload_dir();

    $cache_key = 'tintcal_holidays_' . implode('_', $years_to_load);
    $holidays_by_year = get_transient($cache_key);

    if (false === $holidays_by_year) {
        $holidays_by_year = [];
        $tintcal_dir = $upload_dir['basedir'] . '/tintcal-holidays';

        foreach ($years_to_load as $year) {
            $file_path = "{$tintcal_dir}/{$locale}/{$year}.json";
            $json_content = tintcal_read_file($file_path);
            if ($json_content !== false) {
                $holiday_data = json_decode($json_content, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($holiday_data)) {
                    $holidays_by_year[$year] = $holiday_data;
                }
            }
        }

        // Cache for 1 day
        set_transient($cache_key, $holidays_by_year, DAY_IN_SECONDS);
    }

    $js_data['holidays'] = $holidays_by_year;
    $js_data['locale'] = $locale;
    $js_data['holidayJsonUrl'] = $upload_dir['baseurl'] . '/tintcal-holidays';

    return $js_data;
}

add_action('admin_enqueue_scripts', function ($hook) {
    // 現在の画面情報を取得
    $screen = get_current_screen();

    // TintCal関連の管理画面かを判定
    $is_tintcal_admin_page = false;
    global $tintcal_preference_hook, $tintcal_config_hook;
    if ($hook === $tintcal_preference_hook || $hook === $tintcal_config_hook) {
        $is_tintcal_admin_page = true;
    }

    // カスタム投稿タイプ 'tintcal' の編集画面かを判定
    $is_tintcal_post_edit_page = false;
    if ($screen && $screen->id === 'tintcal' && ($hook === 'post.php' || $hook === 'post-new.php')) {
        $is_tintcal_post_edit_page = true;
    }

    // TintCal関連ページでなければ処理を中断
    if (!$is_tintcal_admin_page && !$is_tintcal_post_edit_page) {
        return;
    }
    
    // --- データ構築とスクリプトエンキューはここから ---
    $post_id = null;
    if ($is_tintcal_post_edit_page) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading post ID from GET is a standard WordPress practice in this context.
        $post_id = isset($_GET['post']) ? absint(wp_unslash($_GET['post'])) : null;
    }
    $js_data = tintcal_build_js_data_for_admin($post_id);

    // スタイルを読み込む
    wp_enqueue_style('tintcal-style', plugin_dir_url(__FILE__) . 'assets/css/calendar.css', [], TINTCAL_VERSION);

    if ($is_tintcal_admin_page) { // 「カテゴリ・日付入力」または「プラグイン設定」ページの場合
        wp_enqueue_script('tintcal-admin', plugin_dir_url(__FILE__) . 'assets/js/calendar-admin.js', ['jquery'], TINTCAL_VERSION, true);
        wp_localize_script('tintcal-admin', 'tintcalPluginData', $js_data); // tintcal-admin に localize
        
        wp_enqueue_script('tintcal-editor', plugin_dir_url(__FILE__) . 'assets/js/category-editor.js', ['tintcal-admin'], TINTCAL_VERSION, true);
        wp_enqueue_script('tintcal-admin-ui', plugin_dir_url(__FILE__) . 'assets/js/admin-ui-tintcal.js', [], TINTCAL_VERSION, true);

    } else if ($is_tintcal_post_edit_page) { // 「カレンダー」投稿の編集・新規作成ページの場合
        wp_enqueue_script('tintcal-front', plugin_dir_url(__FILE__) . 'assets/js/calendar-front.js', ['jquery'], TINTCAL_VERSION, true);
        wp_localize_script('tintcal-front', 'tintcalPluginData', $js_data); // tintcal-front に localize
        
        wp_add_inline_script('tintcal-front', "document.addEventListener('DOMContentLoaded', () => { new TintCal('#tintcal-preview-admin', tintcalPluginData); });", 'after');
    }
});

/**
 * フロントエンドのJavaScriptに渡すための設定データを構築する共通関数
 *
 * @param int|null $post_id カレンダー投稿のID
 * @return array JavaScriptに渡す設定データの連想配列
 */
function tintcal_build_js_data_for_frontend($post_id) {
    $js_data = [];

    // null（ショートコードや汎用読み込み）時は、サイト全体設定のみを返す
    if (empty($post_id)) {
        $setting_keys = [
            'headerColor'            => ['type' => 'string', 'default' => '#eeeeee'],
            'sundayColor'            => ['type' => 'string', 'default' => '#ffecec'],
            'saturdayColor'          => ['type' => 'string', 'default' => '#ecf5ff'],
            'holidayColor'           => ['type' => 'string', 'default' => '#ffdddd'],
            'showSundayColor'        => ['type' => 'bool',   'default' => 1],
            'showSaturdayColor'      => ['type' => 'bool',   'default' => 1],
            'enableHolidays'         => ['type' => 'bool',   'default' => 1],
            'showLegend'             => ['type' => 'bool',   'default' => 1],
            'showHeaderWeekendColor' => ['type' => 'bool',   'default' => 1, 'option_key' => 'tintcal_show_header_weekend_color'],
            'showTodayButton'        => ['type' => 'bool',   'default' => 1, 'option_key' => 'tintcal_show_today_button'],
            'startDay'               => ['type' => 'string', 'default' => 'sunday'],
        ];
        foreach ($setting_keys as $js_key => $props) {
            $option_key = isset($props['option_key']) ? $props['option_key'] : 'tintcal_' . strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $js_key));
            $js_data[$js_key] = get_option($option_key, $props['default']);
        }
        $all_categories = get_option('tintcal_categories', []);
        $visible_in_main_settings = array_filter($all_categories, function($cat) { return ($cat['visible'] ?? true); });
        $js_data['visibleCategories'] = array_values(wp_list_pluck($visible_in_main_settings, 'slug'));
        $js_data['pluginUrl']   = plugin_dir_url(__FILE__);
        $js_data['ajaxUrl']     = admin_url('admin-ajax.php');
        $js_data['nonce']       = wp_create_nonce('tintcal_settings_nonce');
        $js_data['categories']  = tintcal_get_displayable_categories();

        // JSON decode with error handling
        $assignments_raw = get_option('tintcal_date_categories', '{}');
        $assignments_decoded = json_decode($assignments_raw, true);
        $js_data['assignments'] = (json_last_error() === JSON_ERROR_NONE && is_array($assignments_decoded)) ? $assignments_decoded : [];

        // 祝日ファイル（Transient APIでキャッシュ）
        $current_year = (int)date_i18n('Y');
        $years_to_load = [$current_year, $current_year + 1, $current_year + 2];
        $locale = 'ja';
        $upload_dir = wp_upload_dir();

        $cache_key = 'tintcal_holidays_' . implode('_', $years_to_load);
        $holidays_by_year = get_transient($cache_key);

        if (false === $holidays_by_year) {
            $holidays_by_year = [];
            $tintcal_dir = $upload_dir['basedir'] . '/tintcal-holidays';
            foreach ($years_to_load as $year) {
                $file_path = "{$tintcal_dir}/{$locale}/{$year}.json";
                $json_content = tintcal_read_file($file_path);
                if ($json_content !== false) {
                    $holiday_data = json_decode($json_content, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($holiday_data)) {
                        $holidays_by_year[$year] = $holiday_data;
                    }
                }
            }
            // Cache for 1 day
            set_transient($cache_key, $holidays_by_year, DAY_IN_SECONDS);
        }

        $js_data['holidays'] = $holidays_by_year;
        $js_data['locale'] = $locale;
        $js_data['holidayJsonUrl'] = $upload_dir['baseurl'] . '/tintcal-holidays';
        return $js_data;
    }

    // --- 設定項目のキーとデフォルト値を定義 ---
    $setting_keys = [
        'headerColor'            => ['type' => 'string', 'default' => '#eeeeee'],
        'sundayColor'            => ['type' => 'string', 'default' => '#ffecec'],
        'saturdayColor'          => ['type' => 'string', 'default' => '#ecf5ff'],
        'holidayColor'           => ['type' => 'string', 'default' => '#ffdddd'],
        'showSundayColor'        => ['type' => 'bool',   'default' => 1],
        'showSaturdayColor'      => ['type' => 'bool',   'default' => 1],
        'enableHolidays'         => ['type' => 'bool',   'default' => 1],
        'showLegend'             => ['type' => 'bool',   'default' => 1],
        'showHeaderWeekendColor' => ['type' => 'bool',   'default' => 1, 'option_key' => 'tintcal_show_header_weekend_color'],
        'showTodayButton'        => ['type' => 'bool',   'default' => 1, 'option_key' => 'tintcal_show_today_button'],
        'startDay'               => ['type' => 'string', 'default' => 'sunday'],
    ];

    // --- 各設定値を取得 ---
    foreach ($setting_keys as $js_key => $props) {
        // DB上のキー名を生成 (例: showSundayColor -> _tintcal_show_sunday_color)
        $option_key = isset($props['option_key']) ? $props['option_key'] : 'tintcal_' . strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $js_key));
        $meta_key   = '_' . $option_key;

        $meta_value = get_post_meta($post_id, $meta_key, true);

        if ($props['type'] === 'bool') {
            // メタ値が保存されていなければ全体設定を、されていればその値(0か1)を使う
            $js_data[$js_key] = ($meta_value === '') ? (int)get_option($option_key, $props['default']) : (int)$meta_value;
        } else {
            // メタ値が保存されていなければ全体設定を、されていればその値を使う
            $js_data[$js_key] = !empty($meta_value) ? $meta_value : get_option($option_key, $props['default']);
        }
    }

    // --- 表示カテゴリのスラグを取得 ---
    $visible_slugs = get_post_meta($post_id, '_tintcal_visible_categories', true);

    // get_post_metaは空の配列を空文字列で返すことがあるため、is_arrayでチェック
    if (is_array($visible_slugs)) {
        $js_data['visibleCategories'] = $visible_slugs;
    } else {
        // メタデータが存在しない場合（ショートコードでID指定がない場合など）は、共通設定の表示カテゴリを適用
        $all_categories = get_option('tintcal_categories', []);
        $visible_in_main_settings = array_filter($all_categories, function($cat) { return ($cat['visible'] ?? true); });
        $js_data['visibleCategories'] = array_values(wp_list_pluck($visible_in_main_settings, 'slug'));
    }

    // --- 全カレンダーで共通のデータを追加 ---
    $js_data['pluginUrl']   = plugin_dir_url(__FILE__);
    $js_data['ajaxUrl']     = admin_url('admin-ajax.php');
    $js_data['nonce']       = wp_create_nonce('tintcal_settings_nonce');
    $js_data['categories'] = tintcal_get_displayable_categories();

    // JSON decode with error handling
    $assignments_raw = get_option('tintcal_date_categories', '{}');
    $assignments_decoded = json_decode($assignments_raw, true);
    $js_data['assignments'] = (json_last_error() === JSON_ERROR_NONE && is_array($assignments_decoded)) ? $assignments_decoded : [];

    // --- 祝日データを読み込んでJSに渡す（Transient APIでキャッシュ） ---
    $current_year = (int)date_i18n('Y');
    $years_to_load = [$current_year, $current_year + 1, $current_year + 2];
    $locale = 'ja';
    $upload_dir = wp_upload_dir();

    $cache_key = 'tintcal_holidays_' . implode('_', $years_to_load);
    $holidays_by_year = get_transient($cache_key);

    if (false === $holidays_by_year) {
        $holidays_by_year = [];
        $tintcal_dir = $upload_dir['basedir'] . '/tintcal-holidays';

        foreach ($years_to_load as $year) {
            $file_path = "{$tintcal_dir}/{$locale}/{$year}.json";
            $json_content = tintcal_read_file($file_path);
            if ($json_content !== false) {
                $holiday_data = json_decode($json_content, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($holiday_data)) {
                    $holidays_by_year[$year] = $holiday_data;
                }
            }
        }
        // Cache for 1 day
        set_transient($cache_key, $holidays_by_year, DAY_IN_SECONDS);
    }

    $js_data['holidays'] = $holidays_by_year;
    $js_data['locale'] = $locale;
    $js_data['holidayJsonUrl'] = $upload_dir['baseurl'] . '/tintcal-holidays';

    return $js_data;
}

// =============================
// フロント画面用のスクリプト・スタイル登録
// =============================

add_action('wp_enqueue_scripts', function () {
    // --- フロントエンドで使うスクリプトとスタイルを「登録」だけしておく ---
    wp_register_script(
        'tintcal-front',
        plugin_dir_url(__FILE__) . 'assets/js/calendar-front.js',
        [],
        TINTCAL_VERSION,
        true
    );
    wp_register_style(
        'tintcal-style-front',
        plugin_dir_url(__FILE__) . 'assets/css/calendar.css',
        [],
        TINTCAL_VERSION
    );

    // --- ここから下を「if」条件の外に出す ---
    if (is_singular('tintcal')) {
        $post_id = get_the_ID();
        $js_data = tintcal_build_js_data_for_frontend($post_id);
        wp_localize_script('tintcal-front', 'tintcalPluginData', $js_data);

        $json_data = json_encode($js_data, JSON_UNESCAPED_UNICODE);
        $unique_id = 'tintcal-instance-' . $post_id;

        $script = "document.addEventListener('DOMContentLoaded', function() { new TintCal('#" . esc_js($unique_id) . "', tintcalPluginData); });";
        wp_add_inline_script('tintcal-front', $script);

        wp_enqueue_script('tintcal-front');
        wp_enqueue_style('tintcal-style-front');
    } else {
        // ショートコードや他の場所でもカレンダーを使う場合はこちらでデータを渡す
        // 必要に応じて $post_id の取得方法や $js_data の内容を調整
        // 例: グローバルな設定だけ渡す場合
        $js_data = tintcal_build_js_data_for_frontend(null);
        wp_localize_script('tintcal-front', 'tintcalPluginData', $js_data);
        wp_enqueue_script('tintcal-front');
        wp_enqueue_style('tintcal-style-front');
    }
});

// =============================
// カレンダーショートコード表示
// =============================

function tintcal_display_calendar() {
  $start_day = get_option('tintcal_start_day', 'sunday');
  $enable_holidays = get_option('tintcal_enable_holidays', 1);
  $holiday_color = get_option('tintcal_holiday_color', '#ffdddd');
  ob_start();
  ?>
  <div id="tintcal-calendar-container">
    <input type="hidden" id="tintcal-start-day" value="<?php echo esc_attr($start_day); ?>">
    <input type="hidden" id="tintcal-enable-holidays" value="<?php echo esc_attr($enable_holidays); ?>">
    <input type="hidden" id="tintcal-holiday-color" value="<?php echo esc_attr($holiday_color); ?>">
    <input type="hidden" id="tintcal-sunday-color" value="<?php echo esc_attr(get_option('tintcal_sunday_color', '#ffecec')); ?>">
    <input type="hidden" id="tintcal-saturday-color" value="<?php echo esc_attr(get_option('tintcal_saturday_color', '#ecf5ff')); ?>">
    <input type="hidden" id="tintcal-show-sunday-color" value="<?php echo esc_attr(get_option('tintcal_show_sunday_color', 1)); ?>">
    <input type="hidden" id="tintcal-show-saturday-color" value="<?php echo esc_attr(get_option('tintcal_show_saturday_color', 1)); ?>">
    <div id="tintcal-calendar-controls">
    <button id="prev-month"><?php echo esc_html__( '＜ 前の月', 'tintcal' ); ?></button>
    <span id="tintcal-month-year"></span>
    <button id="next-month"><?php echo esc_html__( '次の月 ＞', 'tintcal' ); ?></button>
    </div>
    <table id="tintcal-calendar" border="1" cellspacing="0" cellpadding="5">
      <thead><tr></tr></thead>
      <tbody></tbody>
    </table>
    <div id="calendar-legend" style="margin-top:10px;"></div>
  </div>
  <?php
  return ob_get_clean();
}

// =============================
// Ajax系の処理
// =============================

/**
 * TintCal用のAJAXセキュリティチェック共通関数
 * - 投稿の編集権限（edit_posts）をチェック
 * - Nonceをチェック
 * チェックに失敗した場合は、JSONエラーを返して処理を中断します。
 */
function tintcal_ajax_security_check() {
  check_ajax_referer('tintcal_settings_nonce');
  
  if (!current_user_can('edit_posts')) {
    wp_send_json_error(['message' => esc_html__( 'この操作を行う権限がありません。', 'tintcal' )]);
  }
}

add_action('wp_ajax_save_tintcal_categories', function () {
  tintcal_ajax_security_check();
  
  // 1. まずは生のデータを取得
  // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce check is handled by tintcal_ajax_security_check(), and the value is sanitized in the loop below.
  $raw_categories_post = isset($_POST['categories']) ? wp_unslash($_POST['categories']) : '';

  // 2. デコード
  $raw = urldecode($raw_categories_post);
  $parsed = json_decode($raw, true);

  // 3. デコード後の形式をチェック
  if (!is_array($parsed)) {
    wp_send_json_error(['message' => esc_html__( '不正なデータ形式', 'tintcal' )]);
  }

  // 4. 配列の中身を一つずつサニタイズ
  $sanitized_categories = [];
  foreach ($parsed as $cat) {
      $sanitized_cat = [];
      $sanitized_cat['name'] = isset($cat['name']) ? sanitize_text_field($cat['name']) : '';
      $sanitized_cat['color'] = isset($cat['color']) ? sanitize_hex_color($cat['color']) : '#dddddd';
      $sanitized_cat['slug'] = isset($cat['slug']) ? sanitize_key($cat['slug']) : '';
      $sanitized_cat['visible'] = isset($cat['visible']) ? (bool)$cat['visible'] : true;
      $sanitized_cat['order'] = isset($cat['order']) ? absint($cat['order']) : 0;
      $sanitized_categories[] = $sanitized_cat;
  }
  $parsed = $sanitized_categories;

  // プラグイン仕様：シングルカテゴリのみサポート
  $tintcal_trimmed_to_single = false;
  if (count($parsed) > TINTCAL_FREE_MAX_CATEGORIES) {
      // 2件目以降は無視して、先頭の1件だけを保存対象にする（UIは別途で1件仕様に合わせる）
      $parsed = [ reset($parsed) ];
      $tintcal_trimmed_to_single = true;
  }

  // カテゴリスラグの重複バリデーション
  $slugs = array_column($parsed, 'slug');
  if (count(array_unique($slugs)) !== count($slugs)) {
    wp_send_json_error(['message' => esc_html__( 'カテゴリスラグが重複しています。', 'tintcal' )]);
  }

  // カテゴリ名の重複バリデーション
  $names = [];
  foreach ($parsed as $cat) {
    $name = isset($cat['name']) ? trim($cat['name']) : '';
    if ($name === '') continue;
    if (in_array($name, $names, true)) {
      // translators: %s: Category name.
      wp_send_json_error(['message' => sprintf( esc_html__( '（サーバー判定）カテゴリ名「%s」が重複しています。', 'tintcal' ), $name )]);
    }
    $names[] = $name;
  }

  // 各カテゴリに 'visible' プロパティを確実に設定し、データをサニタイズ（無害化）する
  foreach ($parsed as &$cat) {
    // 'visible'キーが存在し、その値が明確に false でない限り、常に true（表示）とする
    // これにより、古いデータや不正な値が保存されるのを防ぐ
    $cat['visible'] = isset($cat['visible']) ? (bool)$cat['visible'] : true;
  }
  unset($cat); // ループ後の参照を解除

  // DBに複数カテゴリが存在するのに、1つのカテゴリで上書きしようとしているかチェック
  $existing_categories = get_option('tintcal_categories', []);
  // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce check is handled by tintcal_ajax_security_check().
  $is_confirmed = !empty($_POST['confirmed']);

  if (
      count($existing_categories) > TINTCAL_FREE_MAX_CATEGORIES &&  // DBには複数のカテゴリがあり
      count($parsed) <= TINTCAL_FREE_MAX_CATEGORIES &&               // 保存しようとしているデータは上限以下で
      !$is_confirmed                                                 // かつ、まだユーザーの確認を得ていない場合
  ) {
      // 保存せずに、JavaScriptに確認を促すエラーを返す
      wp_send_json_error([
          'confirmation_required' => true,
          'message' => esc_html__( "現在、データベースには複数のカテゴリが保存されています。\n\nこのまま保存すると、表示されていない他のカテゴリが全て削除されますが、よろしいですか？", 'tintcal' )
      ]);
  }

  update_option('tintcal_categories', $parsed);

  // 追加：割当データも整理（スラグベースで割当を整理）
  $allowed_slugs = array_column($parsed, 'slug');
  $dateCategories = json_decode(get_option('tintcal_date_categories', '{}'), true) ?? [];

    foreach ($dateCategories as $date => $slugArr) {
    if (!is_array($slugArr)) $slugArr = [$slugArr];
    // 削除済みカテゴリスラグを除去
    $slugArr = array_values(array_filter($slugArr, function ($slug) use ($allowed_slugs) {
      return in_array($slug, $allowed_slugs, true);
    }));
    if (empty($slugArr)) {
      unset($dateCategories[$date]);
    } else {
      $dateCategories[$date] = $slugArr;
    }
  }
  update_option('tintcal_date_categories', json_encode($dateCategories, JSON_UNESCAPED_UNICODE));

  $response = [
    'saved' => true,
    'assignments' => $dateCategories
  ];
  if (isset($tintcal_trimmed_to_single) && $tintcal_trimmed_to_single) {
      $response['notice'] = esc_html__( 'このプラグインは1つのカテゴリのみをサポートしています。2つ目以降は保存されません。', 'tintcal' );
  }
  wp_send_json_success($response);
});

add_action('wp_ajax_save_tintcal_assignment', function () {
  tintcal_ajax_security_check();
  // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce check handled by tintcal_ajax_security_check().
  $date = isset($_POST['date']) ? sanitize_text_field(wp_unslash($_POST['date'])) : '';
  // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedInput.InputNotSanitized -- Nonce check is handled by tintcal_ajax_security_check() and the value is sanitized.
  $categories_json = isset($_POST['categories']) ? sanitize_text_field(wp_unslash($_POST['categories'])) : '[]';
  $categories = json_decode($categories_json, true);
  if (!is_array($categories)) $categories = [];
  // 各スラグをサニタイズ
  $categories = array_map('sanitize_key', $categories);
  
  $dateCategories = json_decode(get_option('tintcal_date_categories'), true);
  $dateCategories = is_array($dateCategories) ? $dateCategories : [];
  foreach ($dateCategories as $d => $cat) {
      if (!is_array($cat)) $dateCategories[$d] = [$cat];
  }
  if (empty($categories) || (is_array($categories) && count($categories) === 0)) {
        unset($dateCategories[$date]);
  } else {
        $dateCategories[$date] = $categories;
  }

  update_option('tintcal_date_categories', json_encode($dateCategories, JSON_UNESCAPED_UNICODE));
  
  // JSに返すためのカテゴリデータを準備する
  wp_send_json_success([
    'saved' => true,
    'assignments' => $dateCategories,
    'categories' => tintcal_get_displayable_categories()
  ]);
});

add_action('wp_ajax_save_tintcal_holidays', function () {
  tintcal_ajax_security_check();
  check_ajax_referer('tintcal_settings_nonce'); // Add this line to satisfy the plugin checker.

  // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
  $year = isset($_POST['year']) ? sanitize_text_field(wp_unslash($_POST['year'])) : '';
  $data = isset($_POST['holidays']) ? sanitize_text_field(wp_unslash($_POST['holidays'])) : '';
  $locale = isset($_POST['locale']) ? sanitize_text_field(wp_unslash($_POST['locale'])) : 'ja';
  // phpcs:enable

  if (!preg_match('/^\d{4}$/', $year)) {
    wp_send_json_error(['message' => esc_html__( '無効な年です', 'tintcal' )]);
  }
  
  $decoded_data = json_decode(stripslashes($data), true);
  if (!is_array($decoded_data)) {
      $decoded_data = [];
  }
  $sanitized_data = [];
  foreach($decoded_data as $date => $name) {
      $sanitized_date = sanitize_text_field($date);
      $sanitized_name = sanitize_text_field($name);
      $sanitized_data[$sanitized_date] = $sanitized_name;
  }

  $json = wp_json_encode($sanitized_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

  $upload_dir = wp_upload_dir();
  $tintcal_dir = $upload_dir['basedir'] . '/tintcal-holidays';
  $locale_dir = "{$tintcal_dir}/{$locale}";
  wp_mkdir_p($locale_dir);
  $file_path = $locale_dir . "/$year.json";

  $write_result = tintcal_write_file($file_path, $json);
  if ($write_result) {
      wp_send_json_success(['saved' => true, 'file' => $file_path]);
  } else {
      wp_send_json_error(['message' => esc_html__('ファイルの書き込みに失敗しました', 'tintcal')]);
  }
});

add_action('wp_ajax_get_tintcal_categories', function () {
  tintcal_ajax_security_check();
  // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce check handled by tintcal_ajax_security_check().
  // phpcs:ignore WordPress.Security.ValidatedInput.InputNotValidated, WordPress.Security.ValidatedInput.InputNotSanitized -- No user input is directly processed.
  $cats = get_option('tintcal_categories', []);
  if (!is_array($cats)) $cats = [];
  wp_send_json_success($cats);
});

add_action('wp_ajax_get_tintcal_assignments', function () {
  tintcal_ajax_security_check();
  // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce check handled by tintcal_ajax_security_check().
  // phpcs:ignore WordPress.Security.ValidatedInput.InputNotValidated, WordPress.Security.ValidatedInput.InputNotSanitized -- No user input is directly processed.
  $assignments = json_decode(get_option('tintcal_date_categories', '{}'), true);
  wp_send_json_success($assignments);
});

add_action('wp_ajax_reload_tintcal_holidays', function () {
    tintcal_ajax_security_check();
  // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedInput.InputNotValidated, WordPress.Security.ValidatedInput.InputNotSanitized -- Nonce check handled by tintcal_ajax_security_check(). No user input is directly processed.

    // Clear holiday data cache before updating
    $current_year = (int)date_i18n('Y');
    $years_to_load = [$current_year, $current_year + 1, $current_year + 2];
    $cache_key = 'tintcal_holidays_' . implode('_', $years_to_load);
    delete_transient($cache_key);

    // 祝日ファイル更新処理を実行
    $results = tintcal_update_holiday_files(true);

    // 更新後の最新の祝日データを読み込む
    $holidays_by_year = [];
    $current_year = (int)date_i18n('Y');
    $years_to_load = [$current_year, $current_year + 1, $current_year + 2];
    $locale = 'ja';
    $upload_dir = wp_upload_dir();
    $tintcal_dir = $upload_dir['basedir'] . '/tintcal-holidays';

    foreach ($years_to_load as $year) {
        $file_path = "{$tintcal_dir}/{$locale}/{$year}.json";
        $json_content = tintcal_read_file($file_path);
        if ($json_content !== false) {
            $holiday_data = json_decode($json_content, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($holiday_data)) {
                $holidays_by_year[$year] = $holiday_data;
            }
        }
    }

    // 成功応答に、最新の祝日データと処理結果の両方を含めて返す
    wp_send_json_success([
        'message'   => esc_html__( '祝日再取得完了', 'tintcal' ),
        'results'   => $results,
        'holidays'  => $holidays_by_year
    ]);
});

add_action('wp_ajax_reset_tintcal_assignments', function () {
  tintcal_ajax_security_check();
  // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedInput.InputNotValidated, WordPress.Security.ValidatedInput.InputNotSanitized -- Nonce check handled by tintcal_ajax_security_check(). No user input is directly processed.

  update_option('tintcal_date_categories', '{}');
  wp_send_json_success( esc_html__( '日付割り当てデータを初期化しました', 'tintcal' ) );
});

// 全データ初期化（カテゴリ・日付割当をすべて削除）
add_action('wp_ajax_reset_tintcal_all', function () {
  tintcal_ajax_security_check();
  // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedInput.InputNotValidated, WordPress.Security.ValidatedInput.InputNotSanitized -- Nonce check handled by tintcal_ajax_security_check(). No user input is directly processed.
  
  update_option('tintcal_categories', []);
  update_option('tintcal_date_categories', '{}');
  wp_send_json_success( esc_html__( 'カテゴリと日付割当データをすべて初期化しました', 'tintcal' ) );
});

/**
 * 外部APIから祝日データを取得し、ローカルにJSONファイルとして保存する関数
 *
 * @param bool $return_results AJAXレスポンス用に結果を返すかどうか
 * @return array|void AJAX用に結果を返す場合は、処理結果の配列を返す
 */
function tintcal_update_holiday_files($return_results = false) {
    $results = [];
    $current_year = (int)date_i18n('Y');
    $years = [$current_year, $current_year + 1, $current_year + 2];
    $locale = 'ja';

    $upload_dir = wp_upload_dir();
    $tintcal_dir = $upload_dir['basedir'] . '/tintcal-holidays';
    $locale_dir = "{$tintcal_dir}/{$locale}";
    wp_mkdir_p($locale_dir);

    foreach ($years as $year) {
        $url = "https://holidays-jp.github.io/api/v1/{$year}/date.json";
        // WordPressのHTTP APIを使用して安全にデータを取得
        $response = wp_remote_get($url, ['timeout' => 15]);

        // 取得失敗時の処理
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            $results[$year] = ['success' => false, 'error' => esc_html__( 'APIへの接続に失敗しました', 'tintcal' )];
            continue; // 次の年へ
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        // JSONデータが不正な場合の処理
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            $results[$year] = ['success' => false, 'error' => esc_html__( '取得したJSONデータが不正です', 'tintcal' )];
            continue; // 次の年へ
        }

        // ファイルに保存
        $file_path = $locale_dir . "/{$year}.json";
        $json_content = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $write_result = tintcal_write_file($file_path, $json_content);

        $results[$year] = [
            'success'     => $write_result,
            'path'        => $file_path,
            'error'       => $write_result ? '' : esc_html__( 'ファイルの書き込みに失敗しました', 'tintcal' )
        ];
    }

    // AJAX呼び出しの場合、結果を返す
    if ($return_results) {
        return $results;
    }
}

// =============================
// エクスポート・インポート処理
// =============================

add_action('admin_init', function() {
  // --- エクスポート処理 ---
  if (isset($_POST['tintcal_export'])) {
    if (!current_user_can('edit_posts')) {
      wp_die( esc_html__( 'このページにアクセスする権限がありません。', 'tintcal' ) );
    }
    check_admin_referer('tintcal_settings_nonce');

    $categories = get_option('tintcal_categories', []);
    foreach ($categories as &$cat) {
      if (empty($cat['slug'])) {
        $cat['slug'] = wp_generate_uuid4();
      }
    }
    unset($cat);
    
    $assignments = json_decode(get_option('tintcal_date_categories', '{}'), true);
    if (!is_array($assignments)) $assignments = [];
    foreach ($assignments as $date => $cat) {
      if (!is_array($cat)) $assignments[$date] = [$cat];
    }
    $data = ['categories' => $categories, 'assignments' => $assignments];
    $json = wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $date_str = wp_date('Y-m-d', current_datetime()->getTimestamp());
    $filename = "tintcal-export-{$date_str}.json";
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- This is a JSON export, not HTML output. Escaping would corrupt the JSON.
    echo $json;
    exit;
  }

  
  if (isset($_POST['tintcal_import']) && isset($_FILES['tintcal_import_file'])) {
    if (!current_user_can('edit_posts')) {
      wp_die( esc_html__( 'このページにアクセスする権限がありません。', 'tintcal' ) );
    }
    check_admin_referer('tintcal_settings_nonce');
    // Nonce と権限チェックは既にされているため、ここではサニタイズとスラッシュ除去に集中
    $file_path = '';
    if (isset($_FILES['tintcal_import_file']['tmp_name']) && is_string($_FILES['tintcal_import_file']['tmp_name'])) {
        // phpcs:ignore WordPress.Security.ValidatedInput.InputNotValidated, WordPress.Security.ValidatedInput.MissingUnslash -- $_FILES array is handled by WordPress, and the path is sanitized.
        $file_path = sanitize_text_field($_FILES['tintcal_import_file']['tmp_name']);
    }

    if (empty($file_path) || !file_exists($file_path)) {
        // エラー処理（ファイルがない場合）
        wp_safe_redirect(admin_url('admin.php?page=tintcal-preference&import=error'));
        exit;
    }

    // Note: Using file_get_contents for uploaded temp files is acceptable per WordPress standards
    $json = file_get_contents($file_path);
    if ($json === false) {
        wp_safe_redirect(admin_url('admin.php?page=tintcal-preference&import=error'));
        exit;
    }

    $data = json_decode($json, true);
    if (
      json_last_error() !== JSON_ERROR_NONE ||
      !is_array($data) ||
      !isset($data['categories']) || !is_array($data['categories']) ||
      !isset($data['assignments']) || !is_array($data['assignments'])
    ) {
      // 形式が違う場合はエラー画面にリダイレクトして中止
      if (function_exists('wp_safe_redirect')) {
        wp_safe_redirect(admin_url('admin.php?page=tintcal-preference&import=error'));
        exit;
      } else {
        header('Location: ' . admin_url('admin.php?page=tintcal-preference&import=error'));
        exit;
      }
    }
    $newCategories = $data['categories'];
    
    // --- スラグ正規化・重複排除 ---
    $currentCategories = get_option('tintcal_categories', []);
    $slugMap = []; // 古い slug → 新しい slug を記録するマップ
    if (!is_array($currentCategories)) $currentCategories = [];
    $existingSlugs = array_column($currentCategories, 'slug');
    $newSeenSlugs = [];
    foreach ($newCategories as &$cat) {
      // スラグ未設定または重複なら新規発行
      $slug = isset($cat['slug']) ? $cat['slug'] : '';
      // 既存または新規内で重複していたら新規発行
      if (empty($slug) || in_array($slug, $newSeenSlugs, true)) {
        $slug = wp_generate_uuid4();
      }
      $cat['slug'] = $slug;
      $newSeenSlugs[] = $slug;
      $existingSlugs[] = $slug;
    }
    unset($cat);

    // assignments優先、なければassigned_datesをfallback
    if (isset($data['assignments']) && is_array($data['assignments'])) {
      $newAssignments = $data['assignments'];
    } elseif (isset($data['assigned_dates']) && is_array($data['assigned_dates'])) {
      $newAssignments = $data['assigned_dates'];
    } else {
      $newAssignments = [];
    }

    // assignments の値を、oldSlug→newSlug 置換後にフィルタリング
    $allowedSlugs = array_column($newCategories, 'slug');
    foreach ($newAssignments as $date => $catArr) {
      // 1. 配列化：単一文字列なら配列に
      if (!is_array($catArr)) {
        $catArr = [$catArr];
      }
      $processed = [];
      // 2. 1つずつスラグをチェック
      foreach ($catArr as $slug) {
        // a. 古い slug がマップにあれば新しい slug に置換
        if (isset($slugMap[$slug]) && $slugMap[$slug] !== '') {
          $newSlug = $slugMap[$slug];
        } else {
          // マップにない場合はそのままの slug を使う
          $newSlug = $slug;
        }
        // b. 置換後の slug が allowedSlugs に含まれるときだけ残す
        if (in_array($newSlug, $allowedSlugs, true)) {
          $processed[] = $newSlug;
        }
      }
      // 3. 重複を排除して最終セット
      $newAssignments[$date] = array_values(array_unique($processed));
    }

    if (!empty($newCategories) && !empty($newAssignments)) {
      $append = isset($_POST['import_append_mode']);
      // $currentCategories, $existingSlugs already loaded above
      $currentAssignmentsRaw = get_option('tintcal_date_categories', '{}');
      $currentAssignments = json_decode($currentAssignmentsRaw, true);
      $currentAssignments = is_array($currentAssignments) ? $currentAssignments : [];
      if ($append) {
        // --- appendモード: 同名カテゴリのリネーム・ID振り直し ---
        $currentNames = array_column($currentCategories, 'name');
        $currentIds   = array_column($currentCategories, 'id');
        $maxId = !empty($currentIds) ? max($currentIds) : 0;

        $nameMap = []; // [oldId => newId]
        foreach ($newCategories as &$newCat) {
          $originalName = $newCat['name'];
          $originalId   = $newCat['id'];
          $originalSlug = $newCat['slug'];
          $slugMap[$originalSlug] = '';

          // a. 同名が存在する場合、(2)〜(10)でユニーク名を付与
          $tryName = $originalName;
          $suffix  = 2;
          while (in_array($tryName, $currentNames) && $suffix <= 10) {
            $tryName = $originalName . '(' . $suffix . ')';
            $suffix++;
          }
          if (in_array($tryName, $currentNames)) {
            // 10個超ならエラーでスキップ
            continue; // 追加せずスキップ
          }
          // b. IDも重複する場合は新IDを付与
          $tryId = $originalId;
          if (in_array($tryId, $currentIds)) {
            $maxId++;
            $tryId = $maxId;
          }
          // c. スラグも既存や追加内で重複しないように再割当
          $trySlug = $originalSlug;
          while (
            empty($trySlug)
            || in_array($trySlug, $existingSlugs, true)
            || in_array($trySlug, $newSeenSlugs, true)
          ) {
            $trySlug = wp_generate_uuid4();
          }
          $slugMap[$originalSlug] = $trySlug;
          $newCat['name'] = $tryName;
          $newCat['id']   = $tryId;
          $newCat['slug'] = $trySlug;
          $currentCategories[] = $newCat;
          $currentNames[] = $tryName;
          $currentIds[]   = $tryId;
          $existingSlugs[] = $trySlug;
          $newSeenSlugs[] = $trySlug;
          $nameMap[$originalId] = $tryId; // インポート側id→新idマップ
        }
        unset($newCat);

        // --- 古い slug を新しい slug に置き換えつつフィルタリングする処理 ---
        // 1. 最終的に許可される「新しいスラグ」の一覧を取得
        $allowedSlugs = array_column($newCategories, 'slug');

        // 2. $newAssignments を一つひとつチェック
        foreach ($newAssignments as $date => $catArr) {
            // (2-1) 配列化：もし文字列としてスラグが入っていたら配列に変換
            if (!is_array($catArr)) {
                $catArr = [$catArr];
            }
            $processed = [];

            // (2-2) 各スラグをチェック
            foreach ($catArr as $slug) {
                // 古いスラグが $slugMap に登録されていれば、新しいスラグを取得
                if (isset($slugMap[$slug]) && $slugMap[$slug] !== '') {
                    $newSlug = $slugMap[$slug];
                } else {
                    // マップがなければ、そのままの slug を使う
                    $newSlug = $slug;
                }
                // (2-3) 最終的に許可されるスラグ（$allowedSlugs）の中に含まれていれば残す
                if (in_array($newSlug, $allowedSlugs, true)) {
                    $processed[] = $newSlug;
                }
            }

            // (2-4) 重複を排除して $newAssignments にセットし直す
            $newAssignments[$date] = array_values(array_unique($processed));
        }

        // 5. assignmentsをマージ（スラグベースでユニーク化）
        $mergedAssignments = is_array($currentAssignments) ? $currentAssignments : [];
        foreach ($newAssignments as $date => $slugs) {
            // それぞれ必ず配列として扱う
            if (!is_array($slugs)) {
                $slugs = [$slugs];
            }
            if (isset($mergedAssignments[$date]) && is_array($mergedAssignments[$date])) {
                // 既存スラグ配列とマージしてユニーク化
                $all = array_merge($mergedAssignments[$date], $slugs);
                $mergedAssignments[$date] = array_values(array_unique($all));
            } else {
                $mergedAssignments[$date] = $slugs;
            }
        }

        // 6. 保存
        // --- orderを再採番して重複をなくす ---
        $reordered_categories = array_values($currentCategories); // インデックスをリセット
        foreach ($reordered_categories as $index => &$category) {
            $category['order'] = $index;
        }
        unset($category); // 参照を解除
        update_option('tintcal_categories', $reordered_categories);
        $encoded = json_encode($mergedAssignments, JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
          $encoded = '{}'; // フォールバック
        }
        update_option('tintcal_date_categories', $encoded);
      } else {
        // 上書きモード: $newCategoriesはスラグ正規化済み
        // --- orderを再採番して重複をなくす ---
        $reordered_categories = array_values($newCategories); // インデックスをリセット
        foreach ($reordered_categories as $index => &$category) {
            $category['order'] = $index;
        }
        unset($category); // 参照を解除
        update_option('tintcal_categories', $reordered_categories);
        update_option('tintcal_date_categories', json_encode($newAssignments, JSON_UNESCAPED_UNICODE));
      }
      if (function_exists('wp_safe_redirect')) {
        wp_safe_redirect(admin_url('admin.php?page=tintcal-preference&import=success'));
        exit;
      } else {
        header('Location: ' . admin_url('admin.php?page=tintcal-preference&import=success'));
        exit;
      }
    } else {
      if (function_exists('wp_safe_redirect')) {
        wp_safe_redirect(admin_url('admin.php?page=tintcal-preference&import=error'));
        exit;
      } else {
        header('Location: ' . admin_url('admin.php?page=tintcal-preference&import=error'));
        exit;
      }
    }
  }
});


/**
 * カレンダーの基本HTML構造を出力する
 * IDが渡された場合は、そのカレンダーの個別設定を反映する
 *
 * @param int|null $post_id カレンダーの投稿ID
 * @return string カレンダーのHTML
 */
function tintcal_render_calendar_base_html($post_id = null) {
    if ($post_id) {
        $start_day                 = get_post_meta($post_id, '_tintcal_start_day', true) ?: get_option('tintcal_start_day', 'sunday');
        $show_header_weekend_color = get_post_meta($post_id, '_tintcal_show_header_weekend_color', true);
        if ($show_header_weekend_color === '') { $show_header_weekend_color = get_option('tintcal_show_header_weekend_color', 1); }
        $show_today_button         = get_post_meta($post_id, '_tintcal_show_today_button', true);
        if ($show_today_button === '') { $show_today_button = get_option('tintcal_show_today_button', 1); }
    } else {
        $start_day                 = get_option('tintcal_start_day', 'sunday');
        $show_header_weekend_color = get_option('tintcal_show_header_weekend_color', 1);
        $show_today_button         = get_option('tintcal_show_today_button', 1);
    }

    ob_start();
    ?>
    <div class="tintcal-calendar-container-inner">
      <input type="hidden" class="tintcal-start-day" value="<?php echo esc_attr($start_day); ?>">
      <input type="hidden" class="tintcal-show-header-weekend-color" value="<?php echo esc_attr($show_header_weekend_color); ?>">

      <div class="tintcal-calendar-controls" style="display: flex; justify-content: space-between; align-items: center;">
        <div>
          <button type="button" class="prev-month"><?php echo esc_html__( '＜ 前の月', 'tintcal' ); ?></button>
        </div>
        <div style="display: flex; align-items: center; gap: 15px;">
          <span class="tintcal-month-year"></span>
          <?php if ($show_today_button == 1): ?>
            <button type="button" class="back-to-today" style="font-size: 11px; padding: 2px 6px; height: auto;"><?php echo esc_html__( '今月に戻る', 'tintcal' ); ?></button>
          <?php endif; ?>
        </div>
        <div>
          <button type="button" class="next-month"><?php echo esc_html__( '次の月 ＞', 'tintcal' ); ?></button>
        </div>
      </div>
      <table class="tintcal-calendar" border="1" cellspacing="0" cellpadding="5">
        <thead><tr></tr></thead>
        <tbody></tbody>
      </table>
      <div class="calendar-legend" style="margin-top:10px;"></div>
    </div>
    <?php
    return ob_get_clean();
}

// =============================
// WP-Cronによる祝日自動更新
// =============================

/**
 * 祝日更新のスケジュールイベント名
 */
define('TINTCAL_CRON_HOOK', 'tintcal_daily_holiday_update_hook');

/**
 * WP-Cronのスケジュールに、祝日更新処理を紐付ける
 */
add_action(TINTCAL_CRON_HOOK, 'tintcal_update_holiday_files');

/**
 * プラグイン有効化時に、祝日更新のスケジュールと、管理者権限の登録を行う関数
 */
function tintcal_schedule_holiday_update() {
    // --- スケジュール登録 ---
    if (!wp_next_scheduled(TINTCAL_CRON_HOOK)) {
        wp_schedule_event(time(), 'daily', TINTCAL_CRON_HOOK);
    }

    // --- 管理者ロールにカスタム権限を付与 ---
    $role = get_role('administrator');
    if ($role) {
        $tintcal_caps = [
            'edit_tintcal',
            'read_tintcal',
            'delete_tintcal',
            'edit_tintcals',
            'edit_others_tintcals',
            'publish_tintcals',
            'read_private_tintcals',
            'delete_tintcals',
            'delete_private_tintcals',
            'delete_published_tintcals',
            'delete_others_tintcals',
            'edit_private_tintcals',
            'edit_published_tintcals',
        ];
        foreach ($tintcal_caps as $cap) {
            $role->add_cap($cap);
        }
    }
}

/**
 * プラグイン無効化時に、登録したスケジュールを解除する関数
 */
function tintcal_unschedule_holiday_update() {
    wp_clear_scheduled_hook(TINTCAL_CRON_HOOK);
}

/**
 * プラグインの有効化・無効化フックに上記の関数を登録
 */
register_activation_hook(__FILE__, 'tintcal_schedule_holiday_update');
register_deactivation_hook(__FILE__, 'tintcal_unschedule_holiday_update');
