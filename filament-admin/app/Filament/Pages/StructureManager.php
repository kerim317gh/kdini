<?php

namespace App\Filament\Pages;

use App\Support\KdiniMetadataRepository;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use UnitEnum;

class StructureManager extends Page
{
    protected static ?string $title = 'مدیریت ساختار';

    protected string $view = 'filament.pages.structure-manager';

    protected static ?int $navigationSort = 30;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static string | UnitEnum | null $navigationGroup = 'متادیتا';

    public string $section = 'categories';

    public string $search = '';

    /**
     * @var array<string, mixed>
     */
    public array $structure = [
        'schema' => 1,
        'data_version' => '',
        'categories' => [],
        'chapters' => [],
    ];

    public ?int $editingIndex = null;

    /**
     * @var array<string, mixed>
     */
    public array $edit = [];

    public function mount(): void
    {
        $this->loadStructure();
    }

    public function loadStructure(): void
    {
        try {
            $this->structure = KdiniMetadataRepository::readStructure();
        } catch (\Throwable $exception) {
            Notification::make()
                ->title('خواندن فایل ساختار ناموفق بود')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            $this->structure = [
                'schema' => 1,
                'data_version' => '',
                'categories' => [],
                'chapters' => [],
            ];
        }
    }

    public function switchSection(string $section): void
    {
        $this->section = in_array($section, ['categories', 'chapters'], true) ? $section : 'categories';
        $this->cancelEdit();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function currentRows(): array
    {
        $rows = $this->structure[$this->section] ?? [];

        if (! is_array($rows)) {
            return [];
        }

        return array_values(array_filter($rows, 'is_array'));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getFilteredRowsProperty(): array
    {
        $query = mb_strtolower(trim($this->search));
        $result = [];

        foreach ($this->currentRows() as $index => $row) {
            $haystack = mb_strtolower(implode(' ', array_map(static fn ($value): string => (string) $value, $row)));
            if ($query !== '' && ! str_contains($haystack, $query)) {
                continue;
            }

            $row['__index'] = $index;
            $result[] = $row;
        }

        return $result;
    }

    public function startEdit(int $index): void
    {
        $rows = $this->currentRows();

        if (! isset($rows[$index])) {
            return;
        }

        $row = $rows[$index];

        $this->editingIndex = $index;

        if ($this->section === 'categories') {
            $this->edit = [
                'id' => (string) ($row['id'] ?? ''),
                'title' => (string) ($row['title'] ?? ''),
                'sort_order' => (string) ($row['sort_order'] ?? ''),
                'icon' => (string) ($row['icon'] ?? ''),
            ];

            return;
        }

        $this->edit = [
            'id' => (string) ($row['id'] ?? ''),
            'category_id' => (string) ($row['category_id'] ?? ''),
            'parent_id' => (string) ($row['parent_id'] ?? ''),
            'title' => (string) ($row['title'] ?? ''),
            'icon' => (string) ($row['icon'] ?? ''),
        ];
    }

    public function cancelEdit(): void
    {
        $this->editingIndex = null;
        $this->edit = [];
    }

    public function saveEdit(): void
    {
        if ($this->editingIndex === null) {
            return;
        }

        $rows = $this->currentRows();

        if (! isset($rows[$this->editingIndex])) {
            return;
        }

        $index = $this->editingIndex;
        $row = $rows[$index];

        if ($this->section === 'categories') {
            $row['id'] = $this->toIntOrOriginal((string) ($this->edit['id'] ?? ''));
            $row['title'] = trim((string) ($this->edit['title'] ?? ''));
            $row['sort_order'] = $this->toIntOrOriginal((string) ($this->edit['sort_order'] ?? ''));
            $row['icon'] = trim((string) ($this->edit['icon'] ?? ''));
        } else {
            $row['id'] = $this->toIntOrOriginal((string) ($this->edit['id'] ?? ''));
            $row['category_id'] = $this->toIntOrOriginal((string) ($this->edit['category_id'] ?? ''));
            $row['parent_id'] = $this->toIntOrOriginal((string) ($this->edit['parent_id'] ?? ''));
            $row['title'] = trim((string) ($this->edit['title'] ?? ''));
            $row['icon'] = trim((string) ($this->edit['icon'] ?? ''));
        }

        $rows[$index] = $row;
        $this->structure[$this->section] = $rows;

        try {
            KdiniMetadataRepository::writeStructure($this->structure);

            Notification::make()
                ->title('ردیف ساختار ذخیره شد')
                ->success()
                ->send();

            $this->cancelEdit();
            $this->loadStructure();
        } catch (\Throwable $exception) {
            Notification::make()
                ->title('ذخیره با خطا مواجه شد')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function toIntOrOriginal(string $value): int | string
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return '';
        }

        return is_numeric($trimmed) ? (int) $trimmed : $trimmed;
    }
}
