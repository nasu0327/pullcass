// 縦スクロール用のグラデーション制御（参考サイトと完全に同じ実装）
const VerticalScroll = {
    init: function() {
        // 縦スクロールのコンテナのみを対象にする
        const verticalScrollContainers = document.querySelectorAll('.shamenikki-content, .review-content, .history-content');
        
        verticalScrollContainers.forEach(container => {
            const wrapper = container.closest('.shamenikki-wrapper, .review-wrapper, .history-wrapper');
            if (!wrapper) return;

            const checkScrollable = () => {
                const isScrollable = container.scrollHeight > container.clientHeight;
                wrapper.classList.toggle('has-scroll', isScrollable);
                
                // スクロール可能な場合のみshow-gradientを追加、不可能な場合は削除
                if (isScrollable) {
                    wrapper.classList.add('show-gradient');
                } else {
                    wrapper.classList.remove('show-gradient');
                }
            };
            
            container.addEventListener('scroll', function() {
                const isScrollable = container.scrollHeight > container.clientHeight;
                if (!isScrollable) return; // スクロール不要な場合は何もしない
                
                const isAtEnd = container.scrollTop + container.clientHeight >= container.scrollHeight - 1; // -1は誤差吸収
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
