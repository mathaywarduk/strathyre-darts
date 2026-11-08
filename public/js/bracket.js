/* The one-sided bracket.

   On phones and tablets each round fills the screen on its own (the next round is off to the right),
   so the title always matches what you can see, and the arrows, or a swipe, move between rounds.
   On wide screens (the admin) several rounds sit side by side and the arrows scroll across.
   Without JavaScript it is simply a sideways-scrolling bracket. */

(function () {
    const narrow = window.matchMedia('(max-width: 899px)');

    function init(root) {
        const scroller = root.querySelector('[data-bs-scroll]');
        const tree = root.querySelector('.bs-tree');
        const title = root.querySelector('[data-bs-title]');
        const prev = root.querySelector('[data-bs="prev"]');
        const next = root.querySelector('[data-bs="next"]');
        const parts = Array.from(scroller.querySelectorAll('.br-card[data-round], .bs-end[data-round]'));
        if (!parts.length) return;

        // The columns, in order: each round, then the trophy and winner at the end
        const keys = [];
        parts.forEach((p) => { if (!keys.includes(p.dataset.round)) keys.push(p.dataset.round); });
        keys.sort((a, b) => (a === 'end' ? Infinity : Number(a)) - (b === 'end' ? Infinity : Number(b)));
        const names = {};
        parts.forEach((p) => { names[p.dataset.round] = p.dataset.roundName; });
        const firstOf = (key) => parts.filter((p) => p.dataset.round === key).sort((a, b) => a.getBoundingClientRect().top - b.getBoundingClientRect().top)[0];

        // Phones: size the cards so one round exactly fills the width, and let a swipe settle on a round
        const layout = () => {
            if (narrow.matches) {
                const width = scroller.clientWidth - 4; // its padding
                const card = Math.min(width - 28, 440);
                tree.style.setProperty('--cw', card + 'px');
                tree.style.setProperty('--gap', width - card + 'px');
                root.classList.add('paged');
                keys.forEach((k) => firstOf(k).setAttribute('data-snap', ''));
            } else {
                tree.style.removeProperty('--cw');
                tree.style.removeProperty('--gap');
                root.classList.remove('paged');
            }
        };

        const maxScroll = () => scroller.scrollWidth - scroller.clientWidth;
        const startOf = (key) => {
            const origin = scroller.getBoundingClientRect().left - scroller.scrollLeft;
            const left = Math.min(...parts.filter((p) => p.dataset.round === key).map((p) => p.getBoundingClientRect().left));
            return Math.max(0, left - origin - 2);
        };

        // The column at the left edge of the view (or the last one, at the far end)
        const current = () => {
            if (maxScroll() > 2 && scroller.scrollLeft >= maxScroll() - 2) return keys[keys.length - 1];
            let best = keys[0];
            keys.forEach((k) => { if (Math.abs(startOf(k) - scroller.scrollLeft) < Math.abs(startOf(best) - scroller.scrollLeft)) best = k; });
            return best;
        };

        const update = () => {
            layout();
            root.classList.toggle('fits', maxScroll() <= 2);
            title.textContent = names[current()];
            prev.disabled = scroller.scrollLeft <= 2;
            next.disabled = scroller.scrollLeft >= maxScroll() - 2;
        };

        const go = (key) => {
            scroller.scrollTo({ left: Math.min(startOf(key), maxScroll()), behavior: 'smooth' });
            title.textContent = names[key]; // say where we're heading straight away
            setTimeout(update, 450);        // and settle once the scroll has finished
        };

        prev.addEventListener('click', () => {
            const i = keys.indexOf(current());
            go(scroller.scrollLeft - startOf(keys[i]) > 8 ? keys[i] : keys[Math.max(0, i - 1)]);
        });
        next.addEventListener('click', () => {
            go(keys[Math.min(keys.length - 1, keys.indexOf(current()) + 1)]);
        });

        let waiting = false;
        scroller.addEventListener('scroll', () => {
            if (waiting) return;
            waiting = true;
            requestAnimationFrame(() => { update(); waiting = false; });
        });
        if ('onscrollend' in window) scroller.addEventListener('scrollend', update);
        window.addEventListener('resize', update);
        narrow.addEventListener('change', update);
        layout();
        update();
    }

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[data-bracket-single]').forEach(init);
    });
})();
