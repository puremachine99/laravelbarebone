<span
    x-data="{
        channel: null,
        handler: null,
        audio: new Audio('{{ asset('sounds/chat-notification.webm') }}'),
        init() {
            this.handler = () => {
                this.audio.currentTime = 0;
                this.audio.play().catch(() => {});
                $wire.refreshUnread();
            };
            this.channel = Echo.private('chat.{{ auth()->id() }}');
            this.channel.listen('.MessageSent', this.handler);
        },
        destroy() {
            this.channel?.stopListening('.MessageSent', this.handler);
        },
    }"
    class="contents">
    @if ($this->unreadCount > 0)
        <span class="inline-flex items-center justify-center h-5 min-w-5 rounded-full bg-error text-error-content text-[11px] font-bold leading-none tabular-nums {{ $this->unreadCount > 9 ? 'px-1.5' : '' }}">
            {{ $this->unreadCount > 99 ? '99+' : $this->unreadCount }}
        </span>
    @endif
</span>
