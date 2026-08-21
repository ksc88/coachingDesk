<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Receipt {{ $receipt->receipt_no }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: ui-sans-serif, system-ui, sans-serif;
            color: #111827;
            margin: 0;
            padding: 24px;
            background: #f8fafc;
        }
        .receipt {
            max-width: 420px;
            margin: 0 auto;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 24px;
        }
        .head { text-align: center; border-bottom: 1px dashed #d1d5db; padding-bottom: 16px; margin-bottom: 16px; }
        .head h1 { font-size: 1.125rem; margin: 0 0 4px; }
        .head p { margin: 0; font-size: 0.8125rem; color: #6b7280; }
        .meta { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 16px; font-size: 0.875rem; margin-bottom: 16px; }
        .meta dt { color: #6b7280; margin: 0; }
        .meta dd { margin: 0; font-weight: 600; text-align: right; }
        .amount {
            text-align: center;
            font-size: 1.75rem;
            font-weight: 700;
            color: #0f766e;
            margin: 16px 0;
        }
        table { width: 100%; border-collapse: collapse; font-size: 0.8125rem; margin-top: 12px; }
        th, td { padding: 6px 0; border-bottom: 1px solid #f3f4f6; text-align: left; vertical-align: top; }
        th { color: #6b7280; font-weight: 500; }
        td.amount-col { text-align: right; white-space: nowrap; }
        .foot { margin-top: 20px; padding-top: 12px; border-top: 1px dashed #d1d5db; font-size: 0.75rem; color: #6b7280; text-align: center; }
        .actions { text-align: center; margin-top: 16px; }
        .actions button {
            background: #0f766e;
            color: #fff;
            border: 0;
            border-radius: 6px;
            padding: 8px 16px;
            font-size: 0.875rem;
            cursor: pointer;
        }
        @media print {
            body { background: #fff; padding: 0; }
            .receipt { border: 0; border-radius: 0; max-width: none; }
            .actions { display: none; }
        }
    </style>
</head>
<body>
    <div class="receipt">
        <div class="head">
            <h1>{{ $tenant?->name ?? 'CoachingDesk' }}</h1>
            @if($tenant?->address)
                <p>{{ $tenant->address }}</p>
            @endif
            @if($tenant?->phone)
                <p>Phone: {{ $tenant->phone }}</p>
            @endif
            <p><strong>Fee receipt</strong></p>
        </div>

        <dl class="meta">
            <dt>Receipt no</dt>
            <dd>{{ $receipt->receipt_no }}</dd>
            <dt>Date</dt>
            <dd>{{ $receipt->issued_on?->format('d-m-Y') }}</dd>
            <dt>Student</dt>
            <dd>{{ trim($student->first_name.' '.($student->last_name ?? '')) }}</dd>
            <dt>Admission no</dt>
            <dd>{{ $student->admission_no }}</dd>
            <dt>Mode</dt>
            <dd>{{ strtoupper($payment?->mode ?? '—') }}</dd>
            @if($payment?->reference)
                <dt>Reference</dt>
                <dd>{{ $payment->reference }}</dd>
            @endif
        </dl>

        <div class="amount">₹{{ number_format((float) $receipt->amount, 2) }}</div>

        @if($allocations->isNotEmpty())
            <table>
                <thead>
                    <tr>
                        <th>Applied to</th>
                        <th class="amount-col">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($allocations as $allocation)
                        <tr>
                            <td>
                                {{ $allocation->invoice?->batch?->name ?? 'Fee' }}
                                @if($allocation->invoice?->notes)
                                    <br><span style="color:#6b7280">{{ $allocation->invoice->notes }}</span>
                                @endif
                            </td>
                            <td class="amount-col">₹{{ number_format((float) $allocation->amount, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <div class="foot">
            Computer-generated receipt · {{ now()->format('d-m-Y H:i') }}
        </div>
    </div>

    <div class="actions">
        <button type="button" onclick="window.print()">Print receipt</button>
    </div>

    @if(request()->boolean('auto_print'))
        <script>window.addEventListener('load', () => window.print());</script>
    @endif
</body>
</html>
