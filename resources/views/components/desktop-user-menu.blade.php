<div class="dropdown dropdown-top dropdown-end w-full">
 <div tabindex="0" role="button" class="btn btn-ghost flex items-center justify-between w-full p-2 hover:bg-base-200 rounded-xl normal-case">
 <div class="flex items-center gap-2 text-left">
 <div class="avatar placeholder">
 <div class="bg-base-300 text-base-content/90 rounded-full w-8 h-8 flex items-center justify-center font-bold text-xs">
 <span>{{ auth()->user()->initials() }}</span>
 </div>
 </div>
 <div class="truncate max-w-[140px]">
 <div class="font-bold text-sm truncate text-base-content/90">{{ auth()->user()->name }}</div>
 <div class="text-[10px] text-base-content/50 truncate">{{ auth()->user()->email }}</div>
 </div>
 </div>
 <x-icon name="o-chevron-up-down" class="w-4 h-4 text-base-content/50" />
 </div>
 <ul tabindex="0" class="dropdown-content menu p-2 shadow-xl bg-base-100 border border-base-300 rounded-box w-64 z-50">
 <div class="px-3 py-2 text-base-content/50 text-xs font-semibold">
 {{ __('Manage Account') }}
 </div>
 <li>
 <a href="{{ route('profile.show') }}" wire:navigate class="flex items-center gap-2 text-sm text-base-content/80">
 <x-icon name="o-cog-6-tooth" class="w-4 h-4" />
 <span>{{ __('Settings') }}</span>
 </a>
 </li>
 <div class="divider my-1"></div>
 <li>
    <form id="desktop-logout-form" method="POST" action="{{ route('logout') }}" class="hidden">
      @csrf
    </form>
    <button type="button" onclick="event.preventDefault(); document.getElementById('desktop-logout-form').submit();" class="flex items-center gap-2 text-sm text-rose-600 dark:text-rose-400 w-full text-left">
      <x-icon name="o-arrow-left-on-rectangle" class="w-4 h-4" />
      <span>{{ __('Log out') }}</span>
    </button>
  </li>
 </ul>
</div>
