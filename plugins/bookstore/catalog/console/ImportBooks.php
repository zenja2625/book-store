<?php

namespace Bookstore\Catalog\Console;

use Illuminate\Console\Command;
use Bookstore\Catalog\Models\Genre;
use Bookstore\Catalog\Models\Publisher;
use Bookstore\Catalog\Models\Book;



class ImportBooks extends Command
{
    protected $name = 'catalog:import-books';
    protected $description = 'Import books from JSON file';


    public function handle()
    {
        $this->info('Start command');

        $path = plugins_path('bookstore/catalog/data/books.json');

        $json = file_get_contents($path);
        $books = json_decode($json, true);

        $genreMetaDescriptions = [
            'fantasy' => 'Книги в жанре фэнтези: магия, мифические миры, эпические сражения и захватывающие приключения для любителей фантастических историй.',
            'detective' => 'Детективные книги с запутанными расследованиями, тайнами и неожиданными развязками. Классика и современные детективы.',
            'novel' => 'Романы о жизни, любви и человеческих судьбах. Классические и современные произведения мировой литературы.',
            'non-fiction' => 'Нон-фикшн книги: реальные истории, наука, саморазвитие, биографии и полезные знания без вымысла.',
            'fantasy-fiction' => 'Фантастическая литература и фэнтези: альтернативные миры, будущее человечества и смелые авторские идеи.',
            'business' => 'Книги о бизнесе, финансах и предпринимательстве: стратегии, управление, личная эффективность и развитие карьеры.',
        ];

        $addedCount = 0;

        foreach ($books as $book) {
            $slug = $book['genre_slug'];

            $genre = Genre::where('slug', $slug)->first();

            if ($genre === null) {
                $firstGenre =  Genre::orderBy('sort_order', 'desc')->first();

                $afterId = $firstGenre === null ? null : $firstGenre->id;

                $genre = Genre::createAfter([
                    'name' => $book['genre_name'],
                    'slug' => $book['genre_slug'],
                    'meta_description' => $genreMetaDescriptions[$book['genre_slug']]
                ], $afterId);
            }

            $publisher = Publisher::firstOrCreate(
                ['slug' => $book['publisher_slug']],
                ['name' => $book['publisher_name']]
            );

            $book = Book::firstOrCreate(
                ['slug' => $book['slug']],
                [
                    'genre_id' => $genre->id,
                    'publisher_id' => $publisher->id,
                    'name' => $book['name'],
                    'author' => $book['author'],
                    'description' => $book['description'],
                    'price' => $book['price'],
                    'publisher_year' => $book['publisher_year'],
                    'stock_qty' => $book['stock_qty'],
                    'is_visible' => $book['is_visible'],
                    'meta_title' => $book['meta_title'] ?? null,
                    'meta_description' => $book['meta_description'] ?? null,
                ]
            );

            if ($book->wasRecentlyCreated) $addedCount++;
        }

        $this->line('Writed books: ' . $addedCount);
        $this->info('Finish command');
        return Command::SUCCESS;
    }
}
