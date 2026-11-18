/* Pictures on notices: tap one to see it large, with arrows (or the keyboard, or a swipe) to move
   through the rest of that notice's pictures. Without JavaScript each picture is a plain link to the big version. */

(function () {
    let box;
    let items = [];
    let index = 0;
    let startX = null;

    function build() {
        box = document.createElement('dialog');
        box.className = 'lightbox';
        box.setAttribute('aria-label', 'Picture');
        box.innerHTML =
            '<button type="button" class="lb-close" aria-label="Close">×</button>' +
            '<button type="button" class="lb-nav lb-prev" aria-label="Previous picture">‹</button>' +
            '<figure><img alt=""><figcaption></figcaption></figure>' +
            '<button type="button" class="lb-nav lb-next" aria-label="Next picture">›</button>';
        document.body.appendChild(box);

        box.querySelector('.lb-close').addEventListener('click', () => box.close());
        box.querySelector('.lb-prev').addEventListener('click', () => show(index - 1));
        box.querySelector('.lb-next').addEventListener('click', () => show(index + 1));
        // A click on the dark area around the picture closes it
        box.addEventListener('click', (e) => { if (e.target === box) box.close(); });
        box.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowLeft') show(index - 1);
            if (e.key === 'ArrowRight') show(index + 1);
        });
        box.addEventListener('touchstart', (e) => { startX = e.changedTouches[0].clientX; }, { passive: true });
        box.addEventListener('touchend', (e) => {
            if (startX === null) return;
            const dx = e.changedTouches[0].clientX - startX;
            startX = null;
            if (Math.abs(dx) > 50) show(index + (dx < 0 ? 1 : -1));
        });
    }

    function show(i) {
        if (!items.length) return;
        index = (i + items.length) % items.length;
        const link = items[index];
        const img = box.querySelector('img');
        const caption = link.dataset.caption || '';
        img.src = link.href;
        img.alt = caption;
        box.querySelector('figcaption').textContent = caption;
        box.querySelectorAll('.lb-nav').forEach((b) => { b.hidden = items.length < 2; });
    }

    document.addEventListener('click', (e) => {
        const link = e.target.closest('a[data-gallery]');
        if (!link || e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0 || typeof HTMLDialogElement === 'undefined') return;
        e.preventDefault();
        if (!box) build();
        items = Array.from(document.querySelectorAll('a[data-gallery="' + link.dataset.gallery + '"]'));
        show(items.indexOf(link));
        box.showModal();
    });
})();
