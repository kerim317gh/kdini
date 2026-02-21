<?php

namespace App\Support;

use Illuminate\Support\Facades\Process;

class KdiniGitService
{
    /**
     * @return array{ok:bool, code:int, output:string}
     */
    public static function run(array $command, int $timeoutSeconds = 180): array
    {
        $result = Process::path(KdiniMetadataRepository::repoRoot())
            ->timeout($timeoutSeconds)
            ->run($command);

        $output = trim($result->output() . "\n" . $result->errorOutput());

        return [
            'ok' => $result->successful(),
            'code' => $result->exitCode(),
            'output' => $output,
        ];
    }

    /**
     * @return array{status:string, remotes:string, commits:string}
     */
    public static function overview(): array
    {
        $status = self::run(['git', 'status', '--short', '-b'])['output'];
        $remotes = self::run(['git', 'remote', '-v'])['output'];
        $commits = self::run(['git', 'log', '--oneline', '-n', '6'])['output'];

        return [
            'status' => $status,
            'remotes' => $remotes,
            'commits' => $commits,
        ];
    }

    /**
     * @return array{ok:bool, code:int, output:string}
     */
    public static function pullRebase(): array
    {
        $branchResult = self::run(['git', 'branch', '--show-current']);
        $branch = trim($branchResult['output']) ?: 'main';

        return self::run(['git', 'pull', '--rebase', 'origin', $branch]);
    }

    /**
     * @return array{ok:bool, code:int, output:string}
     */
    public static function reorganizeAssets(): array
    {
        return self::run(['zsh', 'tools/reorganize_assets.sh']);
    }

    /**
     * @return array{ok:bool, code:int, output:string}
     */
    public static function quickPush(string $message): array
    {
        if (trim($message) === '') {
            $message = 'بروزرسانی از Filament Panel';
        }

        return self::run(['zsh', 'tools/git_quick_push.sh', $message], 300);
    }
}
