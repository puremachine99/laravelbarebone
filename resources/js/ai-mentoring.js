// Backs the "Pendampingan" tab of the AI Center (resources/views/livewire/ai/partials/mentoring-desk.blade.php).
// Ported from the core UI in report/ai/desk-nakama.html. The guided flow (questions, input widgets, progress) is
// decided by the Nakama mapping API; every call goes through MentoringController so the API key stays on the server.
// All text is inserted with textContent, never innerHTML, because it comes from the AI service and the user.

const TOTAL_STEPS = 13;
const ONBOARDING_FLOATING = [['coffee', 'Ngobrol Santai'], ['forum', 'Cerita Apa Adanya'], ['explore', 'Peta Alur Kerja'], ['auto_awesome', 'Ide Perbaikan'], ['flag', '13 Langkah']];
const FLOAT_SLOTS = [
    { top: '7%', left: '3%', r: '-6deg', d: '6s' },
    { top: '16%', right: '4%', r: '4deg', d: '7.5s' },
    { top: '46%', left: '-1%', r: '-10deg', d: '8s' },
    { bottom: '16%', right: '2%', r: '7deg', d: '6.5s' },
    { bottom: '6%', left: '8%', r: '-3deg', d: '9s' },
];
const STATUS_LABELS = { active: 'Berjalan', paused: 'Dijeda', completed: 'Selesai' };
const SOUNDS = {
    select: [[587.33, 0, 0.12, 'triangle', 0.3], [880, 0.08, 0.18, 'triangle', 0.26]],
    submit: [[523.25, 0, 0.12, 'triangle', 0.32], [783.99, 0.09, 0.22, 'triangle', 0.3]],
    question: [[659.25, 0, 0.12, 'sine', 0.3], [987.77, 0.1, 0.26, 'sine', 0.26]],
    confirm: [[523.25, 0, 0.11, 'square', 0.12], [659.25, 0.1, 0.11, 'square', 0.12], [783.99, 0.2, 0.28, 'square', 0.12]],
    celebrate: [[523.25, 0, 0.14, 'triangle', 0.3], [659.25, 0.13, 0.14, 'triangle', 0.3], [783.99, 0.26, 0.14, 'triangle', 0.3], [1046.5, 0.39, 0.45, 'triangle', 0.32]],
    error: [[311.13, 0, 0.18, 'sawtooth', 0.1], [233.08, 0.15, 0.26, 'sawtooth', 0.1]],
};

const storage = {
    get(key) { try { return window.localStorage.getItem(key); } catch (error) { return null; } },
    set(key, value) { try { window.localStorage.setItem(key, value); } catch (error) { } },
};

const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

class ApiError extends Error {
    constructor(message, status) {
        super(message);
        this.status = status;
    }
}

function el(tag, attrs, ...children) {
    const node = document.createElement(tag);

    Object.entries(attrs || {}).forEach(([key, value]) => {
        if (value === null || value === undefined || value === false) return;
        if (key === 'class') node.className = value;
        else if (key === 'text') node.textContent = value;
        else if (key.startsWith('on')) node.addEventListener(key.slice(2), value);
        else if (key === 'style') node.setAttribute('style', value);
        else node.setAttribute(key, value === true ? '' : value);
    });

    children.flat().forEach((child) => {
        if (child === null || child === undefined || child === false) return;
        node.append(child instanceof Node ? child : document.createTextNode(String(child)));
    });

    return node;
}

function icon(name, size = 20, extra = '') {
    return el('span', { class: `material-symbols-outlined ${extra}`, style: `font-size:${size}px`, 'aria-hidden': 'true', text: name });
}

let audioContext = null;
let masterGain = null;

function audio() {
    if (!audioContext) {
        const Context = window.AudioContext || window.webkitAudioContext;

        if (!Context) return null;

        audioContext = new Context();
        masterGain = audioContext.createGain();
        masterGain.gain.value = 0.9;
        masterGain.connect(audioContext.destination);
    }

    return audioContext;
}

function scheduleSound(context, name) {
    const start = context.currentTime + 0.01;

    SOUNDS[name].forEach(([frequency, offset, duration, type, volume]) => {
        const oscillator = context.createOscillator();
        const gain = context.createGain();
        oscillator.type = type;
        oscillator.frequency.setValueAtTime(frequency, start + offset);
        gain.gain.setValueAtTime(0.0001, start + offset);
        gain.gain.exponentialRampToValueAtTime(volume, start + offset + 0.02);
        gain.gain.exponentialRampToValueAtTime(0.0001, start + offset + duration);
        oscillator.connect(gain);
        gain.connect(masterGain);
        oscillator.start(start + offset);
        oscillator.stop(start + offset + duration + 0.05);
    });
}

