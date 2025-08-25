<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
// File: includes/meta-box-tintcal.php
/**
 * カスタム投稿 tintcal の編集画面に「カレンダー設定」メタボックスを追加
 */


// // メタボックスを登録
add_action('add_meta_boxes', function () {
    add_meta_box(
        'tintcal_meta_box',              // メタボックスID
        esc_html__( 'カレンダー設定', 'tintcal' ), // タイトル
        'tintcal_render_meta_box',       // コールバック関数
        'tintcal',                       // 投稿タイプ
        'normal',                          // コンテキスト
        'high'                          // 優先度
    );
});

// メタボックスの中身を出力する関数
function tintcal_render_meta_box($post) {
    // 保存用 nonce フィールド
    wp_nonce_field('tintcal_meta_box_nonce', 'tintcal_meta_box_nonce_field');

    // 既存データ取得（post_meta から）
    $start_day      = get_post_meta($post->ID, '_tintcal_start_day', true) ?: get_option('tintcal_start_day', 'sunday');
    $header_color   = get_post_meta($post->ID, '_tintcal_header_color', true) ?: get_option('tintcal_header_color', '#eeeeee');
    $enable_holidays_meta = get_post_meta($post->ID, '_tintcal_enable_holidays', true);
    $enable_holidays = $enable_holidays_meta !== '' ? (int)$enable_holidays_meta : (int)get_option('tintcal_enable_holidays', 1);
    $holiday_color  = get_post_meta($post->ID, '_tintcal_holiday_color', true) ?: get_option('tintcal_holiday_color', '#ffdddd');
    $show_header_weekend_color_meta = get_post_meta($post->ID, '_tintcal_show_header_weekend_color', true);
    $show_header_weekend_color = $show_header_weekend_color_meta !== '' ? (int)$show_header_weekend_color_meta : (int)get_option('tintcal_show_header_weekend_color', 1);
    $show_sunday_meta = get_post_meta($post->ID, '_tintcal_show_sunday_color', true);
    $show_sunday = $show_sunday_meta !== '' ? (int)$show_sunday_meta : (int)get_option('tintcal_show_sunday_color', 1);
    $sunday_color   = get_post_meta($post->ID, '_tintcal_sunday_color', true) ?: get_option('tintcal_sunday_color', '#ffecec');
    $show_saturday_meta = get_post_meta($post->ID, '_tintcal_show_saturday_color', true);
    $show_saturday = $show_saturday_meta !== '' ? (int)$show_saturday_meta : (int)get_option('tintcal_show_saturday_color', 1);
    $saturday_color   = get_post_meta($post->ID, '_tintcal_saturday_color', true) ?: get_option('tintcal_saturday_color', '#ecf5ff');
    $show_legend_meta = get_post_meta($post->ID, '_tintcal_show_legend', true);
    $show_legend = $show_legend_meta !== '' ? (int)$show_legend_meta : (int)get_option('tintcal_show_legend', 1);
    $show_today_button_meta = get_post_meta($post->ID, '_tintcal_show_today_button', true);
    $show_today_button = $show_today_button_meta !== '' ? (int)$show_today_button_meta : (int)get_option('tintcal_show_today_button', 1);

    $master_categories = get_option('tintcal_categories', []);
    if (!is_array($master_categories)) {
        $master_categories = [];
    }
    usort($master_categories, function($a, $b) {
        $order_a = isset($a['order']) ? (int)$a['order'] : 0;
        $order_b = isset($b['order']) ? (int)$b['order'] : 0;
        return $order_a <=> $order_b;
    });

    // チェック済みのカテゴリslugsを取得するロジック
    // 新規投稿か、既存投稿かで、取得元を分ける
    $visible_slugs = []; // まずは空で初期化

    if ($post->post_status === 'auto-draft') { // 新規作成時（auto-draft状態）
        // 新規作成時は、共通設定で「表示」がONのカテゴリをデフォルトでチェック済みにする
        $visible_in_main_settings = array_filter($master_categories, function($cat) {
            return ($cat['visible'] ?? true);
        });
        $visible_slugs = array_values(wp_list_pluck($visible_in_main_settings, 'slug'));
    } else { // 投稿IDが既存の場合
        $visible_slugs_from_meta = get_post_meta($post->ID, '_tintcal_visible_categories', false); // falseに変更済み
        // Post Metaから取得した値をフラットな配列として扱う
        $visible_slugs = []; // まず空で初期化
        if (is_array($visible_slugs_from_meta)) {
            // 取得した配列がさらに配列を内包している場合があるので、すべてを1次元配列にフラット化する
            foreach ($visible_slugs_from_meta as $item) {
                if (is_array($item)) {
                    $visible_slugs = array_merge($visible_slugs, $item);
                } else {
                    $visible_slugs[] = $item;
                }
            }
        }
    }
    ?>

    <table class="form-table">
      <tr>
        <th><?php echo esc_html__( '週の開始曜日', 'tintcal' ); ?></th>
        <td>
            <label><input type="radio" name="start_day" value="sunday" <?php echo checked($start_day, 'sunday', false); ?>> <?php echo esc_html__( '日曜日', 'tintcal' ); ?></label><br>
            <label><input type="radio" name="start_day" value="monday" <?php echo checked($start_day, 'monday', false); ?>> <?php echo esc_html__( '月曜日', 'tintcal' ); ?></label>
        </td>
      </tr>
      <tr>
        <th><?php echo esc_html__( '曜日ヘッダーの背景色', 'tintcal' ); ?></th>
        <td>
            <span style="display: inline-block; width: 60px; height: 28px; border: 1px solid #ccc; background-color: <?php echo esc_attr($header_color); ?>; vertical-align: middle;"></span>
            <p class="description"><?php echo esc_html__( '※カラーの変更は「カテゴリ・日付入力」ページの共通設定から行えます。', 'tintcal' ); ?></p>
        </td>
      </tr>
      <tr>
        <th><?php echo esc_html__( '日本の祝日を表示', 'tintcal' ); ?></th>
        <td>
            <label><input type="checkbox" name="enable_holidays" value="1" <?php echo checked($enable_holidays, 1, false); ?>> <?php echo esc_html__( '表示する', 'tintcal' ); ?></label>
            <span style="display: inline-block; width: 60px; height: 28px; border: 1px solid #ccc; background-color: <?php echo esc_attr($holiday_color); ?>; margin-left: 10px; vertical-align: middle;"></span>
        </td>
      </tr>
      <tr>
        <th><?php echo esc_html__( '曜日ヘッダーの色付け', 'tintcal' ); ?></th>
        <td>
            <label><input type="checkbox" name="show_header_weekend_color" value="1" <?php echo checked($show_header_weekend_color, 1, false); ?>> <?php echo esc_html__( '週末の曜日ヘッダーを色付けする', 'tintcal' ); ?></label>
        </td>
      </tr>
      <tr>
        <th><?php echo esc_html__( '日曜カラー', 'tintcal' ); ?></th>
        <td>
            <label><input type="checkbox" name="show_sunday_color" value="1" <?php echo checked($show_sunday, 1, false); ?>> <?php echo esc_html__( '日曜を色付け', 'tintcal' ); ?></label><br>
            <span style="display: inline-block; width: 60px; height: 28px; border: 1px solid #ccc; background-color: <?php echo esc_attr($sunday_color); ?>; vertical-align: middle;"></span>
        </td>
      </tr>
      <tr>
        <th><?php echo esc_html__( '土曜カラー', 'tintcal' ); ?></th>
        <td>
            <label><input type="checkbox" name="show_saturday_color" value="1" <?php echo checked($show_saturday, 1, false); ?>> <?php echo esc_html__( '土曜を色付け', 'tintcal' ); ?></label><br>
            <span style="display: inline-block; width: 60px; height: 28px; border: 1px solid #ccc; background-color: <?php echo esc_attr($saturday_color); ?>; vertical-align: middle;"></span>
        </td>
      </tr>
      <tr>
        <th><?php echo esc_html__( 'カテゴリ凡例を表示', 'tintcal' ); ?></th>
        <td>
            <label><input type="checkbox" name="show_legend" value="1" <?php echo checked($show_legend, 1, false); ?>> <?php echo esc_html__( '表示', 'tintcal' ); ?></label>
        </td>
      </tr>
      <tr>
        <th><?php echo esc_html__( '「今月に戻る」ボタンを表示', 'tintcal' ); ?></th>
        <td>
            <label><input type="checkbox" name="show_today_button" value="1" <?php echo checked($show_today_button, 1, false); ?>> <?php echo esc_html__( '表示', 'tintcal' ); ?></label>
        </td>
      </tr>
      <tr>
        <th scope="row" style="vertical-align: top;"><?php echo esc_html__( '表示するカテゴリ', 'tintcal' ); ?></th>
        <td>
          <?php
            $categories_to_display = $master_categories;
            if (empty($categories_to_display)) {
              echo esc_html__( '「カテゴリ・日付入力」で先にカテゴリを作成してください。', 'tintcal' );
            } else {
                // 1. name属性を削除し、代わりにclass属性を付ける
                foreach ($categories_to_display as $cat) {
                  $is_checked = in_array($cat['slug'], $visible_slugs, true);
                  $is_disabled = ($cat['visible'] ?? true) === false; // 共通設定でvisibleがfalseならdisabled
                  echo '<label style="display: block; margin-bottom: 5px;"><input type="radio" name="tintcal-cat-one" class="tintcal-cat-check" value="' . esc_attr($cat['slug']) . '" ' . checked($is_checked, true, false) . ' ' . ($is_disabled ? 'disabled' : '') . '> ' . esc_html($cat['name']) . '</label>';
                }
                // 2. データを保存するための隠しフィールドを追加
                echo '<input type="hidden" id="tintcal_visible_categories_hidden" name="tintcal_visible_categories_str" value="' . esc_attr(implode(',', $visible_slugs)) . '">';
            }
          ?>
            <p class="description">
              <?php echo esc_html__( 'このカレンダーで表示したいカテゴリだけにチェックを入れてください。', 'tintcal' ); ?><br>
              <?php echo esc_html__( '「カテゴリ・日付入力」で割り当てた日付のうち、ここでチェックされたカテゴリのみが表示されます。', 'tintcal' ); ?>
            </p>
          </td>
      </tr>
    </table>
      <?php
        $script = "
            document.addEventListener('DOMContentLoaded', function() {
              const categoryCheckboxesContainer = document.querySelector('th[scope=\"row\"][style=\"vertical-align: top;\"] + td');
              
              if (categoryCheckboxesContainer) {
                  const hiddenInput = document.querySelector('#tintcal_visible_categories_hidden');
                  const checkboxes = categoryCheckboxesContainer.querySelectorAll('.tintcal-cat-check');

                  const updateHiddenInput = () => {
                      if (hiddenInput && checkboxes.length > 0) {
                          const selected = categoryCheckboxesContainer.querySelector('input.tintcal-cat-check:checked');
                          hiddenInput.value = selected ? selected.value : '';
                          // Trigger an input event to make sure the block editor detects the change
                          hiddenInput.dispatchEvent(new Event('input', { bubbles: true }));
                      }
                  };

                  categoryCheckboxesContainer.addEventListener('change', function(event) {
                      if (event.target.matches('.tintcal-cat-check')) {
                          updateHiddenInput();
                      }
                  });
                  
                  updateHiddenInput();
              }
            });
        ";
        wp_add_inline_script('tintcal-front', $script);
}