<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Support\Concerns\BelongsToTenant;

class NotificationTemplate extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = array (
  0 => 'tenant_id',
  1 => 'key',
  2 => 'channel',
  3 => 'locale',
  4 => 'subject',
  5 => 'body',
  6 => 'provider_template_id',
  7 => 'is_active',
);

    protected function casts(): array
    {
        return array (
  'is_active' => 'boolean',
);
    }
}
