(function () {
    'use strict';
    const form = document.getElementById('chatForm');
    const input = document.getElementById('input');
    const sendBtn = document.getElementById('sendBtn');
    const resetBtn = document.getElementById('resetBtn');
    const messages = document.getElementById('messages');
    const welcome = document.getElementById('welcome');
    const suggestions = document.querySelectorAll('[data-question]');
    let busy = false;

    function addMessage(text, who, sources = [], version = null, sourceMode = null) {
        const wrap = document.createElement('div');
        wrap.className = 'msg msg--' + who;
        const bubble = document.createElement('div');
        bubble.className = 'msg__bubble';
        bubble.textContent = text;
        if (sources.length) {
            const details = document.createElement('details');
            const summary = document.createElement('summary');
            summary.textContent = sourceMode === 'retrieval' ? 'Gefundene Handbuchstellen anzeigen' : 'Textbelege im Referenzhandbuch anzeigen';
            details.appendChild(summary);
            const note = document.createElement('p');
            note.textContent = sourceMode === 'retrieval' ? 'Diese Ausschnitte stammen aus der Handbuchsuche zur Frage. Sie dienen zum Nachlesen und sind keine automatische Bestätigung jedes Antwortsatzes.' : 'Diese Textbelege wurden im angegebenen Kapitel gefunden. Die Nummern ordnen sie den Aussagen zu. Prüfe, ob die Schlussfolgerungen stimmen.';
            details.appendChild(note);
            sources.forEach((source, index) => {
                const quote = document.createElement('blockquote');
                quote.textContent = '[' + (index + 1) + '] ' + source.text;
                details.appendChild(quote);
            });
            bubble.appendChild(details);
        }
        if (version) {
            const meta = document.createElement('small');
            meta.className = 'msg__meta';
            meta.textContent = 'Wissensstand: ' + version;
            bubble.appendChild(meta);
        }
        wrap.appendChild(bubble);
        messages.appendChild(wrap);
        messages.scrollTop = messages.scrollHeight;
        return wrap;
    }
    function setBusy(value) {
        busy = value;
        sendBtn.disabled = input.disabled = resetBtn.disabled = value;
        form.setAttribute('aria-busy', String(value));
        suggestions.forEach(button => { button.disabled = value; });
        if (!value) input.focus();
    }
    async function request(body) {
        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), 150000);
        try {
            const res = await fetch('api/chat.php', {
                method: 'POST', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(body), signal: controller.signal
            });
            let data;
            try { data = await res.json(); }
            catch (_) { throw new Error('Der Server hat keine gültige Antwort geliefert. Bitte informiere den Betreiber.'); }
            if (!res.ok || data.error) {
                throw new Error((data.error || 'Technischer Fehler.') + (data.request_id ? ' Fehlernummer: ' + data.request_id : ''));
            }
            return data;
        } finally { clearTimeout(timer); }
    }
    function errorText(error) {
        if (error.name === 'AbortError') return 'Die Antwort dauert zu lange. Bitte versuche es später nochmals.';
        if (error instanceof TypeError) return 'Die Verbindung zum Server ist unterbrochen. Bitte prüfe deine Verbindung.';
        return error.message;
    }
    input.addEventListener('input', () => {
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 160) + 'px';
    });
    input.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
            event.preventDefault();
            if (!busy) form.requestSubmit();
        }
    });
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const message = input.value.trim();
        if (busy || !message) return;
        welcome.hidden = true;
        addMessage(message, 'user');
        input.value = '';
        input.style.height = 'auto';
        setBusy(true);
        const waiting = addMessage('Ich bearbeite deine Frage …', 'bot');
        waiting.classList.add('is-typing');
        const progress = setTimeout(() => { waiting.querySelector('.msg__bubble').textContent = 'Ich suche noch nach passenden Handbuchstellen und formuliere die Antwort.'; }, 12000);
        try {
            const data = await request({message});
            if (typeof data.reply !== 'string' || !data.reply.trim()) throw new Error('Der Server hat keine Antwort geliefert.');
            addMessage(data.reply, 'bot', Array.isArray(data.sources) ? data.sources : [], data.knowledge_version, data.source_mode);
        } catch (error) {
            addMessage(errorText(error), 'bot');
            input.value = message;
        } finally {
            clearTimeout(progress);
            waiting.remove();
            setBusy(false);
        }
    });
    suggestions.forEach(button => button.addEventListener('click', () => {
        if (busy) return;
        input.value = button.dataset.question;
        form.requestSubmit();
    }));
    resetBtn.addEventListener('click', async () => {
        if (busy) return;
        setBusy(true);
        try {
            const data = await request({reset: true});
            if (data.ok !== true) throw new Error('Die Unterhaltung konnte nicht zurückgesetzt werden.');
            messages.replaceChildren();
            input.value = '';
            input.style.height = 'auto';
            welcome.hidden = false;
        } catch (error) { addMessage(errorText(error), 'bot'); }
        finally { setBusy(false); }
    });
})();

