<?php
/**
 * pullcass - 共通固定フッター（参考サイト準拠）
 * すべてのフロントページで使用する共通固定フッター
 * 
 * 必要な変数:
 * - $businessHours: 営業時間
 * - $businessHoursNote: 営業時間の備考
 * - $phoneNumber: 電話番号
 */
?>
<!-- 固定フッター（電話ボタン）参考サイト準拠 -->
<footer class="site-footer-fixed">
    <div class="fixed-footer-container">
        <div class="fixed-footer-info">
            <p class="open-hours"><?php echo $businessHours ? h($businessHours) : 'OPEN 準備中'; ?></p>
            <p class="reception-info"><?php echo $businessHoursNote ? h($businessHoursNote) : '電話予約受付中！'; ?></p>
        </div>
        <?php if ($phoneNumber): ?>
        <a href="tel:<?php echo h(preg_replace('/[^0-9]/', '', $phoneNumber)); ?>" class="fixed-footer-phone-button">
            <span class="phone-icon"><i class="fas fa-phone"></i></span>
            <span class="phone-number"><?php echo h($phoneNumber); ?></span>
        </a>
        <?php else: ?>
        <span class="fixed-footer-phone-button" style="opacity: 0.6; cursor: default;">
            <span class="phone-icon"><i class="fas fa-phone"></i></span>
            <span class="phone-number">電話番号準備中</span>
        </span>
        <?php endif; ?>
    </div>
</footer>
