<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Support\Concerns\BelongsToTenant;

class TenantPaymentGateway extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = array (
  0 => 'tenant_id',
  1 => 'provider',
  2 => 'mode',
  3 => 'key_id',
  4 => 'key_secret',
  5 => 'webhook_secret',
  6 => 'oauth_access_token',
  7 => 'oauth_refresh_token',
  8 => 'oauth_expires_at',
  9 => 'account_id',
  10 => 'onboarding_status',
  11 => 'enabled_methods',
  12 => 'is_active',
);

    protected function casts(): array
    {
        return array (
  'oauth_expires_at' => 'datetime',
  'enabled_methods' => 'array',
  'is_active' => 'boolean',
  'key_id' => 'encrypted',
  'key_secret' => 'encrypted',
  'webhook_secret' => 'encrypted',
  'oauth_access_token' => 'encrypted',
  'oauth_refresh_token' => 'encrypted',
);
    }
}
