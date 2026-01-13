<?php
/**
 * pullcass - スーパー管理画面
 * システム設定
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';

requireSuperAdminLogin();

$pageTitle = 'システム設定';
include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-cog"></i> システム設定</h1>
    <p class="subtitle">pullcass システム全体の設定を管理します</p>
</div>

<div class="content-section">
    <div class="section-header">
        <h2><i class="fas fa-info-circle"></i> システム情報</h2>
    </div>
    
    <div class="info-grid">
        <div class="info-item">
            <div class="info-label">バージョン</div>
            <div class="info-value">v1.0.0-dev (MVP)</div>
        </div>
        <div class="info-item">
            <div class="info-label">PHP バージョン</div>
            <div class="info-value"><?php echo phpversion(); ?></div>
        </div>
        <div class="info-item">
            <div class="info-label">サーバー</div>
            <div class="info-value"><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></div>
        </div>
        <div class="info-item">
            <div class="info-label">データベース</div>
            <div class="info-value">MariaDB / MySQL</div>
        </div>
    </div>
</div>

<div class="content-section">
    <div class="section-header">
        <h2><i class="fas fa-tools"></i> 機能設定</h2>
    </div>
    
    <div class="coming-soon">
        <div class="coming-soon-icon"><i class="fas fa-hammer"></i></div>
        <h3>準備中</h3>
        <p>システム設定機能は現在開発中です。</p>
    </div>
</div>

<style>
    .info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
    }
    
    .info-item {
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid var(--border-color);
        border-radius: 10px;
        padding: 20px;
    }
    
    .info-label {
        font-size: 0.85rem;
        color: var(--text-muted);
        margin-bottom: 8px;
    }
    
    .info-value {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--text-light);
    }
    
    .coming-soon {
        text-align: center;
        padding: 60px 20px;
    }
    
    .coming-soon-icon {
        font-size: 4rem;
        color: var(--text-muted);
        margin-bottom: 20px;
    }
    
    .coming-soon h3 {
        font-size: 1.3rem;
        color: var(--text-light);
        margin-bottom: 10px;
    }
    
    .coming-soon p {
        color: var(--text-muted);
    }
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>
