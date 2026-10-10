<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\PageVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $page_id
 * @property string $title
 * @property string $body
 * @property string|null $created_by
 * @property CarbonInterface $created_at
 */
#[Fillable(['page_id', 'title', 'body', 'created_by'])]
class PageVersion extends Model
{
    /** @use HasFactory<PageVersionFactory> */
    use HasFactory, HasUuids;

    const UPDATED_AT = null;

    /**
     * @return BelongsTo<Page, $this>
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
