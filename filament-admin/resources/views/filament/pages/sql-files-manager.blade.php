<x-filament-panels::page>
    <div class="space-y-6" dir="rtl">
        <section class="kd-card p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h3 class="text-sm font-bold">مدیریت فایل‌های SQL (پوشه kotob)</h3>
                <div class="flex flex-wrap items-center gap-2">
                    <x-filament::input.wrapper class="min-w-64">
                        <x-filament::input wire:model.live.debounce.300ms="search" type="text" placeholder="جستجو در نام فایل، book_id ..." />
                    </x-filament::input.wrapper>
                    <x-filament::button color="gray" wire:click="loadFiles">بارگذاری مجدد</x-filament::button>
                </div>
            </div>

            <div class="mt-4 grid gap-3 lg:grid-cols-[1fr_auto]">
                <div class="space-y-1">
                    <label class="kd-field-label">آپلود فایل SQL از مک</label>
                    <input
                        type="file"
                        wire:model="uploadedSqlFile"
                        class="kd-file-input"
                        accept=".sql,.sql.gz,.db,.gz"
                    />
                </div>
                <div class="flex items-end gap-2">
                    <x-filament::button color="primary" wire:click="uploadNewSqlFile">آپلود فایل در kotob</x-filament::button>
                    <span wire:loading wire:target="uploadedSqlFile,uploadNewSqlFile" class="text-xs kd-muted">در حال بارگذاری...</span>
                </div>
            </div>

            <p class="mt-3 text-xs kd-muted">
                نکته: بعد از آپلود/ویرایش فایل SQL، برای انتشار روی GitHub دکمه «ثبت و ارسال تغییرات» را از بخش Git بزن.
            </p>

            <div class="kd-table-wrap mt-4 overflow-x-auto">
                <table class="kd-table min-w-full text-right text-xs">
                    <thead class="kd-table-head">
                        <tr>
                            <th class="px-3 py-3 font-bold">#</th>
                            <th class="px-3 py-3 font-bold">نام فایل</th>
                            <th class="px-3 py-3 font-bold">نوع</th>
                            <th class="px-3 py-3 font-bold">Book ID هدف</th>
                            <th class="px-3 py-3 font-bold">تعداد INSERT</th>
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
                                <td class="px-3 py-3">{{ $file['type'] }}</td>
                                <td class="px-3 py-3">{{ $file['book_id'] !== '' ? $file['book_id'] : '—' }}</td>
                                <td class="px-3 py-3">{{ $file['insert_count'] }}</td>
                                <td class="px-3 py-3">{{ $file['size_human'] }}</td>
                                <td class="px-3 py-3" dir="ltr">{{ $file['modified'] }}</td>
                                <td class="px-3 py-3">
                                    @if($file['editable'])
                                        <x-filament::button size="xs" color="gray" wire:click="startEditFile('{{ $file['relative'] }}')">
                                            ویرایش فایل
                                        </x-filament::button>
                                    @else
                                        <span class="kd-muted">فقط مشاهده/آپلود</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-3 py-8 text-center text-sm kd-muted">فایل SQL در پوشه `kotob` پیدا نشد.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        @if($editingRelativePath !== null)
            <section class="kd-card kd-edit-card p-5">
                <h3 class="text-sm font-bold">ویرایش فایل SQL</h3>
                <p class="mt-1 text-xs kd-muted" dir="ltr">{{ $editingRelativePath }}</p>

                <div class="mt-4 grid gap-4 lg:grid-cols-2">
                    <div class="space-y-1">
                        <label class="kd-field-label">نام فایل</label>
                        <x-filament::input.wrapper>
                            <x-filament::input wire:model="editFileName" type="text" placeholder="book_3.sql" dir="ltr" />
                        </x-filament::input.wrapper>
                    </div>

                    <div class="space-y-1">
                        <label class="kd-field-label">Book ID هدف (DELETE WHERE kotob_id=...)</label>
                        <x-filament::input.wrapper>
                            <x-filament::input wire:model="editBookId" type="text" placeholder="مثال: 3" />
                        </x-filament::input.wrapper>
                    </div>
                </div>

                <div class="mt-4 rounded-xl border border-white/10 bg-black/10 px-3 py-2 text-xs kd-muted">
                    تعداد INSERT INTO content در فایل: <span class="font-bold text-white">{{ $detectedInsertCount }}</span>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    <x-filament::button size="sm" color="gray" :disabled="!$showRawSqlEditor" wire:click="reloadTableFromSqlContent">
                        بازخوانی جدول از SQL خام
                    </x-filament::button>
                    <x-filament::button size="sm" color="gray" wire:click="toggleRawSqlEditor">
                        {{ $showRawSqlEditor ? 'بستن حالت کد خام SQL' : 'نمایش حالت کد خام SQL' }}
                    </x-filament::button>
                </div>

                @if($sqlRowsError !== '')
                    <div class="mt-4 rounded-xl border border-amber-200/35 bg-amber-500/10 px-3 py-2 text-xs text-amber-100">
                        {{ $sqlRowsError }}
                    </div>
                @endif

                @if(count($contentRows) > 0)
                    <div class="kd-table-wrap mt-4 overflow-x-auto">
                        <table class="kd-table min-w-full text-right text-xs">
                            <thead class="kd-table-head">
                                <tr>
                                    <th class="px-3 py-2 font-bold">#</th>
                                    <th class="px-3 py-2 font-bold">chapter_id</th>
                                    <th class="px-3 py-2 font-bold">book_id</th>
                                    @foreach($contentTextFields as $field)
                                        <th class="px-3 py-2 font-bold">{{ $this->labelForTextField($field) }}</th>
                                    @endforeach
                                    <th class="px-3 py-2 font-bold">عملیات</th>
                                </tr>
                            </thead>
                            <tbody class="kd-table-body">
                                @foreach($contentRows as $row)
                                    @php($isActive = $selectedRowIndex === $loop->index)
                                    <tr class="kd-row-hover">
                                        <td class="px-3 py-2">{{ $loop->iteration }}</td>
                                        <td class="px-3 py-2">{{ ($row['chapters_id'] ?? '') !== '' ? $row['chapters_id'] : '—' }}</td>
                                        <td class="px-3 py-2">{{ ($row['kotob_id'] ?? '') !== '' ? $row['kotob_id'] : '—' }}</td>
                                        @foreach($contentTextFields as $field)
                                            <td class="px-3 py-2">
                                                @php($fieldText = (string)($row[$field] ?? ''))
                                                <div class="kd-line-preview">{{ $fieldText !== '' ? \Illuminate\Support\Str::limit($fieldText, 180) : '—' }}</div>
                                            </td>
                                        @endforeach
                                        <td class="px-3 py-2">
                                            <div class="flex flex-wrap gap-1">
                                                @foreach($contentTextFields as $field)
                                                    <x-filament::button size="xs" :color="$isActive && $selectedRowField === $field ? 'primary' : 'gray'" wire:click="selectContentRow({{ $loop->index }}, '{{ $field }}')">
                                                        {{ $this->labelForTextField($field) }}
                                                    </x-filament::button>
                                                @endforeach
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="mt-4 rounded-xl border border-white/10 bg-white/5 px-3 py-4 text-xs kd-muted">
                        برای این فایل ردیف قابل‌نمایش پیدا نشد. اگر فایل ساختار متفاوت دارد، «حالت کد خام SQL» را باز کن.
                    </div>
                @endif

                @if($selectedRowIndex !== null && isset($contentRows[$selectedRowIndex]))
                    @php($activeRow = $contentRows[$selectedRowIndex])

                    <section class="mt-4 rounded-xl border border-white/10 bg-slate-900/25 p-4">
                        <div class="grid gap-2 text-xs lg:grid-cols-4">
                            <div><span class="kd-muted">ردیف:</span> <span class="font-bold text-white">{{ $selectedRowIndex + 1 }}</span></div>
                            <div><span class="kd-muted">chapter_id:</span> <span class="font-bold text-white">{{ ($activeRow['chapters_id'] ?? '') !== '' ? $activeRow['chapters_id'] : '—' }}</span></div>
                            <div><span class="kd-muted">book_id:</span> <span class="font-bold text-white">{{ ($activeRow['kotob_id'] ?? '') !== '' ? $activeRow['kotob_id'] : '—' }}</span></div>
                            <div><span class="kd-muted">فیلد فعال:</span> <span class="font-bold text-white">{{ $this->labelForTextField($selectedRowField) }} ({{ $selectedRowField }})</span></div>
                        </div>

                        <div class="mt-4 grid gap-3 lg:grid-cols-[240px_1fr]">
                            <div class="space-y-1">
                                <label class="kd-field-label">فیلدی که می‌خواهی ویرایش کنی</label>
                                <select class="kd-select" wire:model="selectedRowField" wire:change="changeSelectedRowField($event.target.value)">
                                    @foreach($contentTextFields as $field)
                                        <option value="{{ $field }}">{{ $this->labelForTextField($field) }} ({{ $field }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="space-y-1">
                                <label class="kd-field-label">متن ردیف انتخاب‌شده (راست‌چین)</label>
                                <textarea wire:model="selectedRowText" rows="12" class="kd-textarea kd-textarea-rtl" dir="rtl" placeholder="متن این ردیف را اینجا ویرایش کن..."></textarea>
                            </div>
                        </div>

                        <div class="mt-3 flex flex-wrap gap-2">
                            <x-filament::button color="primary" wire:click="saveSelectedRowText">ثبت متن ردیف در SQL</x-filament::button>
                        </div>
                    </section>
                @endif

                @if($showRawSqlEditor)
                    <div class="mt-4 space-y-1">
                        <label class="kd-field-label">کد خام SQL (حالت پیشرفته)</label>
                        <textarea wire:model="sqlContent" rows="16" class="kd-textarea" dir="ltr" placeholder="BEGIN TRANSACTION; ..."></textarea>
                    </div>
                @endif

                <div class="mt-4 flex flex-wrap gap-2">
                    <x-filament::button color="primary" wire:click="saveSqlFile">ذخیره فایل SQL</x-filament::button>
                    <x-filament::button color="gray" wire:click="cancelEditFile">انصراف</x-filament::button>
                </div>
            </section>
        @endif
    </div>
</x-filament-panels::page>
