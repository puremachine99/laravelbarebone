<div class="flex items-start max-md:flex-col">
 <div class="me-10 w-full pb-4 md:w-[220px]">
 <ul class="menu bg-base-200 border border-base-300 rounded-2xl p-2 w-full space-y-1 shadow-sm">
 <li>
 <a href="{{ route('profile.edit') }}" wire:navigate
 @class(['active' => request()->routeIs('profile.edit')])>
 {{ __('Profile') }}
 </a>
 </li>
 <li>
 <a href="{{ route('security.edit') }}" wire:navigate
 @class(['active' => request()->routeIs('security.edit')])>
 {{ __('Security') }}
 </a>
 </li>
 <li>
 <a href="{{ route('appearance.edit') }}" wire:navigate
 @class(['active' => request()->routeIs('appearance.edit')])>
 {{ __('Appearance') }}
 </a>
 </li>
 </ul>
 </div>

 <div class="divider md:hidden"></div>

 <div class="flex-1 self-stretch max-md:pt-6">
 @if(isset($heading))
 <h2 class="text-xl font-bold text-base-content">{{ $heading }}</h2>
 @endif
 @if(isset($subheading))
 <p class="text-sm text-base-content/50 mt-1">{{ $subheading }}</p>
 @endif

 <div class="mt-5 w-full max-w-lg">
 {{ $slot }}
 </div>
 </div>
</div>
