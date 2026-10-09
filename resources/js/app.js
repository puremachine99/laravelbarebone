// Dipakai bareng x-mask:dynamic="$money(...)" di input nominal Rupiah — Alpine mask cuma ngatur tampilan
// (menyisipkan pemisah ribuan), nilai aslinya (buat dikirim ke server) tetap perlu di-strip dulu dari
// pemisahnya. Global biar bisa dipakai di komponen Alpine manapun tanpa nulis ulang.
window.normalizeCurrency = function (value) {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    // "." is the mask's thousands separator, "," is the decimal separator. Dropping
    // everything from the first "," before stripping keeps a stray ",00" from getting
    // concatenated into the integer (e.g. "500.000.000,00" turning into 50000000000).
    const withoutThousands = String(value).replace(/\./g, '');
    const integerPart = withoutThousands.split(',')[0];
    const digits = integerPart.replace(/[^0-9]/g, '');

    return digits === '' ? null : Number(digits);
};

// Backs <x-csv-download-manager> — Chrome-style download popup for the CSV export buttons.
// Kept in app.js rather than inline in the Blade x-data attribute so the HTML-quoting rules
// of an attribute value don't collide with the double quotes this needs for regex/JSON parsing.
window.csvDownloadManager = function () {
    return {
        jobs: [],

        start({ url, label }) {
            const id = `${Date.now()}-${Math.random().toString(36).slice(2)}`;

            this.jobs.push({
                id,
                filename: label,
                status: 'downloading',
                percent: null,
                statusText: 'Menyiapkan...',
            });

            this.run(id, url);
        },

        // Alpine (via @vue/reactivity) only wraps the object *inside* `this.jobs` as
        // reactive — the plain object literal `start()` builds is a separate, non-reactive
        // reference. Mutating that original object later (e.g. `job.percent = X`) changes
        // the value but never notifies the UI. Looking the job up through `this.jobs` on
        // every update reads through the reactive proxy instead, so the bar actually moves.
        patchJob(id, patch) {
            const job = this.jobs.find((j) => j.id === id);
            if (job) Object.assign(job, patch);
            return job;
        },

        async run(id, url) {
            let finalStatus = 'error';

            try {
                const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });

                if (!response.ok) {
                    throw new Error('export failed: ' + response.status);
                }

                const disposition = response.headers.get('Content-Disposition') || '';
                const match = disposition.match(/filename="?([^";]+)"?/);
                const filename = match ? match[1] : null;

                const totalRows = parseInt(response.headers.get('X-Csv-Total-Rows') || '', 10);
                const hasTotal = Number.isFinite(totalRows) && totalRows > 0;

                if (filename) this.patchJob(id, { filename });

                const reader = response.body.getReader();
                const decoder = new TextDecoder();
                const chunks = [];
                let rowsDone = 0;

                // eslint-disable-next-line no-constant-condition
                while (true) {
                    const { done, value } = await reader.read();
                    if (done) break;

                    chunks.push(value);
                    const text = decoder.decode(value, { stream: true });
                    for (let i = 0; i < text.length; i++) {
                        if (text[i] === '\n') rowsDone++;
                    }

                    this.patchJob(id, hasTotal
                        ? {
                              percent: Math.min(99, Math.round((rowsDone / totalRows) * 100)),
                              statusText: `${rowsDone.toLocaleString('id-ID')} / ${totalRows.toLocaleString('id-ID')} baris`,
                          }
                        : { statusText: `${rowsDone.toLocaleString('id-ID')} baris terunduh...` });
                }

                const blob = new Blob(chunks, { type: 'text/csv' });
                const blobUrl = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = blobUrl;
                link.download = filename ?? this.jobs.find((j) => j.id === id)?.filename ?? 'export.csv';
                document.body.appendChild(link);
                link.click();
                link.remove();
                URL.revokeObjectURL(blobUrl);

                finalStatus = 'done';
                this.patchJob(id, { status: 'done', percent: 100, statusText: 'Selesai' });
            } catch (e) {
                this.patchJob(id, { status: 'error', statusText: 'Export gagal, coba lagi.' });
            } finally {
                setTimeout(
                    () => {
                        this.jobs = this.jobs.filter((j) => j.id !== id);
                    },
                    finalStatus === 'error' ? 5000 : 2500,
                );
            }
        },
    };
};

