<?php

namespace App\Filament\Pages;

use App\Support\KdiniMetadataRepository;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use UnitEnum;

class SystemBooksManager extends Page
{
    protected static ?string $title = 'کتاب‌های سیستم';

    protected string $view = 'filament.pages.system-books-manager';

    protected static ?int $navigationSort = 33;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-book-open';

    protected static string | UnitEnum | null $navigationGroup = 'متادیتا';

    public string $search = '';

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $rows = [];

    public ?int $editingIndex = null;

    public bool $isCreating = false;

    /**
     * @var array<string, mixed>
     */
    public array $edit = [];

    public function mount(): void
    {
        $this->loadRows();
    }

    public function loadRows(): void
    {
        try {
            $payload = KdiniMetadataRepository::readJson('json/system_books.json');
            $books = is_array($payload) && is_array($payload['books'] ?? null)
                ? array_values(array_filter($payload['books'], 'is_array'))
                : [];

            $this->rows = $books;
        } catch (\Throwable $exception) {
            Notification::make()
                ->title('خواندن system_books.json ناموفق بود')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            $this->rows = [];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getFilteredRowsProperty(): array
    {
        $query = mb_strtolower(trim($this->search));
        $filtered = [];

        foreach ($this->rows as $index => $row) {
            $id = (string) ($row['id'] ?? '');
            $title = (string) ($row['title'] ?? '');
            $version = (string) ($row['version'] ?? '');
            $url = (string) ($row['url'] ?? '');

            if ($query !== '') {
                $haystack = mb_strtolower(implode(' ', [$id, $title, $version, $url]));
                if (! str_contains($haystack, $query)) {
                    continue;
                }
            }

            $row['__index'] = $index;
            $filtered[] = $row;
        }

        return $filtered;
    }

    public function startEdit(int $index): void
    {
        if (! isset($this->rows[$index])) {
            return;
        }

        $row = $this->rows[$index];

        $this->isCreating = false;
        $this->editingIndex = $index;
        $this->edit = [
            'id' => (string) ($row['id'] ?? ''),
            'title' => (string) ($row['title'] ?? ''),
            'version' => (string) ($row['version'] ?? ''),
            'url' => (string) ($row['url'] ?? ''),
        ];
    }

    public function startCreate(): void
    {
        $this->isCreating = true;
        $this->editingIndex = null;
        $this->edit = [
            'id' => '',
            'title' => '',
            'version' => '1',
            'url' => '',
        ];
    }

    public function cancelEdit(): void
    {
        $this->isCreating = false;
        $this->editingIndex = null;
        $this->edit = [];
    }

    public function removeRow(int $index): void
    {
        if (! isset($this->rows[$index])) {
            return;
        }

        unset($this->rows[$index]);
        $this->rows = array_values($this->rows);
        $this->persistRows('ردیف حذف شد');

        if ($this->editingIndex === $index) {
            $this->cancelEdit();
        }
    }

    public function saveEdit(): void
    {
        $isCreating = $this->isCreating;

        if (! $isCreating && ($this->editingIndex === null || ! isset($this->rows[$this->editingIndex]))) {
            return;
        }

        $row = $isCreating ? [] : $this->rows[$this->editingIndex];
        $row['id'] = $this->toIntOrOriginal((string) ($this->edit['id'] ?? ''));
        $row['title'] = trim((string) ($this->edit['title'] ?? ''));
        $row['version'] = $this->toIntOrOriginal((string) ($this->edit['version'] ?? ''));

        $url = trim((string) ($this->edit['url'] ?? ''));
        $row['url'] = $url === '' ? null : $url;

        if ($isCreating) {
            $this->rows[] = $row;
        } else {
            $this->rows[$this->editingIndex] = $row;
        }

        $this->rows = array_values($this->rows);
        $this->persistRows($isCreating ? 'کتاب سیستم جدید اضافه شد' : 'کتاب سیستم ذخیره شد');
        $this->cancelEdit();
    }

    protected function persistRows(string $successTitle): void
    {
        try {
            KdiniMetadataRepository::writeJson('json/system_books.json', [
                'books' => array_values($this->rows),
            ]);

            Notification::make()
                ->title($successTitle)
                ->success()
                ->send();

            $this->loadRows();
        } catch (\Throwable $exception) {
            Notification::make()
                ->title('ذخیره system_books.json ناموفق بود')
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
