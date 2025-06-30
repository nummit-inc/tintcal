<?php
// File: includes/shortcode-frontend-jcalendar.php

add_shortcode('tintcal', 'jcalendar_frontend_shortcode_handler');

/**
 * [jcalendar id="..."] ショートコードの処理
 *
 * @param array $atts ショートコードの属性
 * @return string カレンダーのHTMLと初期化スクリプト
 */
function jcalendar_frontend_shortcode_handler($atts) {
    // 属性をパースし、IDを取得
    $atts = shortcode_atts(['id' => ''], $atts, 'jcalendar');
    $post_id = absint($atts['id']);

    if (!$post_id || get_post_type($post_id) !== 'jcalendar') {
        return current_user_can('manage_options') ? '<p style="color: red;">[jcalendar] エラー: 有効なカレンダーIDが指定されていません。</p>' : '';
    }

    // カレンダー本体のJSとCSSは、これまで通り読み込み予約をします
    wp_enqueue_style('jcalendar-style-front');
    wp_enqueue_script('jcalendar-front');

    // 1. JavaScriptに渡すためのデータを準備
    $js_data = jcalendar_build_js_data_for_frontend($post_id);
    $json_data = json_encode($js_data, JSON_UNESCAPED_UNICODE);

    // 2. 他のカレンダーと衝突しないユニークなIDを生成
    $unique_id = 'jcalendar-container-' . $post_id . '-' . wp_generate_uuid4();

    // 3. カレンダーのHTMLの箱を作成
    $html = '<div id="' . esc_attr($unique_id) . '" class="jcalendar-shortcode-container">'
          . jcalendar_render_calendar_base_html($post_id)
          . '</div>';

    // 4. その箱を初期化するためのscriptタグを、HTML文字列として作成
    //    ★DOMの準備と、JSファイルの読み込みの両方を待つために、DOMContentLoadedで囲みます★
    $script_tag = "<script type=\"text/javascript\">"
                . "document.addEventListener('DOMContentLoaded', function() {"
                . "    new JCalendar('#" . esc_js($unique_id) . "', " . $json_data . ");"
                . "});"
                . "</script>";
    
    // 5. HTMLとscriptタグを連結して返す
    return $html . $script_tag;
}