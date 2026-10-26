<button {{ $attributes->merge(['type' => 'submit', 'wire:loading.attr' => 'disabled', 'class' => 'inline-flex items-center justify-center px-4 py-2 bg-rose-700 hover:bg-rose-800 active:bg-rose-900 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-wider shadow-sm hover:shadow focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition ease-in-out duration-150']) }}>
    <svg wire:loading class="animate-spin -ms-1 me-2 h-3.5 w-3.5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
    </svg>
    {{ $slot }}
</button>
