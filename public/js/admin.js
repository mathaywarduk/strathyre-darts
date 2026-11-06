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
