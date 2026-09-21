(function () {
    'use strict';
    const form = document.getElementById('chatForm');
    const input = document.getElementById('input');
    const sendBtn = document.getElementById('sendBtn');
    const resetBtn = document.getElementById('resetBtn');
    const messages = document.getElementById('messages');
    let busy = false;

    function addMessage(text, who, sources = []) {
        const wrap = document.createElement('div');
        wrap.className = 'msg msg--' + who;
        const bubble = document.createElement('div');
        bubble.className = 'msg__bubble';
        bubble.textContent = text;
        if (sources.length) {
            const details = document.createElement('details');
            const summary = document.createElement('summary');
            summary.textContent = 'Suchtreffer im Referenzhandbuch anzeigen';
            details.appendChild(summary);
            const note = document.createElement('p');
            note.textContent = 'Diese Textstellen wurden aus den zitierten Dateien gefunden. Prüfe, ob sie die Antwort tragen.';
            details.appendChild(note);
            sources.forEach((source) => {
                const quote = document.createElement('blockquote');
                quote.textContent = source.text;
                details.appendChild(quote);
            });
            bubble.appendChild(details);
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
        if (!value) input.focus();
    }
    async function request(body) {
        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), 90000);
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
        addMessage(message, 'user');
        input.value = '';
        input.style.height = 'auto';
        setBusy(true);
        const waiting = addMessage('Ich suche im Referenzhandbuch …', 'bot');
        try {
            const data = await request({message});
            if (typeof data.reply !== 'string' || !data.reply.trim()) throw new Error('Der Server hat keine Antwort geliefert.');
            addMessage(data.reply + (data.knowledge_version ? '\n\nWissensstand: ' + data.knowledge_version : ''), 'bot', Array.isArray(data.sources) ? data.sources : []);
        } catch (error) {
            addMessage(errorText(error), 'bot');
            input.value = message;
        } finally {
            waiting.remove();
            setBusy(false);
        }
    });
    resetBtn.addEventListener('click', async () => {
        if (busy) return;
        setBusy(true);
        try {
            const data = await request({reset: true});
            if (data.ok !== true) throw new Error('Die Unterhaltung konnte nicht zurückgesetzt werden.');
            messages.replaceChildren();
            input.value = '';
            addMessage('Neue Unterhaltung gestartet. Stell mir deine Frage zu HERMES 2022.', 'bot');
        } catch (error) { addMessage(errorText(error), 'bot'); }
        finally { setBusy(false); }
    });
})();
