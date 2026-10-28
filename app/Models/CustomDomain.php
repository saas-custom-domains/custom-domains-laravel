<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomDomain extends Model
{
    public const PENDING = 'pending';

    public const ACTIVE = 'active';

    protected $fillable = ['host', 'status', 'https_ready', 'provider_uuid'];

    protected $attributes = ['status' => self::PENDING, 'https_ready' => false];

    protected function casts(): array
    {
        return ['https_ready' => 'boolean'];
    }

    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    public static function isActiveHost(string $host): bool
    {
        return static::where('host', $host)->where('status', self::ACTIVE)->exists();
    }

    public function statusLabel(): string
    {
        return match (true) {
            $this->status !== self::ACTIVE => 'Waiting for DNS',
            ! $this->https_ready => 'DNS OK, waiting for HTTPS',
            default => 'Live',
        };
    }
}
