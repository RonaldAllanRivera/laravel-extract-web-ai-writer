<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Models\Page;
use App\Services\ContentExtractor;
use Illuminate\Support\Str;

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
                    // Success metadata
                    $page->last_fetched_at = now();
                    $page->http_status = $data['http_status'] ?? null;
                    $page->content_length = $data['content_length'] ?? null;
                    $page->fetch_error = null;
                } else {
                    $page->cleaned_text = $extractor->recleanText($page->cleaned_text ?? '');
                }
                $page->save();
                $updated++;
            } catch (\Throwable $e) {
                $errors++;
                // Failure metadata
                $page->last_fetched_at = now();
                $code = (int) ($e->getCode() ?: 0);
                $page->http_status = $code > 0 ? $code : null;
                $page->content_length = null;
                $msg = trim($e->getMessage());
                $page->fetch_error = Str::limit($msg, 500);
                $page->save();
                $this->error("#{$page->id} {$page->url} -> " . $e->getMessage());
            } finally {
                $processed++;
            }
        }
    });

    $this->info("Done. Processed {$processed}/{$count}. Updated: {$updated}. Errors: {$errors}.");
})->purpose('Re-clean all Page records; use --refetch to re-download HTML before cleaning');
