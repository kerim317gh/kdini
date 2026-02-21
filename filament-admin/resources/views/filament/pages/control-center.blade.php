<x-filament-panels::page>
    <div class="space-y-6" dir="rtl">
        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <div class="rounded-2xl border border-primary-200/70 bg-white p-4 shadow-sm dark:border-primary-500/20 dark:bg-gray-900">
                <p class="text-xs text-gray-500 dark:text-gray-400">کتاب‌ها</p>
                <p class="mt-2 text-2xl font-black text-primary-600 dark:text-primary-400">{{ $counts['books'] }}</p>
            </div>
            <div class="rounded-2xl border border-primary-200/70 bg-white p-4 shadow-sm dark:border-primary-500/20 dark:bg-gray-900">
                <p class="text-xs text-gray-500 dark:text-gray-400">رکوردهای صوتی</p>
                <p class="mt-2 text-2xl font-black text-primary-600 dark:text-primary-400">{{ $counts['audio'] }}</p>
            </div>
            <div class="rounded-2xl border border-primary-200/70 bg-white p-4 shadow-sm dark:border-primary-500/20 dark:bg-gray-900">
                <p class="text-xs text-gray-500 dark:text-gray-400">دسته‌بندی‌ها</p>
                <p class="mt-2 text-2xl font-black text-primary-600 dark:text-primary-400">{{ $counts['categories'] }}</p>
            </div>
            <div class="rounded-2xl border border-primary-200/70 bg-white p-4 shadow-sm dark:border-primary-500/20 dark:bg-gray-900">
                <p class="text-xs text-gray-500 dark:text-gray-400">فصل‌ها</p>
                <p class="mt-2 text-2xl font-black text-primary-600 dark:text-primary-400">{{ $counts['chapters'] }}</p>
            </div>
            <div class="rounded-2xl border border-primary-200/70 bg-white p-4 shadow-sm dark:border-primary-500/20 dark:bg-gray-900">
                <p class="text-xs text-gray-500 dark:text-gray-400">نسخه برنامه</p>
                <p class="mt-2 text-2xl font-black text-primary-600 dark:text-primary-400">{{ $appVersion }}</p>
            </div>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100">دسترسی سریع</h3>
            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <a href="{{ \App\Filament\Pages\BooksManager::getUrl() }}" class="rounded-xl border border-gray-200 px-4 py-3 text-sm font-bold hover:border-primary-300 hover:bg-primary-50/40 dark:border-gray-700 dark:hover:border-primary-500/40">مدیریت کتاب‌ها</a>
                <a href="{{ \App\Filament\Pages\AudioManager::getUrl() }}" class="rounded-xl border border-gray-200 px-4 py-3 text-sm font-bold hover:border-primary-300 hover:bg-primary-50/40 dark:border-gray-700 dark:hover:border-primary-500/40">مدیریت صوت‌ها</a>
                <a href="{{ \App\Filament\Pages\StructureManager::getUrl() }}" class="rounded-xl border border-gray-200 px-4 py-3 text-sm font-bold hover:border-primary-300 hover:bg-primary-50/40 dark:border-gray-700 dark:hover:border-primary-500/40">مدیریت ساختار</a>
                <a href="{{ \App\Filament\Pages\AppUpdateManager::getUrl() }}" class="rounded-xl border border-gray-200 px-4 py-3 text-sm font-bold hover:border-primary-300 hover:bg-primary-50/40 dark:border-gray-700 dark:hover:border-primary-500/40">مدیریت آپدیت برنامه</a>
                <a href="{{ \App\Filament\Pages\GitOpsManager::getUrl() }}" class="rounded-xl border border-gray-200 px-4 py-3 text-sm font-bold hover:border-primary-300 hover:bg-primary-50/40 dark:border-gray-700 dark:hover:border-primary-500/40">کنسول Git</a>
                <button type="button" wire:click="refreshData" class="rounded-xl border border-primary-300 bg-primary-500 px-4 py-3 text-sm font-bold text-white hover:bg-primary-600">بروزرسانی اطلاعات</button>
            </div>
        </section>

        <section class="grid gap-4 lg:grid-cols-3">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <h3 class="text-sm font-bold">عملیات سریع Git</h3>
                <div class="mt-4 flex flex-wrap gap-2">
                    <button type="button" wire:click="quickPull" class="rounded-xl bg-gray-900 px-3 py-2 text-xs font-bold text-white dark:bg-gray-700">Pull</button>
                    <button type="button" wire:click="quickReorganize" class="rounded-xl bg-teal-600 px-3 py-2 text-xs font-bold text-white">Reorganize</button>
                    <button type="button" wire:click="quickPush" class="rounded-xl bg-primary-500 px-3 py-2 text-xs font-bold text-white">Quick Push</button>
                </div>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900 lg:col-span-2">
                <h3 class="text-sm font-bold">آخرین خروجی عملیات</h3>
                <pre class="mt-3 max-h-48 overflow-auto rounded-xl bg-gray-950 p-3 text-xs leading-6 text-emerald-200" dir="ltr">{{ $lastActionOutput !== '' ? $lastActionOutput : 'هنوز عملیاتی اجرا نشده است.' }}</pre>
            </div>
        </section>

        <section class="grid gap-4 lg:grid-cols-3">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <h3 class="text-sm font-bold">وضعیت مخزن</h3>
                <pre class="mt-3 max-h-64 overflow-auto rounded-xl bg-gray-950 p-3 text-xs leading-6 text-cyan-100" dir="ltr">{{ $gitStatus }}</pre>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <h3 class="text-sm font-bold">ریموت‌ها</h3>
                <pre class="mt-3 max-h-64 overflow-auto rounded-xl bg-gray-950 p-3 text-xs leading-6 text-cyan-100" dir="ltr">{{ $gitRemotes }}</pre>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <h3 class="text-sm font-bold">آخرین کامیت‌ها</h3>
                <pre class="mt-3 max-h-64 overflow-auto rounded-xl bg-gray-950 p-3 text-xs leading-6 text-cyan-100" dir="ltr">{{ $gitCommits }}</pre>
            </div>
        </section>
    </div>
</x-filament-panels::page>
