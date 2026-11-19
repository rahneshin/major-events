@extends('layouts.app')

@section('title', 'Style Guide')

@section('content')
    <x-page-header title="راهنمای استایل و کامپوننت‌ها" subtitle="فقط در محیط local در دسترس است" />

    <div class="space-y-8">
        {{-- Buttons --}}
        <x-card title="دکمه‌ها">
            <div class="flex flex-wrap gap-3">
                <x-button>اصلی</x-button>
                <x-button variant="secondary">ثانویه</x-button>
                <x-button variant="danger">حذف</x-button>
                <x-button variant="ghost">شبح</x-button>
                <x-button size="sm">کوچک</x-button>
                <x-button size="lg">بزرگ</x-button>
                <x-button disabled>غیرفعال</x-button>
            </div>
        </x-card>

        {{-- Form --}}
        <x-card title="فرم‌ها">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-input label="نام" name="name" placeholder="نام خود را وارد کنید" required />
                <x-input label="شماره موبایل" name="phone" type="tel" placeholder="۰۹۱۲۳۴۵۶۷۸۹" dir="ltr" />
                <x-select label="وضعیت" name="status" placeholder="انتخاب کنید">
                    <option value="draft">پیش‌نویس</option>
                    <option value="active">فعال</option>
                </x-select>
                <x-date-picker label="تاریخ شروع" name="start_date" />
                <div class="sm:col-span-2">
                    <x-textarea label="توضیحات" name="description" />
                </div>
            </div>
        </x-card>

        {{-- Badges --}}
        <x-card title="نشان‌ها">
            <div class="flex flex-wrap gap-2">
                <x-badge>پیش‌فرض</x-badge>
                <x-badge color="primary">اصلی</x-badge>
                <x-badge color="success">موفق</x-badge>
                <x-badge color="warning">هشدار</x-badge>
                <x-badge color="danger">خطر</x-badge>
            </div>
        </x-card>

        {{-- Empty state --}}
        <x-card title="حالت خالی">
            <x-empty-state>
                <x-slot:action>
                    <x-button>ایجاد اولین مورد</x-button>
                </x-slot:action>
            </x-empty-state>
        </x-card>

        {{-- Toast --}}
        <x-card title="پیام‌ها">
            <div class="space-y-3">
                <x-toast type="success">عملیات با موفقیت انجام شد.</x-toast>
                <x-toast type="error">خطایی رخ داده است.</x-toast>
                <x-toast type="warning">توجه: این یک هشدار است.</x-toast>
                <x-toast type="info">این یک پیام اطلاعاتی است.</x-toast>
            </div>
        </x-card>

        {{-- Jalali & Phone --}}
        <x-card title="تاریخ شمسی و شماره موبایل">
            <ul class="space-y-2 text-sm">
                <li>امروز: <strong>{{ fa_num(jalali(now())) }}</strong></li>
                <li>شماره نمونه: <strong dir="ltr">{{ phone('+989123456789') }}</strong></li>
                <li>اعداد فارسی: <strong>{{ fa_num(1234567890) }}</strong></li>
            </ul>
        </x-card>
    </div>
@endsection
