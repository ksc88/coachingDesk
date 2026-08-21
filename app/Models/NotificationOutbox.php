<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Support\Concerns\BelongsToTenant;

class NotificationOutbox extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'notification_outbox';

    protected $fillable = array (
  0 => 'tenant_id',
  1 => 'channel',
  2 => 'event_type',
  3 => 'recipient_phone',
  4 => 'recipient_email',
  5 => 'recipient_name',
  6 => 'student_id',
  7 => 'guardian_id',
  8 => 'template_key',
  9 => 'body',
  10 => 'payload',
  11 => 'status',
  12 => 'attempts',
  13 => 'provider_message_id',
  14 => 'cost',
  15 => 'failure_reason',
  16 => 'scheduled_at',
  17 => 'sent_at',
);

    protected function casts(): array
    {
        return array (
  'payload' => 'array',
  'cost' => 'decimal:4',
  'scheduled_at' => 'datetime',
  'sent_at' => 'datetime',
);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
