<?php
/*
Plugin Name:      TintCal
Plugin URI:       https://tintcal.com
Description:      「定休日」や「イベント日」といった予定を自由に設定し、日付をカラフルに色分け。日本の祝日にも対応した、見た目がわかりやすいオリジナルカレンダーを作成できます。
Version:          2.1
Requires at least: 5.8
Requires PHP:     7.4
Author:           QuantaLumina
Author URI:       https://nummit.jp
License:          GPL v2 or later
License URI:      https://www.gnu.org/licenses/gpl-2.0.html
Text Domain:      tintcal
Domain Path:      /languages
*/

// =============================
// JWT検証用関数（RS256, PHP標準のみ）
// =============================
function verify_jwt_rs256($jwt, $public_key_path) {
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) return false;
    list($header_b64, $payload_b64, $signature_b64) = $parts;
    $header = json_decode(base64url_decode($header_b64), true);
    $payload = json_decode(base64url_decode($payload_b64), true);
    $signature = base64url_decode($signature_b64);
    $data = $header_b64 . '.' . $payload_b64;
    $public_key = file_get_contents($public_key_path);
    $verified = openssl_verify($data, $signature, $public_key, OPENSSL_ALGO_SHA256);
    if ($verified === 1) {
        return $payload;
    } else {
        return false;
    }
}
function base64url_decode($data) {
    $remainder = strlen($data) % 4;
    if ($remainder) {
        $padlen = 4 - $remainder;
        $data .= str_repeat('=', $padlen);
    }
    return base64_decode(strtr($data, '-_', '+/'));
}

// =============================
// ライセンス認証・JWT検証・キャッシュ
// =============================
function jcalendar_get_license_status() {
    $transient_key = 'jcalendar_license_jwt';
    $jwt = get_transient($transient_key);
    $public_key_path = plugin_dir_path(__FILE__) . 'assets/license/public_key.pem';
    $license_key = get_option('jcalendar_license_key', '');
    $site_url = home_url();

    // キャッシュがなければAPIリクエスト
    if (!$jwt && $license_key) {
        $jwt = jcalendar_request_jwt_from_server($license_key, $site_url);
        if ($jwt) {
            set_transient($transient_key, $jwt, 24 * HOUR_IN_SECONDS);
        }
    }
    if ($jwt) {

        // ★追加：公開鍵の存在チェック
        if (!file_exists($public_key_path)) {
            return false;
        }

        $payload = verify_jwt_rs256($jwt, $public_key_path);


        // 有効期限チェック
        if ($payload && isset($payload['exp']) && $payload['exp'] > time()) {
            return $payload;
        } else {
            // 期限切れや不正ならキャッシュ削除
            delete_transient($transient_key);
        }
    }
    return false;
}

function jcalendar_request_jwt_from_server($license_key, $site_url) {

    $endpoint = 'https://jp-calendar-license-185317068700.asia-northeast1.run.app/verify'; // ←本番URLに差し替え
    $body = [
        'license_key' => $license_key,
        'site_url' => $site_url
    ];


    $response = wp_remote_post($endpoint, [
        'timeout' => 10,
        'headers' => ['Content-Type' => 'application/json'],
        'body' => json_encode($body)
    ]);

    // is_wp_error() で通信自体が失敗したかチェック
    if (is_wp_error($response)) {
        return false;
    }

    // デバッグ：サーバーからの応答をログに出力
    $code = wp_remote_retrieve_response_code($response);
    $data = wp_remote_retrieve_body($response);

    if ($code === 200 && !empty($data)) {
        $json = json_decode($data, true);
        if (isset($json['jwt'])) {
            return $json['jwt'];
        } elseif (is_string($data) && strpos($data, '.') !== false) {
            // JWTのみ返す場合
            return $data;
        }
    }
    return false;
}

function jcalendar_is_license_valid() {
    $payload = jcalendar_get_license_status();
    
    // 1. payload自体が存在するか
    // 2. statusが 'active' であるか
    // 3. 有効期限(exp)が未来であるか
    // この3つすべてを満たす場合のみ「有効」と判断する
    return (
        $payload &&
        isset($payload['status']) && $payload['status'] === 'active' &&
        isset($payload['exp']) && $payload['exp'] > time()
    );
}

/**
 * 表示用のカテゴリリストを取得・整理して返すヘルパー関数
 * - DBからカテゴリを取得
 * - order順にソート
 * - ライセンスが無効なら1つに絞り込む
 * @return array 整頓済みのカテゴリ配列
 */
function jcalendar_get_displayable_categories() {
    // 1. DBからカテゴリを取得
    $categories = get_option('jcalendar_categories', []);
    if (!is_array($categories)) {
        return [];
    }

    // 2. orderプロパティを基準にカテゴリを昇順で並び替える
    usort($categories, function($a, $b) {
        $order_a = isset($a['order']) ? (int)$a['order'] : 0;
        $order_b = isset($b['order']) ? (int)$b['order'] : 0;
        return $order_a <=> $order_b;
    });

    // 3. ライセンスが無効な場合、リストを先頭の1つだけに絞り込む
    if (!jcalendar_is_license_valid()) {
        $categories = array_slice($categories, 0, 1);
    }

    // 4. 最終的に整頓されたカテゴリリストを返す
    return $categories;
}

define('JCALENDAR_VERSION', '2.1'); //

$includes_path = plugin_dir_path(__FILE__) . 'includes/';

