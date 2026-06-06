<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use App\Models\Book;

class ExportContentToJson extends Command
{
    protected $signature = 'app:export-json';
    protected $description = 'Export all content to JSON files';

    public function handle()
    {
        $this->info('Starting JSON export process...');

        $books = Book::with([
            'categories.chapters' => function ($query) {
                $query->whereNull('parent_id')->with(['children.content', 'children.sound', 'content', 'sound']);
            }
        ])->get();

        $outputDirectory = 'json_exports';
        Storage::disk('local')->deleteDirectory($outputDirectory);
        Storage::disk('local')->makeDirectory($outputDirectory);

        foreach ($books as $book) {
            $bookData = $this->formatBook($book);
            $jsonContent = json_encode($bookData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $fileName = "{$outputDirectory}/book_{$book->id}.json";
            Storage::disk('local')->put($fileName, $jsonContent);
            $this->info("Successfully exported Book ID: {$book->id} to {$fileName}");
        }

        $this->info('JSON export process completed!');
        return 0;
    }

    private function formatBook($book)
    {
        return [
            'id' => $book->id,
            'title' => $book->title,
            'categories' => $book->categories->map(function ($category) {
                return [
                    'id' => $category->id,
                    'title' => $category->title,
                    'icon' => $category->icon,
                    'order' => $category->order,
                    'chapters' => $this->formatChapters($category->chapters)
                ];
            })
        ];
    }

    private function formatChapters($chapters)
    {
        return $chapters->map(function ($chapter) {
            return [
                'id' => $chapter->id,
                'title' => $chapter->title,
                'title_fa' => $chapter->title_fa,
                'title_en' => $chapter->title_en,
                'title_tr' => $chapter->title_tr,
                'title_ru' => $chapter->title_ru,
                'title_tk' => $chapter->title_tk,
                'content' => $chapter->content->text ?? null,
                'sound' => $this->formatSound($chapter->sound),
                'sub_chapters' => $this->formatChapters($chapter->children)
            ];
        });
    }

    private function formatSound($sound)
    {
        if (!$sound) {
            return null;
        }
        return [
            'url' => $sound->url ? Storage::url($sound->url) : null,
            'url_fa' => $sound->url_fa ? Storage::url($sound->url_fa) : null,
            'url_en' => $sound->url_en ? Storage::url($sound->url_en) : null,
            'url_tr' => $sound->url_tr ? Storage::url($sound->url_tr) : null,
            'url_ru' => $sound->url_ru ? Storage::url($sound->url_ru) : null,
            'url_tk' => $sound->url_tk ? Storage::url($sound->url_tk) : null,
        ];
    }
}
