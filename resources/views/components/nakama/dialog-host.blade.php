{{--
    The one popup used by nakama.alert / confirm / prompt (resources/js/notify.js → nakamaDialogHost), by every
    wire:confirm, and by Livewire's $this->alert() / $this->confirmThen() (App\Concerns\WithNakamaAlerts).
--}}
<div>
    @persist('nakama-dialog')
        <div x-data="nakamaDialogHost()"
            x-on:nakama-dialog.window="open($event.detail)"
            x-on:nakama-alert.window="openFromServer('alert', $event.detail)"
            x-on:nakama-confirm.window="openFromServer('confirm', $event.detail)">
            <dialog x-ref="dialog" class="modal nk-dialog" x-on:cancel.prevent="close(false)"
                :aria-labelledby="current?.title ? 'nk-dialog-title' : null" aria-describedby="nk-dialog-text">
                <template x-if="current">
                    <div class="modal-box nk-dialog__box" :class="'nk-dialog--' + current.type">
                        <span class="nk-dialog__mark" aria-hidden="true"></span>

                        <div class="nk-dialog__icon">
                            <x-nakama.type-icon type="current.type" size="size-18" />
                        </div>

                        <h3 id="nk-dialog-title" class="nk-dialog__title" x-show="current.title" x-text="current.title"></h3>
                        <p id="nk-dialog-text" class="nk-dialog__text" x-show="current.text" x-text="current.text"></p>

                        <template x-if="current.kind === 'prompt'">
                            <div class="nk-dialog__prompt">
                                <p class="text-xs text-base-content/70" x-show="current.expected">
                                    Ketik <code class="font-mono font-semibold" x-text="current.expected"></code> untuk melanjutkan.
                                </p>
                                <input x-ref="input" type="text" class="input input-bordered w-full" autocomplete="off"
                                    x-model="input" :placeholder="current.placeholder || current.expected || ''"
                                    x-on:keydown.enter.prevent="close(true)" />
                            </div>
                        </template>

                        <div class="nk-dialog__actions">
                            <button type="button" x-ref="cancel" class="btn btn-ghost" x-show="current.kind !== 'alert'"
                                x-text="current.cancelLabel" x-on:click="close(false)"></button>
                            <button type="button" x-ref="confirm" class="btn"
                                :class="{ 'btn-error text-white': current.type === 'danger', 'btn-primary': current.type !== 'danger' }"
                                :disabled="! canConfirm" x-text="current.confirmLabel" x-on:click="close(true)"></button>
                        </div>
                    </div>
                </template>
                <div class="modal-backdrop" x-on:click="current?.kind === 'alert' ? close(true) : close(false)"></div>
            </dialog>
        </div>
    @endpersist
</div>