require_once $includes_path . 'admin-menu-jcalendar.php';
require_once $includes_path . 'meta-box-jcalendar.php';
require_once $includes_path . 'save-meta-jcalendar.php';
require_once $includes_path . 'list-view-jcalendar.php';
require_once $includes_path . 'post-type-jcalendar.php';
require_once $includes_path . 'shortcode-frontend-jcalendar.php';


// =============================
// 管理画面用の通知・メニュー登録
// =============================

if (isset($_GET['updated']) && $_GET['updated'] == 1) {
  add_action('admin_notices', function () {
    echo '<div class="notice notice-success is-dismissible"><p>設定を保存しました。</p></div>';
  });
}

if (isset($_GET['import']) && $_GET['import'] === 'success') {
  add_action('admin_notices', function () {
    echo '<div class="notice notice-success is-dismissible"><p>✅ カテゴリと日付データをインポートしました。</p></div>';
  });
}

if (isset($_GET['import']) && $_GET['import'] === 'error') {
  add_action('admin_notices', function () {
    echo '<div class="notice notice-error is-dismissible"><p>⚠️ インポートファイルの形式が不正です。</p></div>';
  });
}

if (isset($_GET['jcal_updated']) && $_GET['jcal_updated'] == 1) {
  add_action('admin_notices', function () {
    echo '<div class="notice notice-success is-dismissible"><p>設定を保存しました。</p></div>';
  });
}

/**
 * プラグイン設定ページ（ライセンス・ロール権限）の保存・解除処理
 */
add_action('admin_init', function() {
    // --- ライセンスキーの保存処理 ---
    if (isset($_POST['jcalendar_save_license_settings'])) {
        check_admin_referer('jcalendar_license_settings_nonce');
        if (!current_user_can('manage_options')) {
            wp_die('この操作を行う権限がありません。');
        }
        $license_key = sanitize_text_field($_POST['jcalendar_license_key']);
        update_option('jcalendar_license_key', $license_key);
        delete_transient('jcalendar_license_jwt');
        wp_safe_redirect(admin_url('admin.php?page=jcalendar-config&jcal_updated=1'));
        exit;
    }

    // --- ライセンスの解除処理（修正版） ---
    if (isset($_POST['jcalendar_deactivate_license'])) {
        check_admin_referer('jcalendar_license_settings_nonce');
        if (!current_user_can('manage_options')) {
            wp_die('この操作を行う権限がありません。');
        }

        // ステップ1: サーバーにディアクティベート通知を送る
        $jwt = get_transient('jcalendar_license_jwt');
        if ($jwt) {
            $endpoint = 'https://jp-calendar-license-185317068700.asia-northeast1.run.app/deactivate'; // 解除用エンドポイント
            $response = wp_remote_post($endpoint, [
                'timeout' => 10,
                'headers' => [
                    'Authorization' => 'Bearer ' . $jwt,
                ],
            ]);

            // デバッグログでサーバーからの応答を確認
            if (is_wp_error($response)) {
            } else {
                $code = wp_remote_retrieve_response_code($response);
                $body = wp_remote_retrieve_body($response);
            }
        } else {
        }

        // ステップ2: ローカルのライセンス情報を削除（サーバーへの通知成否に関わらず実行）
        delete_option('jcalendar_license_key');
        delete_transient('jcalendar_license_jwt');

        // ステップ3: ページをリロード
        wp_safe_redirect(admin_url('admin.php?page=jcalendar-config&jcal_updated=1'));
        exit;
    }
    
    // --- ロール権限設定の保存処理 ---
    if (isset($_POST['action']) && $_POST['action'] === 'jcalendar_save_role_settings') {
        check_admin_referer('jcalendar_role_settings_nonce');
        if (!current_user_can('manage_options')) {
            wp_die('権限がありません。');
        }
        $permissions = isset($_POST['permissions']) ? (array) $_POST['permissions'] : [];
        $sanitized_permissions = [];
        foreach ($permissions as $role => $caps) {
            $sanitized_role = sanitize_key($role);
            $sanitized_permissions[$sanitized_role] = [
                'manage_common_settings' => isset($caps['manage_common_settings']) ? true : false,
                'manage_calendars'       => isset($caps['manage_calendars']) ? true : false,
            ];
        }
        update_option('jcalendar_role_permissions', $sanitized_permissions);

        // WordPressの権限システムに実際のcapabilityを登録/削除する
        $role = get_role('editor');
        if ($role) {
            // カレンダー投稿タイプを管理するために必要なWordPressのcapabilityのリスト
            $jcal_caps = [
                'edit_jcalendar',
                'read_jcalendar',
                'delete_jcalendar',
                'edit_jcalendars',
                'edit_others_jcalendars',
                'publish_jcalendars',
                'read_private_jcalendars',
                'delete_jcalendars',
                'delete_private_jcalendars',
                'delete_published_jcalendars',
                'delete_others_jcalendars',
                'edit_private_jcalendars',
                'edit_published_jcalendars',
            ];

            // 「個別カレンダー編集の管理」のチェック状態に応じて権限を付与または削除
            if (!empty($sanitized_permissions['editor']['manage_calendars'])) {
                foreach ($jcal_caps as $cap) {
                    $role->add_cap($cap);
                }
            } else {
                foreach ($jcal_caps as $cap) {
                    $role->remove_cap($cap);
                }
            }
        }

        wp_safe_redirect(admin_url('admin.php?page=jcalendar-config&jcal_updated=1'));
        exit;
    }
});

