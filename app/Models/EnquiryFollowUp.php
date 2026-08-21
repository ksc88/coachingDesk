<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Support\Concerns\BelongsToTenant;

class EnquiryFollowUp extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = array (
  0 => 'tenant_id',
  1 => 'enquiry_id',
  2 => 'user_id',
  3 => 'type',
  4 => 'notes',
  5 => 'outcome',
  6 => 'followed_up_at',
  7 => 'next_follow_up_at',
);

    protected function casts(): array
    {
        return array (
  'followed_up_at' => 'datetime',
  'next_follow_up_at' => 'datetime',
);
    }
}
