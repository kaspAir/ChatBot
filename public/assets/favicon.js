(() => {
    'use strict';
    const icon = document.getElementById('hermaestroFavicon');
    const mascot = document.querySelector('.hermaestro');
    if (!icon || !mascot) return;
    const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const original = icon.href;
    let timer = null;
    let frames = null;
    let building = null;
    let generation = 0;
    function stop() {
        generation++;
        clearInterval(timer);
        timer = null;
        icon.type = 'image/svg+xml';
        icon.sizes = 'any';
        icon.href = original;
    }
    async function buildFrames() {
        const output = [];
        const canvas = document.createElement('canvas');
        canvas.width = canvas.height = 64;
        const context = canvas.getContext('2d');
        if (!context) throw new Error('Canvas unavailable');
        for (let i = 0; i < 12; i++) {
            const svg = mascot.cloneNode(true);
            svg.setAttribute('viewBox', '-24 -315 1180 1180');
            svg.setAttribute('width', '64');
            svg.setAttribute('height', '64');
            svg.removeAttribute('class');
            const phase = i / 12 * Math.PI * 2;
            const left = svg.querySelector('.hermaestro-wing-left');
            const right = svg.querySelector('.hermaestro-wing-right');
            if (left) left.setAttribute('transform', 'rotate(' + (7 * Math.sin(phase)) + ' 387 287)');
            if (right) right.setAttribute('transform', 'rotate(' + (-7 * Math.sin(phase)) + ' 873 280)');
            svg.querySelectorAll('.hermaestro-pupil').forEach(pupil => {
                pupil.setAttribute('transform', 'translate(' + (6 * Math.sin(phase)) + ' ' + (-6 + 6 * Math.cos(phase)) + ')');
            });
            const url = URL.createObjectURL(new Blob([new XMLSerializer().serializeToString(svg)], {type: 'image/svg+xml'}));
            try {
                const image = new Image();
                await new Promise((resolve, reject) => {
                    image.onload = resolve; image.onerror = reject; image.src = url;
                });
                context.clearRect(0, 0, 64, 64);
                context.drawImage(image, 0, 0, 64, 64);
                output.push(canvas.toDataURL('image/png'));
            } finally { URL.revokeObjectURL(url); }
        }
        return output;
    }
    async function play() {
        stop();
        if (motion.matches || document.hidden) return;
        const token = generation;
        try {
            if (!frames) {
                building ||= buildFrames();
                frames = await building;
            }
            if (generation !== token || motion.matches || document.hidden) return;
            let step = 0;
            icon.type = 'image/png';
            icon.sizes = '64x64';
            icon.href = frames[0];
            timer = setInterval(() => {
                step++;
                if (step >= 24) { stop(); return; }
                icon.href = frames[step % frames.length];
            }, 200);
        } catch (_) { if (generation === token) stop(); }
    }
    // Kurze Begrüssung und Bewegung beim Öffnen; kein dauernder Timer.
    document.getElementById('chatLauncher')?.addEventListener('click', () => {
        if (document.getElementById('chatLauncher').getAttribute('aria-expanded') === 'true') play();
    });
    document.addEventListener('visibilitychange', () => { if (document.hidden) stop(); });
    motion.addEventListener('change', stop);
    window.addEventListener('pagehide', stop);
    play();
})();
