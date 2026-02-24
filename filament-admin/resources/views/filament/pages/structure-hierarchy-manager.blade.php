<x-filament-panels::page>
    <div class="kd-page-stack" dir="rtl">
        <section class="kd-card">
            <div class="kd-toolbar">
                <div>
                    <h3 class="text-sm font-bold">مرورگر یکپارچه دسته‌بندی / فصل / زیرفصل</h3>
                    <p class="mt-1 text-xs kd-muted">
                        انتخاب مرحله‌ای انجام بده و همان رکورد را در منبع اصلی‌اش (DB یا JSON) ویرایش و ذخیره کن.
                    </p>
                </div>

                <div class="kd-toolbar-actions">
                    <x-filament::button size="sm" color="gray" wire:click="loadAll">بارگذاری مجدد</x-filament::button>
                </div>
            </div>

            <div class="mt-3 grid gap-3 lg:grid-cols-[minmax(0,1fr)_auto]">
                <x-filament::input.wrapper class="kd-search-wrap">
                    <x-filament::input wire:model.live.debounce.300ms="search" type="text" placeholder="جستجو در دسته‌بندی‌ها (id / title / source)" />
                </x-filament::input.wrapper>

                <div class="flex items-center gap-2 text-[11px] kd-muted">
                    <span>دسته‌بندی‌ها: {{ count($this->combinedCategories) }}</span>
                    <span>فصل‌ها: {{ count($this->combinedChapters) }}</span>
                    @if($dbAvailable)
                        <span class="rounded-lg border border-emerald-400/40 bg-emerald-500/10 px-2 py-1 text-emerald-200">DB مادر متصل</span>
                    @else
                        <span class="rounded-lg border border-amber-400/40 bg-amber-500/10 px-2 py-1 text-amber-200">DB مادر در دسترس نیست</span>
                    @endif
                </div>
            </div>
        </section>

        <section class="grid gap-3 xl:grid-cols-3">
            <div class="kd-card kd-hierarchy-panel">
                <div class="kd-hierarchy-head">
                    <h4 class="text-xs font-bold">1) همه دسته‌بندی‌ها</h4>
                    <span class="text-[11px] kd-muted">{{ count($this->combinedCategories) }} مورد</span>
                </div>

                <div class="kd-hierarchy-list-wrap">
                    <ul class="kd-hierarchy-list">
                        @forelse($this->combinedCategories as $category)
                            @php
                                $catKey = (string) ($category['__key'] ?? '');
                                $catId = (string) ($category['id'] ?? '');
                                $catTitle = trim((string) ($category['title'] ?? ''));
                                $catSourceLabel = (string) ($category['__source_label'] ?? '');
                                $isActiveCategory = $activeCategoryKey === $catKey;
                                $topLevelCount = $this->countTopLevelChaptersForCategory($catId, (string) ($category['__source'] ?? ''));
                            @endphp
                            <li>
                                <button type="button" wire:click="selectCategory('{{ $catKey }}')" class="kd-hierarchy-item {{ $isActiveCategory ? 'is-active' : '' }}">
                                    <span class="kd-hierarchy-title">{{ $catTitle !== '' ? $catTitle : 'بدون عنوان' }}</span>
                                    <span class="kd-hierarchy-meta">id: {{ $catId !== '' ? $catId : '—' }} | {{ $catSourceLabel }} | {{ $topLevelCount }} فصل سطح اول</span>
                                </button>
                            </li>
                        @empty
                            <li class="text-center text-xs kd-muted py-6">دسته‌بندی یافت نشد.</li>
                        @endforelse
                    </ul>
                </div>
            </div>

            <div class="kd-card kd-hierarchy-panel">
                <div class="kd-hierarchy-head">
                    <h4 class="text-xs font-bold">2) فصل‌های دسته انتخاب‌شده</h4>
                    <span class="text-[11px] kd-muted">{{ count($this->topLevelChapters) }} مورد</span>
                </div>

                <div class="kd-hierarchy-list-wrap">
                    @if($this->activeCategory === null)
                        <div class="py-8 text-center text-xs kd-muted">یک دسته‌بندی انتخاب کن.</div>
                    @else
                        <ul class="kd-hierarchy-list">
                            @foreach($this->topLevelChapters as $chapter)
                                @php
                                    $chapterKey = (string) ($chapter['__key'] ?? '');
                                    $chapterId = (string) ($chapter['id'] ?? '');
                                    $chapterTitle = trim((string) ($chapter['title'] ?? ''));
                                    $isActiveChapter = $activeChapterKey === $chapterKey;
                                @endphp
                                <li>
                                    <button type="button" wire:click="selectChapter('{{ $chapterKey }}')" class="kd-hierarchy-item {{ $isActiveChapter ? 'is-active' : '' }}">
                                        <span class="kd-hierarchy-title">{{ $chapterTitle !== '' ? $chapterTitle : 'بدون عنوان' }}</span>
                                        <span class="kd-hierarchy-meta">id: {{ $chapterId !== '' ? $chapterId : '—' }} | parent: {{ (string) ($chapter['parent_id'] ?? '0') }}</span>
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>

            <div class="kd-card kd-hierarchy-panel">
                <div class="kd-hierarchy-head">
                    <h4 class="text-xs font-bold">3) زیرفصل‌های فصل انتخاب‌شده</h4>
                    <span class="text-[11px] kd-muted">{{ count($this->subchapters) }} مورد</span>
                </div>

                <div class="kd-hierarchy-list-wrap">
                    @if($this->activeChapter === null)
                        <div class="py-8 text-center text-xs kd-muted">یک فصل انتخاب کن.</div>
                    @else
                        <ul class="kd-hierarchy-list">
                            @foreach($this->subchapters as $subchapter)
                                @php
                                    $subKey = (string) ($subchapter['__key'] ?? '');
                                    $subId = (string) ($subchapter['id'] ?? '');
                                    $subTitle = trim((string) ($subchapter['title'] ?? ''));
                                    $isActiveSub = $selectedNodeKey === $subKey;
                                @endphp
                                <li>
                                    <button type="button" wire:click="selectChapter('{{ $subKey }}')" class="kd-hierarchy-item {{ $isActiveSub ? 'is-active' : '' }}">
                                        <span class="kd-hierarchy-title">{{ $subTitle !== '' ? $subTitle : 'بدون عنوان' }}</span>
                                        <span class="kd-hierarchy-meta">id: {{ $subId !== '' ? $subId : '—' }} | parent: {{ (string) ($subchapter['parent_id'] ?? '0') }}</span>
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </section>

        <section class="kd-card kd-edit-card">
            @if($selectedNodeKey === '')
                <div class="text-center py-8">
                    <p class="text-sm font-bold">برای شروع یک دسته‌بندی یا فصل را از لیست بالا انتخاب کن.</p>
                    <p class="mt-1 text-xs kd-muted">پس از انتخاب، فرم همان رکورد اینجا باز می‌شود و ذخیره مستقیم روی منبع اصلی انجام می‌شود.</p>
                </div>
            @else
                <div class="kd-toolbar">
                    <div>
                        <h3 class="text-sm font-bold">ویرایش رکورد انتخاب‌شده</h3>
                        <p class="mt-1 text-xs kd-muted">
                            منبع: {{ $this->sourceLabel($selectedSource) }} |
                            نوع: {{ $selectedSection === 'categories' ? 'دسته‌بندی' : 'فصل' }} |
                            اندیس: {{ $selectedIndex }}
                        </p>
                    </div>
                    <div class="kd-toolbar-actions">
                        <x-filament::button
                            size="xs"
                            color="gray"
                            wire:click="$set('edit.id', '{{ $this->nextSuggestedId($selectedSection, $selectedSource) }}')"
                        >
                            پیشنهاد id جدید
                        </x-filament::button>
                        <x-filament::button size="sm" color="primary" wire:click="saveSelected">ذخیره در منبع اصلی</x-filament::button>
                    </div>
                </div>

                @if($selectedSection === 'categories')
                    <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <div class="space-y-1">
                            <label class="kd-field-label">id</label>
                            <x-filament::input.wrapper>
                                <x-filament::input wire:model="edit.id" type="text" dir="ltr" />
                            </x-filament::input.wrapper>
                        </div>

                        <div class="space-y-1 sm:col-span-2">
                            <label class="kd-field-label">title</label>
                            <x-filament::input.wrapper>
                                <x-filament::input wire:model="edit.title" type="text" />
                            </x-filament::input.wrapper>
                        </div>

                        <div class="space-y-1">
                            <label class="kd-field-label">sort_order</label>
                            <x-filament::input.wrapper>
                                <x-filament::input wire:model="edit.sort_order" type="text" dir="ltr" />
                            </x-filament::input.wrapper>
                        </div>

                        <div class="space-y-1 sm:col-span-2 xl:col-span-4">
                            <label class="kd-field-label">icon</label>
                            <x-filament::input.wrapper>
                                <x-filament::input wire:model="edit.icon" type="text" dir="ltr" />
                            </x-filament::input.wrapper>
                        </div>
                    </div>
                @else
                    <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <div class="space-y-1">
                            <label class="kd-field-label">id</label>
                            <x-filament::input.wrapper>
                                <x-filament::input wire:model="edit.id" type="text" dir="ltr" />
                            </x-filament::input.wrapper>
                        </div>

                        <div class="space-y-1">
                            <label class="kd-field-label">category_id</label>
                            <select wire:model="edit.category_id" class="kd-select" dir="ltr">
                                @foreach($this->categoryOptions as $option)
                                    <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="space-y-1">
                            <label class="kd-field-label">parent_id</label>
                            <select wire:model="edit.parent_id" class="kd-select" dir="ltr">
                                @foreach($this->parentChapterOptions as $option)
                                    <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="space-y-1 sm:col-span-2 xl:col-span-4">
                            <label class="kd-field-label">title</label>
                            <x-filament::input.wrapper>
                                <x-filament::input wire:model="edit.title" type="text" />
                            </x-filament::input.wrapper>
                        </div>

                        <div class="space-y-1 sm:col-span-2 xl:col-span-4">
                            <label class="kd-field-label">icon</label>
                            <x-filament::input.wrapper>
                                <x-filament::input wire:model="edit.icon" type="text" dir="ltr" />
                            </x-filament::input.wrapper>
                        </div>

                        <div class="space-y-1">
                            <label class="kd-field-label">title_fa</label>
                            <x-filament::input.wrapper>
                                <x-filament::input wire:model="edit.title_fa" type="text" />
                            </x-filament::input.wrapper>
                        </div>

                        <div class="space-y-1">
                            <label class="kd-field-label">title_en</label>
                            <x-filament::input.wrapper>
                                <x-filament::input wire:model="edit.title_en" type="text" dir="ltr" />
                            </x-filament::input.wrapper>
                        </div>

                        <div class="space-y-1">
                            <label class="kd-field-label">title_tr</label>
                            <x-filament::input.wrapper>
                                <x-filament::input wire:model="edit.title_tr" type="text" dir="ltr" />
                            </x-filament::input.wrapper>
                        </div>

                        <div class="space-y-1">
                            <label class="kd-field-label">title_ru</label>
                            <x-filament::input.wrapper>
                                <x-filament::input wire:model="edit.title_ru" type="text" dir="ltr" />
                            </x-filament::input.wrapper>
                        </div>

                        <div class="space-y-1 sm:col-span-2 xl:col-span-4">
                            <label class="kd-field-label">title_tk</label>
                            <x-filament::input.wrapper>
                                <x-filament::input wire:model="edit.title_tk" type="text" dir="ltr" />
                            </x-filament::input.wrapper>
                        </div>
                    </div>
                @endif
            @endif
        </section>
    </div>
</x-filament-panels::page>
