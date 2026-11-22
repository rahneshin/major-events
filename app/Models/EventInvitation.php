<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EventRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventInvitation extends Model
{
    protected $fillable = [
        'event_id',
        'phone',
        'role',
        'token_hash',
        'invited_by',
        'expires_at',
        'accepted_at',
        'status',
        'committee_id',
        'post',
        'is_head',
        'invitee_name',
    ];

    /** @var list<string> */
    protected $hidden = ['token_hash'];

    /**
     * توکن خام فقط در همان درخواستِ ساخت در حافظه است (برای ساخت لینک/پیامک)؛ در DB ذخیره نمی‌شود.
     */
    public ?string $plainToken = null;

    public static function hashToken(string $plain): string
    {
        return hash('sha256', $plain);
    }

    public static function findByToken(string $plain): ?self
    {
        $hash = self::hashToken($plain);

        $invitation = self::query()->where('token_hash', $hash)->first();

        return $invitation !== null && hash_equals($invitation->token_hash, $hash)
            ? $invitation
            : null;
    }

    protected function casts(): array
    {
        return [
            'role' => EventRole::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'is_head' => 'boolean',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function committee(): BelongsTo
    {
        return $this->belongsTo(Committee::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending' && $this->expires_at->isFuture();
    }
}
