<?php

namespace App\Filament\Resources\PageResource\Pages;

use App\Filament\Resources\PageResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions\Action;

class ListPages extends ListRecords
{
    protected static string $resource = PageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('bulk_actions_help')
                ->label('Help')
                ->icon('heroicon-o-question-mark-circle')
                ->tooltip('Bulk actions: when to use which?')
                ->modalHeading('Bulk actions: when to use which?')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close')
                ->modalContent(view('filament.widgets.bulk-actions-help')),
        ];
    }
}
