<?php

namespace App\Models;

use App\Enums\RegistrationStatus;
use App\Services\QrCodeService;
use Database\Factories\RegistrationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Registration extends Model
{
    /** @use HasFactory<RegistrationFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'registration_code',
        'user_id',
        'event_id',
        'ticket_type_id',
        'status',
        'qr_code_path',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RegistrationStatus::class,
        ];
    }

    /**
     * Get the registered participant.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the registered event.
     *
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Get the registered ticket type.
     *
     * @return BelongsTo<TicketType, $this>
     */
    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }

    /**
     * Get the check-in record for this registration.
     *
     * @return HasOne<CheckIn, $this>
     */
    public function checkIn(): HasOne
    {
        return $this->hasOne(CheckIn::class);
    }

    /**
     * Determine whether this registration has been checked in.
     */
    public function isCheckedIn(): bool
    {
        return $this->checkIn()->exists();
    }

    /**
     * Generate a unique registration code formatted like EVENT-REG-XXXXXXXX.
     */
    public static function generateUniqueCode(): string
    {
        do {
            $code = 'EVENT-REG-'.strtoupper(Str::random(8));
        } while (static::where('registration_code', $code)->exists());

        return $code;
    }

    /**
     * Get the public URL for the QR code image.
     */
    public function getQrCodeUrlAttribute(): ?string
    {
        if (! $this->qr_code_path) {
            return null;
        }

        return Storage::disk('public')->url($this->qr_code_path);
    }

    /**
     * Get the pure SVG string for the QR code.
     */
    public function getQrCodeSvgAttribute(): string
    {
        return app(QrCodeService::class)->getOrGenerateSvg($this);
    }
}
