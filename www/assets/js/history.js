/**
 * 閲覧履歴を管理するクラス
 * Cookieに最大10件の閲覧履歴を保存
 */
class CastHistory {
    constructor() {
        this.cookieName = 'cast_history';
        this.maxHistory = 10;
        // DOMContentLoadedの後に初期化（トップページ用）
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => {
                this.initHistoryDisplay();
            });
        } else {
            // DOMContentLoadedが既に発火している場合
            this.initHistoryDisplay();
        }
    }

    // クッキーの取得
    getCookie(name) {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) {
            try {
                return JSON.parse(decodeURIComponent(parts.pop().split(';').shift()));
            } catch (e) {
                console.error('クッキーの解析に失敗しました:', e);
                return [];
            }
        }
        return [];
    }

    // クッキーの設定
    setCookie(name, value, days) {
        try {
            const expires = new Date();
            expires.setTime(expires.getTime() + (days * 24 * 60 * 60 * 1000));
            const cookieValue = encodeURIComponent(JSON.stringify(value));
            document.cookie = `${name}=${cookieValue}; expires=${expires.toUTCString()}; path=/; SameSite=Lax`;
            return true;
        } catch (e) {
            console.error('クッキーの設定に失敗しました:', e);
            return false;
        }
    }

    // 履歴の保存
    saveHistory(castId) {
        try {
            if (!castId) {
                console.error('無効なキャストIDです');
                return;
            }

            // 既存の履歴を取得
            let history = this.getCookie(this.cookieName);
            
            // 同じIDのものを削除（重複を防ぐ）
            history = history.filter(id => id !== castId);
            
            // 新しい履歴を先頭に追加
            history.unshift(castId);
            
            // 最大履歴数を超えた場合、古いものから削除
            if (history.length > this.maxHistory) {
                history = history.slice(0, this.maxHistory);
            }
            
            // クッキーに保存（30日間有効）
            this.setCookie(this.cookieName, history, 30);
            
            // 表示を更新
            this.updateHistoryDisplay();
        } catch (e) {
            console.error('履歴の保存に失敗しました:', e);
        }
    }

    // キャスト情報の取得
    async fetchCastInfo(castId) {
        try {
            const response = await fetch(`/app/front/cast/get_cast_info.php?id=${castId}`);
            if (!response.ok) {
                throw new Error('キャスト情報の取得に失敗しました');
            }
            return await response.json();
        } catch (e) {
            console.error('キャスト情報の取得に失敗しました:', e);
            return null;
        }
    }

    // 履歴の表示を初期化
    async initHistoryDisplay() {
        const historyContainer = document.querySelector('.history-cards');
        if (!historyContainer) {
            console.log('閲覧履歴: .history-cards要素が見つかりません');
            return Promise.resolve();
        }

        const history = this.getCookie(this.cookieName);
        console.log('閲覧履歴: Cookieから取得した履歴:', history);
        
        if (history.length === 0) {
            historyContainer.innerHTML = '<div class="history-empty">閲覧履歴はありません</div>';
            return Promise.resolve();
        }

        // 履歴カードの生成
        const cardPromises = history.map(async (castId) => {
            try {
                const cast = await this.fetchCastInfo(castId);
                if (!cast || cast.error) {
                    console.warn('閲覧履歴: キャスト情報の取得に失敗:', castId, cast?.error || 'Unknown error');
                    return null;
                }
                return cast;
            } catch (e) {
                console.error('閲覧履歴: キャスト情報の取得中にエラー:', castId, e);
                return null;
            }
        });
        
        // カード生成の結果を待つ
        const castData = await Promise.all(cardPromises);
        
        // カード要素を生成
        const cards = castData.map((cast) => {
            if (!cast) return null;

            const card = document.createElement('a');
            card.href = `/app/front/cast/detail.php?id=${cast.id}`;
            card.className = 'history-card';
            
            card.innerHTML = `
                <div class="card-image">
                    <img src="${cast.image || ''}" alt="${cast.name || ''}" loading="lazy">
                </div>
                <div class="card-info">
                    <div class="card-name">
                        <span>${cast.name || ''}</span>
                        <span>${cast.age ? cast.age + '歳' : ''}</span>
                        <span>${cast.cup ? cast.cup + 'カップ' : ''}</span>
                    </div>
                    <div class="card-pr">${cast.pr_title || ''}</div>
                </div>
            `;

            return card;
        });

        historyContainer.innerHTML = '';
        cards.filter(card => card).forEach(card => historyContainer.appendChild(card));
        
        console.log('閲覧履歴: カードを生成しました:', cards.filter(card => card).length, '件');

        // 画像の読み込みを待つ
        const images = historyContainer.querySelectorAll('img');
        if (images.length > 0) {
            await Promise.all(Array.from(images).map(img => {
                if (img.complete) return Promise.resolve();
                return new Promise(resolve => {
                    img.onload = resolve;
                    img.onerror = resolve;
                });
            }));
        }

        // スクロール可能かチェックして白い影を表示（参考サイトと完全に同じ実装）
        const historyWrapper = document.querySelector('.history-wrapper');
        const historyContent = document.querySelector('.history-content');
        
        if (historyWrapper && historyContent) {
            const checkScrollable = function() {
                const isScrollable = historyContent.scrollHeight > historyContent.clientHeight;
                historyWrapper.classList.toggle('has-scroll', isScrollable);
                
                // スクロール可能な場合のみshow-gradientを追加、不可能な場合は削除
                if (isScrollable) {
                    historyWrapper.classList.add('show-gradient');
                } else {
                    historyWrapper.classList.remove('show-gradient');
                }
            };

            historyContent.addEventListener('scroll', function() {
                const isScrollable = historyContent.scrollHeight > historyContent.clientHeight;
                if (!isScrollable) return; // スクロール不要な場合は何もしない
                
                const isAtEnd = historyContent.scrollTop + historyContent.clientHeight >= historyContent.scrollHeight - 1; // -1は誤差吸収
                historyWrapper.classList.toggle('show-gradient', !isAtEnd);
            });

            checkScrollable();
            window.addEventListener('resize', checkScrollable);
        }

        return Promise.resolve();
    }

    // 履歴の表示を更新
    updateHistoryDisplay() {
        const historyContainer = document.querySelector('.history-cards');
        if (historyContainer) {
            this.initHistoryDisplay();
        }
    }
}

// グローバルで1回だけ初期化
if (!window.historyInstance) {
    window.historyInstance = new CastHistory();
}

// 履歴に追加（キャスト詳細ページ用）
function addToHistory() {
    try {
        const castId = document.querySelector('meta[name="cast-id"]')?.content;
        if (castId && window.historyInstance) {
            window.historyInstance.saveHistory(castId);
        }
    } catch (e) {
        console.error('履歴の追加に失敗しました:', e);
    }
}

// ページ読み込み時に履歴を表示・追加（キャスト詳細ページ用）
document.addEventListener('DOMContentLoaded', () => {
    // キャスト詳細ページの場合のみ履歴に追加
    if (document.querySelector('meta[name="cast-id"]')) {
        addToHistory();
    }
    
    // トップページの場合、履歴表示を再初期化（念のため）
    if (document.querySelector('.history-cards') && window.historyInstance) {
        window.historyInstance.initHistoryDisplay();
    }
});
