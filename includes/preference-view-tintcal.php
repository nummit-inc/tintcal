<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly ?>
<div class="wrap">
  <h1><?php echo esc_html__( 'TintCal 設定', 'tintcal' ); ?></h1>

  <?php settings_errors('tintcal_messages'); ?>

  <h2 class="nav-tab-wrapper">
    <a href="#tintcal-tab-1" class="nav-tab nav-tab-active"><?php echo esc_html__( '基本設定', 'tintcal' ); ?></a>
    <a href="#tintcal-tab-2" class="nav-tab"><?php echo esc_html__( 'カテゴリ・日付設定', 'tintcal' ); ?></a>
  </h2>

  <div id="tintcal-tab-1" class="tintcal-tab-content active">
    <div class="tintcal-settings-container">
      <div class="tintcal-settings-form">
        <form method="post" action="">
          <input type="hidden" name="action" value="tintcal_save_basic_settings">
          <?php wp_nonce_field('tintcal_settings_nonce'); ?>

          <table class="form-table">
            <tr>
              <th scope="row"><?php echo esc_html__( '週の開始曜日', 'tintcal' ); ?></th>
              <td>
                <label><input type="radio" name="start_day" value="sunday" class="tintcal-setting-input" <?php echo checked(get_option('tintcal_start_day', 'sunday'), 'sunday', false); ?>> <?php echo esc_html__( '日曜日', 'tintcal' ); ?></label><br>
                <label><input type="radio" name="start_day" value="monday" class="tintcal-setting-input" <?php echo checked(get_option('tintcal_start_day', 'monday'), 'monday', false); ?>> <?php echo esc_html__( '月曜日', 'tintcal' ); ?></label>
              </td>
            </tr>
            <tr>
              <th scope="row"><?php echo esc_html__( '曜日ヘッダーの背景色', 'tintcal' ); ?></th>
              <td>
                <?php $color = esc_attr(get_option('tintcal_header_color', '#eeeeee')); ?>
                <input type="color" name="header_color" id="tintcal-header-color-input" class="tintcal-setting-input" value="<?php echo esc_attr($color) ?>">
              </td>
            </tr>
            <tr>
              <th scope="row"><?php echo esc_html__( '日本の祝日', 'tintcal' ); ?></th>
              <td>
                <label>
                  <input type="checkbox" name="enable_holidays" value="1" class="tintcal-setting-input" <?php echo checked(get_option('tintcal_enable_holidays', 1), 1, false); ?>> <?php echo esc_html__( '祝日を表示する', 'tintcal' ); ?>
                </label>
                
                <?php $color = esc_attr(get_option('tintcal_holiday_color', '#ffdddd')); ?>
                <input type="color" name="holiday_color" id="tintcal-holiday-color-input" class="tintcal-setting-input" value="<?php echo esc_attr($color) ?>" style="margin-left: 10px;">
              </td>
            </tr>
            <tr>
              <th scope="row"><?php echo esc_html__( '土日の色', 'tintcal' ); ?></th>
              <td>
                <label style="font-weight: bold; display: block; margin-bottom: 5px;">
                  <input type="checkbox" name="tintcal_show_header_weekend_color" id="tintcal-header-weekend-color-toggle" class="tintcal-setting-input" value="1" <?php echo checked(get_option('tintcal_show_header_weekend_color', 1), 1, false); ?>>
                  <?php echo esc_html__( '曜日ヘッダーを色付けする', 'tintcal' ); ?>
                </label>
                <hr style="margin: 10px 0;">
                
                <label><input type="checkbox" name="show_sunday_color" value="1" class="tintcal-setting-input" <?php echo checked(get_option('tintcal_show_sunday_color', 1), 1, false); ?>> <?php echo esc_html__( '日付セルの日曜を色付け', 'tintcal' ); ?></label>
                <?php $color = esc_attr(get_option('tintcal_sunday_color', '#ffecec')); ?>
                <input type="color" name="sunday_color" id="tintcal-sunday-color-input" class="tintcal-setting-input" value="<?php echo esc_attr($color) ?>">

                <br><br>
                <label><input type="checkbox" name="show_saturday_color" value="1" class="tintcal-setting-input" <?php echo checked(get_option('tintcal_show_saturday_color', 1), 1, false); ?>> <?php echo esc_html__( '日付セルの土曜を色付け', 'tintcal' ); ?></label>
                <?php $color = esc_attr(get_option('tintcal_saturday_color', '#ecf5ff')); ?>
                <input type="color" name="saturday_color" id="tintcal-saturday-color-input" class="tintcal-setting-input" value="<?php echo esc_attr($color) ?>">
              </td>
            </tr>
            <tr>
              <th scope="row"><?php echo esc_html__( 'カテゴリ凡例', 'tintcal' ); ?></th>
              <td>
                <label>
                  <input type="checkbox" name="show_legend" value="1" id="tintcal-legend-toggle-input" class="tintcal-setting-input" <?php echo checked(get_option('tintcal_show_legend', 1), 1, false); ?>> <?php echo esc_html__( 'カレンダー下に凡例を表示する', 'tintcal' ); ?>
                </label>
              </td>
            </tr>
            <tr>
              <th scope="row"><?php echo esc_html__( '「今月に戻る」ボタン', 'tintcal' ); ?></th>
              <td>
                <label>
                  <input type="checkbox" name="tintcal_show_today_button" value="1" id="tintcal-show-today-button-input" <?php echo checked(get_option('tintcal_show_today_button', 1), 1, false); ?>> <?php echo esc_html__( 'カレンダーに「今月に戻る」ボタンを表示する', 'tintcal' ); ?>
                </label>
              </td>
            </tr>
          </table>
          <?php submit_button( esc_html__( '基本設定を保存', 'tintcal' ), 'primary', 'tintcal_save_settings' ); ?>
        </form>
      </div>
      <div class="tintcal-settings-demo">
        <h3><?php echo esc_html__( '設定デモ', 'tintcal' ); ?></h3>
        <p style="font-size: 12px; color: #777;"><?php echo esc_html__( '実際の日付やカテゴリは反映されません。色のイメージを確認できます。', 'tintcal' ); ?></p>
        <div style="text-align: center; margin-bottom: 10px; height: 28px; line-height: 28px;">
          <button type="button" id="tintcal-demo-today-btn" style="font-size: 11px; padding: 2px 6px; display: none;"><?php echo esc_html__( '今月に戻る', 'tintcal' ); ?></button>
        </div>
        <div id="tintcal-demo-calendar" class="tintcal-demo-calendar">
          </div>
        <div id="tintcal-demo-legend" class="tintcal-demo-legend">
            <div style="font-size: 13px; margin-top: 10px; font-weight: bold;"><?php echo esc_html__( '凡例の表示イメージ', 'tintcal' ); ?></div>
            <div style="font-size: 12px; margin-top: 5px;">
              <span style="display:inline-block;width:12px;height:12px;background:#ffdddd;margin-right:4px;border:1px solid #ccc;"></span><?php echo esc_html__( 'カテゴリ例', 'tintcal' ); ?>
            </div>
        </div>
      </div>
    </div>
  </div>

  <div id="tintcal-tab-2" class="tintcal-tab-content">
    <h2><?php echo esc_html__( 'プレビューカレンダー', 'tintcal' ); ?></h2>
    <p><?php echo esc_html__( '実際の日付をクリックして、カテゴリの割り当てができます。', 'tintcal' ); ?></p>
    <div id="tintcal-preview-wrapper">
      <?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- This function returns HTML which is already escaped internally. ?>
      <?php echo wp_kses_post(tintcal_render_calendar_base_html()); ?>
    </div>
    <hr>
    <h2><?php echo esc_html__( 'カテゴリ追加・編集', 'tintcal' ); ?></h2>
    <p class="description"><?php echo wp_kses_post( sprintf( /* translators: %1$s, %2$s: bold tag */ esc_html__( 'このプラグインは%1$s単一カテゴリのみ%2$sに対応しています。2件目以降のカテゴリは保存対象になりません。', 'tintcal' ), '<strong>', '</strong>' ) ); ?></p>
    <div id="tintcal-category-editor">
      <table id="tintcal-category-table" class="wp-list-table widefat fixed striped">
        <thead>
          <tr>
            <th style="width: 20%;"><?php echo esc_html__( 'カテゴリ名', 'tintcal' ); ?></th>
            <th style="width: 15%;"><?php echo esc_html__( '色コード', 'tintcal' ); ?></th>
            <th style="width: 10%;"><?php echo esc_html__( '表示', 'tintcal' ); ?></th>
            <th style="width: 55%;"><?php echo esc_html__( '操作', 'tintcal' ); ?></th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
      <div class="tintcal-category-add-form">
        <input type="text" id="new-category-name" placeholder="<?php echo esc_attr__( 'カテゴリ名（1件のみ）', 'tintcal' ); ?>">
        <input type="color" id="new-category-color" value="#dddddd">
        <button type="button" id="add-category" class="button"><?php echo esc_html__( '保存', 'tintcal' ); ?></button>
      </div>
    </div>
  </div>

    </div>

