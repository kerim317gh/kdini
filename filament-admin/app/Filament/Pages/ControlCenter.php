<?php

namespace App\Filament\Pages;

use App\Support\KdiniGitService;
use App\Support\KdiniMetadataRepository;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Panel;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

class ControlCenter extends Page
{
    protected static ?string $title = 'مرکز کنترل';

    protected string $view = 'filament.pages.control-center';

    protected static ?int $navigationSort = -2;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-home';

    protected static string | UnitEnum | null $navigationGroup = 'داشبورد';

    public array $counts = [
        'books' => 0,
        'audio' => 0,
        'categories' => 0,
        'chapters' => 0,
    ];

    public string $appVersion = '-';

    public string $gitStatus = '';

    public string $gitRemotes = '';

    public string $gitCommits = '';

    public string $lastActionOutput = '';

    public function mount(): void
    {
        $this->refreshData();
    }

    public static function getRoutePath(Panel $panel): string
    {
        return '/';
    }

    public function refreshData(): void
    {
        try {
            $this->counts = KdiniMetadataRepository::getSummaryCounts();
            $update = KdiniMetadataRepository::readAppUpdate();
            $this->appVersion = (string) ($update['version'] ?? '-');
        } catch (\Throwable $exception) {
            Notification::make()
                ->title('خواندن متادیتا با خطا مواجه شد')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }

        $this->refreshGitOverview();
    }

    public function refreshGitOverview(): void
    {
        $overview = KdiniGitService::overview();

        $this->gitStatus = $overview['status'];
        $this->gitRemotes = $overview['remotes'];
        $this->gitCommits = $overview['commits'];
    }

    public function quickPull(): void
    {
        $result = KdiniGitService::pullRebase();
        $this->lastActionOutput = $result['output'];

        $notification = Notification::make()->title($result['ok'] ? 'Pull انجام شد' : 'Pull ناموفق بود');
        $result['ok'] ? $notification->success() : $notification->danger();
        $notification->send();

        $this->refreshGitOverview();
    }

    public function quickReorganize(): void
    {
        $result = KdiniGitService::reorganizeAssets();
        $this->lastActionOutput = $result['output'];

        $notification = Notification::make()->title($result['ok'] ? 'مرتب‌سازی فایل‌ها انجام شد' : 'مرتب‌سازی فایل‌ها ناموفق بود');
        $result['ok'] ? $notification->success() : $notification->danger();
        $notification->send();

        $this->refreshData();
    }

    public function quickPush(): void
    {
        $result = KdiniGitService::quickPush('بروزرسانی سریع از مرکز کنترل Filament');
        $this->lastActionOutput = $result['output'];

        $notification = Notification::make()->title($result['ok'] ? 'Push انجام شد' : 'Push ناموفق بود');
        $result['ok'] ? $notification->success() : $notification->danger();
        $notification->send();

        $this->refreshGitOverview();
    }

    public function getHeading(): string | Htmlable
    {
        return 'مرکز کنترل پروژه KDINI';
    }

    public function getSubheading(): string | Htmlable | null
    {
        return 'مدیریت همه متادیتاها + عملیات Git با Filament واقعی';
    }
}
