// cast-review-cards.js - キャスト詳細ページ用口コミカード（review_scrape ON時）
(function() {
    'use strict';

    function escapeHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    function formatDate(dateStr) {
        if (!dateStr) return '日付不明';
        try {
            var d = new Date(dateStr);
            return d.getFullYear() + '年' + (d.getMonth() + 1) + '月' + d.getDate() + '日';
        } catch (e) {
            return dateStr;
        }
    }

    function renderStars(rating, size) {
        var r = parseFloat(rating) || 0;
        var fullStars = Math.floor(r);
        var partial = r - fullStars;
        var html = '';
        var sz = size || '14px';

        for (var i = 0; i < fullStars; i++) {
            html += '<span style="color: var(--color-primary); font-size: ' + sz + ';">★</span>';
        }
        if (partial > 0) {
            var pct = Math.round(partial * 100);
            html += '<span style="position: relative; display: inline-block; font-size: ' + sz + ';">' +
                '<span style="color: #ccc;">☆</span>' +
                '<span style="position: absolute; left: 0; top: 0; width: ' + pct + '%; overflow: hidden; color: var(--color-primary);">★</span>' +
                '</span>';
        }
        var empty = 5 - fullStars - (partial > 0 ? 1 : 0);
        for (var j = 0; j < empty; j++) {
            html += '<span style="color: #ccc; font-size: ' + sz + ';">☆</span>';
        }
        html += ' <span style="font-size: ' + sz + '; font-weight: bold; color: var(--color-primary);">' + (r > 0 ? r.toFixed(1) : '') + '</span>';
        return html;
    }

    window.loadCastReviewCards = async function(castId, castName) {
        var container = document.getElementById('cast-review-cards-container');
        if (!container) return;

        try {
            var tenant = typeof window.PULLCASS_TENANT_CODE !== 'undefined' ? window.PULLCASS_TENANT_CODE : '';
            var params = new URLSearchParams({ cast_id: String(castId) });
            if (tenant) params.set('tenant', tenant);
            var response = await fetch('/cast/get_cast_review_cards.php?' + params.toString());
            if (!response.ok) throw new Error('API request failed');
            var data = await response.json();
            if (!data.success) throw new Error(data.error || 'Invalid data format');
            var reviews = data.reviews || [];

            if (reviews.length === 0) {
                container.innerHTML = '<div style="text-align: center; padding: 50px 20px; color: var(--color-text); font-size: 14px;">現在口コミはありません。</div>';
                return;
            }

            var cardsHtml = '';
            reviews.forEach(function(review, index) {
                var title = review.title ? escapeHtml(review.title) : 'タイトルなし';
                var userName = escapeHtml(review.user_name || '匿名');
                var dateStr = formatDate(review.review_date);
                var stars = renderStars(review.rating, '12px');
                var content = review.content || '';
                var contentPreview = content.length > 80
                    ? escapeHtml(content.substring(0, 80)) + '…'
                    : escapeHtml(content);
                var isPickup = (index === 0);

                cardsHtml += '<div class="cast-review-card-mini" data-review-id="' + review.id + '" style="' +
                    'cursor: pointer; border-radius: 10px; overflow: hidden; ' +
                    'background: ' + (isPickup ? '#FFF8DC' : 'white') + '; ' +
                    'box-shadow: 0 2px 10px rgba(0,0,0,0.1); ' +
                    (isPickup ? 'border: 2px solid #FFD700; ' : '') +
                    'transition: all 0.3s ease; padding: 12px 14px;">' +

                    (isPickup
                        ? '<div style="color: var(--color-primary); font-size: 12px; font-weight: bold; text-align: left; margin-bottom: 4px; transform: rotate(-3deg); transform-origin: left center;">ピックアップ！</div>'
                        : '') +

                    '<div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 8px; margin-bottom: 6px;">' +
                        '<div style="flex: 1; min-width: 0;">' +
                            '<div style="font-weight: bold; font-size: 13px; color: var(--color-text); line-height: 1.3; margin-bottom: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">' + title + '</div>' +
                            '<div style="font-size: 10px; color: var(--color-text);">投稿者: ' + userName + ' / ' + dateStr + '</div>' +
                        '</div>' +
                        '<div style="flex-shrink: 0; text-align: right;">' +
                            '<div style="white-space: nowrap;">' + stars + '</div>' +
                        '</div>' +
                    '</div>' +

                    '<div style="font-size: 12px; color: var(--color-text); line-height: 1.4; text-align: left; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">' + contentPreview + '</div>' +

                    (review.shop_comment
                        ? '<div style="margin-top: 6px; padding: 6px 8px; background: #f8f9fa; border-radius: 6px; border: 1px solid #ddd;">' +
                            '<div style="font-size: 10px; font-weight: bold; color: var(--color-primary); margin-bottom: 2px;">お店からのコメント</div>' +
                            '<div style="font-size: 10px; color: var(--color-text); line-height: 1.3; display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; overflow: hidden;">' + escapeHtml(review.shop_comment.substring(0, 50)) + (review.shop_comment.length > 50 ? '…' : '') + '</div>' +
                          '</div>'
                        : '') +

                    '</div>';
            });

            cardsHtml += '<div style="text-align: center; margin-top: 10px;">' +
                '<a href="/reviews?cast=' + encodeURIComponent(castName) + '" style="display: inline-flex; align-items: center; background: linear-gradient(135deg, var(--color-primary), var(--color-primary-light)); color: var(--color-btn-text); font-size: 12px; font-weight: bold; padding: 8px 16px; border-radius: 20px; box-shadow: 0 2px 8px rgba(245, 104, 223, 0.3); text-decoration: none;">' +
                '<span>' + escapeHtml(castName) + 'の全ての口コミを見る</span></a></div>';

            container.innerHTML = cardsHtml;

            container.querySelectorAll('.cast-review-card-mini').forEach(function(card) {
                card.addEventListener('click', function() {
                    var reviewId = this.getAttribute('data-review-id');
                    var review = reviews.find(function(r) { return String(r.id) === reviewId; });
                    if (review) CastReviewModal.show(review);
                });
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-2px)';
                    this.style.boxShadow = '0 4px 15px rgba(0,0,0,0.15)';
                });
                card.addEventListener('mouseleave', function() {
                    this.style.transform = '';
                    this.style.boxShadow = '';
                });
            });
        } catch (err) {
            container.innerHTML = '<div style="text-align: center; padding: 40px; color: #e74c3c;">口コミの読み込みに失敗しました</div>';
        }
    };

    var CastReviewModal = {
        init: function() {
            var modal = document.getElementById('cast-review-modal');
            var closeBtn = document.getElementById('crm-close');
            if (!modal || !closeBtn) return;
            closeBtn.addEventListener('click', function() { CastReviewModal.close(); });
            modal.addEventListener('click', function(e) { if (e.target === modal) CastReviewModal.close(); });
            document.addEventListener('keydown', function(e) { if (e.key === 'Escape') CastReviewModal.close(); });
        },
        show: function(review) {
            var modal = document.getElementById('cast-review-modal');
            var title = document.getElementById('crm-title');
            var meta = document.getElementById('crm-meta');
            var body = document.getElementById('crm-body');
            if (!modal || !title || !meta || !body) return;

            title.textContent = review.title || 'タイトルなし';

            var metaHtml = '<div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 8px;">' +
                '<div>' +
                    '<div style="display: flex; gap: 15px; font-size: 14px; color: var(--color-text); flex-wrap: wrap;">' +
                        '<span>投稿者: ' + escapeHtml(review.user_name || '匿名') + '</span>' +
                        '<span>投稿日: ' + formatDate(review.review_date) + '</span>' +
                    '</div>' +
                '</div>' +
                '<div style="text-align: right;">' +
                    '<div style="font-size: 18px; font-weight: bold;">' + renderStars(review.rating, '18px') + '</div>' +
                    (review.cast_name
                        ? '<div style="margin-top: 4px;"><span style="color: var(--color-primary); font-size: 16px; font-weight: bold;">' + escapeHtml(review.cast_name) + '</span></div>'
                        : '') +
                '</div>' +
            '</div>';
            meta.innerHTML = metaHtml;

            var bodyHtml = '<p style="margin: 0; line-height: 1.8; color: var(--color-text); text-align: left; white-space: pre-wrap;">' + escapeHtml(review.content || '') + '</p>';

            if (review.shop_comment) {
                bodyHtml += '<div style="margin-top: 15px; padding: 15px; background: #f8f9fa; border-radius: 8px; border: 1px solid #ddd;">' +
                    '<h5 style="margin: 0 0 10px 0; color: var(--color-primary); font-size: 14px;">お店からのコメント</h5>' +
                    '<p style="margin: 0; line-height: 1.6; color: var(--color-text); font-size: 14px; text-align: left; white-space: pre-wrap;">' + escapeHtml(review.shop_comment) + '</p>' +
                    '</div>';
            }
            body.innerHTML = bodyHtml;

            modal.style.display = 'block';
            modal.style.visibility = 'visible';
            modal.style.opacity = '1';
            document.body.style.overflow = 'hidden';
        },
        close: function() {
            var modal = document.getElementById('cast-review-modal');
            if (!modal) return;
            modal.style.opacity = '0';
            modal.style.visibility = 'hidden';
            setTimeout(function() {
                modal.style.display = 'none';
                document.body.style.overflow = '';
            }, 500);
        }
    };

    document.addEventListener('DOMContentLoaded', function() {
        CastReviewModal.init();
    });
})();
