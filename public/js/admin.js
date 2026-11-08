/* Admin: Cmd+S (Mac) / Ctrl+S saves the page's main form (marked data-save-shortcut),
   and the weeks-off checklist follows the start date. */

document.addEventListener('keydown', (e) => {
    if (!(e.metaKey || e.ctrlKey) || e.altKey || e.key.toLowerCase() !== 's') return;

    const form = document.querySelector('form[data-save-shortcut]');
    if (!form) return;

    e.preventDefault();
    if (e.repeat) return;
    form.requestSubmit();
});

const start = document.querySelector('[data-weeks-off-start]');
const box = document.querySelector('[data-weeks-off]');
if (start && box) {
    const rebuild = () => {
        const checked = new Set([...box.querySelectorAll('input:checked')].map((i) => i.value));
        const first = new Date(start.value + 'T12:00:00');
        if (isNaN(first)) return;
        const count = Number(box.dataset.count) || 30;
        box.replaceChildren();
        for (let i = 0; i < count; i++) {
            const d = new Date(first);
            d.setDate(first.getDate() + i * 7);
            const value = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
            const label = document.createElement('label');
            const input = document.createElement('input');
            input.type = 'checkbox';
            input.name = 'off[]';
            input.value = value;
            input.checked = checked.has(value);
            label.append(input, ' ' + d.toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' }));
            box.append(label);
        }
    };
    start.addEventListener('change', rebuild);
}

/* Doubles: tick everyone / clear, mark and tick the defending champions, and say how many players and teams that makes */
const doubles = document.querySelector('form[data-doubles]');
if (doubles) {
    const picks = () => [...doubles.querySelectorAll('.pick[data-name]')];
    const boxes = () => doubles.querySelectorAll('input[data-player]');
    const extras = doubles.querySelector('[data-extras]');
    const champions = () => [...doubles.querySelectorAll('[data-champion]')].map((i) => i.value.trim()).filter(Boolean);
    const out = doubles.querySelector('[data-count]');
    const split = (text) => text.split(/[,\n]+/).map((s) => s.trim().replace(/\s+/g, ' ')).filter(Boolean);

    const count = () => {
        const total = [...boxes()].filter((b) => b.checked).length + split(extras.value).length;
        if (total === 0) out.textContent = '';
        else if (total < 4) out.textContent = total + ' picked so far. You need at least four.';
        else if (total % 2) out.textContent = total + ' players. Add or remove one: doubles needs an even number.';
        else out.textContent = total + ' players, ' + total / 2 + ' teams';
    };

    // The marker follows whatever is typed in the champion boxes
    const mark = () => {
        const names = champions().map((n) => n.toLowerCase());
        picks().forEach((p) => {
            const badge = p.querySelector('[data-champ]');
            if (badge) badge.hidden = !names.includes(p.dataset.name);
        });
    };

    // Once a champion is entered they're ticked (or, if they aren't in the league, added to the other players)
    const bring = () => {
        const have = split(extras.value);
        champions().forEach((name) => {
            const pick = picks().find((p) => p.dataset.name === name.toLowerCase());
            if (pick) pick.querySelector('input').checked = true;
            else if (!have.some((h) => h.toLowerCase() === name.toLowerCase())) have.push(name);
        });
        extras.value = have.join(', ');
    };

    doubles.addEventListener('change', (e) => {
        if (e.target.matches('[data-champion]')) bring();
        mark();
        count();
    });
    doubles.addEventListener('input', (e) => {
        if (e.target.matches('[data-champion]')) mark();
        count();
    });
    doubles.querySelectorAll('[data-tick]').forEach((b) => b.addEventListener('click', () => {
        boxes().forEach((x) => { x.checked = b.dataset.tick === 'all'; });
        count();
    }));
    mark();
    count();
}
