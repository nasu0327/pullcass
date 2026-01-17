/**
 * pullcass - トップページ用JavaScript
 * 参考: reference/public_html/assets/js/top.js
 */

// 縦スクロール用のグラデーション制御
const VerticalScroll = {
    init: function() {
        const verticalScrollContainers = document.querySelectorAll('.shamenikki-content, .review-content, .history-content');
        
        verticalScrollContainers.forEach(container => {
            const wrapper = container.closest('.shamenikki-wrapper, .review-wrapper, .history-wrapper');
            if (!wrapper) return;

            // グラデーション要素を取得
            let gradient = wrapper.querySelector('.shamenikki-gradient, .review-gradient, .history-gradient');
            if (!gradient) return;

            const checkScrollable = () => {
                const isScrollable = container.scrollHeight > container.clientHeight;
                
                if (!isScrollable) {
                    // スクロール不要な場合は白い影を非表示
                    gradient.style.opacity = '0';
                } else {
                    // スクロール可能な場合は白い影を表示
                    gradient.style.opacity = '1';
                }
            };
            
            container.addEventListener('scroll', function() {
                const isScrollable = container.scrollHeight > container.clientHeight;
                if (!isScrollable) return;
                
                const isAtEnd = container.scrollTop + container.clientHeight >= container.scrollHeight - 1;
                // 最後まで達したら白い影を消す、そうでなければ表示
                gradient.style.opacity = isAtEnd ? '0' : '1';
            });
            
            checkScrollable();
            window.addEventListener('resize', checkScrollable);
        });
    }
};

// ページ読み込み時に初期化
document.addEventListener('DOMContentLoaded', function() {
    VerticalScroll.init();
});
