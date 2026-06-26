(function () {
    "use strict";

    const form     = document.getElementById("chatForm");
    const input    = document.getElementById("input");
    const sendBtn  = document.getElementById("sendBtn");
    const resetBtn = document.getElementById("resetBtn");
    const messages = document.getElementById("messages");

    const API_URL = "api/chat.php";

    /** Escaped HTML und wandelt Zeilenumbrüche in <br>. */
    function renderText(text) {
        const div = document.createElement("div");
        div.textContent = text;
        return div.innerHTML.replace(/\n/g, "<br>");
    }

    function addMessage(text, who, opts) {
        const wrap = document.createElement("div");
        wrap.className = "msg msg--" + who + (opts && opts.typing ? " is-typing" : "");
        const bubble = document.createElement("div");
        bubble.className = "msg__bubble";
        bubble.innerHTML = renderText(text);
        wrap.appendChild(bubble);
        messages.appendChild(wrap);
        messages.scrollTop = messages.scrollHeight;
        return wrap;
    }

    function setBusy(busy) {
        sendBtn.disabled = busy;
        input.disabled = busy;
        if (!busy) input.focus();
    }

    // Textarea wächst mit dem Inhalt.
    input.addEventListener("input", function () {
        input.style.height = "auto";
        input.style.height = Math.min(input.scrollHeight, 160) + "px";
    });

    // Enter sendet, Shift+Enter = neue Zeile.
    input.addEventListener("keydown", function (e) {
        if (e.key === "Enter" && !e.shiftKey) {
            e.preventDefault();
            form.requestSubmit();
        }
    });

    form.addEventListener("submit", async function (e) {
        e.preventDefault();
        const message = input.value.trim();
        if (!message) return;

        addMessage(message, "user");
        input.value = "";
        input.style.height = "auto";
        setBusy(true);

        const typing = addMessage("Chatty schreibt …", "bot", { typing: true });

        try {
            const res = await fetch(API_URL, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ message: message })
            });
            const data = await res.json();
            typing.remove();

            if (!res.ok || data.error) {
                addMessage("Fehler: " + (data.error || ("HTTP " + res.status)), "bot");
            } else {
                addMessage(data.reply || "(leere Antwort)", "bot");
            }
        } catch (err) {
            typing.remove();
            addMessage("Netzwerkfehler: " + err.message, "bot");
        } finally {
            setBusy(false);
        }
    });

    resetBtn.addEventListener("click", async function () {
        try {
            await fetch(API_URL, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ reset: true })
            });
        } catch (e) { /* ignorieren */ }
        messages.innerHTML = "";
        addMessage(
            "Neue Unterhaltung gestartet. Stell mir deine Frage zu HERMES 2022.",
            "bot"
        );
    });
})();
