<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

add_action('save_post_tintcal', function ($post_id) {
    // 自動保存、権限、Nonceチェック
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;
    if (!isset($_POST['tintcal_meta_box_nonce_field']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['tintcal_meta_box_nonce_field'])), 'tintcal_meta_box_nonce')) return;

    $is_first_save = !get_post_meta($post_id, '_tintcal_settings_initialized', true);

    if ($is_first_save) {
        // --- ★ 初回保存時の処理 ★ ---
        // 共通設定の値を、この投稿の初期値として全てコピーして保存する
        update_post_meta($post_id, '_tintcal_start_day', get_option('tintcal_start_day', 'sunday'));
        update_post_meta($post_id, '_tintcal_header_color', get_option('tintcal_header_color', '#eeeeee'));
        update_post_meta($post_id, '_tintcal_show_header_weekend_color', get_option('tintcal_show_header_weekend_color', 1));
        update_post_meta($post_id, '_tintcal_enable_holidays', get_option('tintcal_enable_holidays', 1));
        update_post_meta($post_id, '_tintcal_holiday_color', get_option('tintcal_holiday_color', '#ffdddd'));
        update_post_meta($post_id, '_tintcal_show_sunday_color', get_option('tintcal_show_sunday_color', 1));
        update_post_meta($post_id, '_tintcal_sunday_color', get_option('tintcal_sunday_color', '#ffecec'));
        update_post_meta($post_id, '_tintcal_show_saturday_color', get_option('tintcal_show_saturday_color', 1));
        update_post_meta($post_id, '_tintcal_saturday_color', get_option('tintcal_saturday_color', '#ecf5ff'));
        update_post_meta($post_id, '_tintcal_show_legend', get_option('tintcal_show_legend', 1));
        update_post_meta($post_id, '_tintcal_show_today_button', get_option('tintcal_show_today_button', 1));
    } else {
        // --- ★ 2回目以降の保存処理 ★ ---
        // （カラー以外の）送信された値を保存する
        $start_day_raw = isset($_POST['start_day']) ? sanitize_text_field(wp_unslash($_POST['start_day'])) : 'sunday';
        $start_day_val = in_array($start_day_raw, ['sunday','monday'], true) ? $start_day_raw : 'sunday';
        update_post_meta($post_id, '_tintcal_start_day', $start_day_val);
        update_post_meta($post_id, '_tintcal_show_header_weekend_color', isset($_POST['show_header_weekend_color']) ? 1 : 0);
        update_post_meta($post_id, '_tintcal_enable_holidays', isset($_POST['enable_holidays']) ? 1 : 0);
        update_post_meta($post_id, '_tintcal_show_sunday_color', isset($_POST['show_sunday_color']) ? 1 : 0);
        update_post_meta($post_id, '_tintcal_show_saturday_color', isset($_POST['show_saturday_color']) ? 1 : 0);
        update_post_meta($post_id, '_tintcal_show_legend', isset($_POST['show_legend']) ? 1 : 0);
        update_post_meta($post_id, '_tintcal_show_today_button', isset($_POST['show_today_button']) ? 1 : 0);
        
        // カラー設定は、常に共通設定の値を再保存する
        update_post_meta($post_id, '_tintcal_header_color', get_option('tintcal_header_color', '#eeeeee'));
        update_post_meta($post_id, '_tintcal_holiday_color', get_option('tintcal_holiday_color', '#ffdddd'));
        update_post_meta($post_id, '_tintcal_sunday_color', get_option('tintcal_sunday_color', '#ffecec'));
        update_post_meta($post_id, '_tintcal_saturday_color', get_option('tintcal_saturday_color', '#ecf5ff'));
    }

    // 表示カテゴリの保存処理は、毎回実行する
    if ( isset($_POST['tintcal_visible_categories_str']) ) {
        $slugs_str = sanitize_text_field( wp_unslash( $_POST['tintcal_visible_categories_str'] ) );
        $slugs = [];
        if ( $slugs_str !== '' ) {
            // 旧来のカンマ区切りにも対応しつつ、単一カテゴリに強制
            $parts = array_map( 'sanitize_text_field', array_filter( array_map( 'trim', explode( ',', $slugs_str ) ) ) );
            if ( ! empty( $parts ) ) {
                $slugs = [ $parts[0] ]; // 先頭1件のみ許容
            }
        }
        update_post_meta( $post_id, '_tintcal_visible_categories', $slugs );
    }
    
    // 初回保存が完了したことを記録するフラグを立てる
    if ($is_first_save) {
        update_post_meta($post_id, '_tintcal_settings_initialized', 1);
    }
});