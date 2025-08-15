<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PageResource\Pages as ResourcePages;
use App\Models\Page as PageModel;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

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
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->modalHeading('Extracted Content')
                    ->modalContent(fn (PageModel $record) => view('filament.pages.partials.view-cleaned', [
                        'record' => $record,
                    ])),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ResourcePages\ListPages::route('/'),
            'view' => ResourcePages\ViewPage::route('/{record}'),
        ];
    }
}
