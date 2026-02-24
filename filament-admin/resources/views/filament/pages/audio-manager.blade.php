<x-filament-panels::page>
    <div class="kd-page-stack" dir="rtl">
        @if($editingIndex !== null || $isCreating)
            <section class="kd-card kd-edit-card">
                <h3 class="text-sm font-bold">{{ $isCreating ? 'افزودن فایل صوتی جدید' : 'ویرایش ردیف '.($editingIndex + 1) }}</h3>
                <p class="mt-1 text-xs kd-muted">برای هر ستون، عنوان فارسی و کلید فنی قرار داده شده تا خطای ویرایش کم شود.</p>

                <div class="mt-4 grid gap-4 lg:grid-cols-2">
                    <div class="space-y-1">
                        <label class="kd-field-label">شناسه کتاب (kotob_id)</label>
                        <x-filament::input.wrapper>
                            <x-filament::input wire:model="edit.kotob_id" type="text" placeholder="مثال: 1201" />
                        </x-filament::input.wrapper>
                    </div>

                    <div class="space-y-1">
                        <label class="kd-field-label">شناسه فصل (chapters_id)</label>
                        <x-filament::input.wrapper>
                            <x-filament::input wire:model="edit.chapters_id" type="text" placeholder="مثال: 7" />
                        </x-filament::input.wrapper>
                    </div>

                    <div class="space-y-1">
                        <label class="kd-field-label">زبان (lang)</label>
                        <x-filament::input.wrapper>
                            <x-filament::input wire:model="edit.lang" type="text" placeholder="fa" />
                        </x-filament::input.wrapper>
                    </div>

                    <div class="space-y-1">
                        <label class="kd-field-label">گوینده (narrator)</label>
                        <x-filament::input.wrapper>
                            <x-filament::input wire:model="edit.narrator" type="text" placeholder="نام گوینده" />
                        </x-filament::input.wrapper>
                    </div>

                    <div class="space-y-1 lg:col-span-2">
                        <label class="kd-field-label">عنوان صوت (title)</label>
                        <x-filament::input.wrapper>
                            <x-filament::input wire:model="edit.title" type="text" placeholder="عنوان فایل صوتی" />
                        </x-filament::input.wrapper>
                    </div>

                    <div class="space-y-1 lg:col-span-2">
                        <label class="kd-field-label">لینک فایل (url)</label>
                        <x-filament::input.wrapper>
                            <x-filament::input wire:model="edit.url" type="text" placeholder="https://..." dir="ltr" />
                        </x-filament::input.wrapper>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    <x-filament::button color="primary" wire:click="saveEdit">{{ $isCreating ? 'ثبت فایل صوتی جدید' : 'ذخیره تغییرات' }}</x-filament::button>
                    <x-filament::button color="gray" wire:click="cancelEdit">انصراف</x-filament::button>
                </div>
            </section>
        @endif

        <section class="kd-card">
            <div class="kd-toolbar">
                <h3 class="text-sm font-bold">فایل‌های صوتی ({{ count($rows) }})</h3>
                <div class="kd-toolbar-actions">
                    <x-filament::input.wrapper class="kd-search-wrap">
                        <x-filament::input wire:model.live.debounce.300ms="search" type="text" placeholder="جستجو در narrator، title، url..." />
                    </x-filament::input.wrapper>
                    <x-filament::button color="gray" wire:click="loadRows">بارگذاری مجدد</x-filament::button>
                    <x-filament::button color="primary" wire:click="startCreate">افزودن فایل صوتی</x-filament::button>
                </div>
            </div>

            <div class="kd-table-wrap mt-4 overflow-x-auto">
                <table class="kd-table kd-table-compact min-w-full text-right text-xs">
                    <thead class="kd-table-head">
                        <tr>
                            <th class="px-3 py-3 font-bold">#</th>
                            <th class="px-3 py-3 font-bold">kotob_id</th>
                            <th class="px-3 py-3 font-bold">chapters_id</th>
                            <th class="px-3 py-3 font-bold">lang</th>
                            <th class="px-3 py-3 font-bold">narrator</th>
                            <th class="px-3 py-3 font-bold">title</th>
                            <th class="px-3 py-3 font-bold">url</th>
                            <th class="px-3 py-3 font-bold">عملیات</th>
                        </tr>
                    </thead>
                    <tbody class="kd-table-body">
                        @forelse($this->filteredRows as $row)
                            <tr class="kd-row-hover">
                                <td class="px-3 py-3">{{ $loop->iteration }}</td>
                                <td class="px-3 py-3">{{ $row['kotob_id'] ?? '' }}</td>
                                <td class="px-3 py-3">{{ $row['chapters_id'] ?? '' }}</td>
                                <td class="px-3 py-3">{{ $row['lang'] ?? '' }}</td>
                                <td class="px-3 py-3">{{ $row['narrator'] ?? '' }}</td>
                                <td class="px-3 py-3">{{ $row['title'] ?? '' }}</td>
                                <td class="px-3 py-3" dir="ltr">
                                    @if(! empty($row['url']))
                                        <a class="kd-link" href="{{ $row['url'] }}" target="_blank" rel="noreferrer">
                                            {{ \Illuminate\Support\Str::limit($row['url'], 48) }}
                                        </a>
                                    @else
                                        <span class="kd-muted">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3">
                                    <x-filament::button size="xs" color="gray" wire:click="startEdit({{ $row['__index'] }})">ویرایش</x-filament::button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-3 py-8 text-center text-sm kd-muted">داده‌ای پیدا نشد.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

    </div>
</x-filament-panels::page>
