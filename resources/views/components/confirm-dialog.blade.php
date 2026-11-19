@props([
    'name' => 'confirm',
    'title' => __('ui.confirm_delete_title'),
    'message' => __('ui.confirm_delete_message'),
    'confirmText' => __('ui.confirm'),
    'cancelText' => __('ui.cancel'),
])

<div
    x-data="{ open: false, callback: null }"
    x-on:confirm-dialog.window="
        open = true;
        callback = $event.detail?.callback ?? null;
    "
    x-show="open"
    class="relative z-50"
    style="display: none;"
>
    <div x-show="open" class="fixed inset-0 bg-black/50"></div>

    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="w-full max-w-sm rounded-xl bg-white p-5 shadow-xl" @click.outside="open = false">
            <h3 class="text-base font-semibold text-gray-900">{{ $title }}</h3>
            <p class="mt-2 text-sm text-gray-600">{{ $message }}</p>

            <div class="mt-5 flex justify-end gap-2">
                <x-button variant="secondary" @click="open = false">{{ $cancelText }}</x-button>
                <x-button variant="danger" @click="
                    if (typeof callback === 'function') callback();
                    open = false;
                ">{{ $confirmText }}</x-button>
            </div>
        </div>
    </div>
</div>