// =============================
// 管理画面 設定ページの描画
// =============================

/**
 * 個別カレンダー設定画面のレンダリングと保存処理
 */
function jcalendar_render_preference_page() {
    
    // 必要なら管理者権限だけチェック
    if ( ! jcalendar_current_user_can_access('manage_common_settings') ) {
        wp_die('このページにアクセスする権限がありません。');
    }

    // --- [UUID slug migration] 既存カテゴリ全件にslugフィールド付与 ---
    $categories = get_option('jcalendar_categories', []);
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
        update_option('jcalendar_categories', $categories);
    }

    // 設定保存処理
    if ( isset($_POST['jcalendar_save_settings']) && check_admin_referer('jcalendar_settings_nonce') ) {

        // カラー以外の設定は、ライセンス状況に関わらず常に保存する
        update_option('jcalendar_start_day', sanitize_text_field($_POST['start_day']));
        update_option('jcalendar_enable_holidays', isset($_POST['enable_holidays']) ? 1 : 0);
        update_option('jcalendar_show_sunday_color', isset($_POST['show_sunday_color']) ? 1 : 0);
        update_option('jcalendar_show_saturday_color', isset($_POST['show_saturday_color']) ? 1 : 0);
        update_option('jcalendar_show_legend', isset($_POST['show_legend']) ? 1 : 0);
        update_option('jcal_show_header_weekend_color', isset($_POST['jcal_show_header_weekend_color']) ? 1 : 0);
        update_option('jcal_show_today_button', isset($_POST['jcal_show_today_button']) ? 1 : 0);

        // カラー設定は、Pro版ユーザーの場合のみ保存する
        if (jcalendar_is_license_valid()) {
            update_option('jcalendar_header_color', sanitize_hex_color($_POST['header_color']));
            update_option('jcalendar_holiday_color', sanitize_hex_color($_POST['holiday_color']));
            update_option('jcalendar_sunday_color', sanitize_hex_color($_POST['sunday_color']));
            update_option('jcalendar_saturday_color', sanitize_hex_color($_POST['saturday_color']));
        }
        
        add_settings_error('jcalendar_messages', 'saved', '設定を保存しました。', 'updated');
        // 保存後は同じ個別設定ページにリダイレクト
        wp_safe_redirect(admin_url('admin.php?page=jcalendar-preference&updated=1' . (isset($_POST['active_tab']) ? '&hash=' . urlencode($_POST['active_tab']) : '')));
        exit;
    }

    // 設定画面の表示
    global $includes_path;
    include $includes_path . 'preference-view-jcalendar.php';
}

/**
 * 管理画面のJavaScriptに渡すための設定データを構築する共通関数
 *
 * @param int|null $post_id カレンダー投稿のID（新規作成や一括設定画面ではnull）
 * @return array JavaScriptに渡す設定データの連想配列
 */
function jcalendar_build_js_data_for_admin($post_id = null) {
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
        'showHeaderWeekendColor' => ['type' => 'bool',   'default' => 1, 'option_key' => 'jcal_show_header_weekend_color'],
        'showTodayButton'        => ['type' => 'bool',   'default' => 1, 'option_key' => 'jcal_show_today_button'],
    ];

    if ($post_id) {
        // 【編集画面の場合】
        foreach ($setting_keys as $js_key => $props) {
            // DB上のキー名を生成
            $option_key = isset($props['option_key']) ? $props['option_key'] : 'jcalendar_' . strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $js_key));
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
        $visible_slugs = get_post_meta($post_id, '_jcalendar_visible_categories', true);
        $js_data['visibleCategories'] = is_array($visible_slugs) ? $visible_slugs : [];
    } else {
        // 【新規作成画面 or 一括設定画面の場合】
        foreach ($setting_keys as $js_key => $props) {
            $option_key = $props['option_key'] ?? 'jcalendar_' . strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $js_key));
            $js_data[$js_key] = get_option($option_key, $props['default']);
        }
        $all_categories = get_option('jcalendar_categories', []);
        $visible_in_main_settings = array_filter($all_categories, function($cat) { return $cat['visible'] ?? true; });
        $js_data['visibleCategories'] = array_values(wp_list_pluck($visible_in_main_settings, 'slug'));
    }

    // 全ての管理画面で共通して渡すデータ
    $js_data['pluginUrl']   = plugin_dir_url(__FILE__);
    $js_data['ajaxUrl']     = admin_url('admin-ajax.php');
    $js_data['nonce']       = wp_create_nonce('jcalendar_settings_nonce');
    $js_data['categories'] = jcalendar_get_displayable_categories();
    $js_data['assignments'] = json_decode(get_option('jcalendar_date_categories', '{}'), true) ?: [];
    
    // 祝日データを年ごとに読み込む
    $holidays_by_year = [];
    $current_year = (int)date_i18n('Y');
    $years_to_load = [$current_year, $current_year + 1, $current_year + 2];
    $locale = 'ja';
    foreach ($years_to_load as $year) {
        $file_path = plugin_dir_path(__FILE__) . "assets/holidays/{$locale}/{$year}.json";
        if (file_exists($file_path)) {
            $json_content = @file_get_contents($file_path);
            if ($json_content) {
                $holiday_data = json_decode($json_content, true);
                if (is_array($holiday_data)) {
                    $holidays_by_year[$year] = $holiday_data;
                }
            }
        }
    }
    $js_data['holidays'] = $holidays_by_year;
    $js_data['locale'] = $locale;
    $js_data['isLicenseValid'] = jcalendar_is_license_valid();

    return $js_data;
}

