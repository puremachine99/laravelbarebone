<?php

use Livewire\Component;
use Livewire\Attributes\Title;

new #[Title('Appearance settings')] class extends Component {
 //
}; ?>

<section class="w-full">
 @include('partials.settings-heading')

 <x-pages::settings.layout :heading="__('Appearance')" :subheading="__('Update the appearance settings for your account')">
 <div 
 x-data="{ 
 appearance: localStorage.getItem('theme') ||'system',
 setTheme(theme) {
 this.appearance = theme;
 localStorage.setItem('theme', theme);
 this.applyTheme();
 },
 applyTheme() {
 let isDark = this.appearance === 'dark' || (this.appearance === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
 if (isDark) {
 document.documentElement.classList.add('dark');
 document.documentElement.setAttribute('data-theme', 'dark');
 } else {
 document.documentElement.classList.remove('dark');
 document.documentElement.setAttribute('data-theme', 'light');
 }
 }
 }"
 x-init="applyTheme()"
 class="space-y-4"
 >
 <div class="grid grid-cols-3 gap-4">
 <button 
 type="button" 
 @click="setTheme('light')" 
 :class="appearance ==='light' ?'border-primary ring-2 ring-primary/20 bg-primary/5 text-primary' :'border-base-300 text-base-content/80 bg-base-200'" 
 class="flex flex-col items-center gap-2 p-4 border rounded-xl hover:bg-base-300 transition-all font-semibold shadow-sm"
 >
 <x-icon name="o-sun" class="w-6 h-6" />
 <span>{{ __('Light') }}</span>
 </button>
 <button 
 type="button" 
 @click="setTheme('dark')" 
 :class="appearance ==='dark' ?'border-primary ring-2 ring-primary/20 bg-primary/5 text-primary' :'border-base-300 text-base-content/80 bg-base-200'" 
 class="flex flex-col items-center gap-2 p-4 border rounded-xl hover:bg-base-300 transition-all font-semibold shadow-sm"
 >
 <x-icon name="o-moon" class="w-6 h-6" />
 <span>{{ __('Dark') }}</span>
 </button>
 <button 
 type="button" 
 @click="setTheme('system')" 
 :class="appearance ==='system' ?'border-primary ring-2 ring-primary/20 bg-primary/5 text-primary' :'border-base-300 text-base-content/80 bg-base-200'" 
 class="flex flex-col items-center gap-2 p-4 border rounded-xl hover:bg-base-300 transition-all font-semibold shadow-sm"
 >
 <x-icon name="o-computer-desktop" class="w-6 h-6" />
 <span>{{ __('System') }}</span>
 </button>
 </div>
 </div>
 </x-pages::settings.layout>
</section>
