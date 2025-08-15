<?php

namespace App\Filament\Resources\PageResource\Pages;

use App\Filament\Resources\PageResource;
use App\Models\GeneratedContent;
use App\Services\AiRewriter;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewPage extends ViewRecord
{
    protected static string $resource = PageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('generate_ai')
                ->label('Generate AI')
                ->icon('heroicon-o-sparkles')
                ->modalHeading('Generate AI Content')
                ->form([
                    Forms\Components\Select::make('layout')
                        ->label('Layout')
                        ->options([
                            'interstitial' => 'Interstitial',
                            'advertorial' => 'Advertorial',
                        ])
                        ->required(),
                ])
                ->action(function (array $data) {
                    /** @var \App\Models\Page $record */
                    $record = $this->record;
                    $layout = (string) ($data['layout'] ?? '');

                    if (! $record->cleaned_text) {
                        Notification::make()
                            ->title('No cleaned content to generate from')
                            ->danger()
                            ->send();
                        return;
                    }

                    try {
                        $rewriter = new AiRewriter();
                        $res = $rewriter->generate($layout, $record->cleaned_text);
                        GeneratedContent::create([
                            'page_id' => $record->id,
                            'layout' => $layout,
                            'status' => 'success',
                            'content' => $res['content'] ?? null,
                            'error' => null,
                            'ai_model' => $res['ai_model'] ?? null,
                            'tokens_input' => $res['tokens_input'] ?? null,
                            'tokens_output' => $res['tokens_output'] ?? null,
                            'temperature' => $res['temperature'] ?? null,
                            'provider' => $res['provider'] ?? null,
                            'prompt_version' => $res['prompt_version'] ?? null,
                        ]);
                        Notification::make()
                            ->title('AI content generated')
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        GeneratedContent::create([
                            'page_id' => $record->id,
                            'layout' => $layout,
                            'status' => 'error',
                            'content' => null,
                            'error' => \Illuminate\Support\Str::limit($e->getMessage(), 500),
                        ]);
                        Notification::make()
                            ->title('Generation failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
