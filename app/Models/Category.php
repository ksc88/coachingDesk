<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Support\Concerns\BelongsToTenant;

class Category extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = array (
  0 => 'tenant_id',
  1 => 'name',
  2 => 'slug',
  3 => 'description',
  4 => 'is_active',
);

    protected function casts(): array
    {
        return array (
  'is_active' => 'boolean',
);
    }
}
