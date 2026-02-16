// cast-diary-cards.js - キャスト詳細ページ用写メ日記カード（diary_scrape ON時）
(function() {
    'use strict';

    function processPath(path) {
        if (!path) return '';
        path = String(path).replace(/^@/, '');
        if (path.indexOf('http') === 0 || path.indexOf('//') === 0) return path;
        if (path.indexOf('admin/diary_scrape/uploads/') === 0) return '/' + path;
        if (path.indexOf('/admin/diary_scrape/uploads/') === 0) return path;
        return path;
    }

    window.loadCastDiaryCards = async function(castId, castName) {
        const container = document.getElementById('cast-diary-cards-container');
        if (!container) return;

        try {
            const response = await fetch('/cast/get_cast_diary_cards.php?cast_id=' + encodeURIComponent(castId));
            if (!response.ok) throw new Error('API request failed');
            const data = await response.json();
            if (!data.success) throw new Error(data.error || 'Invalid data format');
            const posts = data.posts || [];

            if (posts.length === 0) {
                container.innerHTML = '<div style="text-align: center; padding: 40px; color: var(--color-text); grid-column: 1 / -1;">まだ日記が投稿されていません</div>';
                return;
            }

            let cardsHtml = '';
            posts.forEach(function(post) {
                const isVideo = post.has_video;
                let displayImg = '';
                if (isVideo && post.poster_url) {
                    displayImg = processPath(post.poster_url);
                } else if (post.thumb_url) {
                    displayImg = processPath(post.thumb_url);
                } else if (post.html_body) {
                    const imgMatches = post.html_body.match(/<img[^>]+src=["']([^"']+)["']/gi);
                    if (imgMatches) {
                        for (var i = 0; i < imgMatches.length; i++) {
                            var srcMatch = imgMatches[i].match(/src=["']([^"']+)["']/i);
                            if (srcMatch) {
                                displayImg = processPath(srcMatch[1]);
                                break;
                            }
                        }
                    }
                }

                const title = post.title ? (post.title.substring(0, 30) + (post.title.length > 30 ? '…' : '')) : '(無題)';
                const writer = post.cast_name || '不明';
                const postedAt = post.posted_at ? new Date(post.posted_at).toLocaleDateString('ja-JP', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }) : '-';
                const displayVideo = isVideo && post.video_url ? processPath(post.video_url) : '';
                const displayPoster = isVideo && post.poster_url ? processPath(post.poster_url) : '';

                cardsHtml += '<div class="cast-diary-card-mini" data-pd="' + post.pd_id + '" style="cursor: pointer; border-radius: 6px; box-shadow: 0 1px 4px rgba(0,0,0,0.1); overflow: hidden; background: rgba(255,255,255,0.8); transition: all 0.3s ease;">' +
                    '<div style="position: relative; aspect-ratio: 3/2; background: #f0f0f0; display: flex; align-items: center; justify-content: center;">' +
                    (displayVideo
                        ? '<video src="' + displayVideo + '" ' + (displayPoster ? 'poster="' + displayPoster + '"' : '') + ' style="width: 100%; height: 100%; object-fit: cover;" autoplay muted loop playsinline preload="auto"></video><div style="position: absolute; right: 4px; bottom: 4px; background: rgba(0,0,0,0.8); color: #fff; padding: 1px 4px; font-size: 9px; border-radius: 2px;">動画</div>'
                        : displayImg
                            ? '<img src="' + displayImg + '" alt="" style="width: 100%; height: 100%; object-fit: cover;">' + (isVideo ? '<div style="position: absolute; right: 4px; bottom: 4px; background: rgba(0,0,0,0.7); color: #fff; padding: 1px 4px; font-size: 9px; border-radius: 2px;">動画あり</div>' : '')
                            : '<div style="color: var(--color-text); text-align: center; font-size: 11px;">' + (isVideo ? '動画' : '画像') + '</div>') +
                    '</div>' +
                    '<div style="padding: 6px 8px;">' +
                    '<div style="font-weight: 600; font-size: 11px; margin-bottom: 1px;">' + title + '</div>' +
                    '<div style="font-size: 9px; color: var(--color-text);">' + writer + '</div>' +
                    '<div style="font-size: 9px; color: var(--color-text);">' + postedAt + '</div>' +
                    '</div></div>';
            });

            cardsHtml += '<div style="grid-column: 1 / -1; text-align: center; margin-top: 10px;">' +
                '<a href="/diary?writer=' + encodeURIComponent(castName) + '" style="display: inline-flex; align-items: center; background: linear-gradient(135deg, var(--color-primary), var(--color-primary-light)); color: var(--color-btn-text); font-size: 12px; font-weight: bold; padding: 8px 16px; border-radius: 20px; box-shadow: 0 2px 8px rgba(245, 104, 223, 0.3); text-decoration: none;">' +
                '<span>' + castName + 'の全ての投稿を見る</span></a></div>';

            container.innerHTML = cardsHtml;

            container.querySelectorAll('.cast-diary-card-mini').forEach(function(card) {
                card.addEventListener('click', function() {
                    var pd = this.getAttribute('data-pd');
                    if (pd && typeof loadCastDiaryPost === 'function') loadCastDiaryPost(pd);
                });
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-1px)';
                    this.style.boxShadow = '0 2px 8px rgba(0,0,0,0.15)';
                });
                card.addEventListener('mouseleave', function() {
                    this.style.transform = '';
                    this.style.boxShadow = '';
                });
            });
        } catch (err) {
            container.innerHTML = '<div style="text-align: center; padding: 40px; color: #e74c3c; grid-column: 1 / -1;">日記の読み込みに失敗しました</div>';
        }
    };

    var CastDiaryModal = {
        init: function() {
            var modal = document.getElementById('cast-diary-modal');
            var cdmClose = document.getElementById('cdm-close');
            if (!modal || !cdmClose) return;
            cdmClose.addEventListener('click', function() { CastDiaryModal.close(); });
            modal.addEventListener('click', function(e) { if (e.target === modal) CastDiaryModal.close(); });
            document.addEventListener('keydown', function(e) { if (e.key === 'Escape') CastDiaryModal.close(); });
        },
        open: function() {
            var modal = document.getElementById('cast-diary-modal');
            if (!modal) return;
            modal.style.display = 'block';
            modal.style.visibility = 'visible';
            modal.style.opacity = '1';
            document.body.style.overflow = 'hidden';
        },
        close: function() {
            var modal = document.getElementById('cast-diary-modal');
            if (!modal) return;
            modal.style.opacity = '0';
            modal.style.visibility = 'hidden';
            setTimeout(function() {
                modal.style.display = 'none';
                document.body.style.overflow = '';
                var t = document.getElementById('cdm-title');
                var m = document.getElementById('cdm-meta');
                var b = document.getElementById('cdm-body');
                if (t) t.textContent = '';
                if (m) m.textContent = '';
                if (b) b.innerHTML = '';
            }, 500);
        }
    };

    window.loadCastDiaryPost = async function(pd) {
        var cdmTitle = document.getElementById('cdm-title');
        var cdmMeta = document.getElementById('cdm-meta');
        var cdmBody = document.getElementById('cdm-body');
        if (!cdmTitle || !cdmMeta || !cdmBody) return;

        cdmTitle.textContent = '投稿を読み込み中...';
        cdmMeta.textContent = '';
        cdmBody.innerHTML = '';
        CastDiaryModal.open();

        try {
            var res = await fetch('/diary?ajax=post&pd=' + encodeURIComponent(pd));
            if (!res.ok) throw new Error('HTTP ' + res.status);
            var data = await res.json();
            if (!data.success || !data.post) throw new Error('データなし');
            var p = data.post;

            cdmTitle.textContent = (p.title && p.title.length) ? p.title : '(無題)';
            var writerHtml = p.cast_id
                ? '<a href="/cast/?id=' + p.cast_id + '" style="color:var(--color-primary); font-weight:bold; text-decoration:none;">' + (p.cast_name || '不明') + '</a>'
                : '<span style="color:var(--color-primary); font-weight:bold;">' + (p.cast_name || '不明') + '</span>';
            cdmMeta.innerHTML = '投稿者：' + writerHtml + '　投稿日時：' + (p.posted_at_formatted || '-');

            var mediaHtml = '';
            var hasVideo = (p.has_video === 1 || p.has_video === true);
            if (hasVideo && p.video_url) {
                var posterAttr = p.poster_url ? ' poster="' + p.poster_url + '"' : '';
                mediaHtml = '<div style="text-align:center; margin:20px 0;"><video src="' + p.video_url + '"' + posterAttr + ' controls autoplay muted loop playsinline style="max-width:100%; height:auto; border-radius:8px;"></video></div>';
            } else if (p.thumb_url) {
                mediaHtml = '<div style="text-align:center; margin:20px 0;"><img src="' + p.thumb_url + '" alt="" style="max-width:100%; height:auto; border-radius:8px;"></div>';
            }

            var bodyContent = '';
            if (p.html_body && p.html_body.length) {
                bodyContent = p.html_body;
                if (mediaHtml) bodyContent = bodyContent.replace(/<video[^>]*>[\s\S]*?<\/video>/gi, '');
                bodyContent = bodyContent.replace(/<img([^>]*)src=["']([^"']+)["']/gi, function(_, before, src) {
                    return '<img' + before + 'src="' + (src.indexOf('//') === 0 ? 'https:' + src : src) + '"';
                });
                bodyContent = bodyContent.replace(/<div[^>]*class=["\'][^"\']*diary_title[^"\']*["\'][^>]*>[\s\S]*?<\/div>/gi, '');
                bodyContent = bodyContent.replace(/<h3[^>]*class=["\'][^"\']*diary_title[^"\']*["\'][^>]*>[\s\S]*?<\/h3>/gi, '');
                if (p.title && p.title.length) {
                    bodyContent = bodyContent.replace(new RegExp(p.title.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'gi'), '');
                }
                bodyContent = bodyContent.trim();
            }
            if (!bodyContent && !mediaHtml) bodyContent = '<div style="text-align:center; padding:40px; color:#999;">表示できる内容がありません</div>';
            cdmBody.innerHTML = mediaHtml + (bodyContent || '');
        } catch (err) {
            cdmTitle.textContent = '読み込みエラー';
            cdmBody.innerHTML = '<div style="color:#e74c3c;">投稿の読み込みに失敗しました。</div>';
        }
    };

    document.addEventListener('DOMContentLoaded', function() {
        CastDiaryModal.init();
    });
})();
