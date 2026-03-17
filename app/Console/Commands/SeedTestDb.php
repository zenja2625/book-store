<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Tailor\Models\EntryRecord;
use Responsiv\Currency\Models\Currency;
use File;
use DB;
use Carbon\Carbon;

class SeedTestDb extends Command
{
    const TOTAL_BOOKS = 50000;
    const CHUNK_SIZE = 500;
    const ACTIVE_BOOK_DISCOUNTS_COUNT = 30000;
    const INACTIVE_DISCOUNTS_COUNT = 100000;
    const ACTIVE_DISCOUNTS_PER_GENRE_PER_TYPE = 2; // 2 pct + 2 fixed = 4 total
    const ACTIVE_DISCOUNTS_PER_PUBLISHER_PER_TYPE = 2; // 2 pct + 2 fixed = 4 total
    const INACTIVE_BOOKS_RATIO = 0.9;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:seed-test-db';

    /**
     * Genre meta descriptions for on-the-fly creation.
     */
    protected array $genreMetaDescriptions = [
        'fantasy' => 'Книги в жанре фэнтези: магия, мифические миры, эпические сражения и захватывающие приключения для любителей фантастических историй.',
        'detective' => 'Детективные книги с запутанными расследованиями, тайнами и неожиданными развязками. Классика и современные детективы.',
        'novel' => 'Романы о жизни, любви и человеческих судьбах. Классические и современные произведения мировой литературы.',
        'non-fiction' => 'Нон-фикшн книги: реальные истории, наука, саморазвитие, биографии и полезные знания без вымысла.',
        'fantasy-fiction' => 'Фантастическая литература и фэнтези: альтернативные миры, будущее человечества и смелые авторские идеи.',
        'business' => 'Книги о бизнесе, финансах и предпринимательстве: стратегии, управление, личная эффективность и развитие карьеры.',
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("Starting the app:seed-test-db command...");

        $jsonPath = __DIR__ . '/books.json';
        if (!File::exists($jsonPath)) {
            $this->error("File books.json not found at {$jsonPath}");
            return;
        }

        $records = json_decode(File::get($jsonPath), true);
        if (empty($records)) {
            $this->error("books.json is empty or invalid.");
            return;
        }

        $this->info("Caching genres and publishers...");
        $genres = EntryRecord::inSection('Catalog\Genre')->get()->keyBy('slug');
        $publishers = EntryRecord::inSection('Catalog\Publisher')->get()->keyBy('slug');

        // 1. Create 50,000 books
        $this->createBooks($records, $genres, $publishers);

        // Fetch all books for discount assignment
        $this->info("Fetching all IDs for discount generation...");
        $allBookIds = EntryRecord::inSection('Catalog\Book')->pluck('id')->toArray();
        $allGenreIds = EntryRecord::inSection('Catalog\Genre')->pluck('id')->toArray();
        $allPublisherIds = EntryRecord::inSection('Catalog\Publisher')->pluck('id')->toArray();

        // 2. Create active discounts per genre
        $this->createActiveGenreDiscounts($allGenreIds);

        // 3. Create active discounts per publisher
        $this->createActivePublisherDiscounts($allPublisherIds);

        // 4. Create active discounts for books
        $this->createActiveBookDiscounts($allBookIds, self::ACTIVE_BOOK_DISCOUNTS_COUNT);

        // 5. Create inactive discounts
        $this->createInactiveDiscounts($allBookIds, $allGenreIds, $allPublisherIds, self::INACTIVE_DISCOUNTS_COUNT);

        $this->info("Done! Seed process completed successfully.");
    }

