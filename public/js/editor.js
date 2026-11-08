/* A small rich text editor for the admin: bold, italic, underline, links, lists and a
   line. It enhances a plain textarea, so without JavaScript you can still edit the HTML.
   Whatever is saved is cleaned again on the server. */

(function () {
    const esc = (s) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

    function init(root) {
        const area = root.querySelector('.rte-area');
        const tools = root.querySelector('.rte-toolbar');
        const source = root.querySelector('.rte-source');
        const form = root.closest('form');
        if (!area || !tools || !source) return;

        area.innerHTML = source.value; // already cleaned by the server
        area.hidden = false;
        tools.hidden = false;
        source.hidden = true;

        const sync = () => {
            const empty = area.textContent.trim() === '' && !area.querySelector('hr, li');
            source.value = empty ? '' : area.innerHTML;
        };

        const anchorAt = (node) => {
            while (node && node !== area) {
                if (node.nodeType === 1 && node.tagName === 'A') return node;
                node = node.parentNode;
            }
            return null;
        };

        const link = () => {
            const sel = window.getSelection();
            const current = sel && sel.anchorNode ? anchorAt(sel.anchorNode) : null;
            const answer = window.prompt('Link address (https://…). Leave empty to remove the link.', current ? current.getAttribute('href') : 'https://');
            if (answer === null) return;
            let url = answer.trim();
            if (url === '' || url === 'https://') {
                document.execCommand('unlink');
                return;
            }
            if (!/^(https?:|mailto:)/i.test(url)) url = 'https://' + url;
            if (sel && sel.isCollapsed && !current) {
                document.execCommand('insertHTML', false, '<a href="' + esc(url) + '">' + esc(url) + '</a>');
            } else {
                document.execCommand('createLink', false, url);
            }
        };

        const run = (cmd) => {
            area.focus();
            if (cmd === 'link') link();
            else document.execCommand(cmd, false, null);
            sync();
            refresh();
        };

        const refresh = () => {
            tools.querySelectorAll('button[data-cmd]').forEach((b) => {
                const cmd = b.dataset.cmd;
                if (['bold', 'italic', 'underline', 'insertUnorderedList', 'insertOrderedList'].includes(cmd)) {
                    let on = false;
                    try { on = document.queryCommandState(cmd); } catch (e) { /* not supported */ }
                    b.setAttribute('aria-pressed', on && document.activeElement === area ? 'true' : 'false');
                }
            });
        };

        tools.addEventListener('mousedown', (e) => e.preventDefault()); // keep the selection
        tools.addEventListener('click', (e) => {
            const b = e.target.closest('button[data-cmd]');
            if (b) run(b.dataset.cmd);
        });

        area.addEventListener('input', sync);
        area.addEventListener('keyup', refresh);
        area.addEventListener('mouseup', refresh);
        area.addEventListener('blur', () => { sync(); refresh(); });
        area.addEventListener('keydown', (e) => {
            if ((e.metaKey || e.ctrlKey) && !e.altKey && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                run('link');
            }
        });
        // Pasted text goes in as plain text, so nothing comes along with it
        area.addEventListener('paste', (e) => {
            e.preventDefault();
            const text = (e.clipboardData || window.clipboardData).getData('text/plain');
            document.execCommand('insertText', false, text);
        });
        if (form) form.addEventListener('submit', sync);
        sync();
    }

    document.addEventListener('DOMContentLoaded', () => {
        try { document.execCommand('defaultParagraphSeparator', false, 'p'); } catch (e) { /* older browsers */ }
        document.querySelectorAll('[data-rte]').forEach(init);
    });
})();
