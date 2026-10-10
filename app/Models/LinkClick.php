<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\LinkClickFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $link_id
 * @property CarbonInterface $clicked_at
 * @property string|null $country_code
 * @property string|null $country
 * @property string|null $city
 * @property string|null $browser
 * @property string|null $os
 * @property string|null $device_type
 * @property string|null $entry
 * @property string|null $source
 */
#[Fillable(['link_id', 'clicked_at', 'country_code', 'country', 'city', 'browser', 'os', 'device_type', 'entry', 'source'])]
class LinkClick extends Model
{
    /** @use HasFactory<LinkClickFactory> */
    use HasFactory, HasUuids;

    protected $table = 'link_statistics';

    public $timestamps = false;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'clicked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Link, $this>
     */
    public function link(): BelongsTo
    {
        return $this->belongsTo(Link::class);
    }
}
