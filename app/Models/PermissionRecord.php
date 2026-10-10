<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $key
 * @property string $label
 * @property string $group
 */
#[Fillable(['key', 'label', 'group'])]
class PermissionRecord extends Model
{
    use HasUuids;

    protected $table = 'permissions';
}
