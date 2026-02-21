<x-filament-panels::page>
    <div class="space-y-6" dir="rtl">
        <section class="kd-card">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h3 class="text-sm font-bold">کتاب‌ها ({{ count($books) }})</h3>
                <div class="flex flex-wrap items-center gap-2">
                    <x-filament::input.wrapper class="min-w-64">
                        <x-filament::input wire:model.live.debounce.300ms="search" type="text" placeholder="جستجو در عنوان، نسخه، لینک..." />
                    </x-filament::input.wrapper>
                    <x-filament::button color="gray" wire:click="loadBooks">بارگذاری مجدد</x-filament::button>
                    <x-filament::button color="primary" wire:click="startCreate">افزودن کتاب جدید</x-filament::button>
                </div>
            </div>

            <div class="kd-table-wrap mt-4 overflow-x-auto">
                <table class="kd-table min-w-full text-right text-xs">
                    <thead class="kd-table-head">
                        <tr>
                            <th class="px-3 py-3 font-bold">#</th>
                            <th class="px-3 py-3 font-bold">ID</th>
                            <th class="px-3 py-3 font-bold">عنوان</th>
                            <th class="px-3 py-3 font-bold">نسخه</th>
                            <th class="px-3 py-3 font-bold">وضعیت</th>
                            <th class="px-3 py-3 font-bold">لینک</th>
                            <th class="px-3 py-3 font-bold">عملیات</th>
                        </tr>
                    </thead>
                    <tbody class="kd-table-body">
                        @forelse($this->filteredRows as $row)
                            @php
                                $rowIndex = $row['__index'];
                                $url = null;
                                foreach (['sql_download_url', 'download_url', 'url'] as $urlKey) {
                                    if (!empty($row[$urlKey])) {
                                        $url = (string) $row[$urlKey];
                                        break;
                                    }
                                }
                            @endphp
                            <tr class="kd-row-hover">
                                <td class="px-3 py-3">{{ $loop->iteration }}</td>
                                <td class="px-3 py-3">{{ $row['id'] ?? '' }}</td>
                                <td class="px-3 py-3 font-semibold">{{ $row['title'] ?? '' }}</td>
                                <td class="px-3 py-3">{{ $row['version'] ?? '' }}</td>
                                <td class="px-3 py-3">{{ $row['status'] ?? '' }}</td>
                                <td class="px-3 py-3" dir="ltr">
                                    @if($url)
                                        <a class="kd-link" href="{{ $url }}" target="_blank" rel="noreferrer">
                                            {{ \Illuminate\Support\Str::limit($url, 56) }}
                                        </a>
                                    @else
                                        <span class="kd-muted">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3">
                                    <x-filament::button size="xs" color="gray" wire:click="startEdit({{ $rowIndex }})">ویرایش</x-filament::button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-3 py-8 text-center text-sm kd-muted">داده‌ای پیدا نشد.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        @if($editingIndex !== null || $isCreating)
            <section class="kd-card kd-edit-card">
                <h3 class="text-sm font-bold">{{ $isCreating ? 'افزودن کتاب جدید' : 'ویرایش ردیف '.($editingIndex + 1) }}</h3>
                <p class="mt-1 text-xs kd-muted">هر فیلد با عنوان مشخص شده تا دقیقا بدانید چه موردی را تغییر می‌دهید.</p>

                <div class="mt-4 grid gap-4 lg:grid-cols-2">
                    <div class="space-y-1">
                        <label class="kd-field-label">شناسه کتاب (id)</label>
                        <x-filament::input.wrapper>
                            <x-filament::input wire:model="edit.id" type="text" placeholder="مثال: 1201" />
                        </x-filament::input.wrapper>
                    </div>

                    <div class="space-y-1">
                        <label class="kd-field-label">نسخه (version)</label>
                        <x-filament::input.wrapper>
                            <x-filament::input wire:model="edit.version" type="text" placeholder="مثال: 1.0.0" />
                        </x-filament::input.wrapper>
                    </div>

                    <div class="space-y-1 lg:col-span-2">
                        <label class="kd-field-label">عنوان کتاب (title)</label>
                        <x-filament::input.wrapper>
                            <x-filament::input wire:model="edit.title" type="text" placeholder="عنوان کتاب" />
                        </x-filament::input.wrapper>
                    </div>

                    <div class="space-y-1 lg:col-span-2">
                        <label class="kd-field-label">توضیحات کتاب (description)</label>
                        <x-filament::input.wrapper>
                            <textarea wire:model="edit.description" rows="4" placeholder="توضیحات کامل کتاب" class="kd-textarea" dir="rtl"></textarea>
                        </x-filament::input.wrapper>
                    </div>

                    <div class="space-y-1">
                        <label class="kd-field-label">پیش‌فرض بودن (is_default)</label>
                        <x-filament::input.wrapper>
                            <x-filament::input wire:model="edit.is_default" type="text" placeholder="0 یا 1" />
                        </x-filament::input.wrapper>
                    </div>

                    <div class="space-y-1">
                        <label class="kd-field-label">دانلود شده روی دستگاه (is_downloaded_on_device)</label>
                        <x-filament::input.wrapper>
                            <x-filament::input wire:model="edit.is_downloaded_on_device" type="text" placeholder="0 یا 1" />
                        </x-filament::input.wrapper>
                    </div>

                    <div class="space-y-1">
                        <label class="kd-field-label">وضعیت (status)</label>
                        <x-filament::input.wrapper>
                            <x-filament::input wire:model="edit.status" type="text" placeholder="active / inactive" />
                        </x-filament::input.wrapper>
                    </div>

                    <div></div>

                    <div class="space-y-1 lg:col-span-2">
                        <label class="kd-field-label">لینک SQL (sql_download_url)</label>
                        <x-filament::input.wrapper>
                            <x-filament::input wire:model="edit.sql_download_url" type="text" placeholder="https://..." dir="ltr" />
                        </x-filament::input.wrapper>
                    </div>

                    <div class="space-y-1 lg:col-span-2">
                        <label class="kd-field-label">لینک دانلود جایگزین (download_url)</label>
                        <x-filament::input.wrapper>
                            <x-filament::input wire:model="edit.download_url" type="text" placeholder="https://..." dir="ltr" />
                        </x-filament::input.wrapper>
                    </div>

                    <div class="space-y-1 lg:col-span-2">
                        <label class="kd-field-label">لینک عمومی (url)</label>
                        <x-filament::input.wrapper>
                            <x-filament::input wire:model="edit.url" type="text" placeholder="https://..." dir="ltr" />
                        </x-filament::input.wrapper>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    <x-filament::button color="primary" wire:click="saveEdit">{{ $isCreating ? 'ثبت کتاب جدید' : 'ذخیره تغییرات' }}</x-filament::button>
                    <x-filament::button color="gray" wire:click="cancelEdit">انصراف</x-filament::button>
                </div>
            </section>
        @endif
    </div>
</x-filament-panels::page>
