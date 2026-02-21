<?php

namespace App\Support;

use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;

class KdiniMetadataRepository
{
    public const BOOKS = 'json/books_metadata.json';
    public const AUDIO = 'json/content_audio_metadata.json';
    public const STRUCTURE = 'json/structure_metadata.json';
    public const APP_UPDATE = 'update/update.json';

    public static function repoRoot(): string
    {
        $configured = env('KDINI_REPO_ROOT');
        $default = dirname(base_path());

        $candidate = $configured ?: $default;
        $resolved = realpath($candidate);

        return $resolved ?: $candidate;
    }

    public static function safePath(string $relativePath): string
    {
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
        if ($relativePath === '') {
            throw new RuntimeException('مسیر فایل خالی است.');
        }

        $root = rtrim(self::repoRoot(), DIRECTORY_SEPARATOR);
        $fullPath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

        $resolvedRoot = realpath($root) ?: $root;
        $resolvedDir = realpath(dirname($fullPath));

        if ($resolvedDir === false) {
            $resolvedDir = dirname($fullPath);
        }

        $normalizedRoot = rtrim($resolvedRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $normalizedDir = rtrim($resolvedDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        if (! str_starts_with($normalizedDir, $normalizedRoot)) {
            throw new RuntimeException('مسیر خارج از مخزن مجاز نیست.');
        }

        return $fullPath;
    }

    /**
     * @return mixed
     */
    public static function readJson(string $relativePath)
    {
        $path = self::safePath($relativePath);

        if (! File::exists($path)) {
            throw new RuntimeException("فایل پیدا نشد: {$relativePath}");
        }

        $content = File::get($path);

        try {
            return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            throw new RuntimeException("JSON نامعتبر در {$relativePath}: {$exception->getMessage()}");
        }
    }

    /**
     * @param  mixed  $data
     */
    public static function writeJson(string $relativePath, $data): void
    {
        $path = self::safePath($relativePath);
        File::ensureDirectoryExists(dirname($path));

        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        File::put($path, $json . PHP_EOL);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function readBooks(): array
    {
        $data = self::readJson(self::BOOKS);

        if (! is_array($data)) {
            throw new RuntimeException('ساختار books_metadata.json باید آرایه باشد.');
        }

        return array_values(array_filter($data, 'is_array'));
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public static function writeBooks(array $rows): void
    {
        self::writeJson(self::BOOKS, array_values($rows));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function readAudio(): array
    {
        $data = self::readJson(self::AUDIO);

        if (! is_array($data)) {
            throw new RuntimeException('ساختار content_audio_metadata.json باید آرایه باشد.');
        }

        return array_values(array_filter($data, 'is_array'));
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public static function writeAudio(array $rows): void
    {
        self::writeJson(self::AUDIO, array_values($rows));
    }

    /**
     * @return array<string, mixed>
     */
    public static function readStructure(): array
    {
        $data = self::readJson(self::STRUCTURE);

        if (! is_array($data)) {
            throw new RuntimeException('ساختار structure_metadata.json باید آبجکت باشد.');
        }

        $data['categories'] = is_array($data['categories'] ?? null)
            ? array_values(array_filter($data['categories'], 'is_array'))
            : [];

        $data['chapters'] = is_array($data['chapters'] ?? null)
            ? array_values(array_filter($data['chapters'], 'is_array'))
            : [];

        return $data;
    }

    /**
     * @param  array<string, mixed>  $structure
     */
    public static function writeStructure(array $structure): void
    {
        if (! isset($structure['categories']) || ! is_array($structure['categories'])) {
            $structure['categories'] = [];
        }

        if (! isset($structure['chapters']) || ! is_array($structure['chapters'])) {
            $structure['chapters'] = [];
        }

        self::writeJson(self::STRUCTURE, $structure);
    }

    /**
     * @return array<string, mixed>
     */
    public static function readAppUpdate(): array
    {
        $data = self::readJson(self::APP_UPDATE);

        if (! is_array($data)) {
            throw new RuntimeException('ساختار update.json باید آبجکت باشد.');
        }

        if (! is_array($data['changes'] ?? null)) {
            $data['changes'] = [];
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function writeAppUpdate(array $payload): void
    {
        $payload['changes'] = is_array($payload['changes'] ?? null)
            ? array_values(array_map(static fn ($item): string => (string) $item, $payload['changes']))
            : [];

        self::writeJson(self::APP_UPDATE, $payload);
    }

    /**
     * @return array<string, int>
     */
    public static function getSummaryCounts(): array
    {
        $books = self::readBooks();
        $audio = self::readAudio();
        $structure = self::readStructure();

        return [
            'books' => count($books),
            'audio' => count($audio),
            'categories' => count($structure['categories'] ?? []),
            'chapters' => count($structure['chapters'] ?? []),
        ];
    }
}