<?php
// --- 【条件分岐①】カレンダー管理権限がある場合のみ、このセクションを表示 ---
if (current_user_can('edit_posts')) :
    // --- 存在するカレンダーのリストを取得 ---
    $tintcal_calendars = get_posts([
        'post_type'      => 'tintcal',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'post_status'    => ['publish', 'draft', 'private', 'pending'],
    ]);

    // --- 【条件分岐②】カレンダーが1件以上存在する場合のみ、このセクションを表示 ---
    if (!empty($tintcal_calendars)) :
?>
    
<hr style="margin-top: 40px;">
<div class="tintcal-shortcuts-section">
    <h3><?php echo esc_html__( '個別カレンダーの編集', 'tintcal' ); ?></h3>
    <p><?php echo esc_html__( '各カレンダーに固有の設定（表示するカテゴリの選択など）を行うには、以下の一覧から編集画面へ移動してください。', 'tintcal' ); ?></p>
    <ul>
        <?php
        foreach ($tintcal_calendars as $jc) {
            // 編集画面への正しいURLを生成
            $edit_url = admin_url('post.php?post=' . $jc->ID . '&action=edit');
            ?>
            <li>
                <a href="<?php echo esc_url($edit_url); ?>">
                    <?php echo esc_html($jc->post_title); ?> (ID: <?php echo esc_html($jc->ID); ?>)
                </a>
            </li>
            <?php
        }
        ?>
    </ul>
</div>

<?php
    endif; // if (!empty($tintcal_calendars))
endif; // if (tintcal_current_user_can_access('manage_calendars'))
?>