add_action('admin_enqueue_scripts', function ($hook) {
    // --- ページの種類を判定 ---
    $is_post_edit_page = ($hook === 'post.php' && isset($_GET['post'])) || $hook === 'post-new.php';
    $post_type = $_GET['post_type'] ?? (isset($_GET['post']) ? get_post_type($_GET['post']) : '');
    $is_jcalendar_post_type = $post_type === 'jcalendar';
    global $jcal_preference_hook, $jcal_config_hook; // admin-menu.phpで定義したグローバル変数を呼び出す
    $is_preference_page = ($hook === $jcal_preference_hook); // 変数を使って判定
    $is_config_page = ($hook === $jcal_config_hook); // 変数を使って判定

    // --- TintCal関連ページでなければ処理を中断 ---
    if (!$is_preference_page && !$is_config_page && !$is_jcalendar_post_type) {
        return;
    }

    // --- ステップ1で定義したヘルパー関数を呼び出して、データを準備 ---
    $post_id = ($is_post_edit_page && isset($_GET['post'])) ? absint($_GET['post']) : null;
    $js_data = jcalendar_build_js_data_for_admin($post_id);

    // --- スタイルとスクリプトを読み込む ---
    wp_enqueue_style('jcalendar-style', plugin_dir_url(__FILE__) . 'assets/css/calendar.css', [], JCALENDAR_VERSION);

    if ($is_preference_page || $is_config_page) {
        // 「カテゴリ・日付入力」または「プラグイン設定」ページの場合
        wp_enqueue_script('jcalendar-admin', plugin_dir_url(__FILE__) . 'assets/js/calendar-admin.js', ['jquery'], JCALENDAR_VERSION, true);
        wp_enqueue_script('jcalendar-editor', plugin_dir_url(__FILE__) . 'assets/js/category-editor.js', [], JCALENDAR_VERSION, true);
        wp_enqueue_script('jcalendar-admin-ui', plugin_dir_url(__FILE__) . 'assets/js/admin-ui-jcalendar.js', [], JCALENDAR_VERSION, true);
        wp_localize_script('jcalendar-admin', 'jcalendarPluginData', $js_data);
    } else if ($is_jcalendar_post_type && $is_post_edit_page) {
        // 「カレンダー」投稿の編集・新規作成ページの場合
        wp_enqueue_script('jcalendar-front', plugin_dir_url(__FILE__) . 'assets/js/calendar-front.js', [], JCALENDAR_VERSION, true);
        wp_localize_script('jcalendar-front', 'jcalendarPluginData', $js_data);
        wp_add_inline_script('jcalendar-front', "document.addEventListener('DOMContentLoaded', () => new JCalendar('#jcalendar-preview-admin', jcalendarPluginData));");
    }
});

/**
 * フロントエンドのJavaScriptに渡すための設定データを構築する共通関数
 *
 * @param int $post_id カレンダー投稿のID
 * @return array JavaScriptに渡す設定データの連想配列
 */
function jcalendar_build_js_data_for_frontend($post_id) {
    $js_data = [];

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
        'showHeaderWeekendColor' => ['type' => 'bool',   'default' => 1, 'option_key' => 'jcal_show_header_weekend_color'],
        'showTodayButton'        => ['type' => 'bool',   'default' => 1, 'option_key' => 'jcal_show_today_button'],
        'startDay'               => ['type' => 'string', 'default' => 'sunday'],
    ];

    // --- 各設定値を取得 ---
    foreach ($setting_keys as $js_key => $props) {
        // DB上のキー名を生成 (例: showSundayColor -> _jcalendar_show_sunday_color)
        $option_key = isset($props['option_key']) ? $props['option_key'] : 'jcalendar_' . strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $js_key));
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
    $visible_slugs = get_post_meta($post_id, '_jcalendar_visible_categories', true);
    $js_data['visibleCategories'] = is_array($visible_slugs) ? $visible_slugs : [];

    // --- 全カレンダーで共通のデータを追加 ---
    $js_data['pluginUrl']   = plugin_dir_url(__FILE__);
    $js_data['ajaxUrl']     = admin_url('admin-ajax.php');
    $js_data['nonce']       = wp_create_nonce('jcalendar_settings_nonce');
    $js_data['categories'] = jcalendar_get_displayable_categories();
    $js_data['assignments'] = json_decode(get_option('jcalendar_date_categories', '{}'), true) ?: [];

    // --- 祝日データを読み込んでJSに渡す ---
    $holidays_by_year = [];
    $current_year = (int)date_i18n('Y');
    $years_to_load = [$current_year, $current_year + 1, $current_year + 2];
    
    // 1. ▼▼▼ 国コード(locale)を一旦 'ja' で固定 ▼▼▼
    $locale = 'ja';

    foreach ($years_to_load as $year) {
        // 2. ▼▼▼ 新しいフォルダパスを参照する ▼▼▼
        $file_path = plugin_dir_path(__FILE__) . "assets/holidays/{$locale}/{$year}.json";
        if (file_exists($file_path)) {
            $json_content = file_get_contents($file_path);
            $holiday_data = json_decode($json_content, true);
            if (is_array($holiday_data)) {
                $holidays_by_year[$year] = $holiday_data;
            }
        }
    }
    $js_data['holidays'] = $holidays_by_year;
    $js_data['locale'] = $locale; // 3. ▼▼▼ JS側で使うためlocaleも渡す ▼▼▼
    $js_data['isLicenseValid'] = jcalendar_is_license_valid();

    return $js_data;
}

