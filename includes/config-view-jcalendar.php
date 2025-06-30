<div class="wrap">
    <h1>プラグイン設定</h1>
    <p>このページでは、TintCalプラグインの全体的な設定を行います。</p>
    
    <hr>

    <h2>ライセンス認証</h2>
    <?php

    $jcal_license_key = get_option('jcalendar_license_key', '');
    $payload = function_exists('jcalendar_get_license_status') ? jcalendar_get_license_status() : false;
    $is_valid = ($payload && isset($payload['exp']) && $payload['exp'] > time());
    ?>

    <p>
        ご購入時に発行されたライセンスキーを入力し、認証を行ってください。<br>
        <?php if (!$is_valid): // ライセンスが有効でない場合のみ、以下を表示 ?>
            <a href="https://tintcal.com/pricing.html" target="_blank" class="button button-primary" style="margin-top: 10px;">ライセンスキーを取得する</a>
            <a href="https://jcalendar-key-recovery-185317068700.asia-northeast1.run.app" target="_blank" style="margin-left: 15px; line-height: 2.5; text-decoration: none;">キーを紛失した場合</a>
        <?php endif; ?>
    </p>
    <form method="post" action="">
        <?php // 2つの処理で使うnonceを両方ここに含めます ?>
        <?php wp_nonce_field('jcalendar_license_settings_nonce'); ?>
        
        <table class="form-table">
            <tr>
                <th scope="row"><label for="jcalendar_license_key">ライセンスキー (UUID形式)</label></th>
                <td>
                    <input type="text" id="jcalendar_license_key" name="jcalendar_license_key" value="<?php echo esc_attr($jcal_license_key); ?>" class="regular-text" style="width: 350px;" <?php echo $is_valid ? 'disabled' : ''; ?>>
                    <?php if ($is_valid): ?>
                        <span style="color:green; font-weight:bold; margin-left:10px;">認証済み</span>
                    <?php elseif (!empty($jcal_license_key)): ?>
                        <span style="color:red; font-weight:bold; margin-left:10px;">認証に失敗しました。キーを確認するか、Stripeの契約状況をご確認ください。</span>
                    <?php endif; ?>
                </td>
            </tr>
        </table>

        <?php // ボタンを1つのフォーム内に配置します ?>
        <p class="submit">
            <?php // ▼▼▼ ライセンスが「未認証」の時だけ、保存ボタンを表示します ▼▼▼ ?>
            <?php if (!$is_valid): ?>
                <input type="submit" name="jcalendar_save_license_settings" class="button button-primary" value="ライセンス設定を保存">
            <?php endif; ?>

            <?php // ▼▼▼ ライセンスキーが入力されている場合（認証済み or 認証失敗）に、解除ボタンと管理リンクを表示します ▼▼▼ ?>
            <?php if (!empty($jcal_license_key)): ?>
                <input type="submit" name="jcalendar_deactivate_license" class="button button-secondary" value="ライセンス解除">
                
                <?php // 認証済みの場合のみ、契約管理リンクを表示します ?>
                <?php if ($is_valid): ?>
                    <a href="https://billing.stripe.com/p/login/28E6oIc3Ydww2JFdgW6oo00" target="_blank" class="button button-secondary" style="margin-left: 10px;">ご契約内容の確認</a>
                <?php endif; ?>
            <?php endif; ?>
        </p>
    </form>

    <hr>

    <h2>
    ロール権限設定
    <span style="color:#fff;background:#888;padding:2px 6px;border-radius:3px;font-size:12px;vertical-align:middle;">Pro専用</span>
    </h2>
    <p>ユーザーの権限グループごとに、アクセスできるTintCalの管理メニューを制限します。<br>※管理者(Administrator)は、常にすべてのメニューにアクセスできます。</p>
    <?php if (!$is_valid): ?>
    <div class="jcal-pro-lock-msg" style="color:#888; margin-bottom:8px;">
        この機能はPro版専用です。アップグレードするとロール権限を自由に設定できます。
    </div>
    <?php endif; ?>

    <form method="post" action="" <?php if (!$is_valid) echo 'class="jcal-pro-disabled"'; ?>>
        <input type="hidden" name="action" value="jcalendar_save_role_settings">
        <?php wp_nonce_field('jcalendar_role_settings_nonce'); ?>

        <table class="form-table jcal-permissions-table" style="width: auto;">
            <thead>
                <tr>
                    <th scope="col">権限グループ</th>
                    <th scope="col" style="text-align: center;">カテゴリ・日付入力の管理</th>
                    <th scope="col" style="text-align: center;">個別カレンダー編集の管理</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // 設定を管理したい権限グループのリスト
                $roles_to_manage = [
                    'editor'      => '編集者'
                ];
                // 保存されている設定値を取得
                $permissions = get_option('jcalendar_role_permissions', []);
                ?>

                <?php foreach ($roles_to_manage as $role_slug => $role_name) : ?>
                    <tr>
                        <th scope="row"><?php echo esc_html($role_name); ?></th>
                        <td style="text-align: center;">
                            <?php
                            // 「共通設定の管理」のチェック状態
                            $checked_common = isset($permissions[$role_slug]['manage_common_settings']) && $permissions[$role_slug]['manage_common_settings'];
                            ?>
                            <input type="checkbox" <?php if (!$is_valid) echo 'disabled'; ?> name="permissions[<?php echo esc_attr($role_slug); ?>][manage_common_settings]" value="1" <?php checked($checked_common); ?>>
                        </td>
                        <td style="text-align: center;">
                             <?php
                            // 「カレンダーの管理」のチェック状態
                            $checked_calendars = isset($permissions[$role_slug]['manage_calendars']) && $permissions[$role_slug]['manage_calendars'];
                            ?>
                            <input type="checkbox" <?php if (!$is_valid) echo 'disabled'; ?> name="permissions[<?php echo esc_attr($role_slug); ?>][manage_calendars]" value="1" <?php checked($checked_calendars); ?>>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <button type="submit" class="button button-primary" <?php if (!$is_valid) echo 'disabled'; ?>>権限設定を保存</button>
    </form>
</div>