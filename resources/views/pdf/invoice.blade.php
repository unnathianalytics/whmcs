<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->number }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1f2937; margin: 0; padding: 32px; }
        h1 { font-size: 22px; margin: 0; }
        .muted { color: #6b7280; }
        .header { width: 100%; margin-bottom: 28px; }
        .header td { vertical-align: top; }
        .text-right { text-align: right; }
        .badge { display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
        .meta { margin: 18px 0 26px; width: 100%; }
        .meta td { padding: 2px 0; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.items th { text-align: left; border-bottom: 2px solid #e5e7eb; padding: 8px 6px; font-size: 11px; text-transform: uppercase; color: #6b7280; }
        table.items td { padding: 8px 6px; border-bottom: 1px solid #f3f4f6; }
        .totals { width: 45%; margin-left: 55%; margin-top: 16px; }
        .totals td { padding: 4px 6px; }
        .totals .grand { font-weight: bold; border-top: 2px solid #e5e7eb; font-size: 14px; }
        .notes { margin-top: 28px; padding-top: 12px; border-top: 1px solid #e5e7eb; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td>
                <h1>{{ $invoice->company?->name ?? config('app.name') }}</h1>
                @if ($invoice->company?->address)
                    <div class="muted">{{ $invoice->company->address }}</div>
                @endif
                @if ($invoice->company?->email)
                    <div class="muted">{{ $invoice->company->email }}</div>
                @endif
            </td>
            <td class="text-right">
                <h1>{{ __('INVOICE') }}</h1>
                <div class="muted">{{ $invoice->number }}</div>
                <div style="margin-top: 6px;">
                    <span class="badge" style="background:#f3f4f6; color:#374151;">{{ $invoice->status->label() }}</span>
                </div>
            </td>
        </tr>
    </table>

    <table class="meta">
        <tr>
            <td>
                <div class="muted">{{ __('Bill To') }}</div>
                <strong>{{ $invoice->client?->name }}</strong><br>
                @if ($invoice->client?->company_name)
                    {{ $invoice->client->company_name }}<br>
                @endif
                @if ($invoice->client?->email)
                    <span class="muted">{{ $invoice->client->email }}</span><br>
                @endif
                @php
                    $address = collect([
                        $invoice->client?->address,
                        $invoice->client?->city,
                        $invoice->client?->state,
                        $invoice->client?->postcode,
                        $invoice->client?->country,
                    ])->filter()->join(', ');
                @endphp
                @if ($address)
                    <span class="muted">{{ $address }}</span>
                @endif
            </td>
            <td class="text-right">
                <table style="width:100%;">
                    <tr><td class="muted">{{ __('Issue Date') }}</td><td class="text-right">{{ $invoice->issue_date->format('M j, Y') }}</td></tr>
                    <tr><td class="muted">{{ __('Due Date') }}</td><td class="text-right">{{ $invoice->due_date->format('M j, Y') }}</td></tr>
                    <tr><td class="muted">{{ __('Currency') }}</td><td class="text-right">{{ $invoice->currency }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th>{{ __('Description') }}</th>
                <th class="text-right">{{ __('Qty') }}</th>
                <th class="text-right">{{ __('Unit Price') }}</th>
                <th class="text-right">{{ __('Tax') }}</th>
                <th class="text-right">{{ __('Amount') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($invoice->items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td class="text-right">{{ number_format((float) $item->quantity, 2) }}</td>
                    <td class="text-right">{{ number_format((float) $item->unit_price, 2) }}</td>
                    <td class="text-right">{{ number_format((float) $item->tax_rate, 2) }}%</td>
                    <td class="text-right">{{ number_format((float) $item->line_total, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">{{ __('No line items.') }}</td></tr>
            @endforelse
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td class="muted">{{ __('Subtotal') }}</td>
            <td class="text-right">{{ $invoice->currency }} {{ number_format((float) $invoice->subtotal, 2) }}</td>
        </tr>
        <tr>
            <td class="muted">{{ __('Tax') }}</td>
            <td class="text-right">{{ $invoice->currency }} {{ number_format((float) $invoice->tax_total, 2) }}</td>
        </tr>
        <tr class="grand">
            <td>{{ __('Total') }}</td>
            <td class="text-right">{{ $invoice->currency }} {{ number_format((float) $invoice->total, 2) }}</td>
        </tr>
        <tr>
            <td class="muted">{{ __('Paid') }}</td>
            <td class="text-right">{{ $invoice->currency }} {{ number_format($invoice->amountPaid(), 2) }}</td>
        </tr>
        <tr>
            <td><strong>{{ __('Balance Due') }}</strong></td>
            <td class="text-right"><strong>{{ $invoice->currency }} {{ number_format($invoice->balance(), 2) }}</strong></td>
        </tr>
    </table>

    @if ($invoice->notes)
        <div class="notes">
            <div class="muted">{{ __('Notes') }}</div>
            {{ $invoice->notes }}
        </div>
    @endif
</body>
</html>
