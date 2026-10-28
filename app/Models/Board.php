<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;

class Board extends Model
{
    protected $fillable = ['slug', 'name', 'tagline'];

    public function jobPostings(): HasMany
    {
        return $this->hasMany(JobPosting::class);
    }

    public function customDomains(): HasMany
    {
        return $this->hasMany(CustomDomain::class);
    }

    // The one lookup every request on a customer domain depends on.
    // Pending and unknown domains get null, so they 404.
    public static function forActiveHost(string $host): ?self
    {
        return static::whereRelation('customDomains', fn ($query) => $query
            ->where('host', $host)
            ->where('status', CustomDomain::ACTIVE)
        )->first();
    }

    // The board ResolveBoard found for this request.
    public static function current(Request $request): self
    {
        return $request->attributes->get('board');
    }
}
