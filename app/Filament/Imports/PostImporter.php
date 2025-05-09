<?php

namespace App\Filament\Imports;

use App\Models\Post;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Facades\Log;

class PostImporter extends Importer
{
    protected static ?string $model = Post::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('title')
                ->requiredMapping()
                ->rules(['required']),
            ImportColumn::make('slug')
                ->requiredMapping()
                ->rules(['required']),
            ImportColumn::make('description'),
            ImportColumn::make('category_id')
                ->requiredMapping()
                // ->relationship()
                ->rules(['required']),
        ];
    }

    public function handleRecord(array $data): ?Post
    {
        $post = Post::firstOrNew([
            'slug' => $data['slug'], // Duplikaterkennung nach slug
        ]);

        $post->fill([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'category_id' => $data['category_id'],
        ]);

        $post->save();

        return $post;
    }



    public function resolveRecord(): ?Post
    {
        // return Post::firstOrNew([
        //     // Update existing records, matching them by `$this->data['column_name']`
        //     'email' => $this->data['email'],
        // ]);

        return new Post();
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        return trans_choice(
            ':success erfolgreich importiert, :failed fehlgeschlagen.',
            $import->successful_rows,
            [
                'success' => $import->successful_rows,
                'failed' => $import->getFailedRowsCount(),
            ]
        );
    }
}
