// Nakama notifications: toasts, alert / confirm / prompt dialogs, and the theme-aware replacement of the browser's
// native confirm() behind wire:confirm. Rendered by <x-nakama.toaster /> and <x-nakama.dialog-host /> in the layouts;
// styled in resources/css/nakama-notify.css with daisyUI tokens, so every theme (and the toon look) applies.
//
//   nakama.success('Tersimpan')                          → toast
//   nakama.toast({ type: 'warning', title, description, timeout, action: { label, onClick } })
//   await nakama.alert({ type: 'success', title, text }) → popup with one button
//   if (await nakama.confirm({ title, text, variant: 'danger', confirmLabel: 'Hapus' })) { … }
//   await nakama.prompt({ title, text, expected: 'HAPUS' }) → typed value, or null when cancelled
//
// From Livewire (PHP): Mary's $this->success()/error()/… toasts are shown by the same toaster, and
// App\Concerns\WithNakamaAlerts adds $this->alert() and $this->confirmThen().

const TOAST_TYPES = ['success', 'error', 'warning', 'info'];
const DEFAULT_TOAST_TIMEOUT = 4000;
const MAX_TOASTS = 5;

const DIALOG_DEFAULTS = {
    alert: { confirmLabel: 'OK' },
    confirm: { confirmLabel: 'Ya, lanjutkan', cancelLabel: 'Batal' },
    prompt: { confirmLabel: 'Konfirmasi', cancelLabel: 'Batal' },
};

const DANGER_WORDS = /\b(hapus|delete|remove|cabut|revoke|nonaktif|kosongkan|purge|reset)\b/i;

const pendingDialogs = new Map();
let dialogSequence = 0;
let toastSequence = 0;

function emit(name, detail) {
    window.dispatchEvent(new CustomEvent(name, { detail }));
}

function toastType(type) {
    return TOAST_TYPES.includes(type) ? type : 'info';
}

function openDialog(kind, options = {}) {
    const id = ++dialogSequence;
    const type = options.type ?? (options.variant === 'danger' ? 'danger' : kind === 'alert' ? 'info' : 'question');

    return new Promise((resolve) => {
        pendingDialogs.set(id, resolve);
        emit('nakama-dialog', {
            id,
            kind,
            type,
            title: options.title ?? (kind === 'alert' ? '' : 'Yakin?'),
            text: options.text ?? '',
            confirmLabel: options.confirmLabel ?? DIALOG_DEFAULTS[kind].confirmLabel,
            cancelLabel: options.cancelLabel ?? DIALOG_DEFAULTS[kind].cancelLabel ?? null,
            expected: options.expected ?? null,
            placeholder: options.placeholder ?? '',
        });
    });
}

window.nakama = {
    toast(options = {}) {
        emit('nakama-toast', {
            type: toastType(options.type),
            title: options.title ?? '',
            description: options.description ?? '',
            timeout: options.timeout ?? DEFAULT_TOAST_TIMEOUT,
            action: options.action ?? null,
        });
    },
    success(title, description = '') {
        this.toast({ type: 'success', title, description });
    },
    error(title, description = '') {
        this.toast({ type: 'error', title, description, timeout: 6000 });
    },
    warning(title, description = '') {
        this.toast({ type: 'warning', title, description, timeout: 5000 });
    },
    info(title, description = '') {
        this.toast({ type: 'info', title, description });
    },
    alert(options = {}) {
        return openDialog('alert', options);
    },
    confirm(options = {}) {
        return openDialog('confirm', typeof options === 'string' ? { text: options } : options);
    },
    prompt(options = {}) {
        return openDialog('prompt', options);
    },
    /**
     * Called by the dialog host when a dialog closes: true / false for alert & confirm, the typed text or null for prompt.
     */
    resolveDialog(id, value) {
        pendingDialogs.get(id)?.(value);
        pendingDialogs.delete(id);
    },
};

// Mary UI's toast() (used by $this->success() & co. in Livewire) now feeds the Nakama toaster.
window.toast = function (payload) {
    emit('mary-toast', payload);
};

/**
 * Alpine component behind <x-nakama.toaster />: a stack of toasts that pause on hover and close on click.
 */
window.nakamaToaster = function () {
    return {
        toasts: [],

        push(toast) {
            const entry = { ...toast, id: ++toastSequence, paused: false, remaining: toast.timeout, startedAt: 0, timer: null };
            this.toasts.push(entry);

            if (this.toasts.length > MAX_TOASTS) {
                this.dismiss(this.toasts[0].id);
            }

            this.schedule(this.toasts[this.toasts.length - 1]);
        },

        pushMary(toast = {}) {
            this.push({
                type: toastType(toast.type),
                title: toast.title ?? '',
                description: toast.description ?? '',
                timeout: toast.timeout ?? DEFAULT_TOAST_TIMEOUT,
                action: null,
            });
        },

        schedule(toast) {
            if (!(toast.timeout > 0)) {
                return;
            }

            toast.startedAt = Date.now();
            toast.timer = setTimeout(() => this.dismiss(toast.id), toast.remaining);
        },

        pause(toast) {
            if (toast.paused || !(toast.timeout > 0)) {
                return;
            }

            clearTimeout(toast.timer);
            toast.remaining -= Date.now() - toast.startedAt;
            toast.paused = true;
        },

        resume(toast) {
            if (!toast.paused) {
                return;
            }

            toast.paused = false;
            this.schedule(toast);
        },

        runAction(toast) {
            toast.action?.onClick?.();
            this.dismiss(toast.id);
        },

        dismiss(id) {
            const toast = this.toasts.find((entry) => entry.id === id);
            clearTimeout(toast?.timer);
            this.toasts = this.toasts.filter((entry) => entry.id !== id);
        },
    };
};

