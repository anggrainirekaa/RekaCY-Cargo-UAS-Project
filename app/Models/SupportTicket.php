<?php

namespace App\Models;

use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends Model
{
    protected $fillable = ['shipment_id', 'subject', 'category', 'priority'];

    protected function casts(): array
    {
        return ['status' => TicketStatus::class, 'resolved_at' => 'datetime'];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function replies(): HasMany
    {
        return $this->hasMany(SupportReply::class)->oldest();
    }

    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        return $user->isCustomer() ? $q->where('support_tickets.user_id', $user->id) : $q;
    }
}
