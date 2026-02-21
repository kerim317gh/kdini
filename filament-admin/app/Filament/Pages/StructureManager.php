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

    public bool $isCreating = false;

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

        $rows = $this->section === 'chapters'
            ? $this->prepareChapterRows($this->currentRows())
            : $this->currentRows();

        foreach ($rows as $index => $row) {
            $haystack = mb_strtolower(implode(' ', array_map(static fn ($value): string => (string) $value, $row)));
            if ($query !== '' && ! str_contains($haystack, $query)) {
                continue;
            }

            $row['__index'] = $row['__index'] ?? $index;
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

        $this->isCreating = false;
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

    public function startCreate(): void
    {
        $this->isCreating = true;
        $this->editingIndex = null;

        if ($this->section === 'categories') {
            $this->edit = [
                'id' => '',
                'title' => '',
                'sort_order' => (string) (count($this->currentRows()) + 1),
                'icon' => '',
            ];

            return;
        }

        $this->edit = [
            'id' => '',
            'category_id' => '',
            'parent_id' => '0',
            'title' => '',
            'icon' => '',
        ];
    }

    public function startCreateChild(int $parentId, int $categoryId): void
    {
        $this->section = 'chapters';
        $this->isCreating = true;
        $this->editingIndex = null;
        $this->edit = [
            'id' => '',
            'category_id' => (string) $categoryId,
            'parent_id' => (string) $parentId,
            'title' => '',
            'icon' => '',
        ];
    }

    public function cancelEdit(): void
    {
        $this->isCreating = false;
        $this->editingIndex = null;
        $this->edit = [];
    }

    public function saveEdit(): void
    {
        $isCreating = $this->isCreating;

        if (! $isCreating && $this->editingIndex === null) {
            return;
        }

        $rows = $this->currentRows();

        if (! $isCreating && ! isset($rows[$this->editingIndex])) {
            return;
        }

        $row = $isCreating ? [] : $rows[$this->editingIndex];

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

        if ($isCreating) {
            $rows[] = $row;
        } else {
            $rows[$this->editingIndex] = $row;
        }
        $this->structure[$this->section] = $rows;

        try {
            KdiniMetadataRepository::writeStructure($this->structure);

            Notification::make()
                ->title($isCreating ? 'رکورد جدید ساختار اضافه شد' : 'ردیف ساختار ذخیره شد')
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

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    protected function prepareChapterRows(array $rows): array
    {
        $nodes = [];
        $children = [];
        $idToIndex = [];

        foreach ($rows as $index => $row) {
            $id = $this->toIntOrNull($row['id'] ?? null);
            $parentId = $this->toIntOrNull($row['parent_id'] ?? null) ?? 0;
            $categoryId = $this->toIntOrNull($row['category_id'] ?? null) ?? 0;

            $nodes[$index] = [
                'id' => $id,
                'parent_id' => $parentId,
                'category_id' => $categoryId,
            ];

            if ($id !== null) {
                $idToIndex[$id] = $index;
            }
        }

        foreach ($nodes as $index => $meta) {
            $parent = $meta['parent_id'] ?? 0;
            $children[$parent][] = $index;
        }

        foreach ($children as &$indices) {
            usort($indices, function (int $a, int $b) use ($nodes): int {
                $aCat = $nodes[$a]['category_id'] ?? 0;
                $bCat = $nodes[$b]['category_id'] ?? 0;
                if ($aCat !== $bCat) {
                    return $aCat <=> $bCat;
                }

                $aId = $nodes[$a]['id'] ?? 0;
                $bId = $nodes[$b]['id'] ?? 0;

                return $aId <=> $bId;
            });
        }
        unset($indices);

        $rootIndices = [];
        foreach ($nodes as $index => $meta) {
            $parent = $meta['parent_id'] ?? 0;
            if ($parent <= 0 || ! isset($idToIndex[$parent])) {
                $rootIndices[] = $index;
            }
        }

        usort($rootIndices, function (int $a, int $b) use ($nodes): int {
            $aCat = $nodes[$a]['category_id'] ?? 0;
            $bCat = $nodes[$b]['category_id'] ?? 0;
            if ($aCat !== $bCat) {
                return $aCat <=> $bCat;
            }

            $aId = $nodes[$a]['id'] ?? 0;
            $bId = $nodes[$b]['id'] ?? 0;

            return $aId <=> $bId;
        });

        $visited = [];
        $ordered = [];

        $walk = function (int $index, int $depth) use (&$walk, &$ordered, &$visited, $rows, $nodes, $children): void {
            if (isset($visited[$index])) {
                return;
            }

            $visited[$index] = true;
            $row = $rows[$index];
            $title = (string) ($row['title'] ?? '');
            $prefix = str_repeat('— ', max(0, min($depth, 8)));

            $row['__index'] = $index;
            $row['__depth'] = $depth;
            $row['__title_display'] = $prefix . $title;
            $ordered[] = $row;

            $id = $nodes[$index]['id'] ?? null;
            if ($id !== null && isset($children[$id])) {
                foreach ($children[$id] as $childIndex) {
                    $walk($childIndex, $depth + 1);
                }
            }
        };

        foreach ($rootIndices as $rootIndex) {
            $walk($rootIndex, 0);
        }

        foreach (array_keys($rows) as $index) {
            $walk((int) $index, 0);
        }

        return $ordered;
    }

    protected function toIntOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric((string) $value) ? (int) $value : null;
    }
}
