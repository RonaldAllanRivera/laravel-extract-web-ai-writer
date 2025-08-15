<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PageResource\Pages as ResourcePages;
use App\Models\Page as PageModel;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Infolists\Components\TextEntry;
use App\Services\ContentExtractor;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Filament\Actions\BulkAction;
use Illuminate\Support\Str;

class PageResource extends Resource
{
    protected static ?string $model = PageModel::class;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('url')->searchable()->wrap()->limit(60),
                TextColumn::make('page_type')->label('Type')->sortable(),
                TextColumn::make('http_status')
                    ->label('HTTP')
                    ->sortable()
                    ->badge()
                    ->color(fn ($state) => $state === null ? 'gray' : (($state >= 200 && $state < 300) ? 'success' : 'danger'))
                    ->tooltip(function (PageModel $record) {
                        $status = $record->http_status;
                        $len = $record->content_length;
                        $when = $record->last_fetched_at ? $record->last_fetched_at->toDateTimeString() : null;
                        if (!empty($record->fetch_error)) {
                            $head = 'HTTP ' . ($status !== null ? $status : '—') . ($when ? ' • ' . $when : '');
                            return trim($head . "\n" . $record->fetch_error);
                        }
                        $parts = [];
                        $parts[] = 'HTTP ' . ($status !== null ? $status : '—');
                        if ($len) { $parts[] = number_format($len) . ' B'; }
                        if ($when) { $parts[] = $when; }
                        return implode(' • ', $parts);
                    }),
                TextColumn::make('last_fetched_at')->label('Fetched')->dateTime()->sortable(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->recordUrl(fn (PageModel $record) => static::getUrl('view', ['record' => $record]))
            ->defaultSort('created_at', 'desc')
            ->filters([
                \Filament\Tables\Filters\Filter::make('fetch_failed')
                    ->label('Fetch failed')
                    ->query(function ($query) {
                        return $query->whereNotNull('fetch_error')
                            ->orWhere(function ($q) {
                                $q->whereNotNull('http_status')
                                  ->where(function ($qq) {
                                      $qq->where('http_status', '<', 200)
                                         ->orWhere('http_status', '>=', 300);
                                  });
                            });
                    }),
            ])
            ->bulkActions([
                BulkAction::make('reclean')
                    ->label('Re-clean selected')
                    ->tooltip('Re-run the cleaner on already stored text. No network requests. Use after updating cleaning rules or to normalize spacing/boilerplate removal.')
                    ->action(function (Collection $records) {
                        $extractor = new ContentExtractor();
                        $updated = 0;
                        $errors = 0;
                        foreach ($records as $page) {
                            try {
                                $page->cleaned_text = $extractor->recleanText($page->cleaned_text ?? '');
                                // Do not modify fetch metadata for reclean-only
                                $page->save();
                                $updated++;
                            } catch (\Throwable $e) {
                                $errors++;
                            }
                        }
                        $note = Notification::make()
                            ->title("Re-cleaned {$updated} record(s)")
                            ->body($errors ? "{$errors} error(s) occurred." : null);
                        if ($errors) { $note->danger(); } else { $note->success(); }
                        $note->send();
                    }),
                BulkAction::make('refetch_reclean')
                    ->label('Refetch & re-clean selected')
                    ->tooltip('Download pages again, then apply cleaner. Updates cleaned_text and possibly meta.title. Slower; obeys HTTP guardrails (2xx, HTML/XHTML, size limits, redirects).')
                    ->action(function (Collection $records) {
                        $extractor = new ContentExtractor();
                        $updated = 0;
                        $errors = 0;
                        foreach ($records as $page) {
                            try {
                                $data = $extractor->extract($page->url);
                                $page->cleaned_text = $data['cleaned_text'] ?? '';
                                $meta = $page->meta ?? [];
                                if (!empty($data['title'])) {
                                    $meta['title'] = $data['title'];
                                }
                                $page->meta = $meta;
                                // Metadata on success
                                $page->last_fetched_at = now();
                                $page->http_status = $data['http_status'] ?? null;
                                $page->content_length = $data['content_length'] ?? null;
                                $page->fetch_error = null;
                                $page->save();
                                $updated++;
                            } catch (\Throwable $e) {
                                // Persist failure metadata
                                $page->last_fetched_at = now();
                                $code = (int) ($e->getCode() ?: 0);
                                $page->http_status = $code > 0 ? $code : null;
                                $page->content_length = null;
                                $msg = trim($e->getMessage());
                                $page->fetch_error = Str::limit($msg, 500);
                                $page->save();
                                $errors++;
                            }
                        }
                        $note = Notification::make()
                            ->title("Refetched & re-cleaned {$updated} record(s)")
                            ->body($errors ? "{$errors} error(s) occurred." : null);
                        if ($errors) { $note->danger(); } else { $note->success(); }
                        $note->send();
                    }),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextEntry::make('url')->label('URL')->copyable()->columnSpanFull(),
                TextEntry::make('page_type')->label('Type'),
                TextEntry::make('status')->label('Status'),
                TextEntry::make('meta.title')->label('Title')->placeholder('-'),
                TextEntry::make('http_status')->label('HTTP Status')->placeholder('-'),
                TextEntry::make('last_fetched_at')->label('Last Fetched')->dateTime()->placeholder('-'),
                TextEntry::make('content_length')->label('Content Length')->placeholder('-'),
                TextEntry::make('fetch_error')->label('Fetch Error')->visible(fn ($record) => !empty($record->fetch_error))->columnSpanFull(),
                TextEntry::make('cleaned_text')
                    ->label('Content')
                    ->copyable()
                    ->columnSpanFull()
                    ->html()
                    ->formatStateUsing(fn ($state) => nl2br(e($state))),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ResourcePages\ListPages::route('/'),
            'view' => ResourcePages\ViewPage::route('/{record}'),
        ];
    }
}