// TintCal管理画面でライセンス未入力時のみ警告を表示
add_action('admin_notices', function() {
    if (!current_user_can('manage_options')) return;
    $screen = get_current_screen();
    // TintCalの管理画面のみ
    if (strpos($screen->id, 'jcalendar') === false) return;
    $license_key = get_option('jcalendar_license_key', '');
    $payload = function_exists('jcalendar_get_license_status') ? jcalendar_get_license_status() : false;
    if (empty($license_key) || !$payload || !isset($payload['exp']) || $payload['exp'] <= time()) {
        echo '<div class="notice notice-warning"><p>TintCal：ライセンスキーが未入力です。正規ライセンスを入力してください。</p></div>';
    }
});

// =============================
// フロント画面用のスクリプト・スタイル登録
// =============================

add_action('wp_enqueue_scripts', function () {
    // --- フロントエンドで使うスクリプトとスタイルを「登録」だけしておく ---
    wp_register_script(
        'jcalendar-front',
        plugin_dir_url(__FILE__) . 'assets/js/calendar-front.js',
        [],
        JCALENDAR_VERSION,
        true
    );
    wp_register_style(
        'jcalendar-style-front',
        plugin_dir_url(__FILE__) . 'assets/css/calendar.css',
        [],
        JCALENDAR_VERSION
    );

    // --- ここから下を「if」条件の外に出す ---
    if (is_singular('jcalendar')) {
        $post_id = get_the_ID();
        $js_data = jcalendar_build_js_data_for_frontend($post_id);
        wp_localize_script('jcalendar-front', 'jcalendarPluginData', $js_data);

        $json_data = json_encode($js_data, JSON_UNESCAPED_UNICODE);
        $unique_id = 'jcalendar-instance-' . $post_id;

        $script = "document.addEventListener('DOMContentLoaded', function() { new JCalendar('#" . esc_js($unique_id) . "', jcalendarPluginData); });";
        wp_add_inline_script('jcalendar-front', $script);

        wp_enqueue_script('jcalendar-front');
        wp_enqueue_style('jcalendar-style-front');
    } else {
        // ショートコードや他の場所でもカレンダーを使う場合はこちらでデータを渡す
        // 必要に応じて $post_id の取得方法や $js_data の内容を調整
        // 例: グローバルな設定だけ渡す場合
        $js_data = jcalendar_build_js_data_for_frontend(null);
        wp_localize_script('jcalendar-front', 'jcalendarPluginData', $js_data);
        wp_enqueue_script('jcalendar-front');
        wp_enqueue_style('jcalendar-style-front');
    }
});

// =============================
// カレンダーショートコード表示
// =============================

