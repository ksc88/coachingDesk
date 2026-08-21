<?php

namespace App\Models;

use App\Support\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Receipt extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id', 'payment_id', 'student_id', 'receipt_no', 'financial_year',
        'issued_on', 'amount', 'pdf_path', 'issued_by', 'snapshot',
    ];

    protected function casts(): array
    {
        return [
            'issued_on' => 'date',
            'amount' => 'decimal:2',
            'snapshot' => 'array',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
