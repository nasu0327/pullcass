// diary-cards.js - トップページ右カラム「写メ日記」セクション用（全キャストの最新一覧）
(function() {
    'use strict';

    function processPath(path) {
        if (!path) return '';
        path = String(path).replace(/^@/, '');
        if (path.indexOf('http') === 0 || path.indexOf('//') === 0) return path;
        return path;
    }

    function loadDiaryCards() {
        var container = document.getElementById('diary-cards-container');
        var wrapper = document.getElementById('view-all-diary-wrapper');
        if (!container) return;

        var tenant = typeof window.PULLCASS_TENANT_CODE !== 'undefined' ? window.PULLCASS_TENANT_CODE : '';
        var apiUrl = (window.location.origin || '') + '/get_latest_diary_cards.php' + (tenant ? '?tenant=' + encodeURIComponent(tenant) : '');
        fetch(apiUrl)
            .then(function(res) {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                var ct = (res.headers.get('Content-Type') || '').toLowerCase();
                if (ct.indexOf('application/json') === -1) throw new Error('Invalid response type');
                return res.json();
            })
            .then(function(data) {
                if (!data || !data.success) throw new Error(data && data.error ? data.error : 'Invalid data');
                var posts = (data.posts || []);

                var loading = document.getElementById('diary-loading');
                if (loading) loading.remove();

                if (posts.length === 0) {
                    container.innerHTML = '<div style="text-align: center; padding: 40px; color: var(--color-text); grid-column: 1 / -1;">まだ日記が投稿されていません</div>';
                    if (wrapper) {
                        wrapper.innerHTML = '<a href="/diary" class="view-all-diary-button" style="display:inline-flex; align-items:center; background:linear-gradient(135deg, var(--color-primary), var(--color-primary-light)); color:var(--color-btn-text); font-size:12px; font-weight:bold; padding:8px 16px; border-radius:20px; text-decoration:none;"><span>全ての投稿を見る</span></a>';
                    }
                    return;
                }

                var cardsHtml = '';
                posts.forEach(function(post) {
                    var isVideo = post.has_video;
                    var displayImg = '';
                    if (isVideo && post.poster_url) displayImg = processPath(post.poster_url);
                    else if (post.thumb_url) displayImg = processPath(post.thumb_url);
                    else if (post.html_body) {
                        var m = post.html_body.match(/<img[^>]+src=["']([^"']+)["']/i);
                        if (m) displayImg = processPath(m[1]);
                    }
                    var title = post.title ? (post.title.substring(0, 30) + (post.title.length > 30 ? '…' : '')) : '(無題)';
                    var writer = post.cast_name || '不明';
                    var postedAt = post.posted_at ? new Date(post.posted_at).toLocaleDateString('ja-JP', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }) : '-';
                    var displayVideo = isVideo && post.video_url ? processPath(post.video_url) : '';
                    var displayPoster = isVideo && post.poster_url ? processPath(post.poster_url) : '';

                    cardsHtml += '<div class="diary-card-mini" data-pd="' + post.pd_id + '" style="cursor:pointer; border-radius:6px; box-shadow:0 1px 4px rgba(0,0,0,0.1); overflow:hidden; background:rgba(255,255,255,0.8); transition:all 0.3s ease;">' +
                        '<div style="position:relative; aspect-ratio:3/2; background:#f0f0f0; display:flex; align-items:center; justify-content:center;">' +
                        (displayVideo ? '<video src="' + displayVideo + '" ' + (displayPoster ? 'poster="' + displayPoster + '"' : '') + ' style="width:100%; height:100%; object-fit:cover;" autoplay muted loop playsinline></video><div style="position:absolute; right:4px; bottom:4px; background:rgba(0,0,0,0.8); color:#fff; padding:1px 4px; font-size:9px; border-radius:2px;">動画</div>' :
                            displayImg ? '<img src="' + displayImg + '" alt="" style="width:100%; height:100%; object-fit:cover;">' + (isVideo ? '<div style="position:absolute; right:4px; bottom:4px; background:rgba(0,0,0,0.7); color:#fff; padding:1px 4px; font-size:9px; border-radius:2px;">動画あり</div>' : '') :
                            '<div style="color:var(--color-text); text-align:center; font-size:11px;">' + (isVideo ? '動画' : '画像') + '</div>') +
                        '</div><div style="padding:6px 8px;">' +
                        '<div style="font-weight:600; font-size:11px; margin-bottom:1px;">' + title + '</div>' +
                        '<div style="font-size:9px; color:var(--color-text);">' + writer + '</div>' +
                        '<div style="font-size:9px; color:var(--color-text);">' + postedAt + '</div></div></div>';
                });

                container.insertAdjacentHTML('beforeend', cardsHtml);
                if (wrapper) {
                    wrapper.innerHTML = '<a href="/diary" class="view-all-diary-button" style="display:inline-flex; align-items:center; background:linear-gradient(135deg, var(--color-primary), var(--color-primary-light)); color:var(--color-btn-text); font-size:12px; font-weight:bold; padding:8px 16px; border-radius:20px; text-decoration:none;"><span>全ての投稿を見る</span></a>';
                }

                container.querySelectorAll('.diary-card-mini').forEach(function(card) {
                    card.addEventListener('click', function() {
                        var pd = this.getAttribute('data-pd');
                        if (pd && typeof loadDiaryPost === 'function') loadDiaryPost(pd);
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
            })
            .catch(function(err) {
                if (container) {
                    var loading = document.getElementById('diary-loading');
                    if (loading) loading.remove();
                    container.innerHTML = '<div style="text-align: center; padding: 40px; color: #e74c3c; grid-column: 1 / -1;">日記の読み込みに失敗しました</div>';
                }
                if (wrapper) wrapper.innerHTML = '<a href="/diary" style="color:var(--color-primary);">全ての投稿を見る</a>';
            });
    }

    var DiaryModal = {
        init: function() {
            var modal = document.getElementById('diary-modal');
            var closeBtn = document.getElementById('dm-close');
            if (!modal || !closeBtn) return;
            closeBtn.addEventListener('click', function() { DiaryModal.close(); });
            modal.addEventListener('click', function(e) { if (e.target === modal) DiaryModal.close(); });
            document.addEventListener('keydown', function(e) { if (e.key === 'Escape') DiaryModal.close(); });
        },
        open: function() {
            var modal = document.getElementById('diary-modal');
            if (!modal) return;
            modal.style.display = 'block';
            modal.style.visibility = 'visible';
            modal.style.opacity = '1';
            document.body.style.overflow = 'hidden';
        },
        close: function() {
            var modal = document.getElementById('diary-modal');
            if (!modal) return;
            modal.style.opacity = '0';
            modal.style.visibility = 'hidden';
            setTimeout(function() {
                modal.style.display = 'none';
                document.body.style.overflow = '';
                var t = document.getElementById('dm-title');
                var m = document.getElementById('dm-meta');
                var b = document.getElementById('dm-body');
                if (t) t.textContent = '';
                if (m) m.textContent = '';
                if (b) b.innerHTML = '';
            }, 500);
        }
    };

    window.loadDiaryPost = function(pd) {
        var dmTitle = document.getElementById('dm-title');
        var dmMeta = document.getElementById('dm-meta');
        var dmBody = document.getElementById('dm-body');
        if (!dmTitle || !dmMeta || !dmBody) return;

        dmTitle.textContent = '投稿を読み込み中...';
        dmMeta.textContent = '';
        dmBody.innerHTML = '';
        DiaryModal.open();

        fetch('/diary?ajax=post&pd=' + encodeURIComponent(pd))
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (!data.success || !data.post) throw new Error('データなし');
                var p = data.post;
                dmTitle.textContent = (p.title && p.title.length) ? p.title : '(無題)';
                var writerHtml = p.cast_id
                    ? '<a href="/cast/?id=' + p.cast_id + '" style="color:var(--color-primary); font-weight:bold; text-decoration:none;">' + (p.cast_name || '不明') + '</a>'
                    : '<span style="color:var(--color-primary); font-weight:bold;">' + (p.cast_name || '不明') + '</span>';
                dmMeta.innerHTML = '投稿者：' + writerHtml + '　投稿日時：' + (p.posted_at_formatted || '-');

                var mediaHtml = '';
                if ((p.has_video === 1 || p.has_video === true) && p.video_url) {
                    mediaHtml = '<div style="text-align:center; margin:20px 0;"><video src="' + p.video_url + '" ' + (p.poster_url ? 'poster="' + p.poster_url + '"' : '') + ' controls style="max-width:100%; height:auto; border-radius:8px;"></video></div>';
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
                    bodyContent = bodyContent.trim();
                }
                dmBody.innerHTML = mediaHtml + (bodyContent || '');
            })
            .catch(function() {
                dmTitle.textContent = '読み込みエラー';
                dmBody.innerHTML = '<div style="color:#e74c3c;">投稿の読み込みに失敗しました。</div>';
            });
    };

    document.addEventListener('DOMContentLoaded', function() {
        DiaryModal.init();
        loadDiaryCards();
    });
})();
