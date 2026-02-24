<x-filament-panels::page>
    <div class="kd-page-stack" dir="rtl">
        <section class="kd-card kd-edit-card">
            <div class="kd-toolbar">
                <div>
                    <h3 class="text-sm font-bold">ویرایش JSON خام</h3>
                    <p class="mt-1 text-xs kd-muted" dir="ltr">{{ $selectedRelativePath }}</p>
                </div>

                <div class="kd-toolbar-actions">
                    <x-filament::button color="gray" wire:click="reloadSelectedFile">بازخوانی فایل</x-filament::button>
                    <x-filament::button color="primary" wire:click="saveSelectedFile">ذخیره JSON</x-filament::button>
                </div>
            </div>

            <div class="mt-4">
                <textarea
                    wire:model="jsonContent"
                    rows="18"
                    class="kd-textarea kd-textarea--code"
                    dir="ltr"
                    placeholder="{&#10;  &quot;key&quot;: &quot;value&quot;&#10;}"
                ></textarea>
            </div>

            <p class="mt-3 text-xs kd-muted">هنگام ذخیره، JSON اعتبارسنجی و با فرمت استاندارد (pretty) ذخیره می‌شود.</p>
        </section>

        <section class="kd-card">
            <div class="kd-toolbar">
                <h3 class="text-sm font-bold">مدیریت فایل‌های JSON (پوشه json)</h3>
                <div class="kd-toolbar-actions">
                    <x-filament::input.wrapper class="kd-search-wrap">
                        <x-filament::input wire:model.live.debounce.300ms="search" type="text" placeholder="جستجو در نام فایل..." />
                    </x-filament::input.wrapper>
                    <x-filament::button color="gray" wire:click="loadAvailableFiles">بارگذاری مجدد لیست</x-filament::button>
                </div>
            </div>

            <div class="kd-table-wrap mt-4 overflow-x-auto">
                <table class="kd-table kd-table-compact min-w-full text-right text-xs">
                    <thead class="kd-table-head">
                        <tr>
                            <th class="px-3 py-3 font-bold">#</th>
                            <th class="px-3 py-3 font-bold">نام فایل</th>
                            <th class="px-3 py-3 font-bold">حجم</th>
                            <th class="px-3 py-3 font-bold">آخرین تغییر</th>
                            <th class="px-3 py-3 font-bold">عملیات</th>
                        </tr>
                    </thead>
                    <tbody class="kd-table-body">
                        @forelse($this->filteredFiles as $file)
                            <tr class="kd-row-hover">
                                <td class="px-3 py-3">{{ $loop->iteration }}</td>
                                <td class="px-3 py-3 font-semibold" dir="ltr">{{ $file['name'] }}</td>
                                <td class="px-3 py-3">{{ $file['size_human'] }}</td>
                                <td class="px-3 py-3" dir="ltr">{{ $file['modified'] }}</td>
                                <td class="px-3 py-3">
                                    <x-filament::button
                                        size="xs"
                                        :color="$selectedRelativePath === $file['relative'] ? 'primary' : 'gray'"
                                        wire:click="selectFile('{{ $file['relative'] }}')"
                                    >
                                        {{ $selectedRelativePath === $file['relative'] ? 'در حال ویرایش' : 'ویرایش' }}
                                    </x-filament::button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-3 py-8 text-center text-sm kd-muted">فایل JSON پیدا نشد.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-filament-panels::page>
