<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Models\Page;
use App\Services\ContentExtractor;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('pages:reclean {--refetch}', function () {
    $refetch = (bool) $this->option('refetch');
    $count = Page::count();
    $this->info("Re-cleaning {$count} page(s) " . ($refetch ? 'with refetch' : 'without refetch') . '...');

    $extractor = new ContentExtractor();
    $processed = 0;
    $updated = 0;
    $errors = 0;

    Page::chunkById(100, function ($pages) use ($refetch, $extractor, &$processed, &$updated, &$errors) {
        foreach ($pages as $page) {
            try {
                if ($refetch) {
                    $data = $extractor->extract($page->url);
                    $page->cleaned_text = $data['cleaned_text'] ?? '';
                    $meta = $page->meta ?? [];
                    if (!empty($data['title'])) {
                        $meta['title'] = $data['title'];
                    }
                    $page->meta = $meta;
                } else {
                    $page->cleaned_text = $extractor->recleanText($page->cleaned_text ?? '');
                }
                $page->save();
                $updated++;
            } catch (\Throwable $e) {
                $errors++;
                $this->error("#{$page->id} {$page->url} -> " . $e->getMessage());
            } finally {
                $processed++;
            }
        }
    });

    $this->info("Done. Processed {$processed}/{$count}. Updated: {$updated}. Errors: {$errors}.");
})->purpose('Re-clean all Page records; use --refetch to re-download HTML before cleaning');
