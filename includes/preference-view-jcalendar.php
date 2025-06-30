<div class="wrap">
  <h1>TintCal 設定</h1>

  <?php settings_errors('jcalendar_messages'); ?>

  <?php
    // ライセンス状況を一度だけ取得
    $is_license_valid = jcalendar_is_license_valid();
  ?>

  <h2 class="nav-tab-wrapper">
    <a href="#jcal-tab-1" class="nav-tab nav-tab-active">基本設定</a>
    <a href="#jcal-tab-2" class="nav-tab">カテゴリ・日付設定</a>
    <?php if (jcalendar_is_license_valid()): ?>
      <a href="#jcal-tab-3" class="nav-tab">データ管理</a>
    <?php endif; ?>
  </h2>

  <div id="jcal-tab-1" class="jcal-tab-content active">
    <div class="jcal-settings-container">
      <div class="jcal-settings-form">
        <?php if (!$is_license_valid): ?>
            <div class="notice notice-info inline">
                <p>カレンダーのカラーカスタマイズはPro版の機能です。Pro版にアップグレードすると、全てのカラーを自由に変更できます。</p>
            </div>
        <?php endif; ?>
        <form method="post" action="">
          <input type="hidden" name="action" value="jcalendar_save_basic_settings">
          <?php wp_nonce_field('jcalendar_settings_nonce'); ?>

          <table class="form-table">
            <tr>
              <th scope="row">週の開始曜日</th>
              <td>
                <label><input type="radio" name="start_day" value="sunday" class="jcal-setting-input" <?= checked(get_option('jcalendar_start_day', 'sunday'), 'sunday', false); ?>> 日曜日</label><br>
                <label><input type="radio" name="start_day" value="monday" class="jcal-setting-input" <?= checked(get_option('jcalendar_start_day', 'monday'), 'monday', false); ?>> 月曜日</label>
              </td>
            </tr>
            <tr>
              <th scope="row">曜日ヘッダーの背景色</th>
              <td>
                <?php $color = esc_attr(get_option('jcalendar_header_color', '#eeeeee')); ?>
                <?php if ($is_license_valid): ?>
                  <input type="color" name="header_color" id="jcal-header-color-input" class="jcal-setting-input" value="<?= $color ?>">
                <?php else: ?>
                  <span style="display: inline-block; width: 60px; height: 28px; border: 1px solid #ccc; background-color: <?= $color ?>; vertical-align: middle;"></span>
                <?php endif; ?>
              </td>
            </tr>
            <tr>
              <th scope="row">日本の祝日</th>
              <td>
                <label>
                  <input type="checkbox" name="enable_holidays" value="1" class="jcal-setting-input" <?= checked(get_option('jcalendar_enable_holidays', 1), 1, false); ?>> 祝日を表示する
                </label>
                
                <?php $color = esc_attr(get_option('jcalendar_holiday_color', '#ffdddd')); ?>
                <?php if ($is_license_valid): ?>
                  <input type="color" name="holiday_color" id="jcal-holiday-color-input" class="jcal-setting-input" value="<?= $color ?>" style="margin-left: 10px;">
                <?php else: ?>
                  <span style="display: inline-block; width: 60px; height: 28px; border: 1px solid #ccc; background-color: <?= $color ?>; margin-left: 10px; vertical-align: middle;"></span>
                <?php endif; ?>
              </td>
            </tr>
            <tr>
              <th scope="row">土日の色</th>
              <td>
                <label style="font-weight: bold; display: block; margin-bottom: 5px;">
                  <input type="checkbox" name="jcal_show_header_weekend_color" id="jcal-header-weekend-color-toggle" class="jcal-setting-input" value="1" <?= checked(get_option('jcal_show_header_weekend_color', 1), 1, false); ?>>
                  曜日ヘッダーを色付けする
                </label>
                <hr style="margin: 10px 0;">
                
                <label><input type="checkbox" name="show_sunday_color" value="1" class="jcal-setting-input" <?= checked(get_option('jcalendar_show_sunday_color', 1), 1, false); ?>> 日付セルの日曜を色付け</label>
                <?php $color = esc_attr(get_option('jcalendar_sunday_color', '#ffecec')); ?>
                <?php if ($is_license_valid): ?>
                  <input type="color" name="sunday_color" id="jcal-sunday-color-input" class="jcal-setting-input" value="<?= $color ?>">
                <?php else: ?>
                  <span style="display: inline-block; width: 60px; height: 28px; border: 1px solid #ccc; background-color: <?= $color ?>; vertical-align: middle;"></span>
                <?php endif; ?>

                <br><br>
                <label><input type="checkbox" name="show_saturday_color" value="1" class="jcal-setting-input" <?= checked(get_option('jcalendar_show_saturday_color', 1), 1, false); ?>> 日付セルの土曜を色付け</label>
                <?php $color = esc_attr(get_option('jcalendar_saturday_color', '#ecf5ff')); ?>
                <?php if ($is_license_valid): ?>
                  <input type="color" name="saturday_color" id="jcal-saturday-color-input" class="jcal-setting-input" value="<?= $color ?>">
                <?php else: ?>
                  <span style="display: inline-block; width: 60px; height: 28px; border: 1px solid #ccc; background-color: <?= $color ?>; vertical-align: middle;"></span>
                <?php endif; ?>
              </td>
            </tr>
            <tr>
              <th scope="row">カテゴリ凡例</th>
              <td>
                <label>
                  <input type="checkbox" name="show_legend" value="1" id="jcal-legend-toggle-input" class="jcal-setting-input" <?= checked(get_option('jcalendar_show_legend', 1), 1, false); ?>> カレンダー下に凡例を表示する
                </label>
              </td>
            </tr>
            <tr>
              <th scope="row">「今月に戻る」ボタン</th>
              <td>
                <label>
                  <input type="checkbox" name="jcal_show_today_button" value="1" id="jcal-show-today-button-input" <?= checked(get_option('jcal_show_today_button', 1), 1, false); ?>> カレンダーに「今月に戻る」ボタンを表示する
                </label>
              </td>
            </tr>
          </table>
          <?php submit_button('基本設定を保存', 'primary', 'jcalendar_save_settings'); ?>
        </form>
      </div>
      <div class="jcal-settings-demo">
        <h3>設定デモ</h3>
        <p style="font-size: 12px; color: #777;">実際の日付やカテゴリは反映されません。色のイメージを確認できます。</p>
        <div style="text-align: center; margin-bottom: 10px; height: 28px; line-height: 28px;">
          <button type="button" id="jcal-demo-today-btn" style="font-size: 11px; padding: 2px 6px; display: none;">今月に戻る</button>
        </div>
        <div id="jcal-demo-calendar" class="jcal-demo-calendar">
          </div>
        <div id="jcal-demo-legend" class="jcal-demo-legend">
            <div style="font-size: 13px; margin-top: 10px; font-weight: bold;">凡例の表示イメージ</div>
            <div style="font-size: 12px; margin-top: 5px;">
              <span style="display:inline-block;width:12px;height:12px;background:#ffdddd;margin-right:4px;border:1px solid #ccc;"></span>カテゴリ例1
              <span style="display:inline-block;width:12px;height:12px;background:#ecf5ff;margin-right:4px;margin-left:8px;border:1px solid #ccc;"></span>カテゴリ例2
            </div>
        </div>
      </div>
    </div>
  </div>

  <div id="jcal-tab-2" class="jcal-tab-content">
    <h2>プレビューカレンダー</h2>
    <p>実際の日付をクリックして、カテゴリの割り当てができます。</p>
    <div id="jcal-preview-wrapper">
      <?php echo jcalendar_render_calendar_base_html(); ?>
    </div>
    <hr>
    <h2>カテゴリ追加・編集</h2>
    <div id="jcal-category-editor">
      <table id="jcalendar-category-table" class="wp-list-table widefat fixed striped">
        <thead>
          <tr>
            <th style="width: 20%;">カテゴリ名</th>
            <th style="width: 15%;">色コード</th>
            <th style="width: 10%;">表示</th>
            <th style="width: 55%;">操作</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
      <div class="jcal-category-add-form">
        <input type="text" id="new-category-name" placeholder="例：休日">
        <input type="color" id="new-category-color" value="#dddddd" <?php if (!$is_license_valid) echo 'disabled'; ?>>
        <?php if (!$is_license_valid): ?>
          <span class="description" style="margin-left: 10px;">Pro版では自由な色を設定できます。</span>
        <?php endif; ?>
        <button type="button" id="add-category" class="button">追加</button>
      </div>
    </div>
  </div>

  <?php if (jcalendar_is_license_valid()): ?>
    <div id="jcal-tab-3" class="jcal-tab-content">
      <h2>カテゴリ & 日付データのエクスポート / インポート</h2>
      <p>このカレンダーのカテゴリ設定と日付の割り当てデータをファイルに保存（エクスポート）したり、ファイルから読み込む（インポート）ことができます。</p>
      
      <div style="margin-bottom: 2em; padding: 1em; border: 1px solid #ccc;">
        <h3>エクスポート</h3>
        <form method="post" action="">
            <?php wp_nonce_field('jcalendar_settings_nonce'); ?>
            <input type="submit" name="jcalendar_export" class="button" value="現在のデータをエクスポート">
        </form>
      </div>

      <div style="margin-bottom: 2em; padding: 1em; border: 1px solid #ccc;">
        <h3>インポート</h3>
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('jcalendar_settings_nonce'); ?>
            <input type="file" name="jcalendar_import_file" required />
            <p><label><input type="checkbox" name="import_append_mode" value="1"> 既存のデータとマージする（追加モード）</label></p>
            <p class="description">チェックを入れない場合、既存のデータは全て上書きされます。</p>
            <input type="submit" name="jcalendar_import" class="button button-primary" value="インポート実行" />
        </form>
      </div>

      <hr>
      <h2>データリセット</h2>
      <div style="padding: 1em; border: 1px solid #c00; background: #fff8f8;">
        <h3>祝日データの再取得</h3>
        <p>外部サイトから最新の祝日データを強制的に再取得し、ローカルの祝日ファイルを更新します。通常は自動で更新されるため、手動での実行は不要です。</p>
        <p><button id="reFetchHolidays" class="button button-secondary">祝日データを再取得</button></p>
        <hr>
        <h3>日付割り当てのリセット</h3>
        <p>カレンダーに設定した日付の色付け（カテゴリの割り当て）をすべて削除します。カテゴリ自体は削除されません。</p>
        <p><button id="reset-assignments" class="button">日付の割当を初期化</button></p>
        <hr>
        <h3>全データ初期化</h3>
        <p style="color: #c00;"><b>注意：この操作は元に戻せません。</b>作成したカテゴリと、日付の割り当ての全てのデータが削除されます。</p>
        <p><button id="reset-all-data" class="button button-danger">全データ初期化</button></p>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php
// --- 【条件分岐①】カレンダー管理権限がある場合のみ、このセクションを表示 ---
if (jcalendar_current_user_can_access('manage_calendars')) :
    // さらに、ライセンスが有効な場合のみ表示
    if (jcalendar_is_license_valid()):
        // --- 存在するカレンダーのリストを取得 ---
        $jcal_calendars = get_posts([
            'post_type'      => 'jcalendar',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'post_status'    => ['publish', 'draft', 'private', 'pending'],
        ]);

        // --- 【条件分岐②】カレンダーが1件以上存在する場合のみ、このセクションを表示 ---
        if (!empty($jcal_calendars)) :
?>
    
<hr style="margin-top: 40px;">
<div class="jcal-shortcuts-section">
    <h3>個別カレンダーの編集</h3>
    <p>各カレンダーに固有の設定（表示するカテゴリの選択など）を行うには、以下の一覧から編集画面へ移動してください。</p>
    <ul>
        <?php
        foreach ($jcal_calendars as $jc) {
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
        endif; // if (!empty($jcal_calendars))
    endif; // if (jcalendar_is_license_valid())
endif; // if (jcalendar_current_user_can_access('manage_calendars'))
?>