<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Support\Concerns\BelongsToTenant;

class FeePlan extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = array (
  0 => 'tenant_id',
  1 => 'batch_id',
  2 => 'name',
  3 => 'frequency',
  4 => 'amount',
  5 => 'installments',
  6 => 'is_active',
);

    protected function casts(): array
    {
        return array (
  'amount' => 'decimal:2',
  'is_active' => 'boolean',
);
    }
}
