<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Support\Concerns\BelongsToTenant;

class StudentFeeOverride extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = array (
  0 => 'tenant_id',
  1 => 'student_id',
  2 => 'fee_plan_id',
  3 => 'discount_type',
  4 => 'discount_value',
  5 => 'reason',
  6 => 'approved_by',
);

    protected function casts(): array
    {
        return array (
  'discount_value' => 'decimal:2',
);
    }
}