// Backs the AI Center chat box (resources/views/livewire/ai/ai-center.blade.php). Sending a message is a plain
// fetch() to the SSE endpoint rather than a Livewire action, so the answer can be shown as it streams in and the
// Stop button can cut the request off. Once the stream ends, Livewire re-renders the thread from the database.
window.aiCenterChat = function ({ streamUrl, maxChars }) {
    return {
        maxChars,
        input: '',
        quickAction: null,
        pending: null,
        errorMessage: null,
        controller: null,

        get isStreaming() {
            return this.controller !== null;
        },

        get overLimit() {
            return this.input.length > this.maxChars;
        },

        resize() {
            const el = this.$refs.input;
            if (!el) return;
            el.style.height = 'auto';
            el.style.height = Math.min(el.scrollHeight, 160) + 'px';
        },

        scrollToBottom() {
            this.$nextTick(() => {
                const el = this.$refs.thread;
                if (el) el.scrollTop = el.scrollHeight;
            });
        },

        useQuickAction(key, template) {
            this.quickAction = key;
            this.input = template;
            this.errorMessage = null;
            this.$nextTick(() => {
                const el = this.$refs.input;
                el.focus();
                el.setSelectionRange(el.value.length, el.value.length);
                this.resize();
            });
        },

        stop() {
            this.controller?.abort();
        },

        async send(text = null) {
            const message = (text ?? this.input).trim();
            if (message === '' || this.isStreaming) return;

            if (message.length > this.maxChars) {
                this.errorMessage = `Pesan terlalu panjang (maks ${this.maxChars.toLocaleString('id-ID')} karakter).`;
                return;
            }

            const quickAction = text === null ? this.quickAction : null;
            let conversationId = this.$wire.selectedConversationId;
            let refused = false;
            let aborted = false;

            this.errorMessage = null;
            if (text === null) {
                this.input = '';
                this.quickAction = null;
                this.$nextTick(() => this.resize());
            }
            this.pending = { prompt: message, text: '', error: null };
            this.controller = new AbortController();
            this.scrollToBottom();

            try {
                const response = await fetch(streamUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'text/event-stream',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    },
                    body: JSON.stringify({ conversation_id: conversationId, message, quick_action: quickAction }),
                    signal: this.controller.signal,
                });

                if (!response.ok) throw new Error('stream failed: ' + response.status);

                const reader = response.body.getReader();
                const decoder = new TextDecoder();
                let buffer = '';

                // eslint-disable-next-line no-constant-condition
                while (true) {
                    const { done, value } = await reader.read();
                    if (done) break;

                    buffer += decoder.decode(value, { stream: true });

                    let boundary;
                    while ((boundary = buffer.indexOf('\n\n')) !== -1) {
                        const block = buffer.slice(0, boundary);
                        buffer = buffer.slice(boundary + 2);

                        const name = block.match(/^event: (.*)$/m)?.[1];
                        const data = JSON.parse(block.match(/^data: (.*)$/m)?.[1] ?? '{}');

                        if (name === 'conversation') {
                            conversationId = data.id;
                        } else if (name === 'delta') {
                            this.pending.text += data.text;
                            this.scrollToBottom();
                        } else if (name === 'error') {
                            if (['rate_limited', 'input_too_long'].includes(data.code)) {
                                refused = true;
                                this.errorMessage = data.message;
                            } else {
                                this.pending.error = data.message;
                            }
                        }
                    }
                }
            } catch (e) {
                aborted = e.name === 'AbortError';
                if (!aborted) this.pending.error = 'Terjadi gangguan pada layanan AI.';
            } finally {
                this.controller = null;

                if (refused || !conversationId) {
                    // Nothing was saved: put the text back so the user can edit and resend it.
                    if (!refused) this.errorMessage = this.pending.error ?? 'Terjadi gangguan pada layanan AI.';
                    if (text === null) {
                        this.input = message;
                        this.quickAction = quickAction;
                        this.$nextTick(() => this.resize());
                    }
                    this.pending = null;
                } else {
                    // After Stop, give the server a moment to save the reply as "cancelled" before re-rendering.
                    if (aborted) await new Promise((resolve) => setTimeout(resolve, 600));
                    await this.$wire.streamFinished(conversationId);
                    this.pending = null;
                    this.scrollToBottom();
                }
            }
        },
    };
};

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allow your team to quickly build robust real-time web applications.
 */

import './echo';
