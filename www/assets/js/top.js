// 縦スクロール用のグラデーション制御（参考サイトと完全に同じ実装）
const VerticalScroll = {
    init: function() {
        const verticalScrollContainers = document.querySelectorAll('.shamenikki-content, .review-content, .history-content');
        
        verticalScrollContainers.forEach(container => {
            const wrapper = container.closest('.shamenikki-wrapper, .review-wrapper, .history-wrapper');
            if (!wrapper) return;

            const checkScrollable = () => {
                const isScrollable = container.scrollHeight > container.clientHeight;
                wrapper.classList.toggle('has-scroll', isScrollable);
                if (isScrollable) {
                    wrapper.classList.add('show-gradient');
                }
            };
            
            container.addEventListener('scroll', function() {
                const isAtEnd = container.scrollTop + container.clientHeight >= container.scrollHeight;
                wrapper.classList.toggle('show-gradient', !isAtEnd);
            });
            
            checkScrollable();
            window.addEventListener('resize', checkScrollable);
        });
    }
};

document.addEventListener('DOMContentLoaded', function() {
    VerticalScroll.init();
});
