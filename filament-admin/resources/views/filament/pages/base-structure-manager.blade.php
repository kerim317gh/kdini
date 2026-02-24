<x-filament-panels::page>
    <div class="kd-page-stack" dir="rtl">
        <section class="kd-card">
            <h3 class="text-sm font-bold">مشخصات پایه فایل base_structure.json</h3>
            <div class="mt-4 grid gap-4 lg:grid-cols-[220px_220px_auto]">
                <div class="space-y-1">
                    <label class="kd-field-label">schema_version</label>
                    <x-filament::input.wrapper>
                        <x-filament::input wire:model="schemaVersion" type="text" dir="ltr" />
                    </x-filament::input.wrapper>
                </div>
                <div class="space-y-1">
                    <label class="kd-field-label">data_version</label>
                    <x-filament::input.wrapper>
                        <x-filament::input wire:model="dataVersion" type="text" dir="ltr" />
                    </x-filament::input.wrapper>
                </div>
                <div class="flex items-end">
                    <x-filament::button color="primary" wire:click="saveMetadata">ذخیره مشخصات</x-filament::button>
                </div>
            </div>
        </section>

        <section class="kd-card">
            <div class="kd-toolbar">
                <h3 class="text-sm font-bold">مدیریت ردیف‌ها</h3>
                <div class="kd-toolbar-actions">
                    <x-filament::button color="gray" wire:click="switchSection('categories')" :outlined="$section !== 'categories'">دسته‌بندی‌ها</x-filament::button>
                    <x-filament::button color="gray" wire:click="switchSection('chapters')" :outlined="$section !== 'chapters'">فصل‌ها</x-filament::button>
                    <x-filament::button color="primary" wire:click="startCreate">افزودن {{ $section === 'categories' ? 'دسته‌بندی' : 'فصل' }}</x-filament::button>
                    <x-filament::button color="gray" wire:click="loadStructure">بارگذاری مجدد</x-filament::button>
                </div>
            </div>

            <div class="mt-4 kd-toolbar-actions">
                <x-filament::input.wrapper class="kd-search-wrap">
                    <x-filament::input wire:model.live.debounce.300ms="search" type="text" placeholder="جستجو..." />
                </x-filament::input.wrapper>
                <span class="text-xs kd-muted">تعداد: {{ count($this->filteredRows) }}</span>
                @if($editingIndex !== null || $isCreating)
                    <span class="rounded-lg border border-primary-300/40 bg-primary-500/15 px-2 py-1 text-[11px] font-bold text-primary-200">
                        حالت ویرایش درجا فعال است
                    </span>
                @endif
            </div>

            <div class="kd-table-wrap mt-4 overflow-x-auto">
                <table class="kd-table kd-table-compact min-w-full text-right text-xs">
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
                                <th class="px-3 py-3 font-bold">زبان‌ها</th>
                                <th class="px-3 py-3 font-bold">icon</th>
                                <th class="px-3 py-3 font-bold">عملیات</th>
                            </tr>
                        @endif
                    </thead>
                    <tbody class="kd-table-body">
                        @php
                            $chapterLangFields = [
                                'title_fa' => 'عنوان فارسی',
                                'title_en' => 'عنوان انگلیسی',
                                'title_tr' => 'عنوان ترکی',
                                'title_ru' => 'عنوان روسی',
                                'title_tk' => 'عنوان ترکمنی',
                            ];
                            $chapterColspan = 8;
                        @endphp

                        @if($isCreating)
                            <tr class="kd-inline-edit-row">
                                <td class="px-3 py-3 text-center font-bold text-primary-200">جدید</td>
                                @if($section === 'categories')
                                    <td class="px-3 py-3"><input wire:model="edit.id" type="text" class="kd-inline-input" placeholder="id" /></td>
                                    <td class="px-3 py-3"><input wire:model="edit.title" type="text" class="kd-inline-input kd-inline-input--wide" placeholder="title" /></td>
                                    <td class="px-3 py-3"><input wire:model="edit.sort_order" type="text" class="kd-inline-input" placeholder="sort" /></td>
                                    <td class="px-3 py-3"><input wire:model="edit.icon" type="text" class="kd-inline-input" placeholder="icon" /></td>
                                @else
                                    <td class="px-3 py-3"><input wire:model="edit.id" type="text" class="kd-inline-input" placeholder="id" /></td>
                                    <td class="px-3 py-3"><input wire:model="edit.category_id" type="text" class="kd-inline-input" placeholder="category" /></td>
                                    <td class="px-3 py-3"><input wire:model="edit.parent_id" type="text" class="kd-inline-input" placeholder="parent" /></td>
                                    <td class="px-3 py-3"><input wire:model="edit.title" type="text" class="kd-inline-input kd-inline-input--wide" placeholder="title" /></td>
                                    <td class="px-3 py-3 text-[11px] kd-muted">فیلدهای زبان در پنل پایین</td>
                                    <td class="px-3 py-3"><input wire:model="edit.icon" type="text" class="kd-inline-input" placeholder="icon" /></td>
                                @endif
                                <td class="px-3 py-3">
                                    <div class="flex flex-wrap gap-1">
                                        <x-filament::button size="xs" color="primary" wire:click="saveEdit">ثبت</x-filament::button>
                                        <x-filament::button size="xs" color="gray" wire:click="cancelEdit">لغو</x-filament::button>
                                    </div>
                                </td>
                            </tr>

                            @if($section === 'chapters')
                                <tr class="kd-inline-edit-row">
                                    <td colspan="{{ $chapterColspan }}" class="px-3 py-3">
                                        <div class="kd-inline-subpanel">
                                            <div class="kd-inline-subgrid">
                                                @foreach($chapterLangFields as $field => $label)
                                                    <div class="space-y-1">
                                                        <label class="kd-field-label">{{ $label }} ({{ $field }})</label>
                                                        <input wire:model="edit.{{ $field }}" type="text" class="kd-inline-input kd-inline-input--wide" placeholder="{{ $field }}" />
                                                    </div>
                                                @endforeach
                                            </div>

                                            <div class="mt-3 flex flex-wrap gap-1">
                                                <x-filament::button size="xs" color="primary" wire:click="saveEdit">ذخیره فصل</x-filament::button>
                                                <x-filament::button size="xs" color="gray" wire:click="cancelEdit">انصراف</x-filament::button>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @endif

                        @forelse($this->filteredRows as $row)
                            @php
                                $rowIndex = (int) ($row['__index'] ?? $loop->index);
                                $isEditing = ! $isCreating && $editingIndex === $rowIndex;
                            @endphp
                            <tr class="{{ $isEditing ? 'kd-inline-edit-row' : 'kd-row-hover' }}">
                                <td class="px-3 py-3">{{ $loop->iteration }}</td>

                                @if($isEditing)
                                    @if($section === 'categories')
                                        <td class="px-3 py-3"><input wire:model="edit.id" type="text" class="kd-inline-input" /></td>
                                        <td class="px-3 py-3"><input wire:model="edit.title" type="text" class="kd-inline-input kd-inline-input--wide" /></td>
                                        <td class="px-3 py-3"><input wire:model="edit.sort_order" type="text" class="kd-inline-input" /></td>
                                        <td class="px-3 py-3"><input wire:model="edit.icon" type="text" class="kd-inline-input" /></td>
                                    @else
                                        <td class="px-3 py-3"><input wire:model="edit.id" type="text" class="kd-inline-input" /></td>
                                        <td class="px-3 py-3"><input wire:model="edit.category_id" type="text" class="kd-inline-input" /></td>
                                        <td class="px-3 py-3"><input wire:model="edit.parent_id" type="text" class="kd-inline-input" /></td>
                                        <td class="px-3 py-3"><input wire:model="edit.title" type="text" class="kd-inline-input kd-inline-input--wide" /></td>
                                        <td class="px-3 py-3 text-[11px] kd-muted">فیلدهای زبان در پنل پایین</td>
                                        <td class="px-3 py-3"><input wire:model="edit.icon" type="text" class="kd-inline-input" /></td>
                                    @endif

                                    <td class="px-3 py-3">
                                        <div class="flex flex-wrap gap-1">
                                            <x-filament::button size="xs" color="primary" wire:click="saveEdit">ذخیره</x-filament::button>
                                            <x-filament::button size="xs" color="gray" wire:click="cancelEdit">انصراف</x-filament::button>
                                        </div>
                                    </td>
                                @else
                                    <td class="px-3 py-3">{{ $row['id'] ?? '' }}</td>

                                    @if($section === 'categories')
                                        <td class="px-3 py-3"><div class="kd-cell-preview">{{ $row['title'] ?? '' }}</div></td>
                                        <td class="px-3 py-3">{{ $row['sort_order'] ?? '' }}</td>
                                        <td class="px-3 py-3"><div class="kd-cell-preview">{{ $row['icon'] ?? '' }}</div></td>
                                    @else
                                        <td class="px-3 py-3">{{ $row['category_id'] ?? '' }}</td>
                                        <td class="px-3 py-3">{{ $row['parent_id'] ?? '' }}</td>
                                        <td class="px-3 py-3"><div class="kd-cell-preview">{{ $row['__title_display'] ?? ($row['title'] ?? '') }}</div></td>
                                        @php
                                            $langPieces = [];
                                            foreach (array_keys($chapterLangFields) as $langKey) {
                                                $langValue = trim((string) ($row[$langKey] ?? ''));
                                                if ($langValue !== '') {
                                                    $langPieces[] = $langValue;
                                                }
                                            }
                                            $langSummary = implode(' | ', array_slice($langPieces, 0, 2));
                                        @endphp
                                        <td class="px-3 py-3"><div class="kd-cell-preview">{{ $langSummary !== '' ? $langSummary : '—' }}</div></td>
                                        <td class="px-3 py-3"><div class="kd-cell-preview">{{ $row['icon'] ?? '' }}</div></td>
                                    @endif

                                    <td class="px-3 py-3">
                                        <div class="flex flex-wrap gap-1">
                                            <x-filament::button size="xs" color="gray" wire:click="startEdit({{ $rowIndex }})">ویرایش</x-filament::button>
                                            <x-filament::button size="xs" color="danger" wire:click="removeRow({{ $rowIndex }})">حذف</x-filament::button>
                                            @if($section === 'chapters' && is_numeric($row['id'] ?? null) && is_numeric($row['category_id'] ?? null))
                                                <x-filament::button size="xs" color="info" wire:click="startCreateChild({{ (int) $row['id'] }}, {{ (int) $row['category_id'] }})">
                                                    افزودن زیر‌فصل
                                                </x-filament::button>
                                            @endif
                                        </div>
                                    </td>
                                @endif
                            </tr>

                            @if($isEditing && $section === 'chapters')
                                <tr class="kd-inline-edit-row">
                                    <td colspan="{{ $chapterColspan }}" class="px-3 py-3">
                                        <div class="kd-inline-subpanel">
                                            <div class="kd-inline-subgrid">
                                                @foreach($chapterLangFields as $field => $label)
                                                    <div class="space-y-1">
                                                        <label class="kd-field-label">{{ $label }} ({{ $field }})</label>
                                                        <input wire:model="edit.{{ $field }}" type="text" class="kd-inline-input kd-inline-input--wide" />
                                                    </div>
                                                @endforeach
                                            </div>

                                            <div class="mt-3 flex flex-wrap gap-1">
                                                <x-filament::button size="xs" color="primary" wire:click="saveEdit">ذخیره فصل</x-filament::button>
                                                <x-filament::button size="xs" color="gray" wire:click="cancelEdit">انصراف</x-filament::button>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr>
                                <td colspan="{{ $section === 'categories' ? 6 : 8 }}" class="px-3 py-8 text-center text-sm kd-muted">داده‌ای پیدا نشد.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-filament-panels::page>
