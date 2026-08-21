<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Support\Concerns\BelongsToTenant;

class AttendanceCorrection extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = array (
  0 => 'tenant_id',
  1 => 'attendance_record_id',
  2 => 'from_status',
  3 => 'to_status',
  4 => 'reason',
  5 => 'corrected_by',
);

    protected function casts(): array
    {
        return array (
);
    }
}
