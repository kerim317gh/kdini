<x-filament-panels::page>
    <div class="space-y-6" dir="rtl">
        <section class="kd-card">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h3 class="text-sm font-bold">مدیریت ساختار</h3>
                <div class="flex flex-wrap gap-2">
                    <x-filament::button color="gray" wire:click="switchSection('categories')" :outlined="$section !== 'categories'">دسته‌بندی‌ها</x-filament::button>
                    <x-filament::button color="gray" wire:click="switchSection('chapters')" :outlined="$section !== 'chapters'">فصل‌ها</x-filament::button>
                    <x-filament::button color="primary" wire:click="startCreate">افزودن {{ $section === 'categories' ? 'دسته‌بندی' : 'فصل' }}</x-filament::button>
                    <x-filament::button color="gray" wire:click="loadStructure">بارگذاری مجدد</x-filament::button>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap items-center gap-2">
                <x-filament::input.wrapper class="min-w-72">
                    <x-filament::input wire:model.live.debounce.300ms="search" type="text" placeholder="جستجو در ردیف‌ها..." />
                </x-filament::input.wrapper>
                <span class="text-xs kd-muted">مجموع بخش فعلی: {{ count($this->filteredRows) }}</span>
            </div>

            <div class="kd-table-wrap mt-4 overflow-x-auto">
                <table class="kd-table min-w-full text-right text-xs">
                    <thead class="kd-table-head">
                        @if($section === 'categories')
                            <tr>
                                <th class="px-3 py-3 font-bold">#</th>
                                <th class="px-3 py-3 font-bold">id</th>
                                <th class="px-3 py-3 font-bold">title</th>
                                <th class="px-3 py-3 font-bold">sort_order</th>
                                <th class="px-3 py-3 font-bold">icon</th>
                                <th class="px-3 py-3 font-bold">عملیات</th>
                            </tr>
                        @else
                            <tr>
                                <th class="px-3 py-3 font-bold">#</th>
                                <th class="px-3 py-3 font-bold">id</th>
                                <th class="px-3 py-3 font-bold">category_id</th>
                                <th class="px-3 py-3 font-bold">parent_id</th>
                                <th class="px-3 py-3 font-bold">title</th>
                                <th class="px-3 py-3 font-bold">icon</th>
                                <th class="px-3 py-3 font-bold">عملیات</th>
                            </tr>
                        @endif
                    </thead>
                    <tbody class="kd-table-body">
                        @forelse($this->filteredRows as $row)
                            <tr class="kd-row-hover">
                                <td class="px-3 py-3">{{ $loop->iteration }}</td>
                                <td class="px-3 py-3">{{ $row['id'] ?? '' }}</td>

                                @if($section === 'categories')
                                    <td class="px-3 py-3">{{ $row['title'] ?? '' }}</td>
                                    <td class="px-3 py-3">{{ $row['sort_order'] ?? '' }}</td>
                                    <td class="px-3 py-3">{{ $row['icon'] ?? '' }}</td>
                                @else
                                    <td class="px-3 py-3">{{ $row['category_id'] ?? '' }}</td>
                                    <td class="px-3 py-3">{{ $row['parent_id'] ?? '' }}</td>
                                    <td class="px-3 py-3">{{ $row['__title_display'] ?? ($row['title'] ?? '') }}</td>
                                    <td class="px-3 py-3">{{ $row['icon'] ?? '' }}</td>
                                @endif

                                <td class="px-3 py-3">
                                    <x-filament::button size="xs" color="gray" wire:click="startEdit({{ $row['__index'] }})">ویرایش</x-filament::button>
                                    @if($section === 'chapters' && is_numeric($row['id'] ?? null) && is_numeric($row['category_id'] ?? null))
                                        <x-filament::button
                                            size="xs"
                                            color="info"
                                            wire:click="startCreateChild({{ (int) $row['id'] }}, {{ (int) $row['category_id'] }})"
                                        >
                                            افزودن زیر‌فصل
                                        </x-filament::button>
                                    @endif
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
                <h3 class="text-sm font-bold">
                    {{ $isCreating ? 'افزودن '.($section === 'categories' ? 'دسته‌بندی جدید' : 'فصل جدید') : 'ویرایش '.($section === 'categories' ? 'دسته‌بندی' : 'فصل').' ردیف '.($editingIndex + 1) }}
                </h3>
                <p class="mt-1 text-xs kd-muted">قبل از ذخیره، شناسه‌ها و ارتباط‌ها را بررسی کن تا ساختار برنامه درست بماند.</p>

                <div class="mt-4 grid gap-4 lg:grid-cols-2">
                    <div class="space-y-1">
                        <label class="kd-field-label">شناسه (id)</label>
                        <x-filament::input.wrapper>
                            <x-filament::input wire:model="edit.id" type="text" placeholder="مثال: 10" />
                        </x-filament::input.wrapper>
                    </div>

                    @if($section === 'categories')
                        <div class="space-y-1">
                            <label class="kd-field-label">ترتیب نمایش (sort_order)</label>
                            <x-filament::input.wrapper>
                                <x-filament::input wire:model="edit.sort_order" type="text" placeholder="مثال: 1" />
                            </x-filament::input.wrapper>
                        </div>
                    @else
                        <div class="space-y-1">
                            <label class="kd-field-label">شناسه دسته‌بندی (category_id)</label>
                            <x-filament::input.wrapper>
                                <x-filament::input wire:model="edit.category_id" type="text" placeholder="مثال: 3" />
                            </x-filament::input.wrapper>
                        </div>
                        <div class="space-y-1">
                            <label class="kd-field-label">شناسه والد (parent_id)</label>
                            <x-filament::input.wrapper>
                                <x-filament::input wire:model="edit.parent_id" type="text" placeholder="در صورت نیاز" />
                            </x-filament::input.wrapper>
                        </div>
                    @endif

                    <div class="space-y-1 lg:col-span-2">
                        <label class="kd-field-label">عنوان (title)</label>
                        <x-filament::input.wrapper>
                            <x-filament::input wire:model="edit.title" type="text" placeholder="عنوان این ردیف" />
                        </x-filament::input.wrapper>
                    </div>

                    <div class="space-y-1 lg:col-span-2">
                        <label class="kd-field-label">آیکون (icon)</label>
                        <x-filament::input.wrapper>
                            <x-filament::input wire:model="edit.icon" type="text" placeholder="نام آیکون یا مسیر آن" />
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
