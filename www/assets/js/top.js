// 横スクロール用のグラデーション制御（参考サイトと完全に同じ実装）
const HorizontalScroll = {
    init: function() {
        const scrollContainers = document.querySelectorAll('.scroll-container-x');
        
        scrollContainers.forEach(container => {
            const scrollWrapper = container.closest('.scroll-wrapper');
            if (!scrollWrapper) return;
            
            const gradient = scrollWrapper.querySelector('.scroll-gradient-right');
            if (!gradient) return;
            
            gradient.style.opacity = '0';
            gradient.style.transition = 'opacity 0.3s ease';
            
            const checkScrollable = () => {
                const isScrollable = container.scrollWidth > container.clientWidth;
                gradient.style.opacity = isScrollable ? '1' : '0';
            };
            
            container.addEventListener('scroll', function() {
                const isScrollable = container.scrollWidth > container.clientWidth;
                if (!isScrollable) return; // スクロール不要な場合は何もしない
                
                const isAtEnd = container.scrollLeft + container.clientWidth >= container.scrollWidth - 1; // -1は誤差吸収
                gradient.style.opacity = isAtEnd ? '0' : '1';
            });
            
            checkScrollable();
            window.addEventListener('resize', checkScrollable);
        });
    }
};

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

// ニュースティッカー（縦フェード切り替え）
const NewsTicker = {
    init: function() {
        const wrapper = document.querySelector('.news-ticker-wrapper');
        if (!wrapper) return;

        const items = wrapper.querySelectorAll('.news-item');
        if (items.length === 0) return;

        let currentIndex = 0;

        const showNextItem = () => {
            items[currentIndex].classList.remove('active');
            currentIndex = (currentIndex + 1) % items.length;
            items[currentIndex].classList.add('active');
        };

        // 最初のアイテムを表示
        items[0].classList.add('active');

        // 4秒ごとに次のアイテムを表示
        if (items.length > 1) {
            setInterval(showNextItem, 4000);
        }
    }
};

document.addEventListener('DOMContentLoaded', function() {
    HorizontalScroll.init();
    VerticalScroll.init();
    NewsTicker.init();
});
