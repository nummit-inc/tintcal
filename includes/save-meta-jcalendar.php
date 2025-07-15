<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

add_action('save_post_jcalendar', function ($post_id) {
    // 自動保存、権限、Nonceチェック
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;
    if (!isset($_POST['jcalendar_meta_box_nonce_field']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['jcalendar_meta_box_nonce_field'])), 'jcalendar_meta_box_nonce')) return;

    $is_license_valid = jcalendar_is_license_valid();
    $is_first_save = !get_post_meta($post_id, '_jcal_settings_initialized', true);

    if ($is_license_valid) {
        // --- ★ Pro版の保存処理 ★ ---
        // 初回・2回目以降を問わず、常にフォームの値を保存する
        update_post_meta($post_id, '_jcalendar_start_day', isset($_POST['start_day']) ? sanitize_text_field(wp_unslash($_POST['start_day'])) : 'sunday');
        update_post_meta($post_id, '_jcalendar_header_color', isset($_POST['header_color']) ? sanitize_hex_color(wp_unslash($_POST['header_color'])) : '#eeeeee');
        update_post_meta($post_id, '_jcal_show_header_weekend_color', isset($_POST['show_header_weekend_color']) ? 1 : 0);
        update_post_meta($post_id, '_jcalendar_enable_holidays', isset($_POST['enable_holidays']) ? 1 : 0);
        update_post_meta($post_id, '_jcalendar_holiday_color', isset($_POST['holiday_color']) ? sanitize_hex_color(wp_unslash($_POST['holiday_color'])) : '#ffdddd');
        update_post_meta($post_id, '_jcalendar_show_sunday_color', isset($_POST['show_sunday_color']) ? 1 : 0);
        update_post_meta($post_id, '_jcalendar_sunday_color', isset($_POST['sunday_color']) ? sanitize_hex_color(wp_unslash($_POST['sunday_color'])) : '#ffecec');
        update_post_meta($post_id, '_jcalendar_show_saturday_color', isset($_POST['show_saturday_color']) ? 1 : 0);
        update_post_meta($post_id, '_jcalendar_saturday_color', isset($_POST['saturday_color']) ? sanitize_hex_color(wp_unslash($_POST['saturday_color'])) : '#ecf5ff');
        update_post_meta($post_id, '_jcalendar_show_legend', isset($_POST['show_legend']) ? 1 : 0);
        update_post_meta($post_id, '_jcalendar_show_today_button', isset($_POST['show_today_button']) ? 1 : 0);
    } elseif ($is_first_save) {
        // --- ★ 無料版の、初回保存時の処理 ★ ---
        // 共通設定の値を、この投稿の初期値として全てコピーして保存する
        update_post_meta($post_id, '_jcalendar_start_day', get_option('jcalendar_start_day', 'sunday'));
        update_post_meta($post_id, '_jcalendar_header_color', get_option('jcalendar_header_color', '#eeeeee'));
        update_post_meta($post_id, '_jcal_show_header_weekend_color', get_option('jcal_show_header_weekend_color', 1));
        update_post_meta($post_id, '_jcalendar_enable_holidays', get_option('jcalendar_enable_holidays', 1));
        update_post_meta($post_id, '_jcalendar_holiday_color', get_option('jcalendar_holiday_color', '#ffdddd'));
        update_post_meta($post_id, '_jcalendar_show_sunday_color', get_option('jcalendar_show_sunday_color', 1));
        update_post_meta($post_id, '_jcalendar_sunday_color', get_option('jcalendar_sunday_color', '#ffecec'));
        update_post_meta($post_id, '_jcalendar_show_saturday_color', get_option('jcalendar_show_saturday_color', 1));
        update_post_meta($post_id, '_jcalendar_saturday_color', get_option('jcalendar_saturday_color', '#ecf5ff'));
        update_post_meta($post_id, '_jcalendar_show_legend', get_option('jcalendar_show_legend', 1));
        update_post_meta($post_id, '_jcalendar_show_today_button', get_option('jcal_show_today_button', 1));
    }

    // 表示カテゴリの保存処理は、Pro/Free版ともに毎回実行する
    if ($is_license_valid) {
        // Pro版の場合、隠しフィールドからカテゴリのスラグ文字列を受け取る
        $slugs_str = isset($_POST['jcalendar_visible_categories_str']) ? sanitize_text_field(wp_unslash($_POST['jcalendar_visible_categories_str'])) : '';
        $slugs = [];
        if (!empty($slugs_str)) { // 文字列が空でなければexplodeする
            $slugs = explode(',', $slugs_str);
            // 各スラグもサニタイズ（念のため）
            $slugs = array_map('sanitize_text_field', $slugs);
        }
        update_post_meta($post_id, '_jcalendar_visible_categories', $slugs);
    } else { // 無料版
        // 1. デフォルト値を設定
        $sanitized_slugs = [];

        // 2. POSTデータの存在だけをチェック
        if (isset($_POST['jcalendar_visible_categories'])) {
            // 3. 存在すれば、直ちに wp_unslash を実行
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The value is sanitized in the next step.
            $unslashed_data = wp_unslash($_POST['jcalendar_visible_categories']);

            // 4. unslash したデータが配列であることを確認し、サニタイズ
            if (is_array($unslashed_data)) {
                $sanitized_slugs = array_map('sanitize_text_field', $unslashed_data);
            }
        }

        // 5. 最終的にサニタイズされたデータを保存
        update_post_meta($post_id, '_jcalendar_visible_categories', $sanitized_slugs);
    }
    
    // 初回保存が完了したことを記録するフラグを立てる
    if ($is_first_save) {
        update_post_meta($post_id, '_jcal_settings_initialized', true);
    }
});