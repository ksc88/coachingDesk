<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Support\Concerns\BelongsToTenant;

class Announcement extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = array (
  0 => 'tenant_id',
  1 => 'created_by',
  2 => 'title',
  3 => 'body',
  4 => 'scope',
  5 => 'branch_id',
  6 => 'batch_id',
  7 => 'category_id',
  8 => 'attachment_path',
  9 => 'status',
  10 => 'published_at',
  11 => 'scheduled_at',
);

    protected function casts(): array
    {
        return array (
  'published_at' => 'datetime',
  'scheduled_at' => 'datetime',
);
    }
}
