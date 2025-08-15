<?php

namespace App\Filament\Pages;

use App\Models\Page as PageModel;
use App\Services\ContentExtractor;
use App\Filament\Resources\PageResource;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Filament\Notifications\Notification;
use Filament\Pages\Page as FilamentPage;
use Illuminate\Support\Facades\App;

class ExtractContent extends FilamentPage implements HasForms
{
    use InteractsWithForms;

    // Rely on Filament defaults for navigation icon/label/title/group to avoid signature mismatches.

    protected string $view = 'filament.pages.extract-content';

    // Filament forms store state in an array by default
    public ?array $data = [];

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\TextInput::make('url')
                    ->label('Website URL')
                    ->placeholder('https://example.com')
                    ->url()
                    ->required()
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function submit(): void
    {
        $data = $this->data ?? [];
        $url = (string) ($data['url'] ?? '');
        if (! $url) {
            Notification::make()->title('Please provide a valid URL.')->danger()->send();
            return;
        }

        try {
            /** @var ContentExtractor $extractor */
            $extractor = App::make(ContentExtractor::class);
            $result = $extractor->extract($url);

            $page = PageModel::create([
                'url' => $url,
                'page_type' => null,
                'status' => 'extracted',
                'cleaned_text' => $result['cleaned_text'] ?? '',
                'meta' => [
                    'title' => $result['title'] ?? null,
                ],
            ]);

            Notification::make()
                ->title('Content extracted successfully')
                ->success()
                ->send();

            $this->redirect(PageResource::getUrl());
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Extraction failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}

