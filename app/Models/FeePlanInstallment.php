<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FeePlanInstallment extends Model
{
    use HasFactory;

    protected $fillable = array (
  0 => 'fee_plan_id',
  1 => 'label',
  2 => 'amount',
  3 => 'due_on',
  4 => 'sequence',
);

    protected function casts(): array
    {
        return array (
  'amount' => 'decimal:2',
  'due_on' => 'date',
);
    }
}
