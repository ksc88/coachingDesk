<?php

namespace App\Models;

use App\Support\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Enquiry extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id', 'branch_id', 'course_id', 'batch_id', 'owner_id', 'name', 'phone',
        'email', 'source', 'campaign', 'status', 'notes', 'whatsapp_opt_in', 'sms_opt_in',
        'next_follow_up_at', 'converted_student_id',
    ];

    protected function casts(): array
    {
        return [
            'next_follow_up_at' => 'datetime',
            'whatsapp_opt_in' => 'boolean',
            'sms_opt_in' => 'boolean',
        ];
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(EnquiryFollowUp::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