    private function createBooks($records, $genres, $publishers)
    {
        $targetCount = self::TOTAL_BOOKS;
        $this->info("Generating {$targetCount} books based on original records...");
        
        $bar = $this->output->createProgressBar($targetCount);
        $bar->start();

        $chunkSize = self::CHUNK_SIZE;
        $totalOriginal = count($records);
        $baseCurrency = Currency::getDefault();

        DB::beginTransaction();

        for ($i = 1; $i <= $targetCount; $i++) {
            $recordIndex = ($i - 1) % $totalOriginal;
            $originalRecord = $records[$recordIndex];
            
            // Handle Genre on the fly
            $genreSlug = $originalRecord['genre_slug'];
            if (!$genres->has($genreSlug)) {
                $genreEntry = EntryRecord::inSection('Catalog\Genre');
                $genreEntry->title = $originalRecord['genre_name'];
                $genreEntry->slug = $genreSlug;
                $genreEntry->is_enabled = true;
                if (isset($this->genreMetaDescriptions[$genreSlug])) {
                    $genreEntry->meta_description = $this->genreMetaDescriptions[$genreSlug];
                }
                $genreEntry->save();
                $genres->put($genreSlug, $genreEntry);
            }

            // Handle Publisher on the fly
            $publisherSlug = $originalRecord['publisher_slug'];
            if (!$publishers->has($publisherSlug)) {
                $publisherEntry = EntryRecord::inSection('Catalog\Publisher');
                $publisherEntry->title = $originalRecord['publisher_name'];
                $publisherEntry->slug = $publisherSlug;
                $publisherEntry->is_enabled = true;
                $publisherEntry->save();
                $publishers->put($publisherSlug, $publisherEntry);
            }

            $book = EntryRecord::inSection('Catalog\Book');
            $book->title = $originalRecord['name'] . ' - Copy ' . $i;
            $book->slug = $originalRecord['slug'] . '-' . $i;
            $book->is_enabled = true;
            $book->author = $originalRecord['author'];
            $book->publisher_year = $originalRecord['publisher_year'];
            $book->description = $originalRecord['description'];
            $book->price = $baseCurrency ? $baseCurrency->toBaseValue($originalRecord['price']) : $originalRecord['price'];
            $book->stock_qty = $originalRecord['stock_qty'];
            $book->meta_title = $originalRecord['meta_title'];
            $book->meta_description = $originalRecord['meta_description'];
            $book->is_featured = false;

            $book->genre = $genres->get($genreSlug);
            $book->publisher = $publishers->get($publisherSlug);

            $book->save();
            $bar->advance();

            if ($i % $chunkSize === 0) {
                DB::commit();
                DB::beginTransaction();
            }

            unset($book);
        }

        DB::commit();
        $bar->finish();
        $this->newLine();
    }

    private function createActiveGenreDiscounts($genreIds)
    {
        if (empty($genreIds)) return;

        $totalPerGenre = self::ACTIVE_DISCOUNTS_PER_GENRE_PER_TYPE * 2;
        $total = count($genreIds) * $totalPerGenre;
        $this->info("Creating active discounts per genre...");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        DB::beginTransaction();
        $count = 0;
        foreach ($genreIds as $genreId) {
            for ($i = 0; $i < self::ACTIVE_DISCOUNTS_PER_GENRE_PER_TYPE; $i++) {
                $this->makeDiscount("genre-{$genreId}-pct-{$i}", 'percentage', rand(1, 100), 'genres', [$genreId]);
                $bar->advance();
            }
            for ($i = 0; $i < self::ACTIVE_DISCOUNTS_PER_GENRE_PER_TYPE; $i++) {
                $this->makeDiscount("genre-{$genreId}-fxd-{$i}", 'fixed_amount', rand(1, 150), 'genres', [$genreId]);
                $bar->advance();
            }
            if (++$count % 100 === 0) {
                DB::commit();
                DB::beginTransaction();
            }
        }
        DB::commit();
        $bar->finish();
        $this->newLine();
    }

    private function createActivePublisherDiscounts($publisherIds)
    {
        if (empty($publisherIds)) return;

        $totalPerPublisher = self::ACTIVE_DISCOUNTS_PER_PUBLISHER_PER_TYPE * 2;
        $total = count($publisherIds) * $totalPerPublisher;
        $this->info("Creating active discounts per publisher...");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        DB::beginTransaction();
        $count = 0;
        foreach ($publisherIds as $publisherId) {
            for ($i = 0; $i < self::ACTIVE_DISCOUNTS_PER_PUBLISHER_PER_TYPE; $i++) {
                $this->makeDiscount("publisher-{$publisherId}-pct-{$i}", 'percentage', rand(1, 100), 'publishers', [$publisherId]);
                $bar->advance();
            }
            for ($i = 0; $i < self::ACTIVE_DISCOUNTS_PER_PUBLISHER_PER_TYPE; $i++) {
                $this->makeDiscount("publisher-{$publisherId}-fxd-{$i}", 'fixed_amount', rand(1, 150), 'publishers', [$publisherId]);
                $bar->advance();
            }
            if (++$count % 100 === 0) {
                DB::commit();
                DB::beginTransaction();
            }
        }
        DB::commit();
        $bar->finish();
        $this->newLine();
    }

