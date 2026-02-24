<?php

namespace App\Filament\Pages;

use App\Support\KdiniGitService;
use App\Support\KdiniMetadataRepository;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\File;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use UnitEnum;

class BooksManager extends Page
{
    use WithFileUploads;

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
     * @var TemporaryUploadedFile|string|null
     */
    public $uploadedSqlFile = null;

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
        $this->uploadedSqlFile = null;
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

    public function uploadSqlFileAndFillUrl(): void
    {
        if (! $this->isCreating && $this->editingIndex === null) {
            Notification::make()
                ->title('اول یک ردیف را برای ویرایش/افزودن باز کن')
                ->warning()
                ->send();

            return;
        }

        if (! $this->uploadedSqlFile instanceof TemporaryUploadedFile) {
            Notification::make()
                ->title('هیچ فایلی انتخاب نشده است')
                ->warning()
                ->send();

            return;
        }

        $originalName = $this->uploadedSqlFile->getClientOriginalName();
        if (! $this->isAllowedSqlFileName($originalName)) {
            Notification::make()
                ->title('فرمت فایل مجاز نیست')
                ->body('فقط sql / sql.gz / db / gz مجاز است.')
                ->danger()
                ->send();

            return;
        }

        $safeName = $this->sanitizeUploadFileName($originalName);
        $relativePath = 'kotob/' . $safeName;
        $targetPath = KdiniMetadataRepository::safePath($relativePath);
        File::ensureDirectoryExists(dirname($targetPath));

        if (File::exists($targetPath)) {
            $safeName = $this->appendTimestampToFileName($safeName);
            $relativePath = 'kotob/' . $safeName;
            $targetPath = KdiniMetadataRepository::safePath($relativePath);
        }

        File::put($targetPath, File::get($this->uploadedSqlFile->getRealPath()));

        $rawUrl = KdiniGitService::githubRawUrlForRelativePath($relativePath);
        $linkValue = $rawUrl ?? $relativePath;

        $this->edit['sql_download_url'] = $linkValue;

        if (trim((string) ($this->edit['download_url'] ?? '')) === '') {
            $this->edit['download_url'] = $linkValue;
        }

        if (trim((string) ($this->edit['url'] ?? '')) === '') {
            $this->edit['url'] = $linkValue;
        }

        $this->uploadedSqlFile = null;

        Notification::make()
            ->title('فایل SQL آپلود شد و لینک پر شد')
            ->body("مسیر فایل: {$relativePath}")
            ->success()
            ->send();
    }

    public function deleteBookFile(int $index): void
    {
        if (! isset($this->books[$index]) || ! is_array($this->books[$index])) {
            return;
        }

        $row = $this->books[$index];
        $relativePath = $this->extractBookLocalRelativePath($row);

        if ($relativePath === null) {
            Notification::make()
                ->title('برای این ردیف فایل محلی قابل حذف پیدا نشد')
                ->body('فقط مسیرهای داخل پوشه kotob قابل حذف هستند.')
                ->warning()
                ->send();

            return;
        }

        $path = KdiniMetadataRepository::safePath($relativePath);

        if (! File::exists($path)) {
            Notification::make()
                ->title('فایل روی دیسک پیدا نشد')
                ->body($relativePath)
                ->warning()
                ->send();

            return;
        }

        try {
            File::delete($path);

            foreach (['sql_download_url', 'download_url', 'url'] as $key) {
                if ($this->valuePointsToRelativePath($row[$key] ?? null, $relativePath)) {
                    $this->books[$index][$key] = null;
                }
            }

            KdiniMetadataRepository::writeBooks($this->books);
            $this->loadBooks();

            if (! $this->isCreating && $this->editingIndex === $index) {
                foreach (['sql_download_url', 'download_url', 'url'] as $key) {
                    if ($this->valuePointsToRelativePath($this->edit[$key] ?? null, $relativePath)) {
                        $this->edit[$key] = '';
                    }
                }
            }

            Notification::make()
                ->title('فایل کتاب حذف شد')
                ->body($relativePath)
                ->success()
                ->send();
        } catch (\Throwable $exception) {
            Notification::make()
                ->title('حذف فایل ناموفق بود')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    public function localBookFilePath(array $row): ?string
    {
        return $this->extractBookLocalRelativePath($row);
    }

    /**
     * @param array<string, mixed> $row
     */
    protected function extractBookLocalRelativePath(array $row): ?string
    {
        foreach (['sql_download_url', 'download_url', 'url'] as $key) {
            $resolved = $this->normalizeBookFileReferenceToRelativePath((string) ($row[$key] ?? ''));
            if ($resolved !== null) {
                return $resolved;
            }
        }

        return null;
    }

    protected function valuePointsToRelativePath(mixed $value, string $relativePath): bool
    {
        if (! is_string($value)) {
            return false;
        }

        return $this->normalizeBookFileReferenceToRelativePath($value) === $relativePath;
    }

    protected function normalizeBookFileReferenceToRelativePath(string $value): ?string
    {
        $trimmed = trim(str_replace('\\', '/', $value));
        if ($trimmed === '') {
            return null;
        }

        if (preg_match('/^https?:\/\//i', $trimmed) === 1) {
            $path = (string) parse_url($trimmed, PHP_URL_PATH);
            $path = str_replace('\\', '/', $path);
            $position = mb_stripos($path, '/kotob/');

            if ($position === false) {
                return null;
            }

            $trimmed = ltrim(substr($path, $position), '/');
        }

        return $this->normalizeKotobRelativePath($trimmed);
    }

    protected function normalizeKotobRelativePath(string $path): ?string
    {
        $normalized = ltrim(str_replace('\\', '/', trim($path)), '/');
        if ($normalized === '' || ! str_starts_with(mb_strtolower($normalized), 'kotob/')) {
            return null;
        }

        $baseName = basename($normalized);
        if ($baseName === '' || $baseName === '.' || $baseName === '..') {
            return null;
        }

        return 'kotob/' . $baseName;
    }

    protected function isAllowedSqlFileName(string $name): bool
    {
        $lower = mb_strtolower(trim($name));

        return str_ends_with($lower, '.sql')
            || str_ends_with($lower, '.sql.gz')
            || str_ends_with($lower, '.db')
            || str_ends_with($lower, '.gz');
    }

    protected function sanitizeUploadFileName(string $originalName): string
    {
        $trimmed = trim($originalName);
        $lower = mb_strtolower($trimmed);

        $ext = '';
        $stem = $trimmed;

        if (str_ends_with($lower, '.sql.gz')) {
            $ext = '.sql.gz';
            $stem = substr($trimmed, 0, -7);
        } else {
            $ext = '.' . pathinfo($trimmed, PATHINFO_EXTENSION);
            $stem = pathinfo($trimmed, PATHINFO_FILENAME);
        }

        $stem = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) $stem) ?: '';
        $stem = trim($stem, '._-');

        if ($stem === '') {
            $stem = 'book_' . date('Ymd_His');
        }

        return $stem . $ext;
    }

    protected function appendTimestampToFileName(string $fileName): string
    {
        $lower = mb_strtolower($fileName);
        $stamp = date('Ymd_His');

        if (str_ends_with($lower, '.sql.gz')) {
            $stem = substr($fileName, 0, -7);

            return "{$stem}_{$stamp}.sql.gz";
        }

        $ext = pathinfo($fileName, PATHINFO_EXTENSION);
        $stem = pathinfo($fileName, PATHINFO_FILENAME);

        if ($ext === '') {
            return "{$stem}_{$stamp}";
        }

        return "{$stem}_{$stamp}.{$ext}";
    }
}
