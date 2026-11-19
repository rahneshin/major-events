@extends('layouts.guest')

@section('title', 'صفحه منقضی شده')

@section('content')
    <div class="text-center">
        <p class="text-6xl font-bold text-warning-500">{{ fa_num(419) }}</p>
        <h1 class="mt-4 text-xl font-semibold">نشست شما منقضی شده است</h1>
        <p class="mt-2 text-gray-500">لطفاً صفحه را رفرش کنید و دوباره تلاش نمایید.</p>
        <div class="mt-6">
            <a href="{{ url()->previous() }}">
                <x-button>تلاش مجدد</x-button>
            </a>
        </div>
    </div>
@endsection
