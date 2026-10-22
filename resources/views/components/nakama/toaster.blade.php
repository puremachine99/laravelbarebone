{{--
    Toast stack for the whole app (resources/js/notify.js → nakamaToaster). Shows nakama.toast(…) and the toasts of
    Mary's $this->success()/error()/warning()/info() in Livewire. Persisted so toasts survive wire:navigate.
--}}
<div>
    @persist('nakama-toaster')
        <div x-data="nakamaToaster()"
            x-on:nakama-toast.window="push($event.detail)"
            x-on:mary-toast.window="pushMary($event.detail.toast)"
            class="nk-toaster" role="region" aria-label="Notifikasi">
            <template x-for="toast in toasts" :key="toast.id">
                <div class="nk-toast alert" :class="'nk-toast--' + toast.type + ' alert-' + toast.type"
                    :role="toast.type === 'error' ? 'alert' : 'status'"
                    x-on:mouseenter="pause(toast)" x-on:mouseleave="resume(toast)">
                    <x-nakama.type-icon type="toast.type" size="size-12" class="nk-toast__icon" />
                    <div class="nk-toast__body">
                        <p class="nk-toast__title" x-text="toast.title"></p>
                        <p class="nk-toast__description" x-show="toast.description" x-text="toast.description"></p>
                        <button type="button" class="btn btn-xs mt-2" x-show="toast.action" x-text="toast.action?.label"
                            x-on:click="runAction(toast)"></button>
                    </div>
                    <button type="button" class="nk-toast__close btn btn-ghost btn-xs btn-circle" x-on:click="dismiss(toast.id)" aria-label="Tutup notifikasi">
                        <x-icon name="o-x-mark" class="size-4" />
                    </button>
                    <span class="nk-toast__progress" x-show="toast.timeout > 0"
                        :style="`animation-duration: ${toast.timeout}ms; animation-play-state: ${toast.paused ? 'paused' : 'running'}`"></span>
                </div>
            </template>
        </div>
    @endpersist
</div>
