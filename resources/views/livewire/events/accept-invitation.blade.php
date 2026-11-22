<div class="w-full max-w-sm text-center">
    <h1 class="text-xl font-bold text-gray-900">{{ __('hr.invite_page_title') }}</h1>

    @if ($eventTitle)
        <p class="mt-2 text-sm text-gray-600">{{ $eventTitle }}</p>
    @endif

    @if ($error)
        <p class="mt-5 rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $error }}</p>
        <a href="{{ route('auth.login') }}" wire:navigate class="mt-5 inline-block text-sm font-semibold text-indigo-600 hover:underline">
            {{ __('hr.invite_go_login') }}
        </a>
    @endif
</div>
