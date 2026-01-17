// 縦スクロール用のグラデーション制御（参考サイトと完全に同じ実装）
const VerticalScroll = {
    init: function() {
        const verticalScrollContainers = document.querySelectorAll('.shamenikki-content, .review-content, .history-content');
        
        verticalScrollContainers.forEach(container => {
            const wrapper = container.closest('.shamenikki-wrapper, .review-wrapper, .history-wrapper');
            if (!wrapper) return;

            // グラデーション要素を取得
            const gradient = wrapper.querySelector('.shamenikki-gradient, .review-gradient, .history-gradient');
            if (!gradient) return;

            const checkScrollable = () => {
                const isScrollable = container.scrollHeight > container.clientHeight;
                
                if (!isScrollable) {
                    // スクロール不要な場合は非表示
                    gradient.style.opacity = '0';
                } else {
                    // スクロール可能な場合は表示
                    gradient.style.opacity = '1';
                }
            };
            
            container.addEventListener('scroll', function() {
                const isScrollable = container.scrollHeight > container.clientHeight;
                if (!isScrollable) return;
                
                const isAtEnd = container.scrollTop + container.clientHeight >= container.scrollHeight - 1;
                // 最後まで達したら非表示、そうでなければ表示
                gradient.style.opacity = isAtEnd ? '0' : '1';
            });
            
            checkScrollable();
            window.addEventListener('resize', checkScrollable);
        });
    }
};

document.addEventListener('DOMContentLoaded', function() {
    VerticalScroll.init();
});