    private function createActiveBookDiscounts($bookIds, $targetCount)
    {
        $this->info("Creating {$targetCount} active discounts for books...");
        $bar = $this->output->createProgressBar($targetCount);
        $bar->start();

        $maxBookIdx = count($bookIds) - 1;

        DB::beginTransaction();
        for ($i = 1; $i <= $targetCount; $i++) {
            $bookId = $bookIds[mt_rand(0, $maxBookIdx)];
            $isPct = ($i % 2 === 0);
            $group = $isPct ? 'percentage' : 'fixed_amount';
            $val = $isPct ? rand(1, 100) : rand(1, 150);
            
            $this->makeDiscount("book-active-{$i}", $group, $val, 'books', [$bookId]);

            $bar->advance();

            if ($i % self::CHUNK_SIZE === 0) {
                DB::commit();
                DB::beginTransaction();
            }
        }
        DB::commit();
        $bar->finish();
        $this->newLine();
    }

    private function createInactiveDiscounts($bookIds, $genreIds, $publisherIds, $targetCount)
    {
        $this->info("Creating {$targetCount} inactive discounts...");
        $bar = $this->output->createProgressBar($targetCount);
        $bar->start();

        $maxBookIdx = count($bookIds) - 1;
        $maxGenreIdx = count($genreIds) - 1 ?: 0;
        $maxPublisherIdx = count($publisherIds) - 1 ?: 0;

        DB::beginTransaction();
        for ($i = 1; $i <= $targetCount; $i++) {
            $isPct = (mt_rand(0, 1) === 1);
            $group = $isPct ? 'percentage' : 'fixed_amount';
            $val = $isPct ? rand(1, 100) : rand(1, 150);
            
            // 90% books
            if ($i <= ($targetCount * self::INACTIVE_BOOKS_RATIO)) {
                $bookId = $bookIds[mt_rand(0, $maxBookIdx)];
                $this->makeDiscount("inactive-book-{$i}", $group, $val, 'books', [$bookId], true);
            } else {
                // 10% randomly to genre or publisher
                if (mt_rand(0, 1) === 1 && !empty($genreIds)) {
                    $genreId = $genreIds[mt_rand(0, $maxGenreIdx)];
                    $this->makeDiscount("inactive-genre-{$i}", $group, $val, 'genres', [$genreId], true);
                } elseif (!empty($publisherIds)) {
                    $pubId = $publisherIds[mt_rand(0, $maxPublisherIdx)];
                    $this->makeDiscount("inactive-pub-{$i}", $group, $val, 'publishers', [$pubId], true);
                }
            }

            $bar->advance();

            if ($i % self::CHUNK_SIZE === 0) {
                DB::commit();
                DB::beginTransaction();
            }
        }
        DB::commit();
        $bar->finish();
        $this->newLine();
    }

    private function makeDiscount($slug, $group, $value, $relationType, $relationIds, $isInactive = false)
    {
        $discount = EntryRecord::inSection('Catalog\Discount');
        $discount->title = $slug; // Tailor records require a title
        $discount->slug = $slug;
        $discount->content_group = $group;
        $discount->is_enabled = true;

        if ($group === 'percentage') {
            $discount->discount_pct = $value;
        } else {
            $discount->discount_num = $value;
        }

        if ($isInactive) {
            $discount->expired_at = Carbon::now()->subDays(rand(1, 30));
        }

        $discount->save();
        
        if (!empty($relationIds)) {
            $discount->$relationType()->sync($relationIds);
        }

        unset($discount);
    }
}
