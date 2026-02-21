<?php

namespace App\Filament\Pages;

use App\Support\KdiniGitService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use UnitEnum;

class GitOpsManager extends Page
{
    protected static ?string $title = 'مدیریت Git و Deploy';

    protected string $view = 'filament.pages.git-ops-manager';

    protected static ?int $navigationSort = 50;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-command-line';

    protected static string | UnitEnum | null $navigationGroup = 'عملیات';

    public string $commitMessage = 'بروزرسانی از Filament Panel';

    public string $output = '';

    public string $status = '';

    public string $remotes = '';

    public string $commits = '';

    public function mount(): void
    {
        $this->refreshOverview();
    }

    public function refreshOverview(): void
    {
        $overview = KdiniGitService::overview();

        $this->status = $overview['status'];
        $this->remotes = $overview['remotes'];
        $this->commits = $overview['commits'];
    }

    public function pullRebase(): void
    {
        $result = KdiniGitService::pullRebase();
        $this->output = $result['output'];

        $notification = Notification::make()->title($result['ok'] ? 'Pull انجام شد' : 'Pull ناموفق بود');
        $result['ok'] ? $notification->success() : $notification->danger();
        $notification->send();

        $this->refreshOverview();
    }

    public function reorganizeAssets(): void
    {
        $result = KdiniGitService::reorganizeAssets();
        $this->output = $result['output'];

        $notification = Notification::make()->title($result['ok'] ? 'مرتب‌سازی فایل‌ها انجام شد' : 'مرتب‌سازی فایل‌ها ناموفق بود');
        $result['ok'] ? $notification->success() : $notification->danger();
        $notification->send();

        $this->refreshOverview();
    }

    public function commitAndPush(): void
    {
        $result = KdiniGitService::quickPush($this->commitMessage);
        $this->output = $result['output'];

        $notification = Notification::make()->title($result['ok'] ? 'Commit و Push انجام شد' : 'Commit یا Push ناموفق بود');
        $result['ok'] ? $notification->success() : $notification->danger();
        $notification->send();

        $this->refreshOverview();
    }
}
