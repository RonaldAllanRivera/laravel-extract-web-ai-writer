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

class PageResource extends Resource
{
    protected static ?string $model = PageModel::class;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('url')->searchable()->wrap()->limit(60),
                TextColumn::make('page_type')->label('Type')->sortable(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->recordUrl(fn (PageModel $record) => static::getUrl('view', ['record' => $record]))
            ->defaultSort('created_at', 'desc')
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
                                $page->save();
                                $updated++;
                            } catch (\Throwable $e) {
                                $errors++;
                            }
                        }
                        Notification::make()
                            ->title("Re-cleaned {$updated} record(s)")
                            ->body($errors ? "{$errors} error(s) occurred." : null)
                            ->success()
                            ->send();
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
                                $page->save();
                                $updated++;
                            } catch (\Throwable $e) {
                                $errors++;
                            }
                        }
                        Notification::make()
                            ->title("Refetched & re-cleaned {$updated} record(s)")
                            ->body($errors ? "{$errors} error(s) occurred." : null)
                            ->success()
                            ->send();
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

