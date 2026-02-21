<x-filament-panels::page>
    <div class="space-y-6" dir="rtl">
        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <div class="kd-stat-card">
                <p class="text-xs kd-muted">کتاب‌ها</p>
                <p class="mt-2 text-2xl font-black text-primary-300">{{ $counts['books'] }}</p>
            </div>
            <div class="kd-stat-card">
                <p class="text-xs kd-muted">رکوردهای صوتی</p>
                <p class="mt-2 text-2xl font-black text-primary-300">{{ $counts['audio'] }}</p>
            </div>
            <div class="kd-stat-card">
                <p class="text-xs kd-muted">دسته‌بندی‌ها</p>
                <p class="mt-2 text-2xl font-black text-primary-300">{{ $counts['categories'] }}</p>
            </div>
            <div class="kd-stat-card">
                <p class="text-xs kd-muted">فصل‌ها</p>
                <p class="mt-2 text-2xl font-black text-primary-300">{{ $counts['chapters'] }}</p>
            </div>
            <div class="kd-stat-card">
                <p class="text-xs kd-muted">نسخه برنامه</p>
                <p class="mt-2 text-2xl font-black text-primary-300">{{ $appVersion }}</p>
            </div>
        </section>

        <section class="kd-card">
            <h3 class="text-sm font-bold">دسترسی سریع</h3>
            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <a href="{{ \App\Filament\Pages\BooksManager::getUrl() }}" class="kd-quick-link">مدیریت کتاب‌ها</a>
                <a href="{{ \App\Filament\Pages\AudioManager::getUrl() }}" class="kd-quick-link">مدیریت صوت‌ها</a>
                <a href="{{ \App\Filament\Pages\StructureManager::getUrl() }}" class="kd-quick-link">مدیریت ساختار</a>
                <a href="{{ \App\Filament\Pages\AppUpdateManager::getUrl() }}" class="kd-quick-link">مدیریت آپدیت برنامه</a>
                <a href="{{ \App\Filament\Pages\GitOpsManager::getUrl() }}" class="kd-quick-link">کنسول Git</a>
                <button type="button" wire:click="refreshData" class="kd-quick-button">بروزرسانی اطلاعات</button>
            </div>
        </section>

        <section class="grid gap-4 lg:grid-cols-3">
            <div class="kd-card">
                <h3 class="text-sm font-bold">عملیات سریع Git</h3>
                <p class="mt-1 text-xs kd-muted">دریافت، مرتب‌سازی و ارسال تغییرات انجام‌شده روی همین مخزن.</p>
                <p class="mt-1 text-xs kd-muted">ارسال سریع تغییرات، همه فایل‌های تغییرکرده را یکجا کامیت و پوش می‌کند.</p>
                <div class="mt-4 flex flex-wrap gap-2">
                    <button type="button" wire:click="quickPull" class="kd-chip-button">دریافت تغییرات (Pull)</button>
                    <button type="button" wire:click="quickReorganize" class="kd-chip-button kd-chip-button--success">مرتب‌سازی فایل‌ها</button>
                    <button type="button" wire:click="quickPush" class="kd-chip-button kd-chip-button--primary">ارسال سریع تغییرات</button>
                </div>
            </div>
            <div class="kd-card lg:col-span-2">
                <h3 class="text-sm font-bold">آخرین خروجی عملیات</h3>
                <pre class="kd-console mt-3 max-h-48 overflow-auto p-3 text-xs leading-6" dir="ltr">{{ $lastActionOutput !== '' ? $lastActionOutput : 'هنوز عملیاتی اجرا نشده است.' }}</pre>
            </div>
        </section>

        <section class="grid gap-4 lg:grid-cols-3">
            <div class="kd-card">
                <h3 class="text-sm font-bold">وضعیت مخزن</h3>
                <pre class="kd-console mt-3 max-h-64 overflow-auto p-3 text-xs leading-6" dir="ltr">{{ $gitStatus }}</pre>
            </div>
            <div class="kd-card">
                <h3 class="text-sm font-bold">ریموت‌ها</h3>
                <pre class="kd-console mt-3 max-h-64 overflow-auto p-3 text-xs leading-6" dir="ltr">{{ $gitRemotes }}</pre>
            </div>
            <div class="kd-card">
                <h3 class="text-sm font-bold">آخرین کامیت‌ها</h3>
                <pre class="kd-console mt-3 max-h-64 overflow-auto p-3 text-xs leading-6" dir="ltr">{{ $gitCommits }}</pre>
            </div>
        </section>
    </div>
</x-filament-panels::page>
