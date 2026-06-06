<?php

namespace App\Http\Livewire;

use App\Models\ContentAudio;
use App\Models\Content;
use Livewire\Component;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Log;

class TimingEditor extends Component implements HasForms, HasActions
{
    use InteractsWithForms;
    use InteractsWithActions;

    public ?ContentAudio $audio;
    public ?string $textContent = '';
    public array $words = [];
    public array $timingData = [];

    public function mount(int $audioId): void
    {
        $this->audio = ContentAudio::with('chapter.content')->find($audioId);
        if (!$this->audio) {
            // Handle error: Audio not found
            return;
        }

        // Find the specific content for the chapter and book
        $content = Content::where('chapters_id', $this->audio->chapters_id)
                          ->where('kotob_id', $this->audio->kotob_id)
                          ->first();

        $this->textContent = $content ? $content->text : 'محتوایی یافت نشد.';
        
        // Strip HTML tags and split into words
        $plainText = strip_tags($this->textContent);
        // Handle various whitespace characters
        $this->words = preg_split('/\s+/u', $plainText, -1, PREG_SPLIT_NO_EMPTY);

        $this->timingData = $this->audio->timing ?? [];
    }

    public function saveTimingData($newTimingData)
    {
        $this->timingData = $newTimingData;
        $this->audio->timing = $this->timingData;
        $this->audio->save();
        
        $this->dispatchBrowserEvent('close-modal', ['id' => 'timing-editor-modal']);
        $this->dispatchBrowserEvent('notify', ['message' => 'زمان‌بندی با موفقیت ذخیره شد!', 'type' => 'success']);
    }
    
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('ذخیره')
                ->submit('save'),
        ];
    }

    public function render()
    {
        return view('livewire.timing-editor');
    }
}
