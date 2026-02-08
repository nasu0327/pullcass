/* ================================
   ハンバーガーメニュー（ポップアップ）スタイル
   ================================ */

/* オーバーレイ */
.popup-menu-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.85);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    z-index: 9999;
    display: none;
    opacity: 0;
    transition: opacity 0.3s ease-in-out;
}

.popup-menu-overlay.active {
    display: flex;
    opacity: 1;
}

/* メニューパネル */
.popup-menu-panel {
    position: fixed;
    top: 0;
    right: -100%;
    width: 100%;
    max-width: 500px;
    height: 100%;
    background: linear-gradient(135deg, rgba(26, 26, 46, 0.98) 0%, rgba(45, 45, 68, 0.98) 100%);
    box-shadow: -5px 0 30px rgba(0, 0, 0, 0.5);
    transition: right 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    z-index: 10000;
    overflow-y: auto;
}

.popup-menu-panel.active {
    right: 0;
}

/* 背景スライドショー（オプション） */
.menu-background-slideshow {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 1;
}

.menu-background-slide {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-size: cover;
    background-position: center;
    opacity: 0;
    transition: opacity 1.5s ease-in-out;
}

/* オーバーレイ（背景画像の上） */
.menu-panel-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, rgba(26, 26, 46, 0.95) 0%, rgba(45, 45, 68, 0.90) 100%);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    z-index: 2;
}

/* メニューコンテンツ */
.menu-panel-content {
    position: relative;
    z-index: 3;
    padding: 80px 40px 40px;
    height: 100%;
    display: flex;
    flex-direction: column;
}

/* 閉じるボタン */
.close-menu-icon {
    position: absolute;
    top: 20px;
    right: 20px;
    font-size: 40px;
    color: rgba(255, 255, 255, 0.8);
    background: none;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    width: 50px;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
}

.close-menu-icon:hover {
    color: var(--color-primary, #ff6b9d);
    background: rgba(255, 255, 255, 0.1);
    transform: rotate(90deg);
}

/* ナビゲーション */
.popup-main-nav {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 5px;
    overflow-y: auto;
    padding-bottom: 20px;
}

/* メニュー項目 */
.popup-nav-item {
    display: flex;
    flex-direction: column;
    gap: 5px;
    padding: 18px 25px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    text-decoration: none;
    transition: all 0.3s ease;
    cursor: pointer;
}

.popup-nav-item:hover {
    background: rgba(255, 107, 157, 0.15);
    border-color: var(--color-primary, #ff6b9d);
    transform: translateX(10px);
    box-shadow: 0 8px 20px rgba(255, 107, 157, 0.2);
}

/* メニューコード */
.nav-item-code {
    font-family: 'Roboto', 'Arial', sans-serif;
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    color: var(--color-primary, #ff6b9d);
    opacity: 0.9;
}

/* メニューラベル */
.nav-item-label {
    font-size: 1.1rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.95);
    line-height: 1.4;
}

/* フッターリンク */
.popup-footer-link {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    padding: 25px 20px;
    margin-top: 20px;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 15px;
    text-decoration: none;
    transition: all 0.3s ease;
}

.popup-footer-link:hover {
    background: rgba(255, 255, 255, 0.08);
    border-color: var(--color-primary, #ff6b9d);
}

.popup-footer-logo {
    height: 50px;
    width: auto;
    object-fit: contain;
}

.popup-footer-text-official {
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 2px;
    color: var(--color-primary, #ff6b9d);
    text-transform: uppercase;
}

.popup-footer-text-sitename {
    font-size: 0.85rem;
    font-weight: 500;
    color: rgba(255, 255, 255, 0.7);
}

/* レスポンシブ対応 */
@media (max-width: 768px) {
    .popup-menu-panel {
        max-width: 100%;
    }
    
    .menu-panel-content {
        padding: 70px 30px 30px;
    }
    
    .popup-nav-item {
        padding: 15px 20px;
    }
    
    .nav-item-label {
        font-size: 1rem;
    }
}

@media (max-width: 480px) {
    .menu-panel-content {
        padding: 60px 20px 20px;
    }
    
    .close-menu-icon {
        top: 15px;
        right: 15px;
        font-size: 35px;
    }
}
