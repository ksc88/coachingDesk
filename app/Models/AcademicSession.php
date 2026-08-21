<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Support\Concerns\BelongsToTenant;

class AcademicSession extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = array (
  0 => 'tenant_id',
  1 => 'name',
  2 => 'starts_on',
  3 => 'ends_on',
  4 => 'is_current',
);

    protected function casts(): array
    {
        return array (
  'starts_on' => 'date',
  'ends_on' => 'date',
  'is_current' => 'boolean',
);
    }
}