function mountMentoringDesk(root, config) {
    const ref = (name) => root.querySelector(`[data-ref="${name}"]`);
    const url = (template, sessionId) => template.replace('map_SESSIONID', encodeURIComponent(sessionId));

    const scroller = ref('scroller');
    const feed = ref('feed');

    const app = {
        units: [],
        sessions: [],
        unit: null,
        session: null,
        muted: storage.get('pemetaan-muted') === '1',
        busy: false,
        prompt: null,
    };

    async function api(path, options = {}) {
        const response = await fetch(path, {
            ...options,
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
        });

        if (!response.ok) {
            let message = 'Terjadi kesalahan, coba lagi ya.';

            if (response.status === 419) message = 'Sesi login kedaluwarsa. Muat ulang halaman ya.';
            else if (response.status === 429) message = 'Terlalu cepat, tunggu sebentar lalu coba lagi ya.';

            try {
                const body = await response.json();
                message = body.message || message;
            } catch (error) { }

            throw new ApiError(message, response.status);
        }

        return options.raw ? response : response.json();
    }

    function play(name) {
        if (app.muted || !SOUNDS[name]) return;

        try {
            const context = audio();

            if (!context) return;

            if (context.state === 'running') scheduleSound(context, name);
            else context.resume().then(() => scheduleSound(context, name)).catch(() => { });
        } catch (error) { }
    }

    function renderSoundToggles() {
        root.querySelectorAll('[data-sound-icon]').forEach((node) => {
            const compact = node.closest('button').querySelector('[data-sound-label]') === null;
            node.textContent = app.muted ? 'volume_off' : (compact ? 'volume_up' : 'graphic_eq');
        });
        root.querySelectorAll('[data-sound-label]').forEach((node) => {
            node.textContent = app.muted ? 'Sound: Senyap' : 'Sound: Nyala';
        });
    }

    root.querySelectorAll('[data-sound-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            app.muted = !app.muted;
            storage.set('pemetaan-muted', app.muted ? '1' : '0');
            renderSoundToggles();
            play('select');
        });
    });

    let toastTimer = null;

    function toast(message, tone = 'info') {
        const wrap = ref('toast');
        const body = ref('toast-body');
        const styles = { info: 'bg-paper text-cocoa', error: 'bg-[#FEE2E2] text-[#7F1D1D]', success: 'bg-mint text-leaf' };
        body.className = `toon rounded-2xl px-5 py-3 font-bold flex items-center gap-2 ${styles[tone] || styles.info}`;
        body.replaceChildren(icon(tone === 'error' ? 'warning' : tone === 'success' ? 'check_circle' : 'chat', 20), el('span', { text: message }));
        wrap.classList.remove('hidden');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => wrap.classList.add('hidden'), 4200);

        if (tone === 'error') play('error');
    }

    function renderFloating(items, soft) {
        const container = ref('floating');
        container.replaceChildren();

        (items || []).slice(0, FLOAT_SLOTS.length).forEach(([iconName, label], index) => {
            const slot = FLOAT_SLOTS[index];
            const position = Object.entries(slot).filter(([key]) => ['top', 'left', 'right', 'bottom'].includes(key)).map(([key, value]) => `${key}:${value}`).join(';');
            const visibility = index >= 2 ? 'hidden md:flex' : 'flex';
            container.append(
                el('div', {
                    class: `${visibility} absolute items-center gap-2 px-3 py-2 rounded-2xl toon-sm floaty opacity-50 md:opacity-90`,
                    style: `${position};--r:${slot.r};--d:${slot.d};background:${index % 2 ? '#FFFDF9' : soft}`,
                },
                    icon(iconName, 20, 'text-cocoa'),
                    el('span', { class: 'text-[12px] font-extrabold text-cocoa', text: label }),
                ),
            );
        });
    }

    function applyUnitTheme(unit) {
        root.style.setProperty('--unit', unit ? unit.color : '#A0522D');
        root.style.setProperty('--unit-soft', unit ? unit.soft_color : '#FFE4A0');
        // The service sends [emoji, label] pairs; the emoji is swapped for the unit's own icon.
        const floating = unit ? (unit.floating || []).map(([, label]) => [unit.icon, label]) : ONBOARDING_FLOATING;
        renderFloating(floating, unit ? unit.soft_color : '#FFE4A0');
    }

    function showScreen(name) {
        root.querySelectorAll('[data-screen]').forEach((node) => {
            const screen = node.dataset.screen;
            node.classList.toggle('hidden', screen !== name);
            node.classList.toggle('flex', screen === name && screen !== 'closing');
        });
        scroller.scrollTop = 0;
    }

    function setVisible(node, visible) {
        node.classList.toggle('hidden', !visible);
        node.classList.toggle('flex', visible);
    }

    function renderUnits() {
        const grid = ref('unit-grid');
        grid.replaceChildren();

        if (!app.units.length) {
            grid.append(el('p', { class: 'col-span-full text-center text-latte font-bold py-8', text: 'Daftar unit belum tersedia. Hubungi tim Digital Transformation.' }));
            return;
        }

        const assigned = assignedUnit();
        ref('unit-heading').textContent = assigned ? 'Unit / Departemen Kamu' : 'Pilih Unit / Departemen Kamu';
        ref('unit-hint').textContent = assigned ? 'Terdeteksi otomatis dari departemen akunmu' : 'Setiap unit punya identitas & alur probing kontekstual';

        if (assigned) {
            grid.append(unitCard(assigned, { selected: true, locked: true }));
            return;
        }

        app.units.forEach((unit) => grid.append(unitCard(unit, { selected: Boolean(app.unit && app.unit.key === unit.key), locked: false })));
    }

    /**
     * The user's own unit when their department maps to one (see config/ai_center.php); null lets them pick.
     */
    function assignedUnit() {
        return config.assignedUnit ? app.units.find((unit) => unit.key === config.assignedUnit) || null : null;
    }

    function unitCard(unit, { selected, locked }) {
        const badge = locked
            ? el('span', { class: 'inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-cocoa text-paper text-[11px] font-extrabold' }, icon('lock', 13), 'Unit kamu')
            : selected
                ? el('span', { class: 'inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-cocoa text-paper text-[11px] font-extrabold' }, icon('check', 13), 'Terpilih')
                : null;
        const footer = locked ? ['badge', 'Terdeteksi dari akun login · siap mulai ngobrol'] : selected ? ['check_circle', 'Siap mulai ngobrol'] : ['schedule', 'Siap Dipetakan'];

        return el(locked ? 'div' : 'button', {
            type: locked ? null : 'button',
            role: locked ? null : 'radio',
            'aria-checked': locked ? null : (selected ? 'true' : 'false'),
            class: `text-left rounded-3xl p-5 flex flex-col justify-between gap-4 transition-all ${locked ? 'col-span-full toon' : selected ? 'toon toon-press -translate-y-0.5' : 'toon-sm toon-press hover:-translate-y-0.5'}`,
            style: `background:${selected ? unit.soft_color : '#FFF8F0'}`,
            onclick: locked ? null : () => selectUnit(unit.key),
        },
            el('div', { class: 'flex items-start justify-between gap-3' },
                el('div', { class: 'w-14 h-14 rounded-2xl toon-sm flex items-center justify-center text-white -rotate-3', style: `background:${unit.color}` }, icon(unit.icon, 30)),
                badge,
            ),
            el('div', {},
                el('h3', { class: 'text-[19px] font-extrabold text-cocoa', text: unit.name }),
                el('p', { class: 'text-[14px] leading-6 text-bark font-medium mt-1', text: unit.tagline }),
            ),
            el('div', { class: 'text-[12px] font-bold flex items-center gap-1.5 text-latte' }, icon(footer[0], 16), footer[1]),
        );
    }

    function selectUnit(key) {
        app.unit = app.units.find((unit) => unit.key === key) || null;
        applyUnitTheme(app.unit);
        renderUnits();
        syncStartButton();
        play('select');
    }

    function syncStartButton() {
        ref('btn-start').disabled = !app.unit;
    }

    ref('btn-start').addEventListener('click', startSession);

    async function startSession() {
        if (!app.unit || app.busy) return;

        const button = ref('btn-start');
        const original = Array.from(button.childNodes).map((node) => node.cloneNode(true));
        app.busy = true;
        button.disabled = true;
        button.replaceChildren(icon('sync', 22, 'animate-spin'), el('span', { text: 'Menyiapkan ruang ngobrol...' }));
        play('submit');

        try {
            const detail = await api(config.sessionsUrl, {
                method: 'POST',
                body: JSON.stringify({ unit: app.unit.key }),
            });
            await openChat(detail, true);
        } catch (error) {
            toast(error.message, 'error');
        } finally {
            app.busy = false;
            button.replaceChildren(...original);
            syncStartButton();
        }
    }

    function initials(session) {
        if (session.anonymous) return '🕶️';
        const parts = (session.display_name || '').split(/\s+/).filter(Boolean);
        return (parts[0] ? parts[0][0] : '?').toUpperCase() + (parts[1] ? parts[1][0].toUpperCase() : '');
    }

    function shortName(session) {
        return session.anonymous ? 'Kamu (Anonim)' : session.display_name;
    }

    function formatTime(value) {
        const date = value ? new Date(value) : new Date();
        return date.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
    }

    function formatDate(value) {
        return value ? new Date(value).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) : '';
    }

    function updateBanner(session) {
        const progress = session.progress || {};
        const total = Math.max(progress.outputs_total || 0, progress.output_no || 1);
        ref('chat-unit-label').textContent = `Unit: ${session.unit.name}`;
        ref('chat-step-pill').textContent = `${progress.step || 1}/${TOTAL_STEPS}`;
        ref('chat-avatar').textContent = initials(session);
        ref('banner-icon').textContent = session.unit.icon;
        ref('banner-output-no').textContent = progress.outputs_total ? `Output ${progress.output_no} dari ${total}` : `Output ${progress.output_no || 1}`;
        ref('banner-output-name').textContent = progress.output_name || (progress.outputs_total ? 'Memilih output yang dibedah' : 'Memetakan output kerja');
        const stage = progress.stage_name ? ` · Tahap ${progress.stage_no}/${progress.stage_total}` : '';
        ref('banner-title').textContent = `Langkah ${progress.step || 1} dari ${TOTAL_STEPS} · ${progress.title || ''}${stage}`;
        ref('banner-percent').textContent = `${progress.percent || 0}% Selesai`;
        ref('banner-bar').style.width = `${Math.max(2, progress.percent || 0)}%`;
        ref('saved-label').textContent = `Tersimpan aman untuk Unit ${session.unit.name}`;
    }

    function scrollToBottom() {
        requestAnimationFrame(() => {
            const dock = ref('composer-dock');
            const target = feed.lastElementChild;

            if (!target) return;

            const visibleBottom = scroller.getBoundingClientRect().bottom - dock.offsetHeight - 24;
            const delta = target.getBoundingClientRect().bottom - visibleBottom;
            scroller.scrollTo({ top: Math.max(0, scroller.scrollTop + delta), behavior: 'smooth' });
        });
    }

    function botAvatar(kind) {
        const map = {
            question: ['smart_toy', 'bg-butter'],
            followup: ['lightbulb', 'bg-peach'],
            faq: ['shield_with_heart', 'bg-mint'],
            stage_cards: ['map', 'bg-butter'],
            summary: ['celebration', 'bg-tangerine text-white'],
            ack: ['sentiment_satisfied', 'bg-butter'],
            greeting: ['waving_hand', 'bg-butter'],
        };
        const [name, color] = map[kind] || ['smart_toy', 'bg-butter'];
        return el('div', { class: 'relative shrink-0' },
            el('div', { class: `w-12 h-12 rounded-2xl ${color} toon-sm flex items-center justify-center text-cocoa -rotate-3` }, icon(name, 26)),
            kind === 'question' ? el('span', { class: 'absolute -bottom-1 -right-1 bg-mint border border-cocoa rounded-full text-[10px] px-1 font-extrabold', text: 'BOT' }) : null,
        );
    }

    function stageCards(stages) {
        const palette = ['#FFD166', '#D2F5DC', '#FFE4A0', '#FFE3B3', '#E8DAEF', '#FADBD8'];
        return el('div', { class: 'grid grid-cols-1 sm:grid-cols-2 gap-3 mt-4' },
            (stages || []).map((stage, index) => el('div', {
                class: 'bg-cream rounded-2xl p-4 toon-sm flex flex-col gap-3 pop-in',
                style: `animation-delay:${index * 90}ms`,
            },
                el('div', { class: 'flex items-center justify-between' },
                    el('span', { class: 'w-9 h-9 rounded-xl border-2 border-cocoa text-cocoa text-sm font-extrabold flex items-center justify-center', style: `background:${palette[index % palette.length]}`, text: String(stage.no).padStart(2, '0') }),
                    el('span', { class: 'text-2xl', text: stage.emoji }),
                ),
                el('div', { class: 'flex items-start gap-2' },
                    el('div', { class: 'w-8 h-8 rounded-lg unit-soft border border-cocoa flex items-center justify-center shrink-0' }, icon(stage.icon, 18, 'text-cocoa')),
                    el('p', { class: 'font-bold text-ink leading-snug', text: stage.name }),
                ),
            )),
        );
    }

    function botBubble(message, animate) {
        const isQuestion = message.kind === 'question' || message.kind === 'followup';
        const bubbleClass = isQuestion
            ? 'bg-paper text-ink p-5 sm:p-6 rounded-3xl rounded-tl-md toon'
            : message.kind === 'faq'
                ? 'bg-mint/60 text-ink p-4 sm:p-5 rounded-3xl rounded-tl-md toon-sm'
                : message.kind === 'summary'
                    ? 'bg-butter text-ink p-5 sm:p-6 rounded-3xl rounded-tl-md toon'
                    : 'bg-paper text-ink p-4 sm:p-5 rounded-3xl rounded-tl-md toon-sm';

        const header = el('div', { class: 'flex items-center gap-2 flex-wrap' },
            el('span', { class: 'font-extrabold text-cocoa text-[13px] flex items-center gap-1' }, el('span', { class: 'w-2 h-2 rounded-full bg-[#2E7D32]' }), 'SmartID Companion'),
            isQuestion && message.step ? el('span', { class: 'px-2.5 py-0.5 rounded-full bg-honey border border-cocoa text-cocoa text-[11px] font-extrabold', text: `Langkah ${message.step} dari ${TOTAL_STEPS}` }) : null,
            message.kind === 'followup' ? el('span', { class: 'px-2 py-0.5 rounded-full bg-peach border border-cocoa text-cocoa text-[11px] font-extrabold', text: '💡 Pertanyaan lanjutan' }) : null,
            message.method === 'llm' ? el('span', { class: 'px-2 py-0.5 rounded-full bg-sand border border-cocoa text-latte text-[10px] font-extrabold', text: 'AI' }) : null,
            el('span', { class: 'text-latte text-xs font-semibold', text: formatTime(message.created_at) }),
        );

        const context = message.context
            ? el('div', { class: 'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-2xl bg-[#FFF2B2] toon-sm text-cocoa self-start font-bold text-xs' },
                el('span', { class: 'text-base', text: '📍' }),
                el('span', {}, 'Konteks sedang ditanyakan: ', el('strong', { text: `Tahap ${message.context.stage_no} dari ${message.context.stage_total} — ${message.context.stage}` })),
            )
            : null;

        const hasStageCards = message.ui && message.ui.type === 'stage_cards';
        const body = el('div', { class: bubbleClass },
            el('p', { class: `${isQuestion ? 'text-[18px] sm:text-[19px] font-bold' : 'text-[16px] sm:text-[17px] font-medium'} leading-relaxed whitespace-pre-line`, text: message.text }),
            hasStageCards ? stageCards(message.ui.stages) : null,
            message.kind === 'summary' ? el('button', {
                type: 'button',
                class: 'mt-4 toon-sm toon-press px-5 py-3 rounded-2xl bg-cocoa text-paper font-bold flex items-center gap-2',
                onclick: () => showClosing(app.session),
            }, icon('emoji_events', 20), 'Lihat ringkasan kontribusiku') : null,
        );

        return el('div', { class: `flex items-start gap-3 sm:gap-4 ${animate ? 'pop-in' : ''}` },
            botAvatar(message.kind),
            el('div', { class: `flex flex-col gap-2 ${hasStageCards ? 'w-full' : 'max-w-[88%] sm:max-w-[80%]'}` }, header, context, body),
        );
    }

    function userBubble(text, animate, time) {
        const session = app.session;
        return el('div', { class: `flex items-start justify-end gap-3 sm:gap-4 ${animate ? 'pop-in' : ''}` },
            el('div', { class: 'flex flex-col items-end gap-1.5 max-w-[85%] sm:max-w-[78%]' },
                el('div', { class: 'flex items-center gap-2' },
                    el('span', { class: 'text-latte text-xs font-semibold', text: formatTime(time) }),
                    el('span', { class: 'font-extrabold text-cocoa text-[13px]', text: session ? shortName(session) : 'Kamu' }),
                ),
                el('div', { class: 'bg-cocoa text-[#FDF4E7] p-4 sm:p-5 rounded-3xl rounded-tr-md border-[2.5px] border-espresso', style: 'box-shadow:4px 4px 0 #D35400' },
                    el('p', { class: 'text-[16px] sm:text-[17px] leading-relaxed font-medium whitespace-pre-line', text }),
                ),
            ),
            el('div', { class: 'relative shrink-0' },
                el('div', { class: 'w-12 h-12 rounded-2xl bg-honey toon-sm flex items-center justify-center font-extrabold text-cocoa rotate-3', text: session ? initials(session) : '?' }),
                el('span', { class: 'absolute -bottom-1 -right-1 bg-tangerine text-white border border-cocoa rounded-full text-[10px] px-1 font-extrabold', text: 'KAMU' }),
            ),
        );
    }

    function typingIndicator() {
        return el('div', { class: 'flex items-start gap-3 sm:gap-4 pop-in' },
            botAvatar('ack'),
            el('div', { class: 'bg-paper toon-sm rounded-3xl rounded-tl-md px-5 py-4 flex items-center gap-1.5' },
                ['', '', ''].map(() => el('span', { class: 'dot w-2.5 h-2.5 rounded-full bg-latte inline-block' })),
            ),
        );
    }

    function messageNode(message, animate) {
        return message.role === 'user' ? userBubble(message.text, animate, message.created_at) : botBubble(message, animate);
    }

    async function revealMessages(messages) {
        for (const message of messages) {
            const typing = typingIndicator();
            feed.append(typing);
            scrollToBottom();
            await wait(Math.min(1100, 380 + (message.text || '').length * 6));
            typing.remove();
            feed.append(botBubble(message, true));

            if (message.kind === 'question' || message.kind === 'followup') play('question');
            if (message.kind === 'summary') play('celebrate');

            scrollToBottom();
        }
    }

    function activePrompt(messages) {
        const inputs = ['list', 'scale', 'choice', 'duration', 'stages', 'confirm', 'text'];
        return [...(messages || [])].reverse().find((message) => message.ui && inputs.includes(message.ui.type)) || null;
    }

    async function openChat(detail, animate) {
        app.session = detail;
        app.unit = detail.unit;
        applyUnitTheme(detail.unit);
        showScreen('chat');
        updateBanner(detail);
        feed.replaceChildren();
        ref('composer').replaceChildren();
        ref('dock-actions').classList.remove('hidden');
        const messages = detail.messages || [];

        if (detail.status === 'completed') {
            messages.forEach((message) => feed.append(messageNode(message, false)));
            showClosing(detail);
            return;
        }

        if (animate) {
            await revealMessages(messages);
        } else {
            messages.forEach((message) => feed.append(messageNode(message, false)));

            if (detail.status === 'paused') {
                feed.append(botBubble({ kind: 'ack', text: `Selamat datang kembali, ${detail.anonymous ? 'Rekan' : detail.display_name}! ☕ Kita lanjut dari pertanyaan terakhir ya.`, created_at: new Date().toISOString() }, true));
            }

            scrollToBottom();
        }

        renderComposer(activePrompt(detail.prompt && detail.prompt.length ? detail.prompt : messages));
    }

    /**
     * Read-only replay of a session from the history: every message, no input, nothing can be changed.
     */
    function openHistory(detail) {
        app.session = detail;
        app.unit = detail.unit;
        applyUnitTheme(detail.unit);
        showScreen('chat');
        updateBanner(detail);
        feed.replaceChildren(...(detail.messages || []).map((message) => messageNode(message, false)));
        ref('dock-actions').classList.add('hidden');
        app.prompt = null;

        const completed = detail.status === 'completed';
        const composer = ref('composer');
        composer.replaceChildren(
            el('div', { class: 'flex items-start gap-3' },
                el('div', { class: 'w-10 h-10 rounded-2xl bg-sand toon-sm flex items-center justify-center text-cocoa shrink-0' }, icon('lock', 22)),
                el('div', { class: 'min-w-0' },
                    el('div', { class: 'flex items-center gap-2 flex-wrap' },
                        el('span', { class: 'text-[12px] font-extrabold uppercase tracking-wider text-rust', text: '📖 Mode baca' }),
                        el('span', { class: `px-2 py-0.5 rounded-full border border-cocoa text-[11px] font-extrabold ${completed ? 'bg-mint text-leaf' : 'bg-honey text-cocoa'}`, text: STATUS_LABELS[detail.status] || detail.status }),
                    ),
                    el('p', { class: 'text-bark font-semibold text-[14px] mt-0.5', text: `Riwayat sesi ${detail.unit.name} · ${formatDate(detail.created_at)}. Jawaban yang sudah terkirim tidak bisa diubah.` }),
                ),
            ),
            el('div', { class: 'flex flex-col sm:flex-row gap-2.5' },
                completed
                    ? el('button', { type: 'button', class: 'toon-sm toon-press px-5 py-3 rounded-2xl bg-cocoa text-paper font-extrabold flex items-center justify-center gap-2', onclick: () => showClosing(detail) }, icon('emoji_events', 20), 'Lihat ringkasan')
                    : el('button', { type: 'button', class: 'toon-sm toon-press px-5 py-3 rounded-2xl bg-rust hover:bg-[#B74300] text-white font-extrabold flex items-center justify-center gap-2', onclick: () => openChat(detail, false) }, icon('play_circle', 20), 'Lanjutkan sesi ini'),
                completed ? el('button', { type: 'button', class: 'toon-sm toon-press px-5 py-3 rounded-2xl bg-paper hover:bg-peach text-cocoa font-extrabold flex items-center justify-center gap-2', onclick: () => download(detail.id, 'markdown', 'ringkasan-pendampingan.md') }, icon('download', 20), 'Unduh ringkasan') : null,
                completed ? null : el('button', { type: 'button', class: 'toon-sm toon-press px-5 py-3 rounded-2xl bg-[#FEE2E2] hover:bg-[#FECACA] text-[#7F1D1D] font-extrabold flex items-center justify-center gap-2', onclick: () => confirmCancel(detail) }, icon('cancel', 20), 'Batalkan Sesi'),
                el('button', { type: 'button', class: 'px-5 py-3 rounded-2xl text-latte hover:text-espresso hover:bg-peach font-extrabold flex items-center justify-center gap-1.5', onclick: () => showOnboarding() }, icon('arrow_back', 18), 'Kembali'),
            ),
        );
        scroller.scrollTop = 0;
    }

    /**
     * Asks before cancelling an unfinished session. Finished sessions never get the button (and the server refuses).
     */
    function confirmCancel(detail) {
        if (detail.status === 'completed' || app.busy) return;

        const overlay = el('div', { class: 'absolute inset-0 z-50 bg-espresso/40 backdrop-blur-sm flex items-center justify-center p-4', role: 'dialog', 'aria-modal': 'true' });
        const close = () => overlay.remove();
        const confirmButton = el('button', { type: 'button', class: 'toon-sm toon-press px-5 py-3 rounded-2xl bg-[#B91C1C] hover:bg-[#991B1B] text-white font-extrabold flex items-center justify-center gap-2 disabled:opacity-60' }, icon('cancel', 20), 'Ya, batalkan sesi');

        confirmButton.addEventListener('click', async () => {
            if (app.busy) return;

            app.busy = true;
            confirmButton.disabled = true;

            try {
                await api(url(config.cancelUrl, detail.id), { method: 'POST' });
                close();
                toast('Sesi dibatalkan dan tidak akan masuk ke rekap.', 'success');
                play('confirm');
                app.busy = false;
                await showOnboarding();
            } catch (error) {
                confirmButton.disabled = false;
                toast(error.message, 'error');
            } finally {
                app.busy = false;
            }
        });

        overlay.addEventListener('click', (event) => { if (event.target === overlay) close(); });
        overlay.append(
            el('div', { class: 'bg-paper toon rounded-3xl p-6 w-full max-w-md flex flex-col gap-4 pop-in' },
                el('div', { class: 'flex items-start gap-3' },
                    el('div', { class: 'w-12 h-12 rounded-2xl bg-[#FEE2E2] toon-sm flex items-center justify-center text-[#B91C1C] shrink-0 -rotate-3' }, icon('cancel', 26)),
                    el('div', {},
                        el('p', { class: 'font-extrabold text-cocoa text-lg', text: 'Batalkan sesi ini?' }),
                        el('p', { class: 'text-bark text-sm font-medium mt-1', text: `Sesi ${detail.unit.name} (${(detail.progress || {}).percent || 0}% selesai) akan hilang dari riwayatmu, tidak bisa dilanjutkan, dan tidak masuk ke rekap. Ini tidak bisa dibatalkan.` }),
                    ),
                ),
                el('div', { class: 'flex flex-col-reverse sm:flex-row sm:justify-end gap-2.5' },
                    el('button', { type: 'button', class: 'px-5 py-3 rounded-2xl text-bark hover:bg-peach font-extrabold', onclick: close }, 'Tidak jadi'),
                    confirmButton,
                ),
            ),
        );
        root.append(overlay);
    }

    function sendButton(label, handler, options = {}) {
        return el('button', {
            type: 'button',
            class: `toon-sm toon-press px-5 py-3 rounded-2xl ${options.secondary ? 'bg-sand text-cocoa hover:bg-peach' : 'bg-rust hover:bg-[#B74300] text-white'} font-extrabold flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed`,
            onclick: handler,
        }, el('span', { text: label }), options.secondary ? null : icon('send', 20));
    }

    function composerHeader(prompt) {
        const title = { list: '✍️ Tulis jawabanmu', scale: '😊 Pilih perasaanmu', choice: '👉 Pilih salah satu', duration: '⏱️ Perkiraan lama pengerjaan', stages: '🧩 Susun tahapan kerjamu', confirm: '✅ Konfirmasi', text: '💭 Ceritakan dengan bebas' }[prompt.ui.type] || 'Jawabanmu';
        return el('div', { class: 'flex items-center justify-between gap-2 flex-wrap' },
            el('span', { class: 'text-[12px] font-extrabold uppercase tracking-wider text-rust', text: title }),
            prompt.context ? el('span', { class: 'px-3 py-1 rounded-full bg-[#FFF2B2] border border-cocoa text-cocoa text-[12px] font-bold truncate max-w-full', text: `📍 Tahap ${prompt.context.stage_no}: ${prompt.context.stage}` }) : null,
        );
    }

    function autoGrow(textarea) {
        if (!textarea.isConnected) return;

        textarea.style.height = 'auto';

        if (textarea.scrollHeight) textarea.style.height = `${Math.min(textarea.scrollHeight, 220)}px`;
    }

    function textArea(placeholder, rows = 2) {
        const area = el('textarea', {
            rows: String(rows),
            maxlength: '4000',
            placeholder,
            class: 'w-full bg-cream toon-sm rounded-2xl px-4 py-3 text-[16px] sm:text-[17px] font-medium text-ink placeholder:text-ghost focus:outline-none focus:ring-4 focus:ring-honey/60 resize-none leading-relaxed',
        });
        area.addEventListener('input', () => autoGrow(area));
        return area;
    }

    function onEnter(input, handler) {
        input.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
                event.preventDefault();
                handler();
            }
        });
    }

    function renderComposer(prompt) {
        const composer = ref('composer');
        composer.replaceChildren();
        app.prompt = prompt;

        if (!prompt) {
            composer.append(el('p', { class: 'text-bark font-bold text-center py-2', text: 'Sesi ini sudah selesai. Terima kasih! 🙏' }));
            return;
        }

        composer.append(composerHeader(prompt));
        const builders = { list: buildList, scale: buildScale, choice: buildChoice, duration: buildDuration, stages: buildStages, confirm: buildConfirm, text: buildText };
        (builders[prompt.ui.type] || buildText)(composer, prompt);
        requestAnimationFrame(() => {
            composer.querySelectorAll('textarea').forEach(autoGrow);
            scrollToBottom();
        });
    }

    function chip(text, onRemove) {
        return el('span', { class: 'inline-flex items-center gap-1.5 pl-3 pr-1.5 py-1.5 rounded-xl bg-paper toon-sm text-cocoa font-bold text-sm pop-in' },
            el('span', { class: 'max-w-[260px] truncate', text }),
            el('button', { type: 'button', 'aria-label': `Hapus ${text}`, class: 'w-6 h-6 rounded-full bg-[#FEE2E2] border border-cocoa text-[#B91C1C] flex items-center justify-center hover:bg-[#EF4444] hover:text-white', onclick: onRemove }, icon('close', 14)),
        );
    }

    function buildList(composer, prompt) {
        const items = [];
        const chips = el('div', { class: 'flex flex-wrap gap-2 min-h-[8px]' });
        const counter = el('span', { class: 'text-[12px] font-bold text-latte', text: 'Belum ada isian' });
        const input = el('input', {
            type: 'text',
            maxlength: '300',
            placeholder: prompt.ui.placeholder || 'Ketik lalu tekan Enter',
            class: 'w-full bg-cream toon-sm rounded-2xl pl-11 pr-4 py-3.5 text-[16px] font-semibold text-ink placeholder:text-ghost focus:outline-none focus:ring-4 focus:ring-honey/60',
        });

        function refresh() {
            chips.replaceChildren(...items.map((item, index) => chip(item, () => { items.splice(index, 1); refresh(); })));
            counter.textContent = items.length ? `${items.length} terisi` : 'Belum ada isian';
        }

        function add(value) {
            const text = (value || '').trim();

            if (!text) return false;

            if (!items.some((item) => item.toLowerCase() === text.toLowerCase())) items.push(text);

            input.value = '';
            refresh();
            play('select');
            return true;
        }

        function submit() {
            if (input.value.trim()) add(input.value);

            if (!items.length) {
                input.focus();
                toast('Isi minimal satu jawaban dulu ya, atau pakai tombol lewati.', 'info');
                return;
            }

            sendAnswer({ items: [...items] }, items.map((item) => `• ${item}`).join('\n'));
        }

        onEnter(input, () => { if (!add(input.value) && items.length) submit(); });

        const examples = (prompt.ui.examples || []).slice(0, 3);
        composer.append(
            el('div', { class: 'bg-sand/70 p-4 rounded-2xl border-2 border-cocoa flex flex-col gap-3' },
                el('div', { class: 'flex items-center justify-between' }, el('span', { class: 'text-[12px] font-extrabold text-cocoa uppercase tracking-wider', text: '🏷️ Daftar jawabanmu' }), counter),
                chips,
                el('div', { class: 'flex items-center gap-2' },
                    el('div', { class: 'relative flex-1' }, el('span', { class: 'absolute left-3.5 top-1/2 -translate-y-1/2 text-latte' }, icon('add_circle', 22)), input),
                    el('button', { type: 'button', class: 'toon-sm toon-press px-4 py-3.5 rounded-2xl bg-butter hover:bg-honey text-cocoa font-extrabold flex items-center gap-1 shrink-0', onclick: () => add(input.value) }, icon('add', 20), el('span', { class: 'hidden sm:inline', text: prompt.ui.add_label || 'Tambah' })),
                ),
                examples.length ? el('div', { class: 'flex flex-wrap items-center gap-2' },
                    el('span', { class: 'text-[12px] font-bold text-latte', text: 'Contoh:' }),
                    examples.map((example) => el('button', { type: 'button', class: 'px-3 py-1 rounded-full bg-paper border border-cocoa text-[12px] font-bold text-bark hover:bg-peach', onclick: () => add(example), text: `+ ${example}` })),
                ) : null,
            ),
            el('div', { class: 'flex flex-col-reverse sm:flex-row sm:items-center justify-between gap-3' },
                prompt.ui.skip_label ? sendButton(prompt.ui.skip_label, () => sendAnswer({ value: 'skip' }, prompt.ui.skip_label), { secondary: true }) : el('span'),
                sendButton('Kirim jawaban', submit),
            ),
        );
        input.focus({ preventScroll: true });
    }

    function buildScale(composer, prompt) {
        let selected = null;
        const buttons = (prompt.ui.options || []).map((option) => el('button', {
            type: 'button',
            role: 'radio',
            'aria-checked': 'false',
            'data-scale-option': '1',
            class: 'flex-1 min-w-[88px] flex flex-col items-center gap-1.5 px-2 py-3 rounded-2xl bg-cream toon-sm toon-press hover:-translate-y-0.5 transition',
            onclick: (event) => {
                selected = option;
                composer.querySelectorAll('[data-scale-option]').forEach((node) => {
                    const active = node === event.currentTarget;
                    node.setAttribute('aria-checked', active ? 'true' : 'false');
                    node.style.background = active ? '#FFD166' : '';
                });
                play('select');
            },
        }, el('span', { class: 'text-[34px] leading-none', text: option.emoji }), el('span', { class: 'text-[12px] font-extrabold text-cocoa text-center', text: option.label })));
        const area = textArea(prompt.ui.placeholder || 'Ceritakan sedikit (opsional)…', 2);

        function submit() {
            const text = area.value.trim();

            if (!selected && !text) {
                toast('Pilih emoji atau ceritakan perasaanmu dulu ya.', 'info');
                return;
            }

            const echo = [selected ? `${selected.emoji} ${selected.label}` : null, text || null].filter(Boolean).join('\n');
            sendAnswer({ value: selected ? selected.value : null, text: text || null }, echo);
        }

        onEnter(area, submit);
        composer.append(el('div', { class: 'flex flex-wrap gap-2 sm:gap-3', role: 'radiogroup' }, buttons), area, el('div', { class: 'flex justify-end' }, sendButton('Kirim', submit)));
    }

    function buildChoice(composer, prompt) {
        let selected = null;
        const withReason = Boolean(prompt.ui.reason);
        const area = textArea(withReason ? (prompt.ui.placeholder || 'Kenapa?') : 'Atau ketik jawabanmu sendiri…', withReason ? 2 : 1);
        const options = (prompt.ui.options || []).map((option, index) => el('button', {
            type: 'button',
            'data-choice-option': '1',
            class: 'w-full text-left flex items-center gap-3 px-4 py-3.5 rounded-2xl bg-cream toon-sm toon-press hover:-translate-y-0.5 transition',
            onclick: (event) => {
                if (!withReason) {
                    sendAnswer({ value: option.value }, option.label);
                    return;
                }

                selected = option;
                composer.querySelectorAll('[data-choice-option]').forEach((node) => { node.style.background = node === event.currentTarget ? '#FFD166' : ''; });
                play('select');
                area.focus();
            },
        },
            el('span', { class: 'w-9 h-9 rounded-xl bg-butter border-2 border-cocoa flex items-center justify-center font-extrabold text-cocoa shrink-0', text: String(index + 1).padStart(2, '0') }),
            el('span', { class: 'font-bold text-ink text-[16px] leading-snug', text: option.label }),
        ));

        function submit() {
            const text = area.value.trim();

            if (!selected && !text) {
                toast(withReason ? 'Pilih tahapnya lalu ceritakan alasannya ya.' : 'Klik salah satu pilihan atau ketik jawabanmu.', 'info');
                return;
            }

            const echo = [selected ? selected.label : null, text || null].filter(Boolean).join('\n');
            sendAnswer({ value: selected ? selected.value : null, text: text || null }, echo);
        }

        onEnter(area, submit);
        composer.append(el('div', { class: 'grid grid-cols-1 sm:grid-cols-2 gap-2.5' }, options), area, el('div', { class: 'flex justify-end' }, sendButton('Kirim', submit)));
    }

    function buildDuration(composer, prompt) {
        const selectClass = 'bg-cream toon-sm rounded-2xl px-3 py-3.5 font-bold text-ink focus:outline-none focus:ring-4 focus:ring-honey/60';
        const number = el('input', { type: 'number', min: '0', step: '0.5', inputmode: 'decimal', placeholder: '3', class: 'w-24 bg-cream toon-sm rounded-2xl px-4 py-3.5 text-[18px] font-extrabold text-ink text-center focus:outline-none focus:ring-4 focus:ring-honey/60' });
        const unit = el('select', { class: selectClass, 'aria-label': 'Satuan' }, (prompt.ui.units || []).map((value) => el('option', { value, text: value, selected: value === 'hari' })));
        const frequency = el('select', { class: selectClass, 'aria-label': 'Frekuensi' }, (prompt.ui.frequencies || []).map((value) => el('option', { value, text: value })));
        const free = el('input', { type: 'text', maxlength: '200', placeholder: prompt.ui.placeholder || 'Atau ketik bebas', class: 'w-full bg-cream toon-sm rounded-2xl px-4 py-3.5 text-[16px] font-semibold text-ink placeholder:text-ghost focus:outline-none focus:ring-4 focus:ring-honey/60' });

        function submit() {
            const typed = free.value.trim();
            const amount = number.value.trim();

            if (!typed && !amount) {
                toast('Isi angka perkiraannya, atau ketik bebas ya.', 'info');
                return;
            }

            const text = typed || `${amount.replace('.', ',')} ${unit.value}${frequency.value === 'sekali jalan' ? '' : ` ${frequency.value}`}`;
            sendAnswer({ text, value: typed ? null : 'duration' }, text);
        }

        onEnter(number, submit);
        onEnter(free, submit);
        composer.append(
            el('div', { class: 'flex flex-wrap items-center gap-2.5' }, el('span', { class: 'text-2xl', text: '⏱️' }), number, unit, frequency),
            el('div', { class: 'flex items-center gap-3' }, el('span', { class: 'h-[2px] flex-1 border-t-2 border-dashed border-biscuit' }), el('span', { class: 'text-[12px] font-bold text-latte', text: 'atau' }), el('span', { class: 'h-[2px] flex-1 border-t-2 border-dashed border-biscuit' })),
            free,
            el('div', { class: 'flex justify-end' }, sendButton('Kirim', submit)),
        );
        number.focus({ preventScroll: true });
    }

    function buildStages(composer, prompt) {
        const list = el('div', { class: 'relative flex flex-col gap-3' });
        const placeholders = prompt.ui.placeholders && prompt.ui.placeholders.length ? prompt.ui.placeholders : ['Contoh: Terima permintaan dari atasan via WA'];
        const colors = ['#FFD166', '#D2F5DC', '#FFE4A0', '#FF8C42', '#FFE3B3'];

        function renumber() {
            Array.from(list.children).forEach((row, index) => {
                const badge = row.querySelector('[data-no]');
                badge.textContent = String(index + 1).padStart(2, '0');
                badge.style.background = colors[index % colors.length];
                row.querySelector('textarea').placeholder = placeholders[index % placeholders.length];
            });
        }

        function addRow(value, focus) {
            const area = el('textarea', { rows: '1', maxlength: '300', class: 'w-full bg-transparent text-[16px] sm:text-[17px] font-semibold text-ink placeholder:text-ghost focus:outline-none resize-none leading-relaxed' });
            area.value = value || '';
            area.addEventListener('input', () => autoGrow(area));
            const row = el('div', { class: 'flex items-start gap-3 pop-in' },
                el('div', { 'data-no': '1', class: 'w-12 h-12 shrink-0 rounded-2xl toon-sm flex items-center justify-center font-extrabold text-cocoa text-lg -rotate-2' }),
                el('div', { class: 'flex-1 bg-cream rounded-2xl px-4 py-3 toon-sm focus-within:ring-4 focus-within:ring-honey/60 flex items-start gap-2' },
                    area,
                    el('button', {
                        type: 'button',
                        'aria-label': 'Hapus tahap',
                        class: 'p-1.5 rounded-xl text-latte hover:text-[#B91C1C] hover:bg-peach shrink-0',
                        onclick: () => {
                            if (list.children.length === 1) {
                                area.value = '';
                                return;
                            }

                            row.remove();
                            renumber();
                        },
                    }, icon('delete_outline', 20)),
                ),
            );
            area.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
                    event.preventDefault();
                    const rows = Array.from(list.children);
                    const position = rows.indexOf(row);

                    if (position === rows.length - 1 && area.value.trim()) addRow('', true);
                    else if (rows[position + 1]) rows[position + 1].querySelector('textarea').focus();
                }
            });
            list.append(row);
            renumber();
            autoGrow(area);

            if (focus) area.focus();
        }

        const prefill = prompt.ui.prefill && prompt.ui.prefill.length ? prompt.ui.prefill : ['', ''];
        prefill.forEach((value) => addRow(value, false));

        function submit() {
            const stages = Array.from(list.querySelectorAll('textarea')).map((area) => area.value.trim()).filter(Boolean);

            if (!stages.length) {
                toast('Isi minimal satu kotak tahapan ya.', 'info');
                return;
            }

            sendAnswer({ items: stages }, stages.map((stage, index) => `${index + 1}. ${stage}`).join('\n'));
        }

        composer.append(
            list,
            el('button', { type: 'button', class: 'w-full py-3.5 px-4 rounded-2xl bg-butter hover:bg-honey toon-sm toon-press flex items-center justify-center gap-2.5 text-cocoa font-extrabold', onclick: () => { addRow('', true); play('select'); } },
                el('span', { class: 'w-8 h-8 rounded-xl bg-paper border-2 border-cocoa flex items-center justify-center' }, icon('add', 20)), 'Tambah Kotak Tahapan Selanjutnya', el('span', { text: '✨' }),
            ),
            prompt.ui.tip ? el('p', { class: 'text-[13px] font-semibold text-bark flex items-center gap-1.5', text: `💡 Tip: ${prompt.ui.tip}` }) : null,
            el('div', { class: 'flex justify-end' }, sendButton('Simpan & Intip Alur', submit)),
        );
        list.querySelector('textarea').focus({ preventScroll: true });
    }

    function buildConfirm(composer, prompt) {
        const [first, second] = prompt.ui.options || [];
        composer.append(
            el('div', { class: 'grid grid-cols-1 sm:grid-cols-2 gap-3' },
                first ? el('button', { type: 'button', class: 'toon toon-press py-4 px-5 rounded-2xl bg-rust hover:bg-[#B74300] text-white font-extrabold text-[17px] flex items-center justify-center gap-2', onclick: () => { play('confirm'); sendAnswer({ value: first.value }, first.label); } }, el('span', { class: 'text-xl', text: first.emoji }), first.label) : null,
                second ? el('button', { type: 'button', class: 'toon toon-press py-4 px-5 rounded-2xl bg-sand hover:bg-peach text-cocoa font-extrabold text-[17px] flex items-center justify-center gap-2', onclick: () => sendAnswer({ value: second.value }, second.label) }, el('span', { class: 'text-xl', text: second.emoji }), second.label) : null,
            ),
        );
    }

    function buildText(composer, prompt) {
        const area = textArea((prompt.ui && prompt.ui.placeholder) || 'Tulis jawabanmu…', 3);

        function submit() {
            const text = area.value.trim();

            if (!text) {
                area.focus();
                return;
            }

            sendAnswer({ text }, text);
        }

        onEnter(area, submit);
        composer.append(area, el('div', { class: 'flex items-center justify-between gap-3 flex-wrap' }, el('span', { class: 'text-[12px] font-bold text-latte', text: 'Enter untuk kirim · Shift+Enter untuk baris baru' }), sendButton('Kirim', submit)));
        area.focus({ preventScroll: true });
    }

    function lockComposer(locked) {
        const composer = ref('composer');
        composer.classList.toggle('opacity-60', locked);
        composer.classList.toggle('pointer-events-none', locked);
        composer.setAttribute('aria-busy', locked ? 'true' : 'false');
    }

    async function sendAnswer(payload, echo) {
        if (app.busy || !app.session) return;

        app.busy = true;
        lockComposer(true);
        play('submit');
        const bubble = userBubble(echo || payload.text || '', true);
        feed.append(bubble);
        scrollToBottom();

        try {
            const turn = await api(url(config.answerUrl, app.session.id), { method: 'POST', body: JSON.stringify(payload) });
            app.session = { ...app.session, ...turn.session };
            updateBanner(app.session);
            ref('composer').replaceChildren();
            lockComposer(false);
            await revealMessages(turn.messages || []);

            if (turn.session.status === 'paused') {
                renderComposer(activePrompt(turn.session.prompt || []));
                toast('Sesi dijeda. Jawabanmu sudah tersimpan.', 'success');
                return;
            }

            if (turn.session.status === 'completed') {
                renderComposer(null);
                setTimeout(() => showClosing(app.session), 1400);
                return;
            }

            renderComposer(activePrompt(turn.messages) || activePrompt(turn.session.prompt || []));
        } catch (error) {
            bubble.remove();
            lockComposer(false);
            toast(error.message, 'error');
        } finally {
            app.busy = false;
        }
    }

    ref('btn-pause').addEventListener('click', async () => {
        if (!app.session || app.busy) return;

        app.busy = true;

        try {
            await api(url(config.pauseUrl, app.session.id), { method: 'POST' });
            toast('Sesi dijeda dan tersimpan. Sampai jumpa lagi! ☕', 'success');
            play('confirm');
            app.busy = false;
            await showOnboarding();
        } catch (error) {
            toast(error.message, 'error');
        } finally {
            app.busy = false;
        }
    });

    ref('btn-home').addEventListener('click', () => {
        if (!app.busy) showOnboarding();
    });

    async function download(sessionId, format, fallbackName) {
        try {
            const response = await api(`${url(config.exportUrl, sessionId)}?format=${format}`, { raw: true });
            const disposition = response.headers.get('Content-Disposition') || '';
            const match = disposition.match(/filename="([^"]+)"/);
            const blob = await response.blob();
            const objectUrl = URL.createObjectURL(blob);
            const anchor = el('a', { href: objectUrl, download: match ? match[1] : fallbackName });
            document.body.append(anchor);
            anchor.click();
            anchor.remove();
            setTimeout(() => URL.revokeObjectURL(objectUrl), 2000);
            play('select');
        } catch (error) {
            toast(error.message, 'error');
        }
    }

    function metricCard(emoji, tint, value, badge, title, caption) {
        return el('div', { class: 'bg-paper p-5 rounded-2xl toon flex items-start gap-4 transition-transform hover:-translate-y-0.5' },
            el('div', { class: 'w-14 h-14 rounded-2xl toon-sm flex items-center justify-center text-2xl shrink-0', style: `background:${tint}`, text: emoji }),
            el('div', { class: 'min-w-0' },
                el('div', { class: 'flex items-baseline gap-2 flex-wrap' },
                    el('span', { class: 'text-[32px] font-extrabold text-espresso leading-none', text: String(value ?? 0) }),
                    el('span', { class: 'text-[11px] font-extrabold uppercase px-2 py-0.5 rounded-full border border-cocoa', style: `background:${tint}`, text: badge }),
                ),
                el('p', { class: 'font-extrabold text-espresso mt-1.5', text: title }),
                el('span', { class: 'text-[13px] text-bark font-medium leading-snug block mt-0.5', text: caption }),
            ),
        );
    }

    function flowSnapshot(output) {
        return el('div', { class: 'p-4 rounded-2xl bg-cream border-2 border-dashed border-latte/50 overflow-x-auto no-scrollbar' },
            el('p', { class: 'text-[12px] font-extrabold uppercase tracking-wider text-latte mb-3', text: `Output ${output.output_no}: ${output.name}` }),
            el('div', { class: 'flex items-stretch gap-2.5 min-w-max' },
                output.stages.flatMap((stage, index) => {
                    const last = index === output.stages.length - 1;
                    const inputs = stage.inputs || [];
                    const outputs = stage.outputs || [];
                    const card = el('div', {
                        class: 'relative w-40 bg-paper p-3 rounded-2xl text-center flex flex-col items-center gap-1',
                        style: stage.hardest ? 'border:2px solid #D35400;box-shadow:2px 2px 0 #D35400;background:#FFEDD5' : last ? 'border:2px solid #27AE60;box-shadow:2px 2px 0 #27AE60;background:#E8F8F0' : 'border:2px solid #4A2E18;box-shadow:2px 2px 0 #4A2E18',
                    },
                        stage.hardest ? el('span', { class: 'absolute -top-3 px-2 py-0.5 rounded-full bg-rust text-white text-[10px] font-extrabold border border-cocoa', text: '⚠️ Bottleneck' }) : null,
                        el('span', { class: 'w-7 h-7 rounded-full bg-butter border border-cocoa text-xs font-extrabold text-espresso flex items-center justify-center', text: String(stage.no) }),
                        el('span', { class: 'text-xl', text: stage.emoji }),
                        el('span', { class: 'text-[13px] font-extrabold text-espresso leading-snug', text: stage.name }),
                        inputs.length ? el('span', { class: 'text-[11px] font-semibold text-latte', text: `📥 ${inputs.join(', ')}` }) : null,
                        outputs.length ? el('span', { class: 'text-[11px] font-semibold text-leaf', text: `📤 ${outputs.join(', ')}` }) : null,
                    );
                    return last ? [card] : [card, el('span', { class: 'self-center text-rust font-extrabold text-lg', text: '➜' })];
                }),
            ),
        );
    }

    function showClosing(session) {
        if (!session) return;

        const summary = session.summary || {};
        const totals = summary.totals || {};
        const outputs = (summary.outputs || []).filter((output) => output.stages && output.stages.length);
        const root_ = ref('closing-root');
        root_.replaceChildren();
        applyUnitTheme(session.unit);
        const firstName = session.anonymous ? 'Rekan' : (session.display_name || '').split(/\s+/)[0];

        const banner = el('div', { class: 'relative flex flex-col md:flex-row md:items-center justify-between gap-5 bg-paper p-6 sm:p-7 rounded-3xl toon overflow-hidden' },
            el('div', { class: 'flex items-center gap-5' },
                el('div', { class: 'w-16 h-16 rounded-2xl bg-butter toon-sm flex items-center justify-center text-3xl shrink-0 -rotate-3', text: '🏆' }),
                el('div', {},
                    el('div', { class: 'flex items-center gap-2 mb-1 flex-wrap' },
                        el('span', { class: 'text-[11px] uppercase tracking-wider text-cocoa px-3 py-0.5 rounded-full bg-butter border border-cocoa font-extrabold', text: '🎉 Selesai 100%' }),
                        el('span', { class: 'text-[12px] text-latte font-bold', text: `Langkah ${TOTAL_STEPS} dari ${TOTAL_STEPS}` }),
                    ),
                    el('h1', { class: 'text-[24px] sm:text-[30px] text-espresso font-extrabold tracking-tight', text: 'Sesi Pemetaan Probis Berhasil Diselesaikan! 🎊' }),
                    el('p', { class: 'text-bark font-medium mt-0.5' }, 'Seluruh alur, titik kritis, dan aspirasi otomasi tersimpan rapi untuk ', el('strong', { class: 'text-espresso', text: `Unit ${session.unit.name}` }), '.'),
                ),
            ),
            el('span', { class: 'inline-flex items-center gap-2 px-4 py-2 rounded-2xl bg-peach toon-sm font-bold text-espresso self-start md:self-center', text: `🏷️ ID Sesi: #${session.id.slice(-8).toUpperCase()}` }),
        );

        const reflections = el('div', { class: 'lg:col-span-5 flex flex-col gap-5' },
            el('div', { class: 'flex items-center gap-2 px-2' }, el('span', { class: 'w-3 h-3 rounded-full bg-tangerine border border-cocoa' }), el('span', { class: 'text-[12px] uppercase tracking-wider text-latte font-extrabold', text: '📖 Kilas balik jawabanmu (Langkah 10–12)' })),
            outputs.flatMap((output) => [
                output.hardest_stage ? el('div', { class: 'bg-paper p-6 rounded-3xl toon flex flex-col gap-3' },
                    el('div', { class: 'flex items-center justify-between gap-2 flex-wrap' },
                        el('span', { class: 'inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-[#FFE5D3] border border-rust text-[#944A00] text-[12px] font-extrabold' }, icon('warning', 15), 'Titik tersulit'),
                        el('span', { class: 'text-[12px] text-rust font-extrabold', text: output.name }),
                    ),
                    el('span', { class: 'text-[12px] text-rust uppercase font-extrabold tracking-wide', text: `Tahap ${output.hardest_stage.index + 1} dari ${output.stages.length}` }),
                    el('h3', { class: 'text-[20px] text-espresso font-extrabold -mt-2', text: output.hardest_stage.name }),
                    output.hardest_stage.reason ? el('div', { class: 'relative text-bark font-medium bg-[#FFF3E3] border-2 border-dashed border-[#E0AC82] p-4 rounded-2xl mt-1' },
                        el('span', { class: 'absolute -top-2.5 left-4 px-2 bg-[#FFF3E3] text-[#944A00] text-[11px] font-extrabold', text: 'Ungkapan jujur' }),
                        `“${output.hardest_stage.reason}”`,
                    ) : null,
                    el('div', { class: 'flex items-center gap-2 pt-1 text-[13px] text-latte font-semibold' }, icon('timer', 16), 'Lama pengerjaan output: ', el('strong', { class: 'text-espresso', text: output.duration_text || '-' })),
                ) : null,
                output.ideal ? el('div', { class: 'bg-paper p-6 rounded-3xl toon flex flex-col gap-3' },
                    el('span', { class: 'self-start inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-[#E4F7EC] border border-[#27AE60] text-[#1E7E44] text-[12px] font-extrabold' }, icon('auto_awesome', 15), 'Ekspektasi ideal'),
                    el('p', { class: 'text-[17px] text-espresso font-semibold leading-snug', text: `“${output.ideal}”` }),
                    (output.ideal_tags || []).length ? el('div', { class: 'flex flex-wrap gap-2' }, output.ideal_tags.map((tag) => el('span', { class: 'px-2.5 py-1 rounded-full bg-[#FFF0D4] border border-[#E2B77B] text-[12px] text-cocoa font-bold', text: `✨ ${tag}` }))) : null,
                ) : null,
            ]),
            el('div', { class: 'bg-cocoa p-6 rounded-3xl border-2 border-espresso flex flex-col gap-4', style: 'box-shadow:4px 4px 0 #311906' },
                el('div', { class: 'flex items-center gap-3' },
                    el('div', { class: 'w-11 h-11 rounded-2xl bg-butter border-2 border-espresso flex items-center justify-center text-xl', text: '🔄' }),
                    el('div', {}, el('span', { class: 'text-[11px] uppercase tracking-wider text-honey font-extrabold', text: 'Langkah 12' }), el('h4', { class: 'text-[19px] text-cream font-extrabold', text: 'Mau petakan output lainnya?' })),
                ),
                el('p', { class: 'text-[#E8D5C4] font-medium', text: `Kalau masih ada output kerja di ${session.unit.name} yang ingin didokumentasikan, mulai sesi baru kapan saja.` }),
                el('button', { type: 'button', class: 'w-full flex items-center justify-center gap-2 py-3.5 px-4 rounded-2xl bg-[#F39C12] hover:bg-[#E67E22] text-cream font-extrabold border-2 border-espresso toon-press', style: 'box-shadow:3px 3px 0 #2D1603', onclick: () => restartWith(session) }, el('span', { text: '➕' }), 'Petakan output lain (sesi baru)'),
            ),
        );

        const needs = (summary.needs || []).slice(0, 6);
        const firstStages = outputs.length ? outputs[0].stages : [];
        const main = el('div', { class: 'lg:col-span-7 flex flex-col gap-5' },
            el('div', { class: 'bg-paper p-6 sm:p-8 rounded-3xl toon relative overflow-hidden' },
                el('div', { class: 'absolute -right-6 -top-6 w-36 h-36 rounded-full unit-soft opacity-70 pointer-events-none' }),
                el('div', { class: 'relative flex flex-col sm:flex-row items-start sm:items-center gap-6' },
                    el('div', { class: 'relative shrink-0' },
                        el('div', { class: 'w-20 h-20 rounded-2xl bg-butter toon flex items-center justify-center text-4xl -rotate-2', text: session.unit.emoji }),
                        el('div', { class: 'absolute -bottom-2 -right-2 bg-[#F39C12] w-8 h-8 rounded-full flex items-center justify-center toon-sm', text: '🎉' }),
                    ),
                    el('div', { class: 'flex flex-col gap-1.5' },
                        el('span', { class: 'self-start inline-flex items-center gap-1.5 px-3 py-0.5 rounded-full bg-[#FFE5D3] border border-[#E08337] text-[#944A00] text-[12px] font-extrabold', text: '⭐ Penyelesaian FGD Personal · SmartID' }),
                        el('h2', { class: 'text-[26px] sm:text-[30px] text-espresso font-extrabold', text: `Terima kasih banyak, ${firstName}! 🎈` }),
                        el('p', { class: 'text-[17px] text-bark leading-relaxed font-medium', text: 'Kontribusimu sangat berharga untuk fondasi transformasi digital SmartID. Cerita dan kendalamu langsung jadi bahan prioritas desain otomasi kami!' }),
                    ),
                ),
            ),
            el('div', { class: 'grid grid-cols-1 sm:grid-cols-2 gap-4' },
                metricCard('📑', '#FFE4A0', totals.outputs_mapped, 'Output', 'Output kunci terpetakan', outputs.map((output) => `“${output.name}”`).join(', ') || '-'),
                metricCard('🐾', '#D5F5E3', totals.stages, 'Tahapan', 'Tahapan terdokumentasi', firstStages.length ? `${firstStages[0].name} → ${firstStages[firstStages.length - 1].name}` : '-'),
                metricCard('📦', '#E8DAEF', (totals.inputs || 0) + (totals.results || 0), 'Item data', 'Kebutuhan data terurai', `${totals.inputs || 0} data masuk · ${totals.results || 0} data keluar`),
                metricCard('⚡', '#FADBD8', totals.bottlenecks, 'Prioritas', 'Bottleneck prioritas', 'Siap ditindaklanjuti tim DT & HR'),
            ),
            outputs.length ? el('div', { class: 'bg-paper p-6 rounded-3xl toon flex flex-col gap-4' },
                el('div', { class: 'flex items-center justify-between gap-2 flex-wrap' },
                    el('span', { class: 'font-extrabold text-espresso flex items-center gap-2' }, el('span', { class: 'text-xl', text: '🗺️' }), 'Snapshot jalur petualangan probis'),
                    el('span', { class: 'px-3 py-1 rounded-full unit-soft border border-cocoa text-[12px] text-espresso font-extrabold', text: session.unit.name }),
                ),
                outputs.map(flowSnapshot),
                needs.length ? el('div', { class: 'flex flex-wrap items-center gap-2' },
                    el('span', { class: 'text-[12px] font-extrabold text-latte', text: '👂 Kebutuhan yang kami dengar:' }),
                    needs.map((need) => el('span', { class: 'px-2.5 py-1 rounded-full bg-mint border border-leaf text-leaf text-[12px] font-bold', text: need.label })),
                ) : null,
                el('div', { class: 'flex items-center gap-2 text-bark font-medium text-sm' }, el('span', { text: '🛡️' }), 'Data tersimpan aman di Unit ', el('strong', { class: 'text-espresso', text: session.unit.name })),
            ) : null,
            el('div', { class: 'flex flex-col sm:flex-row items-stretch gap-3 pt-1' },
                el('button', { type: 'button', class: 'sm:flex-1 py-4 px-6 rounded-2xl bg-cocoa hover:bg-espresso text-cream font-extrabold border-2 border-espresso flex items-center justify-center gap-2.5 toon-press', style: 'box-shadow:4px 4px 0 #2D1603', onclick: () => download(session.id, 'markdown', 'ringkasan-pendampingan.md') }, el('span', { class: 'text-xl', text: '📥' }), 'Unduh ringkasan probis'),
                el('button', { type: 'button', class: 'py-4 px-6 rounded-2xl bg-paper hover:bg-peach text-espresso font-extrabold toon toon-press flex items-center justify-center gap-2', onclick: () => download(session.id, 'json', 'ringkasan-pendampingan.json') }, el('span', { class: 'text-lg', text: '🧾' }), 'Data JSON'),
                el('button', { type: 'button', class: 'py-4 px-6 rounded-2xl text-latte hover:text-espresso hover:bg-peach font-extrabold flex items-center justify-center gap-1.5', onclick: () => showOnboarding() }, 'Kembali ke beranda', icon('chevron_right', 18)),
            ),
        );

        root_.append(
            banner,
            el('div', { class: 'grid grid-cols-1 lg:grid-cols-12 gap-8 items-start' }, reflections, main),
            el('div', { class: 'bg-paper p-6 rounded-3xl toon-sm flex flex-col md:flex-row items-center justify-between gap-5' },
                el('div', { class: 'flex items-center gap-4' },
                    el('div', { class: 'w-12 h-12 rounded-full bg-mint toon-sm flex items-center justify-center text-leaf shrink-0' }, icon('psychology', 24)),
                    el('div', {},
                        el('h4', { class: 'font-extrabold text-espresso', text: 'Apa yang terjadi selanjutnya dengan datamu?' }),
                        el('p', { class: 'text-bark font-medium', text: `Tim Digital Transformation akan mengagregasi seluruh proses kerja ${session.unit.name} untuk merancang prototipe solusi digital yang paling dibutuhkan.` }),
                    ),
                ),
            ),
        );

        showScreen('closing');
        play('celebrate');
    }

    function restartWith(session) {
        showOnboarding().then(() => selectUnit(session.unit.key));
    }

    async function openSession(sessionId, mode) {
        if (app.busy) return;

        app.busy = true;

        try {
            const detail = await api(url(config.sessionUrl, sessionId));
            app.busy = false;

            if (mode === 'resume' && detail.status !== 'completed') await openChat(detail, false);
            else openHistory(detail);
        } catch (error) {
            toast(error.message, 'error');
        } finally {
            app.busy = false;
        }
    }

    function renderResume() {
        const card = ref('resume-card');
        const unfinished = app.sessions.find((session) => session.status !== 'completed');
        setVisible(card, Boolean(unfinished));

        if (!unfinished) return;

        const progress = unfinished.progress || {};
        ref('resume-text').textContent = `${unfinished.unit.name} · Langkah ${progress.step || 1} dari ${TOTAL_STEPS} · ${progress.percent || 0}% selesai`;
        ref('btn-resume').onclick = () => openSession(unfinished.id, 'resume');
        ref('btn-discard').onclick = () => setVisible(card, false);
    }

    function renderHistory() {
        const section = ref('history');
        const list = ref('history-list');
        setVisible(section, app.sessions.length > 0);
        list.replaceChildren(...app.sessions.map((session) => {
            const progress = session.progress || {};
            const completed = session.status === 'completed';
            return el('button', {
                type: 'button',
                class: 'w-full text-left flex items-center gap-3 px-4 py-3 rounded-2xl bg-cream toon-sm toon-press hover:-translate-y-0.5 transition',
                onclick: () => openSession(session.id, 'read'),
            },
                el('div', { class: 'w-11 h-11 rounded-2xl toon-sm flex items-center justify-center text-white shrink-0 -rotate-3', style: `background:${session.unit.color}` }, icon(session.unit.icon, 24)),
                el('div', { class: 'flex-1 min-w-0' },
                    el('div', { class: 'flex items-center gap-2 flex-wrap' },
                        el('span', { class: 'font-extrabold text-cocoa truncate', text: session.unit.name }),
                        el('span', { class: `px-2 py-0.5 rounded-full border border-cocoa text-[11px] font-extrabold ${completed ? 'bg-mint text-leaf' : 'bg-honey text-cocoa'}`, text: STATUS_LABELS[session.status] || session.status }),
                    ),
                    el('p', { class: 'text-[13px] font-semibold text-latte truncate', text: `${progress.output_name || 'Output kerja'} · ${progress.percent || 0}% · ${formatDate(session.updated_at)}` }),
                ),
                el('span', { class: 'flex items-center gap-1 text-[13px] font-extrabold text-rust shrink-0' }, 'Baca riwayat', icon('visibility', 18)),
            );
        }));
    }

    async function showOnboarding() {
        app.session = null;
        showScreen('onboarding');
        applyUnitTheme(app.unit);

        const [units, sessions] = await Promise.allSettled([
            app.units.length ? Promise.resolve({ items: app.units }) : api(config.unitsUrl),
            api(config.sessionsUrl),
        ]);

        if (units.status === 'fulfilled') app.units = units.value.items || [];
        else toast(units.reason.message, 'error');

        app.sessions = sessions.status === 'fulfilled' ? (sessions.value.items || []) : [];

        if (assignedUnit()) {
            app.unit = assignedUnit();
            applyUnitTheme(app.unit);
        }

        renderUnits();
        syncStartButton();
        renderResume();
        renderHistory();
    }

    renderSoundToggles();
    showOnboarding();
}

let audioUnlockBound = false;

window.aiMentoringDesk = function (config) {
    return {
        init() {
            if (!audioUnlockBound) {
                audioUnlockBound = true;
                const unlock = () => {
                    try {
                        const context = audio();

                        if (context && context.state !== 'running') context.resume().catch(() => { });
                    } catch (error) { }
                };
                ['pointerdown', 'keydown', 'touchstart'].forEach((type) => document.addEventListener(type, unlock, { capture: true, passive: true }));
            }

            mountMentoringDesk(this.$el, config);
        },
    };
};
