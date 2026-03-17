<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Tailor\Models\EntryRecord;
use Responsiv\Currency\Models\Currency;
use File;

class ImportBooks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:import-books';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Imports books from books.json to Tailor models.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $jsonPath = __DIR__ . '/books.json';
        if (!File::exists($jsonPath)) {
            $this->error("File books.json not found at {$jsonPath}");
            return;
        }

        $json = File::get($jsonPath);
        $records = json_decode($json, true);

        if (!is_array($records)) {
            $this->error("Invalid JSON format in books.json");
            return;
        }

        $genreMetaDescriptions = [
            'fantasy' => 'Книги в жанре фэнтези: магия, мифические миры, эпические сражения и захватывающие приключения для любителей фантастических историй.',
            'detective' => 'Детективные книги с запутанными расследованиями, тайнами и неожиданными развязками. Классика и современные детективы.',
            'novel' => 'Романы о жизни, любви и человеческих судьбах. Классические и современные произведения мировой литературы.',
            'non-fiction' => 'Нон-фикшн книги: реальные истории, наука, саморазвитие, биографии и полезные знания без вымысла.',
            'fantasy-fiction' => 'Фантастическая литература и фэнтези: альтернативные миры, будущее человечества и смелые авторские идеи.',
            'business' => 'Книги о бизнесе, финансах и предпринимательстве: стратегии, управление, личная эффективность и развитие карьеры.',
        ];

        $count = count($records);
        $featuredIndices =  $this->getFeaturedId($count);
   

        $this->info("Starting import of {$count} books...");

        foreach ($records as $index => $record) {
            $genreSlug = $record['genre_slug'];
            $genreEntry = EntryRecord::inSection('Catalog\Genre')->where('slug', $genreSlug)->first();
            if (!$genreEntry) {
                $genreEntry = EntryRecord::inSection('Catalog\Genre');
                $genreEntry->title = $record['genre_name'];
                $genreEntry->slug = $genreSlug;
                $genreEntry->is_enabled = true;


                if (isset($genreMetaDescriptions[$genreSlug]))
                    $genreEntry->meta_description = $genreMetaDescriptions[$genreSlug];

                $genreEntry->save();
                $this->info("Created genre: {$genreEntry->title}");
            }

            $publisherEntry = EntryRecord::inSection('Catalog\Publisher')->where('slug', $record['publisher_slug'])->first();
            if (!$publisherEntry) {
                $publisherEntry = EntryRecord::inSection('Catalog\Publisher');
                $publisherEntry->title = $record['publisher_name'];
                $publisherEntry->slug = $record['publisher_slug'];
                $publisherEntry->is_enabled = true;

                $publisherEntry->save();
                $this->info("Created publisher: {$publisherEntry->title}");
            }

            $book = EntryRecord::inSection('Catalog\Book')->where('slug', $record['slug'])->first();
            if (!$book) {
                $book = EntryRecord::inSection('Catalog\Book');
            }

            $book->title = $record['name'];
            $book->slug = $record['slug'];
            $book->is_enabled = true;

            $book->author = $record['author'];
            $book->publisher_year = $record['publisher_year'];
            $book->description = $record['description'];
            $book->price = Currency::getDefault()->toBaseValue($record['price']);
            $book->stock_qty = $record['stock_qty'];

            $book->meta_title = $record['meta_title'];
            $book->meta_description = $record['meta_description'];
            $book->is_featured = in_array($index, $featuredIndices);

            $book->genre = $genreEntry;
            $book->publisher = $publisherEntry;

            $book->save();

            if ($book->wasRecentlyCreated)
                $this->info("NEW book created: {$book->title}");
            elseif ($book->wasChanged())
                $this->info("Book updated: {$book->title}");
        }

        $this->info('Import completed successfully!');
    }

    private function getFeaturedId(int $count)
    {
        if ($count <= 0) return [];

        $featuredWeights = [];

        for ($i = 0; $i < $count; $i++) {
            $featuredWeights[$i] = mt_rand() / mt_getrandmax();
        }
        $featuredWeights[0] = 2;

        arsort($featuredWeights);

        $topFeatured = array_slice($featuredWeights, 0, 16, true);

        return array_keys($topFeatured);
    }
}
