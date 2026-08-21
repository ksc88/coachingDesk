<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Support\Concerns\BelongsToTenant;

class AuditLog extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = array (
  0 => 'tenant_id',
  1 => 'user_id',
  2 => 'action',
  3 => 'auditable_type',
  4 => 'auditable_id',
  5 => 'old_values',
  6 => 'new_values',
  7 => 'ip_address',
);

    protected function casts(): array
    {
        return array (
  'old_values' => 'array',
  'new_values' => 'array',
);
    }
}
