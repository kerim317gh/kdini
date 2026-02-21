<?php

namespace App\Filament\Pages;

use App\Support\KdiniMetadataRepository;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use UnitEnum;

class BooksManager extends Page
{
    protected static ?string $title = 'مدیریت کتاب‌ها';

    protected string $view = 'filament.pages.books-manager';

    protected static ?int $navigationSort = 10;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-book-open';

    protected static string | UnitEnum | null $navigationGroup = 'متادیتا';

    public string $search = '';

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $books = [];

    public ?int $editingIndex = null;

    public bool $isCreating = false;

    /**
     * @var array<string, mixed>
     */
    public array $edit = [];

    public function mount(): void
    {
        $this->loadBooks();
    }

    public function loadBooks(): void
    {
        try {
            $this->books = KdiniMetadataRepository::readBooks();
        } catch (\Throwable $exception) {
            Notification::make()
                ->title('خواندن فایل کتاب‌ها ناموفق بود')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            $this->books = [];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getFilteredRowsProperty(): array
    {
        $query = mb_strtolower(trim($this->search));

        $rows = [];

        foreach ($this->books as $index => $row) {
            $id = (string) ($row['id'] ?? '');
            $title = (string) ($row['title'] ?? '');
            $version = (string) ($row['version'] ?? '');
            $status = (string) ($row['status'] ?? '');
            $url = (string) $this->extractDownloadUrl($row);

            if ($query !== '') {
                $haystack = mb_strtolower(implode(' ', [$id, $title, $version, $status, $url]));
                if (! str_contains($haystack, $query)) {
                    continue;
                }
            }

            $row['__index'] = $index;
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function extractDownloadUrl(array $row): string
    {
        foreach (['sql_download_url', 'download_url', 'url'] as $key) {
            $value = $row[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return '';
    }

    public function startEdit(int $index): void
    {
        if (! isset($this->books[$index])) {
            return;
        }

        $row = $this->books[$index];

        $this->isCreating = false;
        $this->editingIndex = $index;
        $this->edit = [
            'id' => (string) ($row['id'] ?? ''),
            'title' => (string) ($row['title'] ?? ''),
            'description' => (string) ($row['description'] ?? ''),
            'version' => (string) ($row['version'] ?? ''),
            'sql_download_url' => (string) ($row['sql_download_url'] ?? ''),
            'download_url' => (string) ($row['download_url'] ?? ''),
            'url' => (string) ($row['url'] ?? ''),
            'is_default' => (string) ($row['is_default'] ?? ''),
            'is_downloaded_on_device' => (string) ($row['is_downloaded_on_device'] ?? ''),
            'status' => (string) ($row['status'] ?? ''),
        ];
    }

    public function startCreate(): void
    {
        $this->isCreating = true;
        $this->editingIndex = null;
        $this->edit = [
            'id' => '',
            'title' => '',
            'description' => '',
            'version' => '',
            'sql_download_url' => '',
            'download_url' => '',
            'url' => '',
            'is_default' => '0',
            'is_downloaded_on_device' => '0',
            'status' => 'active',
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

        if (! $isCreating && ($this->editingIndex === null || ! isset($this->books[$this->editingIndex]))) {
            return;
        }

        $row = $isCreating ? [] : $this->books[$this->editingIndex];

        $row['id'] = $this->toIntOrOriginal((string) ($this->edit['id'] ?? ''));
        $row['title'] = trim((string) ($this->edit['title'] ?? ''));
        $row['description'] = (string) ($this->edit['description'] ?? '');
        $row['version'] = trim((string) ($this->edit['version'] ?? ''));
        $row['is_default'] = $this->toIntOrOriginal((string) ($this->edit['is_default'] ?? ''));
        $row['is_downloaded_on_device'] = $this->toIntOrOriginal((string) ($this->edit['is_downloaded_on_device'] ?? ''));
        $row['status'] = trim((string) ($this->edit['status'] ?? ''));

        foreach (['sql_download_url', 'download_url', 'url'] as $key) {
            $value = trim((string) ($this->edit[$key] ?? ''));
            $row[$key] = $value === '' ? null : $value;
        }

        if ($isCreating) {
            $this->books[] = $row;
        } else {
            $this->books[$this->editingIndex] = $row;
        }

        try {
            KdiniMetadataRepository::writeBooks($this->books);

            Notification::make()
                ->title($isCreating ? 'کتاب جدید اضافه شد' : 'ردیف کتاب ذخیره شد')
                ->success()
                ->send();

            $this->cancelEdit();
            $this->loadBooks();
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
