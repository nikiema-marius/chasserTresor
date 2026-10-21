<?php

namespace App\Livewire\Admin;

use App\Livewire\Admin\Concerns\InteractsWithAdmin;
use App\Models\Admin;
use App\Models\AuditLog;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Journal des événements sensibles (lecture seule).
 */
#[Layout('components.layouts.admin')]
#[Title('Journal de sécurité')]
class AuditLogs extends Component
{
    use InteractsWithAdmin, WithPagination;

    #[Url]
    public string $event = '';

    public function mount(): void
    {
        $this->authorizeAdmin('super');
    }

    public function updatedEvent(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $logs = AuditLog::query()
            ->when($this->event, fn ($q) => $q->where('event', 'like', mb_substr($this->event, 0, 64).'%'))
            ->latest('id')
            ->paginate(50);

        return view('livewire.admin.audit-logs', [
            'logs' => $logs,
            'admins' => Admin::query()->pluck('name', 'id'),
            'events' => AuditLog::query()->distinct()->orderBy('event')->pluck('event'),
        ]);
    }
}
