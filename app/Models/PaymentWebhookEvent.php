<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Support\Concerns\BelongsToTenant;

class PaymentWebhookEvent extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = array (
  0 => 'tenant_id',
  1 => 'provider',
  2 => 'event_id',
  3 => 'event_type',
  4 => 'payload',
  5 => 'status',
  6 => 'processing_error',
);

    protected function casts(): array
    {
        return array (
  'payload' => 'array',
);
    }
}
