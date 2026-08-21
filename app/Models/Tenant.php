<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'code', 'email', 'phone', 'logo_path', 'primary_color',
        'gstin', 'address', 'timezone', 'locale', 'status', 'settings',
    ];

    protected function casts(): array
    {
        return ['settings' => 'array'];
    }

    /**
     * Academic / school-type coachings keep a student in exactly one batch;
     * subject-wise coachings allow several.
     */
    public function usesSingleBatch(): bool
    {
        return (bool) ($this->settings['single_batch_mode'] ?? false);
    }

    /**
     * Parent alerts stay in the log/outbox until a coaching turns live send on
     * and a real WhatsApp or SMS adapter is configured.
     */
    public function alertsAreLive(): bool
    {
        return ($this->settings['alerts']['mode'] ?? 'safe') === 'live';
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function academicSessions(): HasMany
    {
        return $this->hasMany(AcademicSession::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function paymentGateways(): HasMany
    {
        return $this->hasMany(TenantPaymentGateway::class);
    }
}
