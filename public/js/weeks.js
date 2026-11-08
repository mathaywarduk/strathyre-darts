/* Results and fixtures: the arrows and the dropdown swap the week in place.
   The page isn't reloaded, so the arrow you pressed is still under the pointer for the
   next click, and quick clicks keep counting (three clicks on "next" is three weeks on).
   Without JavaScript they are ordinary links and a form. */

(function () {
    const panel = document.getElementById('week-panel');
    if (!panel) return;

    let latest = 0;   // the newest request; older replies are ignored
    let intended = null; // the week the last click was heading for, until its page arrives

    const select = () => panel.querySelector('select[name="week"]');
    const urlFor = (week) => {
        const url = new URL(select().form.action, window.location.href);
        url.searchParams.set('week', week);
        return url.toString();
    };

    async function show(url, push) {
        const ticket = ++latest;
        panel.setAttribute('aria-busy', 'true');
        try {
            const res = await fetch(url, { headers: { 'X-Requested-With': 'fetch' }, credentials: 'same-origin' });
            if (!res.ok) throw new Error('bad response');
            const doc = new DOMParser().parseFromString(await res.text(), 'text/html');
            const next = doc.getElementById('week-panel');
            if (!next) throw new Error('no panel');
            if (ticket !== latest) return; // a newer click has already taken over

            const focused = document.activeElement && document.activeElement.dataset ? document.activeElement.dataset.dir : null;
            panel.innerHTML = next.innerHTML;
            document.title = doc.title;
            if (push) history.pushState({ week: true }, '', url);
            if (focused) {
                const again = panel.querySelector('[data-dir="' + focused + '"]');
                if (again) again.focus({ preventScroll: true });
            }
            intended = null;
            panel.removeAttribute('aria-busy');
        } catch (e) {
            window.location.href = url; // fall back to a normal page load
        }
    }

    panel.addEventListener('click', (e) => {
        const link = e.target.closest('a.wn-btn');
        if (!link || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0) return;
        e.preventDefault();

        // Count from where the last click was heading, not from the week still on screen
        const weeks = Array.from(select().options, (o) => Number(o.value));
        const from = intended !== null ? intended : Number(select().value);
        const target = weeks[weeks.indexOf(from) + (link.dataset.dir === 'next' ? 1 : -1)];
        if (target === undefined) return;
        intended = target;
        show(urlFor(target), true);
    });

    panel.addEventListener('change', (e) => {
        if (!e.target.closest('select[name="week"]')) return;
        intended = Number(e.target.value);
        show(urlFor(intended), true);
    });

    window.addEventListener('popstate', () => { intended = null; show(window.location.href, false); });
})();
