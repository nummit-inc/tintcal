<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
// File: includes/shortcode-frontend-tintcal.php

add_shortcode('tintcal', 'tintcal_frontend_shortcode_handler');

/**
 * [tintcal id="..."] ショートコードの処理
 *
 * @param array $atts ショートコードの属性
 * @return string カレンダーのHTMLと初期化スクリプト
 */
function tintcal_frontend_shortcode_handler($atts) {
    // 属性をパースし、IDを取得
    $atts = shortcode_atts(['id' => ''], $atts, 'tintcal');
    $post_id = absint($atts['id']);

    if (!$post_id || get_post_type($post_id) !== 'tintcal') {
        return current_user_can('manage_options') ? '<p style="color: red;">' . esc_html__( '[tintcal] エラー: 有効なカレンダーIDが指定されていません。', 'tintcal' ) . '</p>' : '';
    }

    // カレンダー本体のJSとCSSは、これまで通り読み込み予約をします
    wp_enqueue_style('tintcal-style-front');
    wp_enqueue_script('tintcal-front');

    // 1. JavaScriptに渡すためのデータを準備
    $js_data = tintcal_build_js_data_for_frontend($post_id);

    // 2. 他のカレンダーと衝突しないユニークなIDを生成
    $unique_id = 'tintcal-container-' . $post_id . '-' . wp_generate_uuid4();

    // 3. カレンダーのHTMLの箱を作成
    $html = '<div id="' . esc_attr($unique_id) . '" class="tintcal-shortcode-container">'
          . tintcal_render_calendar_base_html($post_id)
          . '</div>';

    // 4. インラインスクリプトを生成
    $script = sprintf(
        "document.addEventListener('DOMContentLoaded', function() { new TintCal('#%s', %s); });",
        esc_js($unique_id),
        wp_json_encode($js_data) // json_encodeの代わりにwp_json_encodeを使用
    );

    // 5. 'tintcal-front' スクリプトにインラインスクリプトを追加
    wp_add_inline_script('tintcal-front', $script);
    
    // 6. HTMLのみを返す
    return $html;
}