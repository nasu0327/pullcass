<?php
/**
 * pullcass - 共通固定フッター
 * すべてのフロントページで使用する共通固定フッター
 * 
 * 必要な変数:
 * - $businessHours: 営業時間
 * - $businessHoursNote: 営業時間の備考
 * - $phoneNumber: 電話番号
 */
?>
<!-- 固定フッター（電話ボタン） -->
<footer class="fixed-footer">
    <div class="fixed-footer-container">
        <div class="fixed-footer-info">
            <p class="open-hours"><?php echo $businessHours ? h($businessHours) : 'OPEN 準備中'; ?></p>
            <p><?php echo $businessHoursNote ? h($businessHoursNote) : '電話予約受付中！'; ?></p>
        </div>
        <?php if ($phoneNumber): ?>
        <a href="tel:<?php echo h(preg_replace('/[^0-9]/', '', $phoneNumber)); ?>" class="phone-button">
            <i class="fas fa-phone"></i>
            <span><?php echo h($phoneNumber); ?></span>
        </a>
        <?php else: ?>
        <span class="phone-button" style="opacity: 0.6; cursor: default;">
            <i class="fas fa-phone"></i>
            <span>電話番号準備中</span>
        </span>
        <?php endif; ?>
    </div>
</footer>
