<x-filament-panels::page>
    <div class="space-y-6" dir="rtl">
        <section class="kd-card">
            <h3 class="text-sm font-bold">عملیات Git</h3>
            <p class="mt-1 text-xs kd-muted">برای اعمال نهایی روی GitHub، بعد از ویرایش‌ها گزینه «ثبت و ارسال تغییرات» را بزنید.</p>

            <div class="mt-4 flex flex-wrap items-center gap-2">
                <x-filament::button color="gray" wire:click="refreshOverview">بروزرسانی وضعیت</x-filament::button>
                <x-filament::button color="gray" wire:click="pullRebase">دریافت تغییرات (Pull با Rebase)</x-filament::button>
                <x-filament::button color="success" wire:click="reorganizeAssets">مرتب‌سازی فایل‌های مخزن</x-filament::button>
            </div>

            <div class="mt-4 flex flex-wrap items-center gap-2">
                <x-filament::input.wrapper class="min-w-96">
                    <x-filament::input wire:model="commitMessage" type="text" placeholder="پیام کامیت" />
                </x-filament::input.wrapper>
                <x-filament::button color="primary" wire:click="commitAndPush">ثبت و ارسال تغییرات (Commit + Push)</x-filament::button>
            </div>
            <p class="mt-2 text-xs kd-muted">این دکمه همه تغییرات جاری مخزن را با `git add -A` وارد کامیت می‌کند.</p>
        </section>

        <section class="grid gap-4 lg:grid-cols-3">
            <div class="kd-card">
                <h3 class="text-sm font-bold">وضعیت مخزن</h3>
                <pre class="kd-console mt-3 max-h-64 overflow-auto p-3 text-xs leading-6" dir="ltr">{{ $status }}</pre>
            </div>
            <div class="kd-card">
                <h3 class="text-sm font-bold">ریموت‌ها</h3>
                <pre class="kd-console mt-3 max-h-64 overflow-auto p-3 text-xs leading-6" dir="ltr">{{ $remotes }}</pre>
            </div>
            <div class="kd-card">
                <h3 class="text-sm font-bold">آخرین کامیت‌ها</h3>
                <pre class="kd-console mt-3 max-h-64 overflow-auto p-3 text-xs leading-6" dir="ltr">{{ $commits }}</pre>
            </div>
        </section>

        <section class="kd-card">
            <h3 class="text-sm font-bold">خروجی آخرین عملیات</h3>
            <pre class="kd-console mt-3 max-h-80 overflow-auto p-3 text-xs leading-6" dir="ltr">{{ $output !== '' ? $output : 'هنوز عملیاتی اجرا نشده است.' }}</pre>
        </section>
    </div>
</x-filament-panels::page>