function mjc_display_calendar() {
  $start_day = get_option('jcalendar_start_day', 'sunday');
  $enable_holidays = get_option('jcalendar_enable_holidays', 1);
  $holiday_color = get_option('jcalendar_holiday_color', '#ffdddd');
  ob_start();
  ?>
  <div id="mjc-calendar-container">
    <input type="hidden" id="jcalendar-start-day" value="<?= esc_attr($start_day); ?>">
    <input type="hidden" id="jcalendar-enable-holidays" value="<?= esc_attr($enable_holidays); ?>">
    <input type="hidden" id="jcalendar-holiday-color" value="<?= esc_attr($holiday_color); ?>">
    <input type="hidden" id="jcalendar-sunday-color" value="<?php echo esc_attr(get_option('jcalendar_sunday_color', '#ffecec')); ?>">
    <input type="hidden" id="jcalendar-saturday-color" value="<?php echo esc_attr(get_option('jcalendar_saturday_color', '#ecf5ff')); ?>">
    <input type="hidden" id="jcalendar-show-sunday-color" value="<?php echo esc_attr(get_option('jcalendar_show_sunday_color', 1)); ?>">
    <input type="hidden" id="jcalendar-show-saturday-color" value="<?php echo esc_attr(get_option('jcalendar_show_saturday_color', 1)); ?>">
    <div id="mjc-calendar-controls">
      <button id="prev-month">＜ 前の月</button>
      <span id="mjc-month-year"></span>
      <button id="next-month">次の月 ＞</button>
    </div>
    <table id="mjc-calendar" border="1" cellspacing="0" cellpadding="5">
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
 * - 管理者権限（manage_options）をチェック
 * - Nonceをチェック
 * チェックに失敗した場合は、JSONエラーを返して処理を中断します。
 */
function mjc_ajax_security_check($cap) {
  check_ajax_referer('jcalendar_settings_nonce');
  
  if (!jcalendar_current_user_can_access($cap)) {
    wp_send_json_error(['message' => 'この操作を行う権限がありません。']);
  }
}

add_action('wp_ajax_save_jcalendar_categories', function () {
  mjc_ajax_security_check('manage_common_settings');

  $raw = urldecode($_POST['categories'] ?? '');
  $parsed = json_decode($raw, true);
  if (!is_array($parsed)) {
    wp_send_json_error(['message' => '不正なデータ形式']);
  }

  // ライセンスが有効でない（無料版の）場合
  if (!jcalendar_is_license_valid()) {
    // 保存しようとしているカテゴリが2つ以上ある場合はエラー
    if (count($parsed) > 1) {
      wp_send_json_error(['message' => '無料版で作成できるカテゴリは1つまでです。']);
    }
  }

  // カテゴリスラグの重複バリデーション
  $slugs = array_column($parsed, 'slug');
  if (count(array_unique($slugs)) !== count($slugs)) {
    wp_send_json_error(['message' => 'カテゴリスラグが重複しています。']);
  }

  // カテゴリ名の重複バリデーション
  $names = [];
  foreach ($parsed as $cat) {
    $name = isset($cat['name']) ? trim($cat['name']) : '';
    if ($name === '') continue;
    if (in_array($name, $names, true)) {
      wp_send_json_error(['message' => "（サーバー判定）カテゴリ名「{$name}」が重複しています。"]);
    }
    $names[] = $name;
  }

  // 各カテゴリに 'visible' プロパティを確実に設定し、データをサニタイズ（無害化）する
  foreach ($parsed as &$cat) {
    // 'visible'キーが存在し、その値が明確に false でない限り、常に true（表示）とする
    // これにより、古いデータや不正な値が保存されるのを防ぐ
    $cat['visible'] = isset($cat['visible']) ? (bool)$cat['visible'] : true;

    // 無料版の場合、色を固定のデフォルトカラーに強制上書き
    if (!jcalendar_is_license_valid()) {
      $cat['color'] = '#dddddd'; // 無料版用の固定カラー
    }

  }
  unset($cat); // ループ後の参照を解除

  // 無料版で、DBに複数カテゴリが存在するのに、1つのカテゴリで上書きしようとしているかチェック
  $existing_categories = get_option('jcalendar_categories', []);
  if (
      !jcalendar_is_license_valid() &&                          // 無料版で
      count($existing_categories) > 1 &&                       // DBには2つ以上のカテゴリがあり
      count($parsed) <= 1 &&                                   // 保存しようとしているデータは1つ以下で
      empty($_POST['confirmed'])                               // かつ、まだユーザーの確認を得ていない場合
  ) {
      // 保存せずに、JavaScriptに確認を促すエラーを返す
      wp_send_json_error([
          'confirmation_required' => true,
          'message' => "現在、データベースには複数のカテゴリが保存されています。\n\nこのまま保存すると、表示されていない他のカテゴリが全て削除されますが、よろしいですか？"
      ]);
  }

  update_option('jcalendar_categories', $parsed);

  // 追加：割当データも整理（スラグベースで割当を整理）
  $allowed_slugs = array_column($parsed, 'slug');
  $dateCategories = json_decode(get_option('jcalendar_date_categories', '{}'), true) ?? [];

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
  update_option('jcalendar_date_categories', json_encode($dateCategories, JSON_UNESCAPED_UNICODE));

  wp_send_json_success([
    'saved' => true,
    'assignments' => $dateCategories
  ]);
});

add_action('wp_ajax_save_jcalendar_assignment', function () {
  mjc_ajax_security_check('manage_common_settings');

  $date = sanitize_text_field($_POST['date'] ?? '');
  $categories = isset($_POST['categories']) ? json_decode(stripslashes($_POST['categories']), true) : [];
  if (!is_array($categories)) $categories = [];
  $dateCategories = json_decode(get_option('jcalendar_date_categories'), true);
  $dateCategories = is_array($dateCategories) ? $dateCategories : [];
  foreach ($dateCategories as $d => $cat) {
      if (!is_array($cat)) $dateCategories[$d] = [$cat];
  }
  if (empty($categories) || (is_array($categories) && count($categories) === 0)) {
        unset($dateCategories[$date]);
  } else {
        $dateCategories[$date] = $categories;
  }

  update_option('jcalendar_date_categories', json_encode($dateCategories, JSON_UNESCAPED_UNICODE));
  
  // JSに返すためのカテゴリデータを準備する
  wp_send_json_success([
    'saved' => true,
    'assignments' => $dateCategories,
    'categories' => jcalendar_get_displayable_categories()
  ]);
});

add_action('wp_ajax_save_jcalendar_holidays', function () {
  mjc_ajax_security_check('manage_common_settings');

  $year = sanitize_text_field($_POST['year'] ?? '');
  $data = $_POST['holidays'] ?? '';
  if (!preg_match('/^\d{4}$/', $year)) {
    wp_send_json_error(['message' => 'Invalid year']);
  }
  $json = json_encode(json_decode(stripslashes($data), true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
  $locale = isset($_POST['locale']) ? sanitize_text_field($_POST['locale']) : 'ja';
  $upload_dir = plugin_dir_path(__FILE__) . "assets/holidays/{$locale}";
  if (!file_exists($upload_dir)) mkdir($upload_dir, 0755, true);
  $file_path = $upload_dir . "/$year.json";
  file_put_contents($file_path, $json);
  wp_send_json_success(['saved' => true, 'file' => $file_path]);
});

add_action('wp_ajax_get_jcalendar_categories', function () {
  mjc_ajax_security_check('manage_common_settings');

  $cats = get_option('jcalendar_categories', []);
  if (!is_array($cats)) $cats = [];
  wp_send_json_success($cats);
});

add_action('wp_ajax_get_jcalendar_assignments', function () {
  mjc_ajax_security_check('manage_common_settings');

  $assignments = json_decode(get_option('jcalendar_date_categories', '{}'), true);
  wp_send_json_success($assignments);
});

add_action('wp_ajax_reload_jcalendar_holidays', function () {
    mjc_ajax_security_check('manage_common_settings');
    
    // 祝日ファイル更新処理を実行
    $results = jcalendar_update_holiday_files(true);

    // 更新後の最新の祝日データを読み込む
    $holidays_by_year = [];
    $current_year = (int)date_i18n('Y');
    $years_to_load = [$current_year, $current_year + 1, $current_year + 2];
    $locale = 'ja';

    foreach ($years_to_load as $year) {
        $file_path = plugin_dir_path(__FILE__) . "assets/holidays/{$locale}/{$year}.json";
        if (file_exists($file_path)) {
            $json_content = @file_get_contents($file_path);
            if($json_content) {
                $holiday_data = json_decode($json_content, true);
                if (is_array($holiday_data)) {
                    $holidays_by_year[$year] = $holiday_data;
                }
            }
        }
    }

    // 成功応答に、最新の祝日データと処理結果の両方を含めて返す
    wp_send_json_success([
        'message'   => '祝日再取得完了',
        'results'   => $results,
        'holidays'  => $holidays_by_year
    ]);
});

add_action('wp_ajax_reset_jcalendar_assignments', function () {
  mjc_ajax_security_check('manage_common_settings');

  update_option('jcalendar_date_categories', '{}');
  wp_send_json_success("日付割り当てデータを初期化しました");
});

// 全データ初期化（カテゴリ・日付割当をすべて削除）
add_action('wp_ajax_reset_jcalendar_all', function () {
  mjc_ajax_security_check('manage_common_settings');
  
  update_option('jcalendar_categories', []);
  update_option('jcalendar_date_categories', '{}');
  wp_send_json_success("カテゴリと日付割当データをすべて初期化しました");
});

/**
 * 外部APIから祝日データを取得し、ローカルにJSONファイルとして保存する関数
 *
 * @param bool $return_results AJAXレスポンス用に結果を返すかどうか
 * @return array|void AJAX用に結果を返す場合は、処理結果の配列を返す
 */
function jcalendar_update_holiday_files($return_results = false) {
    $results = [];
    // 改善案4を考慮し、当年・翌年・翌々年の3年分を取得対象とする
    $current_year = (int)date_i18n('Y');
    $years = [$current_year, $current_year + 1, $current_year + 2];
    
    // ▼▼▼ 国コード(locale)を一旦 'ja' で固定 ▼▼▼
    $locale = 'ja';
    // 1. 保存先のベースとなるディレクトリパス
    $base_upload_dir = plugin_dir_path(__FILE__) . 'assets/holidays/';
    // 2. 国コードを含めた最終的なディレクトリパス
    $upload_dir = $base_upload_dir . $locale;

    // 3. 親ディレクトリがなければ先に作成
    if (!file_exists($base_upload_dir)) {
        wp_mkdir_p($base_upload_dir);
    }
    // 4. 国コードのディレクトリがなければ作成
    if (!file_exists($upload_dir)) {
        wp_mkdir_p($upload_dir);
    }

    foreach ($years as $year) {
        $url = "https://holidays-jp.github.io/api/v1/{$year}/date.json";
        // WordPressのHTTP APIを使用して安全にデータを取得
        $response = wp_remote_get($url, ['timeout' => 15]);

        // 取得失敗時の処理
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            $results[$year] = ['success' => false, 'error' => 'APIへの接続に失敗しました'];
            continue; // 次の年へ
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        // JSONデータが不正な場合の処理
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            $results[$year] = ['success' => false, 'error' => '取得したJSONデータが不正です'];
            continue; // 次の年へ
        }

        // ファイルに保存
        $file_path = $upload_dir . "/{$year}.json";
        // file_put_contentsは書き込んだバイト数を返す。失敗時はfalse
        $write_result = file_put_contents($file_path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        $results[$year] = [
            'success'     => $write_result !== false,
            'path'        => $file_path,
            'bytes'       => $write_result,
            'error'       => $write_result === false ? 'ファイルの書き込みに失敗しました' : ''
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
  if (isset($_POST['jcalendar_export'])) {
    if (!jcalendar_current_user_can_access('manage_common_settings')) {
      wp_die('このページにアクセスする権限がありません。');
    }
    check_admin_referer('jcalendar_settings_nonce');

    $categories = get_option('jcalendar_categories', []);
    foreach ($categories as &$cat) {
      if (empty($cat['slug'])) {
        $cat['slug'] = wp_generate_uuid4();
      }
    }
    unset($cat);
    
    $assignments = json_decode(get_option('jcalendar_date_categories', '{}'), true);
    if (!is_array($assignments)) $assignments = [];
    foreach ($assignments as $date => $cat) {
      if (!is_array($cat)) $assignments[$date] = [$cat];
    }
    $data = ['categories' => $categories, 'assignments' => $assignments];
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $date_str = date('Y-m-d', current_time('timestamp'));
    $filename = "tintcal-export-{$date_str}.json";
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo $json;
    exit;
  }

  
  if (isset($_POST['jcalendar_import']) && isset($_FILES['jcalendar_import_file'])) {
    if (!jcalendar_current_user_can_access('manage_common_settings')) {
      wp_die('このページにアクセスする権限がありません。');
    }
    check_admin_referer('jcalendar_settings_nonce');
    $file = $_FILES['jcalendar_import_file']['tmp_name'];
    $json = file_get_contents($file);
    $data = json_decode($json, true);
    if (
      !is_array($data) ||
      !isset($data['categories']) || !is_array($data['categories']) ||
      !isset($data['assignments']) || !is_array($data['assignments'])
    ) {
      // 形式が違う場合はエラー画面にリダイレクトして中止
      if (function_exists('wp_safe_redirect')) {
        wp_safe_redirect(admin_url('admin.php?page=jcalendar-preference&import=error'));
        exit;
      } else {
        header('Location: ' . admin_url('admin.php?page=jcalendar-preference&import=error'));
        exit;
      }
    }
    $newCategories = $data['categories'];
    
    // --- スラグ正規化・重複排除 ---
    $currentCategories = get_option('jcalendar_categories', []);
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
      $currentAssignmentsRaw = get_option('jcalendar_date_categories', '{}');
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
        update_option('jcalendar_categories', $currentCategories);
        $encoded = json_encode($mergedAssignments, JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
          $encoded = '{}'; // フォールバック
        }
        update_option('jcalendar_date_categories', $encoded);
      } else {
        // 上書きモード: $newCategoriesはスラグ正規化済み
        update_option('jcalendar_categories', $newCategories);
        update_option('jcalendar_date_categories', json_encode($newAssignments, JSON_UNESCAPED_UNICODE));
      }
      if (function_exists('wp_safe_redirect')) {
        wp_safe_redirect(admin_url('admin.php?page=jcalendar-preference&import=success'));
        exit;
      } else {
        header('Location: ' . admin_url('admin.php?page=jcalendar-preference&import=success'));
        exit;
      }
    } else {
      if (function_exists('wp_safe_redirect')) {
        wp_safe_redirect(admin_url('admin.php?page=jcalendar-preference&import=error'));
        exit;
      } else {
        header('Location: ' . admin_url('admin.php?page=jcalendar-preference&import=error'));
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
function jcalendar_render_calendar_base_html($post_id = null) {
    if ($post_id) {
        $start_day                 = get_post_meta($post_id, '_jcalendar_start_day', true) ?: get_option('jcalendar_start_day', 'sunday');
        $show_header_weekend_color = get_post_meta($post_id, '_jcal_show_header_weekend_color', true);
        if ($show_header_weekend_color === '') { $show_header_weekend_color = get_option('jcal_show_header_weekend_color', 1); }
        $show_today_button         = get_post_meta($post_id, '_jcalendar_show_today_button', true);
        if ($show_today_button === '') { $show_today_button = get_option('jcal_show_today_button', 1); }
    } else {
        $start_day                 = get_option('jcalendar_start_day', 'sunday');
        $show_header_weekend_color = get_option('jcal_show_header_weekend_color', 1);
        $show_today_button         = get_option('jcalendar_show_today_button', 1);
    }

    ob_start();
    ?>
    <div class="mjc-calendar-container-inner">
      <input type="hidden" class="jcalendar-start-day" value="<?= esc_attr($start_day); ?>">
      <input type="hidden" class="jcalendar-show-header-weekend-color" value="<?= esc_attr($show_header_weekend_color); ?>">

      <div class="mjc-calendar-controls" style="display: flex; justify-content: space-between; align-items: center;">
        <div>
          <button type="button" class="prev-month">＜ 前の月</button>
        </div>
        <div style="display: flex; align-items: center; gap: 15px;">
          <span class="mjc-month-year"></span>
          <?php if ($show_today_button == 1): ?>
            <button type="button" class="back-to-today" style="font-size: 11px; padding: 2px 6px; height: auto;">今月に戻る</button>
          <?php endif; ?>
        </div>
        <div>
          <button type="button" class="next-month">次の月 ＞</button>
        </div>
      </div>
      <table class="mjc-calendar" border="1" cellspacing="0" cellpadding="5">
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
define('JCALENDAR_CRON_HOOK', 'jcalendar_daily_holiday_update_hook');

/**
 * WP-Cronのスケジュールに、祝日更新処理を紐付ける
 */
add_action(JCALENDAR_CRON_HOOK, 'jcalendar_update_holiday_files');

/**
 * プラグイン有効化時に、祝日更新のスケジュールと、管理者権限の登録を行う関数
 */
function jcalendar_schedule_holiday_update() {
    // --- スケジュール登録 ---
    if (!wp_next_scheduled(JCALENDAR_CRON_HOOK)) {
        wp_schedule_event(time(), 'daily', JCALENDAR_CRON_HOOK);
    }

    // --- 管理者ロールにカスタム権限を付与 ---
    $role = get_role('administrator');
    if ($role) {
        $jcal_caps = [
            'edit_jcalendar',
            'read_jcalendar',
            'delete_jcalendar',
            'edit_jcalendars',
            'edit_others_jcalendars',
            'publish_jcalendars',
            'read_private_jcalendars',
            'delete_jcalendars',
            'delete_private_jcalendars',
            'delete_published_jcalendars',
            'delete_others_jcalendars',
            'edit_private_jcalendars',
            'edit_published_jcalendars',
        ];
        foreach ($jcal_caps as $cap) {
            $role->add_cap($cap);
        }
    }
}

/**
 * プラグイン無効化時に、登録したスケジュールを解除する関数
 */
function jcalendar_unschedule_holiday_update() {
    wp_clear_scheduled_hook(JCALENDAR_CRON_HOOK);
}

/**
 * プラグインの有効化・無効化フックに上記の関数を登録
 */
register_activation_hook(__FILE__, 'jcalendar_schedule_holiday_update');
register_deactivation_hook(__FILE__, 'jcalendar_unschedule_holiday_update');
