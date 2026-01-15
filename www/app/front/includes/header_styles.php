<?php
/**
 * pullcass - 共通ヘッダー・フッター用スタイル
 * すべてのフロントページで使用する共通スタイル
 * 
 * CSSの:rootでテーマ変数を設定した後にincludeしてください
 */
?>
/* ==================== ヘッダー ==================== */
.site-header {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    z-index: 1000;
    background: rgba(255, 255, 255, 0.3);
    backdrop-filter: blur(7px);
    -webkit-backdrop-filter: blur(7px);
    box-shadow: rgba(0, 0, 0, 0.1) 0px 4px 6px -1px, rgba(0, 0, 0, 0.1) 0px 2px 4px -2px;
    height: 70px;
    display: flex;
    align-items: center;
}

.header-container {
    max-width: 1200px;
    width: 100%;
    margin: 0 auto;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0 15px;
}

.logo-area {
    display: flex;
    align-items: center;
    text-decoration: none;
    color: inherit;
}

.logo-image {
    width: 50px;
    height: 50px;
    object-fit: contain;
    margin-right: 12px;
}

.logo-text {
    display: flex;
    flex-direction: column;
}

.logo-main-title {
    font-size: 15px;
    font-weight: 700;
    color: var(--color-text);
    line-height: 1.3;
}

.logo-sub-title {
    font-size: 13px;
    font-weight: 700;
    color: var(--color-text);
    line-height: 1.3;
}

/* ハンバーガーメニューボタン */
.hamburger-button {
    width: 56px;
    height: 56px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: linear-gradient(to right bottom, var(--color-primary), var(--color-primary-light));
    color: var(--color-btn-text);
    border-radius: 9999px;
    border: none;
    cursor: pointer;
    transition: transform 0.2s;
    box-shadow: none;
}

.hamburger-button:hover {
    transform: scale(1.05);
}

.hamburger-lines {
    display: flex;
    flex-direction: column;
    gap: 4px;
    margin-bottom: 3px;
}

.hamburger-line {
    width: 22px;
    height: 2px;
    background: var(--color-btn-text);
    border-radius: 1px;
}

.menu-text {
    font-size: 9px;
    font-weight: 700;
    letter-spacing: 1px;
}

/* ==================== 固定フッター ==================== */
.fixed-footer {
    position: fixed;
    bottom: 0;
    left: 0;
    width: 100%;
    background: rgba(255, 255, 255, 0.3);
    backdrop-filter: blur(7px);
    -webkit-backdrop-filter: blur(7px);
    padding: 8px 0;
    z-index: 1000;
    box-shadow: rgba(0, 0, 0, 0.15) 0px -4px 20px 0px;
    height: 56px;
    display: flex;
    align-items: center;
}

.fixed-footer-container {
    max-width: 1200px;
    width: 100%;
    margin: 0 auto;
    padding: 0 15px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.fixed-footer-info {
    color: var(--color-text);
    font-size: 12px;
    line-height: 1.4;
}

.fixed-footer-info .open-hours {
    font-weight: 700;
    font-size: 14px;
}

.phone-button {
    display: flex;
    align-items: center;
    gap: 8px;
    background: linear-gradient(to right bottom, var(--color-primary), var(--color-primary-light));
    color: var(--color-btn-text);
    padding: 10px 20px;
    border-radius: 30px;
    text-decoration: none;
    font-weight: 700;
    font-size: 14px;
    transition: transform 0.2s;
    box-shadow: none;
}

.phone-button:hover {
    transform: scale(1.03);
}

.phone-button i {
    font-size: 1rem;
}

/* ==================== レスポンシブ ==================== */
@media (max-width: 600px) {
    .site-header {
        height: 60px;
    }
    
    .logo-image {
        width: 40px;
        height: 40px;
    }
    
    .logo-main-title {
        font-size: 13px;
    }
    
    .logo-sub-title {
        font-size: 11px;
    }
    
    .hamburger-button {
        width: 48px;
        height: 48px;
    }
    
    .hamburger-line {
        width: 18px;
    }
    
    .fixed-footer {
        height: auto;
        min-height: 50px;
        padding: 10px 0;
    }
    
    .fixed-footer-container {
        flex-direction: row;
        gap: 10px;
        padding: 0 12px;
    }
    
    .fixed-footer-info {
        text-align: left;
        font-size: 11px;
        flex: 1;
        min-width: 0;
    }
    
    .fixed-footer-info .open-hours {
        font-size: 13px;
    }
    
    .phone-button {
        padding: 4px 12px;
        font-size: 12px;
        white-space: nowrap;
        flex-shrink: 0;
    }
}
