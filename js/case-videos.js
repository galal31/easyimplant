document.addEventListener('click', function (event) {
    const button = event.target.closest('[data-video-src]');
    if (!button) return;
    const player = button.closest('[data-case-video]');
    if (!player) return;
    const iframe = document.createElement('iframe');
    iframe.src = button.dataset.videoSrc + '?autoplay=true';
    iframe.title = button.getAttribute('aria-label') || 'Case video';
    iframe.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture';
    iframe.allowFullscreen = true;
    iframe.loading = 'lazy';
    player.replaceChildren(iframe);
});
