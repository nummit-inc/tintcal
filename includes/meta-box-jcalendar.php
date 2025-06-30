<?php
// File: includes/meta-box-jcalendar.php
/**
 * カスタム投稿 tintcal の編集画面に「カレンダー設定」メタボックスを追加
 */


// // メタボックスを登録
add_action('add_meta_boxes', function () {
    add_meta_box(
        'jcalendar_meta_box',              // メタボックスID
        'カレンダー設定',                  // タイトル
        'render_jcalendar_meta_box',       // コールバック関数
        'jcalendar',                       // 投稿タイプ
        'normal',                          // コンテキスト
        'high'                          // 優先度
    );
});

// メタボックスの中身を出力する関数
function render_jcalendar_meta_box($post) {
    // 保存用 nonce フィールド
    wp_nonce_field('jcalendar_meta_box_nonce', 'jcalendar_meta_box_nonce_field');

    $is_license_valid = jcalendar_is_license_valid();
    if (!$is_license_valid):
    ?>
        <div class="notice notice-info inline" style="margin: 1em 0;">
            <p>無料版では、カレンダーの個別設定はロックされます。設定は「TintCal > カテゴリ・日付入力」の共通設定が適用されます。</p>
        </div>
    <?php
    endif;

    // 既存データ取得（post_meta から）
    $start_day      = get_post_meta($post->ID, '_jcalendar_start_day', true) ?: get_option('jcalendar_start_day', 'sunday');
    $header_color   = get_post_meta($post->ID, '_jcalendar_header_color', true) ?: get_option('jcalendar_header_color', '#eeeeee');
    $enable_holidays_meta = get_post_meta($post->ID, '_jcalendar_enable_holidays', true);
    $enable_holidays = $enable_holidays_meta !== '' ? (int)$enable_holidays_meta : (int)get_option('jcalendar_enable_holidays', 1);
    $holiday_color  = get_post_meta($post->ID, '_jcalendar_holiday_color', true) ?: get_option('jcalendar_holiday_color', '#ffdddd');
    $show_header_weekend_color_meta = get_post_meta($post->ID, '_jcal_show_header_weekend_color', true);
    $show_header_weekend_color = $show_header_weekend_color_meta !== '' ? (int)$show_header_weekend_color_meta : (int)get_option('jcal_show_header_weekend_color', 1);
    $show_sunday_meta = get_post_meta($post->ID, '_jcalendar_show_sunday_color', true);
    $show_sunday = $show_sunday_meta !== '' ? (int)$show_sunday_meta : (int)get_option('jcalendar_show_sunday_color', 1);
    $sunday_color   = get_post_meta($post->ID, '_jcalendar_sunday_color', true) ?: get_option('jcalendar_sunday_color', '#ffecec');
    $show_saturday_meta = get_post_meta($post->ID, '_jcalendar_show_saturday_color', true);
    $show_saturday = $show_saturday_meta !== '' ? (int)$show_saturday_meta : (int)get_option('jcalendar_show_saturday_color', 1);
    $saturday_color   = get_post_meta($post->ID, '_jcalendar_saturday_color', true) ?: get_option('jcalendar_saturday_color', '#ecf5ff');
    $show_legend_meta = get_post_meta($post->ID, '_jcalendar_show_legend', true);
    $show_legend = $show_legend_meta !== '' ? (int)$show_legend_meta : (int)get_option('jcalendar_show_legend', 1);
    $show_today_button_meta = get_post_meta($post->ID, '_jcalendar_show_today_button', true);
    $show_today_button = $show_today_button_meta !== '' ? (int)$show_today_button_meta : (int)get_option('jcal_show_today_button', 1);

    $master_categories = get_option('jcalendar_categories', []);
    if (!is_array($master_categories)) {
        $master_categories = [];
    }
    usort($master_categories, function($a, $b) {
        $order_a = isset($a['order']) ? (int)$a['order'] : 0;
        $order_b = isset($b['order']) ? (int)$b['order'] : 0;
        return $order_a <=> $order_b;
    });

    // ▼▼▼ このブロックをここに追加 ▼▼▼
    // 新規投稿か、既存投稿かで、チェック済みのカテゴリslugの取得方法を分ける
    $is_new_post = $post->post_status === 'auto-draft';
    if ($is_new_post) {
        // 新規作成時は、共通設定で「表示」がONのカテゴリをデフォルトでチェック済みにする
        $visible_in_main_settings = array_filter($master_categories, function($cat) {
            return isset($cat['visible']) ? $cat['visible'] : true;
        });
        $visible_slugs = wp_list_pluck($visible_in_main_settings, 'slug');
    } else {
        // 編集時は、この投稿に保存されているメタデータを読み込む
        $visible_slugs = get_post_meta($post->ID, '_jcalendar_visible_categories', true);
        if (!is_array($visible_slugs)) {
            $visible_slugs = [];
        }
    }
    // ▲▲▲ ここまで追加 ▲▲▲
    ?>

    <table class="form-table">
      <tr>
        <th>週の開始曜日</th>
        <td>
          <?php if ($is_license_valid): ?>
            <label><input type="radio" name="start_day" value="sunday" <?= checked($start_day, 'sunday', false); ?>> 日曜日</label><br>
            <label><input type="radio" name="start_day" value="monday" <?= checked($start_day, 'monday', false); ?>> 月曜日</label>
          <?php else: ?>
            <span><?= $start_day === 'sunday' ? '日曜日' : '月曜日'; ?></span>
          <?php endif; ?>
        </td>
      </tr>
      <tr>
        <th>曜日ヘッダーの背景色</th>
        <td>
          <?php if ($is_license_valid): ?>
            <input type="color" name="header_color" value="<?= esc_attr($header_color); ?>">
          <?php else: ?>
            <span style="display: inline-block; width: 60px; height: 28px; border: 1px solid #ccc; background-color: <?= esc_attr($header_color); ?>; vertical-align: middle;"></span>
          <?php endif; ?>
        </td>
      </tr>
      <tr>
        <th>日本の祝日を表示</th>
        <td>
          <?php if ($is_license_valid): ?>
            <label><input type="checkbox" name="enable_holidays" value="1" <?= checked($enable_holidays, 1, false); ?>> 表示する</label>
            <input type="color" name="holiday_color" value="<?= esc_attr($holiday_color); ?>" style="margin-left:10px;">
          <?php else: ?>
            <span><?= $enable_holidays ? '表示する' : '表示しない'; ?></span>
            <span style="display: inline-block; width: 60px; height: 28px; border: 1px solid #ccc; background-color: <?= esc_attr($holiday_color); ?>; margin-left: 10px; vertical-align: middle;"></span>
          <?php endif; ?>
        </td>
      </tr>
      <tr>
        <th>曜日ヘッダーの色付け</th>
        <td>
          <?php if ($is_license_valid): ?>
            <label><input type="checkbox" name="show_header_weekend_color" value="1" <?= checked($show_header_weekend_color, 1, false); ?>> 週末の曜日ヘッダーを色付けする</label>
          <?php else: ?>
            <span><?= $show_header_weekend_color ? '色付けする' : '色付けしない'; ?></span>
          <?php endif; ?>
        </td>
      </tr>
      <tr>
        <th>日曜カラー</th>
        <td>
          <?php if ($is_license_valid): ?>
            <label><input type="checkbox" name="show_sunday_color" value="1" <?= checked($show_sunday, 1, false); ?>> 日曜を色付け</label><br>
            <input type="color" name="sunday_color" value="<?= esc_attr($sunday_color); ?>">
          <?php else: ?>
            <span><?= $show_sunday ? '日曜を色付け' : '日曜を色付けしない'; ?></span>
            <span style="display: inline-block; width: 60px; height: 28px; border: 1px solid #ccc; background-color: <?= esc_attr($sunday_color); ?>; margin-left: 10px; vertical-align: middle;"></span>
          <?php endif; ?>
        </td>
      </tr>
      <tr>
        <th>土曜カラー</th>
        <td>
          <?php if ($is_license_valid): ?>
            <label><input type="checkbox" name="show_saturday_color" value="1" <?= checked($show_saturday, 1, false); ?>> 土曜を色付け</label><br>
            <input type="color" name="saturday_color" value="<?= esc_attr($saturday_color); ?>">
          <?php else: ?>
            <span><?= $show_saturday ? '土曜を色付け' : '土曜を色付けしない'; ?></span>
            <span style="display: inline-block; width: 60px; height: 28px; border: 1px solid #ccc; background-color: <?= esc_attr($saturday_color); ?>; margin-left: 10px; vertical-align: middle;"></span>
          <?php endif; ?>
        </td>
      </tr>
      <tr>
        <th>カテゴリ凡例を表示</th>
        <td>
          <?php if ($is_license_valid): ?>
            <label><input type="checkbox" name="show_legend" value="1" <?= checked($show_legend, 1, false); ?>> 表示</label>
          <?php else: ?>
            <span><?= $show_legend ? '表示' : '非表示'; ?></span>
          <?php endif; ?>
        </td>
      </tr>
      <tr>
        <th>「今月に戻る」ボタンを表示</th>
        <td>
          <?php if ($is_license_valid): ?>
            <label><input type="checkbox" name="show_today_button" value="1" <?= checked($show_today_button, 1, false); ?>> 表示</label>
          <?php else: ?>
            <span><?= $show_today_button ? '表示' : '非表示'; ?></span>
          <?php endif; ?>
        </td>
      </tr>
      <tr>
        <th scope="row" style="vertical-align: top;">表示するカテゴリ</th>
        <td>
          <?php
            $categories_to_display = $is_license_valid ? $master_categories : array_slice($master_categories, 0, 1);
            if (empty($categories_to_display)) {
              echo '「カテゴリ・日付入力」で先にカテゴリを作成してください。';
            } else {
              if ($is_license_valid) { // Pro版
                // 1. name属性を削除し、代わりにclass属性を付ける
                foreach ($categories_to_display as $cat) {
                  $is_checked = in_array($cat['slug'], $visible_slugs, true);
                  echo '<label style="display: block; margin-bottom: 5px;"><input type="checkbox" class="jcal-cat-check" value="' . esc_attr($cat['slug']) . '" ' . checked($is_checked, true, false) . '> ' . esc_html($cat['name']) . '</label>';
                }
                // 2. データを保存するための隠しフィールドを追加
                echo '<input type="hidden" id="jcal_visible_categories_hidden" name="jcalendar_visible_categories_str" value="' . esc_attr(implode(',', $visible_slugs)) . '">';
              } else { // 無料版
                  echo '<p style="padding: 6px 0;">' . esc_html($categories_to_display[0]['name']) . '</p>';
                  echo '<input type="hidden" name="jcalendar_visible_categories[]" value="' . esc_attr($categories_to_display[0]['slug']) . '">';
              }
            }
          ?>
          <?php if ($is_license_valid): ?>
            <p class="description">
              このカレンダーで表示したいカテゴリだけにチェックを入れてください。<br>
              「カテゴリ・日付入力」で割り当てた日付のうち、ここでチェックされたカテゴリのみが表示されます。
            </p>
          <?php else: ?>
            <p class="description" style="color:#888;">
              無料版では1つのカテゴリのみ表示できます。<br>
              複数カテゴリを表示したい場合はPro版をご利用ください。
            </p>
          <?php endif; ?>

          <?php // 3. チェックボックスを操作するJavaScriptを追加 ?>
          <script>
            document.addEventListener('DOMContentLoaded', function() {
              // ページ全体で 'change' イベントを監視します
              document.addEventListener('change', function(event) {
                // イベントを発生させた要素が、目的のチェックボックスか確認します
                if (event.target.matches('.jcal-cat-check')) {
                  
                  // クリックされた瞬間に、改めて全ての要素を探しに行きます
                  const hiddenInput = document.querySelector('#jcal_visible_categories_hidden');
                  const checkboxes = document.querySelectorAll('.jcal-cat-check');

                  // 要素が見つかった場合のみ、処理を続行します
                  if (hiddenInput && checkboxes.length > 0) {
                    const checkedSlugs = Array.from(checkboxes)
                      .filter(cb => cb.checked)
                      .map(cb => cb.value);
                    
                    hiddenInput.value = checkedSlugs.join(',');
                  }
                }
              });
            });
          </script>
        </td>
      </tr>
    </table>
      <?php
}