/**
 * Alpine component behind <x-nakama.dialog-host />: shows one dialog at a time on a native <dialog> (focus trap and
 * Esc for free) and queues the rest.
 */
window.nakamaDialogHost = function () {
    return {
        queue: [],
        current: null,
        input: '',

        open(dialog) {
            this.queue.push(dialog);

            if (!this.current) {
                this.next();
            }
        },

        // Livewire's dispatch() sends named parameters, so detail is { type, title, text, … } as given in PHP.
        openFromServer(kind, detail = {}) {
            const options = Array.isArray(detail) ? detail[0] ?? {} : detail;

            if (kind === 'confirm') {
                window.nakama.confirm(options).then((confirmed) => {
                    if (confirmed && options.componentId && options.method) {
                        window.Livewire.find(options.componentId)?.call(options.method, ...(options.params ?? []));
                    }
                });

                return;
            }

            window.nakama.alert(options);
        },

        next() {
            this.current = this.queue.shift() ?? null;
            this.input = '';

            if (!this.current) {
                return;
            }

            this.$nextTick(() => {
                this.$refs.dialog.showModal();
                // Safe default focus: Cancel for a dangerous action, the text field for a prompt, otherwise the main button.
                const target = this.current.kind === 'prompt'
                    ? this.$refs.input
                    : this.current.type === 'danger' ? this.$refs.cancel : this.$refs.confirm;
                target?.focus();
            });
        },

        get canConfirm() {
            return this.current?.kind !== 'prompt' || !this.current.expected || this.input.trim() === this.current.expected;
        },

        close(confirmed) {
            if (!this.current) {
                return;
            }

            if (confirmed && !this.canConfirm) {
                return;
            }

            const { id, kind } = this.current;
            const value = kind === 'prompt' ? (confirmed ? this.input.trim() : null) : kind === 'alert' ? true : confirmed;

            this.$refs.dialog.close();
            this.current = null;
            window.nakama.resolveDialog(id, value);
            setTimeout(() => this.next(), 120);
        },
    };
};

/**
 * Picks the danger style for destructive actions when the element does not say: a red button, or wording like "hapus".
 */
function confirmVariant(el, message) {
    if (el.dataset.confirmVariant) {
        return el.dataset.confirmVariant;
    }

    return el.matches('.text-error, .btn-error') || DANGER_WORDS.test(message) ? 'danger' : 'question';
}

document.addEventListener('livewire:init', () => {
    // Livewire's own wire:confirm handler (window.confirm) is installed first; this runs after it for the same
    // element and replaces it with the themed dialog. Optional: data-confirm-title, data-confirm-button,
    // data-confirm-variant ("danger" | "question") on the element.
    window.Livewire.hook('directive.init', ({ el, directive }) => {
        if (directive.value !== 'confirm') {
            return;
        }

        const message = (directive.expression || 'Yakin?').replaceAll('\\n', '\n');
        const isPrompt = directive.modifiers.includes('prompt');

        el.__livewire_confirm = (action, instead) => {
            // Stop the click now (other handlers on the element); the action runs later, once confirmed.
            instead();

            const [question, expected] = isPrompt ? message.split('|') : [message, null];
            const variant = confirmVariant(el, question);
            const options = {
                title: el.dataset.confirmTitle ?? (variant === 'danger' ? 'Yakin?' : 'Konfirmasi'),
                text: question,
                variant,
                confirmLabel: el.dataset.confirmButton ?? (variant === 'danger' ? 'Ya, lanjutkan' : undefined),
            };

            const decision = isPrompt
                ? window.nakama.prompt({ ...options, expected }).then((value) => value !== null && value === expected)
                : window.nakama.confirm(options);

            decision.then((confirmed) => confirmed && action());
        };
    });

    // Same as Mary's toaster: a failed Livewire request whose JSON carries a toast still shows it.
    window.Livewire.hook('request', ({ fail }) => {
        fail(({ content, preventDefault }) => {
            try {
                const result = JSON.parse(content);

                if (result?.toast) {
                    window.toast(result);
                }

                if (result?.prevent_default === true) {
                    preventDefault();
                }
            } catch (error) {
                // Not JSON: Livewire's own error handling applies.
            }
        });
    });
});
