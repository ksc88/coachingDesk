<?php

namespace App\Models;

use App\Support\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id', 'student_id', 'batch_id', 'fee_plan_id', 'invoice_no', 'invoice_date',
        'due_date', 'subtotal', 'discount_total', 'fine_total', 'tax_total', 'total',
        'paid_total', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'due_date' => 'date',
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'fine_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_total' => 'decimal:2',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function balance(): float
    {
        return max(0, round((float) $this->total - (float) $this->paid_total, 2));
    }

    /**
     * Billing month key (Y-m) from due date, else invoice date.
     */
    public function billingPeriod(): string
    {
        $date = $this->due_date ?? $this->invoice_date;

        return $date ? $date->format('Y-m') : 'unknown';
    }

    public function billingPeriodLabel(): string
    {
        $date = $this->due_date ?? $this->invoice_date;

        return $date ? $date->format('M Y') : '—';
    }

    /**
     * UI status derived from paid balance + due_date (DB stays open/partial/paid).
     */
    public function displayStatus(?\Carbon\CarbonInterface $today = null): string
    {
        $today = ($today ?? now())->copy()->startOfDay();
        $balance = $this->balance();

        if ($balance <= 0.001) {
            return 'paid';
        }

        $dueDate = $this->due_date?->copy()->startOfDay();
        $isOverdue = $dueDate !== null && $dueDate->lt($today);

        if ($isOverdue) {
            return 'overdue';
        }

        if ((float) $this->paid_total > 0.001 || $this->status === 'partial') {
            return 'partial';
        }

        if ($dueDate !== null && $dueDate->gt($today)) {
            return 'not_due';
        }

        return 'due';
    }

    public function toLedgerArray(): array
    {
        return [
            'id' => $this->id,
            'invoice_no' => $this->invoice_no,
            'student_id' => $this->student_id,
            'batch_id' => $this->batch_id,
            'total' => (float) $this->total,
            'paid_total' => (float) $this->paid_total,
            'discount_total' => (float) $this->discount_total,
            'pending' => $this->balance(),
            'status' => $this->status,
            'display_status' => $this->displayStatus(),
            'due_date' => optional($this->due_date)->toDateString(),
            'invoice_date' => optional($this->invoice_date)->toDateString(),
            'period' => $this->billingPeriod(),
            'period_label' => $this->billingPeriodLabel(),
            'notes' => $this->notes,
            'student' => $this->relationLoaded('student') ? [
                'id' => $this->student?->id,
                'admission_no' => $this->student?->admission_no,
                'first_name' => $this->student?->first_name,
                'last_name' => $this->student?->last_name,
            ] : null,
            'batch' => $this->relationLoaded('batch') && $this->batch ? [
                'id' => $this->batch->id,
                'name' => $this->batch->name,
            ] : null,
        ];
    }
}
