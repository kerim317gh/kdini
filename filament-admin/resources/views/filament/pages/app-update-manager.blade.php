<x-filament-panels::page>
    <div class="space-y-6" dir="rtl">
        <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <h3 class="text-sm font-bold">تنظیمات آپدیت برنامه</h3>

            <div class="mt-4 grid gap-4 lg:grid-cols-2">
                <x-filament::input.wrapper>
                    <x-filament::input wire:model="app" type="text" placeholder="app" />
                </x-filament::input.wrapper>

                <x-filament::input.wrapper>
                    <x-filament::input wire:model="platform" type="text" placeholder="platform" />
                </x-filament::input.wrapper>

                <x-filament::input.wrapper>
                    <x-filament::input wire:model="version" type="text" placeholder="version" />
                </x-filament::input.wrapper>

                <x-filament::input.wrapper>
                    <x-filament::input wire:model="build" type="text" placeholder="build" />
                </x-filament::input.wrapper>

                <x-filament::input.wrapper>
                    <x-filament::input wire:model="released_at" type="text" placeholder="released_at" />
                </x-filament::input.wrapper>

                <x-filament::input.wrapper>
                    <x-filament::input wire:model="download_url" type="text" placeholder="download_url" dir="ltr" />
                </x-filament::input.wrapper>
            </div>

            <label class="mt-4 inline-flex items-center gap-2 rounded-xl border border-gray-200 px-3 py-2 text-sm dark:border-gray-700">
                <input wire:model="mandatory" type="checkbox" class="rounded border-gray-300 text-primary-500 focus:ring-primary-500">
                آپدیت اجباری باشد
            </label>

            <div class="mt-4">
                <label class="mb-2 block text-xs font-bold text-gray-600">changes (هر خط یک مورد)</label>
                <textarea wire:model="changes_text" rows="8" class="fi-input block w-full rounded-lg border-none bg-transparent text-sm text-gray-950 shadow-sm ring-1 ring-gray-950/10 placeholder:text-gray-400 focus:ring-2 focus:ring-primary-500 dark:text-white dark:ring-white/20" dir="rtl"></textarea>
            </div>

            <div class="mt-4 flex flex-wrap gap-2">
                <x-filament::button color="primary" wire:click="savePayload">ذخیره update.json</x-filament::button>
                <x-filament::button color="gray" wire:click="loadPayload">بازنشانی فرم</x-filament::button>
            </div>
        </section>
    </div>
</x-filament-panels::page>
