@extends('layouts.guest')

@section('title', 'دسترسی غیرمجاز')

@section('content')
    <div class="text-center">
        <p class="text-6xl font-bold text-danger-500">{{ fa_num(403) }}</p>
        <h1 class="mt-4 text-xl font-semibold">دسترسی غیرمجاز</h1>
        <p class="mt-2 text-gray-500">شما اجازه مشاهده این صفحه را ندارید.</p>
        <div class="mt-6">
            <a href="{{ url('/') }}">
                <x-button>بازگشت به خانه</x-button>
            </a>
        </div>
    </div>
@endsection
