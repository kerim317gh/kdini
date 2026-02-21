<x-filament-panels::page>
    <div class="space-y-6" dir="rtl">
        <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <h3 class="text-sm font-bold">عملیات Git</h3>

            <div class="mt-4 flex flex-wrap items-center gap-2">
                <x-filament::button color="gray" wire:click="refreshOverview">بروزرسانی وضعیت</x-filament::button>
                <x-filament::button color="gray" wire:click="pullRebase">Pull (rebase)</x-filament::button>
                <x-filament::button color="success" wire:click="reorganizeAssets">Reorganize Assets</x-filament::button>
            </div>

            <div class="mt-4 flex flex-wrap items-center gap-2">
                <x-filament::input.wrapper class="min-w-96">
                    <x-filament::input wire:model="commitMessage" type="text" placeholder="پیام کامیت" />
                </x-filament::input.wrapper>
                <x-filament::button color="primary" wire:click="commitAndPush">Commit & Push</x-filament::button>
            </div>
        </section>

        <section class="grid gap-4 lg:grid-cols-3">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <h3 class="text-sm font-bold">وضعیت مخزن</h3>
                <pre class="mt-3 max-h-64 overflow-auto rounded-xl bg-gray-950 p-3 text-xs leading-6 text-cyan-100" dir="ltr">{{ $status }}</pre>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <h3 class="text-sm font-bold">ریموت‌ها</h3>
                <pre class="mt-3 max-h-64 overflow-auto rounded-xl bg-gray-950 p-3 text-xs leading-6 text-cyan-100" dir="ltr">{{ $remotes }}</pre>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <h3 class="text-sm font-bold">آخرین کامیت‌ها</h3>
                <pre class="mt-3 max-h-64 overflow-auto rounded-xl bg-gray-950 p-3 text-xs leading-6 text-cyan-100" dir="ltr">{{ $commits }}</pre>
            </div>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <h3 class="text-sm font-bold">خروجی آخرین عملیات</h3>
            <pre class="mt-3 max-h-80 overflow-auto rounded-xl bg-gray-950 p-3 text-xs leading-6 text-emerald-200" dir="ltr">{{ $output !== '' ? $output : 'هنوز عملیاتی اجرا نشده است.' }}</pre>
        </section>
    </div>
</x-filament-panels::page>
