<?php

namespace App\Filament\Pages;

use App\Support\KdiniMetadataRepository;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use UnitEnum;

class AppUpdateManager extends Page
{
    protected static ?string $title = 'مدیریت آپدیت برنامه';

    protected string $view = 'filament.pages.app-update-manager';

    protected static ?int $navigationSort = 40;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-arrow-down-tray';

    protected static string | UnitEnum | null $navigationGroup = 'متادیتا';

    public string $app = 'kdini';

    public string $platform = 'android';

    public string $version = '';

    public string $build = '';

    public string $released_at = '';

    public bool $mandatory = false;

    public string $download_url = '';

    public string $changes_text = '';

    public function mount(): void
    {
        $this->loadPayload();
    }

    public function loadPayload(): void
    {
        try {
            $payload = KdiniMetadataRepository::readAppUpdate();

            $this->app = (string) ($payload['app'] ?? 'kdini');
            $this->platform = (string) ($payload['platform'] ?? 'android');
            $this->version = (string) ($payload['version'] ?? '');
            $this->build = (string) ($payload['build'] ?? '');
            $this->released_at = (string) ($payload['released_at'] ?? '');
            $this->mandatory = (bool) ($payload['mandatory'] ?? false);
            $this->download_url = (string) ($payload['download_url'] ?? '');

            $changes = $payload['changes'] ?? [];
            if (! is_array($changes)) {
                $changes = [];
            }

            $this->changes_text = implode("\n", array_map(static fn ($item): string => (string) $item, $changes));
        } catch (\Throwable $exception) {
            Notification::make()
                ->title('خواندن update.json ناموفق بود')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    public function savePayload(): void
    {
        $changes = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $this->changes_text) ?: []), static fn ($line): bool => $line !== ''));

        $payload = [
            'app' => trim($this->app),
            'platform' => trim($this->platform),
            'version' => trim($this->version),
            'build' => is_numeric(trim($this->build)) ? (int) trim($this->build) : trim($this->build),
            'released_at' => trim($this->released_at),
            'mandatory' => $this->mandatory,
            'download_url' => trim($this->download_url),
            'changes' => $changes,
        ];

        try {
            KdiniMetadataRepository::writeAppUpdate($payload);

            Notification::make()
                ->title('تنظیمات آپدیت ذخیره شد')
                ->success()
                ->send();

            $this->loadPayload();
        } catch (\Throwable $exception) {
            Notification::make()
                ->title('ذخیره update.json ناموفق بود')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }
}
