@extends('layouts.guest')

@section('title', 'صفحه یافت نشد')

@section('content')
    <div class="text-center">
        <p class="text-6xl font-bold text-primary-600">{{ fa_num(404) }}</p>
        <h1 class="mt-4 text-xl font-semibold">صفحه مورد نظر یافت نشد</h1>
        <p class="mt-2 text-ink-500">آدرس وارد شده وجود ندارد یا جابه‌جا شده است.</p>
        <div class="mt-6">
            <a href="{{ url('/') }}">
                <x-button>بازگشت به خانه</x-button>
            </a>
        </div>
    </div>
@endsection
