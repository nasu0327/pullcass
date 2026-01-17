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

            const checkScrollable = () => {
                const isScrollable = container.scrollHeight > container.clientHeight;
                wrapper.classList.toggle('has-scroll', isScrollable);
                // スクロール可能な場合は白い影を表示
                if (isScrollable) {
                    wrapper.classList.add('show-gradient');
                } else {
                    // スクロール不要な場合は白い影を非表示
                    wrapper.classList.remove('show-gradient');
                }
            };
            
            container.addEventListener('scroll', function() {
                const isAtEnd = container.scrollTop + container.clientHeight >= container.scrollHeight;
                // 最後まで達したら白い影を消す、そうでなければ表示
                wrapper.classList.toggle('show-gradient', !isAtEnd);
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
