@extends('layouts.guest')

@section('title', 'خطای سرور')

@section('content')
    <div class="text-center">
        <p class="text-6xl font-bold text-warning-500">{{ fa_num(500) }}</p>
        <h1 class="mt-4 text-xl font-semibold">خطایی رخ داده است</h1>
        <p class="mt-2 text-ink-500">لطفاً چند لحظه دیگر دوباره تلاش کنید.</p>
        <div class="mt-6">
            <a href="{{ url('/') }}">
                <x-button>بازگشت به خانه</x-button>
            </a>
        </div>
    </div>
@endsection
