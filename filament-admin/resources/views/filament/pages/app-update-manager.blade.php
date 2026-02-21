<x-filament-panels::page>
    <div class="space-y-6" dir="rtl">
        <section class="kd-card">
            <h3 class="text-sm font-bold">تنظیمات آپدیت برنامه</h3>
            <p class="mt-1 text-xs kd-muted">این بخش روی فایل `update/update.json` اعمال می‌شود.</p>

            <div class="mt-4 grid gap-4 lg:grid-cols-2">
                <div class="space-y-1">
                    <label class="kd-field-label">نام برنامه (app)</label>
                    <x-filament::input.wrapper>
                        <x-filament::input wire:model="app" type="text" placeholder="kdini" />
                    </x-filament::input.wrapper>
                </div>

                <div class="space-y-1">
                    <label class="kd-field-label">پلتفرم (platform)</label>
                    <x-filament::input.wrapper>
                        <x-filament::input wire:model="platform" type="text" placeholder="android / ios" />
                    </x-filament::input.wrapper>
                </div>

                <div class="space-y-1">
                    <label class="kd-field-label">نسخه (version)</label>
                    <x-filament::input.wrapper>
                        <x-filament::input wire:model="version" type="text" placeholder="مثال: 2.3.1" />
                    </x-filament::input.wrapper>
                </div>

                <div class="space-y-1">
                    <label class="kd-field-label">بیلد (build)</label>
                    <x-filament::input.wrapper>
                        <x-filament::input wire:model="build" type="text" placeholder="مثال: 231" />
                    </x-filament::input.wrapper>
                </div>

                <div class="space-y-1">
                    <label class="kd-field-label">تاریخ انتشار (released_at)</label>
                    <x-filament::input.wrapper>
                        <x-filament::input wire:model="released_at" type="text" placeholder="2026-02-21T12:00:00Z" />
                    </x-filament::input.wrapper>
                </div>

                <div class="space-y-1">
                    <label class="kd-field-label">لینک دانلود (download_url)</label>
                    <x-filament::input.wrapper>
                        <x-filament::input wire:model="download_url" type="text" placeholder="https://..." dir="ltr" />
                    </x-filament::input.wrapper>
                </div>
            </div>

            <label class="kd-checkbox mt-4 inline-flex items-center gap-2 px-3 py-2 text-sm">
                <input wire:model="mandatory" type="checkbox" class="rounded border-gray-300 text-primary-500 focus:ring-primary-500">
                آپدیت اجباری باشد
            </label>

            <div class="mt-4">
                <label class="mb-2 block kd-field-label">تغییرات (changes) - هر خط یک مورد</label>
                <textarea wire:model="changes_text" rows="8" class="kd-textarea" dir="rtl" placeholder="بهبود پایداری برنامه&#10;افزوده شدن محتوای جدید"></textarea>
            </div>

            <div class="mt-4 flex flex-wrap gap-2">
                <x-filament::button color="primary" wire:click="savePayload">ذخیره update.json</x-filament::button>
                <x-filament::button color="gray" wire:click="loadPayload">بازنشانی فرم</x-filament::button>
            </div>
        </section>
    </div>
</x-filament-panels::page>
