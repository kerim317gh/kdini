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

    public function cancelEdit(): void
    {
        $this->editingIndex = null;
        $this->edit = [];
    }

    public function saveEdit(): void
    {
        if ($this->editingIndex === null || ! isset($this->books[$this->editingIndex])) {
            return;
        }

        $index = $this->editingIndex;
        $row = $this->books[$index];

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

        $this->books[$index] = $row;

        try {
            KdiniMetadataRepository::writeBooks($this->books);

            Notification::make()
                ->title('ردیف کتاب ذخیره شد')
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

    public function normalizeSqlUrls(): void
    {
        $pattern = '/(https:\/\/raw\.githubusercontent\.com\/kerim317gh\/kdini\/refs\/heads\/main\/)(?!kotob\/)([^"\s]+\.(?:sql|sql\.gz|db))/i';
        $changes = 0;

        foreach ($this->books as $index => $row) {
            foreach (['sql_download_url', 'download_url', 'url'] as $key) {
                $value = $row[$key] ?? null;
                if (! is_string($value) || $value === '') {
                    continue;
                }

                $newValue = preg_replace($pattern, '$1kotob/$2', $value);
                if (is_string($newValue) && $newValue !== $value) {
                    $this->books[$index][$key] = $newValue;
                    $changes++;
                }
            }
        }

        if ($changes === 0) {
            Notification::make()
                ->title('نیازی به اصلاح نبود')
                ->body('همه لینک‌ها از قبل درست بودند.')
                ->success()
                ->send();

            return;
        }

        try {
            KdiniMetadataRepository::writeBooks($this->books);

            Notification::make()
                ->title('اصلاح لینک‌ها انجام شد')
                ->body("تعداد لینک اصلاح‌شده: {$changes}")
                ->success()
                ->send();

            $this->loadBooks();
        } catch (\Throwable $exception) {
            Notification::make()
                ->title('ذخیره اصلاح لینک‌ها ناموفق بود')
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
