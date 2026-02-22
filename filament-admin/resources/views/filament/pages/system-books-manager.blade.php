<x-filament-panels::page>
    <div class="space-y-6" dir="rtl">
        <section class="kd-card">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h3 class="text-sm font-bold">system_books.json ({{ count($rows) }})</h3>
                <div class="flex flex-wrap items-center gap-2">
                    <x-filament::input.wrapper class="min-w-64">
                        <x-filament::input wire:model.live.debounce.300ms="search" type="text" placeholder="جستجو در عنوان، نسخه، لینک..." />
                    </x-filament::input.wrapper>
                    <x-filament::button color="gray" wire:click="loadRows">بارگذاری مجدد</x-filament::button>
                    <x-filament::button color="primary" wire:click="startCreate">افزودن کتاب</x-filament::button>
                </div>
            </div>

            <div class="kd-table-wrap mt-4 overflow-x-auto">
                <table class="kd-table min-w-full text-right text-xs">
                    <thead class="kd-table-head">
                        <tr>
                            <th class="px-3 py-3 font-bold">#</th>
                            <th class="px-3 py-3 font-bold">id</th>
                            <th class="px-3 py-3 font-bold">title</th>
                            <th class="px-3 py-3 font-bold">version</th>
                            <th class="px-3 py-3 font-bold">url</th>
                            <th class="px-3 py-3 font-bold">عملیات</th>
                        </tr>
                    </thead>
                    <tbody class="kd-table-body">
                        @forelse($this->filteredRows as $row)
                            <tr class="kd-row-hover">
                                <td class="px-3 py-3">{{ $loop->iteration }}</td>
                                <td class="px-3 py-3">{{ $row['id'] ?? '' }}</td>
                                <td class="px-3 py-3 font-semibold">{{ $row['title'] ?? '' }}</td>
                                <td class="px-3 py-3">{{ $row['version'] ?? '' }}</td>
                                <td class="px-3 py-3" dir="ltr">
                                    @if(!empty($row['url']))
                                        <a class="kd-link" href="{{ $row['url'] }}" target="_blank" rel="noreferrer">
                                            {{ \Illuminate\Support\Str::limit((string)$row['url'], 70) }}
                                        </a>
                                    @else
                                        <span class="kd-muted">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3">
                                    <div class="flex flex-wrap gap-1">
                                        <x-filament::button size="xs" color="gray" wire:click="startEdit({{ $row['__index'] }})">ویرایش</x-filament::button>
                                        <x-filament::button size="xs" color="danger" wire:click="removeRow({{ $row['__index'] }})">حذف</x-filament::button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-3 py-8 text-center text-sm kd-muted">داده‌ای پیدا نشد.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        @if($editingIndex !== null || $isCreating)
            <section class="kd-card kd-edit-card">
                <h3 class="text-sm font-bold">{{ $isCreating ? 'افزودن کتاب سیستم جدید' : 'ویرایش کتاب سیستم' }}</h3>

                <div class="mt-4 grid gap-4 lg:grid-cols-2">
                    <div class="space-y-1">
                        <label class="kd-field-label">شناسه (id)</label>
                        <x-filament::input.wrapper>
                            <x-filament::input wire:model="edit.id" type="text" placeholder="مثال: 3" />
                        </x-filament::input.wrapper>
                    </div>

                    <div class="space-y-1">
                        <label class="kd-field-label">نسخه (version)</label>
                        <x-filament::input.wrapper>
                            <x-filament::input wire:model="edit.version" type="text" placeholder="مثال: 1" />
                        </x-filament::input.wrapper>
                    </div>

                    <div class="space-y-1 lg:col-span-2">
                        <label class="kd-field-label">عنوان (title)</label>
                        <x-filament::input.wrapper>
                            <x-filament::input wire:model="edit.title" type="text" placeholder="عنوان کتاب" />
                        </x-filament::input.wrapper>
                    </div>

                    <div class="space-y-1 lg:col-span-2">
                        <label class="kd-field-label">لینک فایل SQL (url)</label>
                        <x-filament::input.wrapper>
                            <x-filament::input wire:model="edit.url" type="text" placeholder="https://..." dir="ltr" />
                        </x-filament::input.wrapper>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    <x-filament::button color="primary" wire:click="saveEdit">{{ $isCreating ? 'ثبت ردیف جدید' : 'ذخیره تغییرات' }}</x-filament::button>
                    <x-filament::button color="gray" wire:click="cancelEdit">انصراف</x-filament::button>
                </div>
            </section>
        @endif
    </div>
</x-filament-panels::page>
