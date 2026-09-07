<?php

namespace App\Models;

use App\Enums\RegistrationStatus;
use Database\Factories\TicketTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketType extends Model
{
    /** @use HasFactory<TicketTypeFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'event_id',
        'name',
        'description',
        'price',
        'quota',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'quota' => 'integer',
        ];
    }

    /**
     * Get the event that owns the ticket type.
     *
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Get the registrations for this ticket type.
     *
     * @return HasMany<Registration, $this>
     */
    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    /**
     * Calculate the remaining available quota.
     */
    public function remainingQuota(): int
    {
        $used = $this->registrations()
            ->where('status', '!=', RegistrationStatus::Cancelled->value)
            ->count();

        return max(0, $this->quota - $used);
    }

    /**
     * Determine whether the ticket type is sold out.
     */
    public function isSoldOut(): bool
    {
        return $this->remainingQuota() <= 0;
    }
}
