<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly ?>
<div class="wrap">
    <h1><?php echo esc_html__( 'TintCal Proで、さらに多くの可能性を', 'tintcal' ); ?></h1>
    <p><?php
        echo wp_kses_post( sprintf(
            /* translators: %1$s: strong tag, %2$s: strong tag */
            esc_html__( 'TintCalをご利用いただきありがとうございます。通常版でも基本的なカレンダー機能をご利用いただけますが、%1$sTintCal Pro%2$sにアップグレードすると、さらに高度で便利な機能が解放されます。', 'tintcal' ),
            '<strong>', '</strong>'
        ) );
    ?></p>
    
    <div style="margin-top: 2em; padding: 1.5em; background: #fff; border: 1px solid #ddd;">
        <h2><?php echo esc_html__( 'Pro版の主な機能', 'tintcal' ); ?></h2>
        <table class="widefat" style="margin-top: 1em;">
            <thead>
                <tr>
                    <th style="width: 25%;"><?php echo esc_html__( '機能', 'tintcal' ); ?></th>
                    <th style="width: 35%;"><?php echo esc_html__( '通常版', 'tintcal' ); ?></th>
                    <th style="width: 40%;"><strong><?php echo esc_html__( 'Pro版', 'tintcal' ); ?></strong></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong><?php echo esc_html__( 'カテゴリ作成数', 'tintcal' ); ?></strong></td>
                    <td><?php echo esc_html__( '1つまで', 'tintcal' ); ?></td>
                    <td><strong><?php echo esc_html__( '無制限', 'tintcal' ); ?></strong></td>
                </tr>
                <tr>
                    <td><strong><?php echo esc_html__( 'カラーカスタマイズ', 'tintcal' ); ?></strong></td>
                    <td><?php echo esc_html__( '共通設定でカレンダーの色を変更可能', 'tintcal' ); ?></td>
                    <td><strong><?php echo esc_html__( '個別カレンダーごとに、すべての色を自由に変更可能', 'tintcal' ); ?></strong></td>
                </tr>
                <tr>
                    <td><strong><?php echo esc_html__( 'ロール権限設定', 'tintcal' ); ?></strong></td>
                    <td><?php echo esc_html__( '利用不可', 'tintcal' ); ?></td>
                    <td><strong><?php echo esc_html__( '編集者などの権限グループごとに操作を制限', 'tintcal' ); ?></strong></td>
                </tr>
                <tr>
                    <td><strong><?php echo esc_html__( 'データ管理', 'tintcal' ); ?></strong></td>
                    <td><?php echo esc_html__( '利用不可', 'tintcal' ); ?></td>
                    <td><strong><?php echo esc_html__( '設定のエクスポート/インポート、データリセット', 'tintcal' ); ?></strong></td>
                </tr>
                <tr>
                    <td><strong><?php echo esc_html__( '優先サポート', 'tintcal' ); ?></strong></td>
                    <td><?php echo esc_html__( 'WordPress.orgフォーラム', 'tintcal' ); ?></td>
                    <td><strong><?php echo esc_html__( 'メールによる優先サポート', 'tintcal' ); ?></strong></td>
                </tr>
            </tbody>
        </table>
    </div>

    <div style="margin-top: 2em; text-align: center;">
        <a href="https://tintcal.com/" target="_blank" class="button button-primary button-hero">
            <?php echo esc_html__( 'TintCal Proの詳細を見てみる', 'tintcal' ); ?>
        </a>
        <p class="description" style="margin-top: 1em;">
            <?php echo esc_html__( 'ウェブサイトの運営をさらに効率的で豊かにするために、ぜひPro版の導入をご検討ください。', 'tintcal' ); ?>
        </p>
    </div>
</div>
