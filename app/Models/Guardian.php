<?php

namespace App\Models;

use App\Support\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Guardian extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id', 'user_id', 'name', 'relation', 'occupation', 'phone', 'alternate_phone', 'email',
        'whatsapp_opt_in', 'sms_opt_in', 'email_opt_in', 'push_opt_in', 'consent_at', 'preferences',
    ];

    protected function casts(): array
    {
        return [
            'whatsapp_opt_in' => 'boolean',
            'sms_opt_in' => 'boolean',
            'email_opt_in' => 'boolean',
            'push_opt_in' => 'boolean',
            'consent_at' => 'datetime',
            'preferences' => 'array',
        ];
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class)->withPivot('is_primary');
    }
}
