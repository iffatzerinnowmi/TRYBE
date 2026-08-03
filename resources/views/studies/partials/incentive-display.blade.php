<style>
    .incentive-display {
        border-radius: 12px;
        background: #95adbe;
        color: #fff;
        padding: 14px 16px;
        margin: 10px 0 14px;
    }
    .incentive-display .type {
        background: #4f3a65;
        border-radius: 999px;
        padding: 5px 10px;
        font-size: 12px;
        font-weight: 700;
        letter-spacing: .2px;
    }
    .incentive-display .amount {
        margin-top: 8px;
        font-size: 18px;
        font-weight: 700;
    }
</style>

<div class="incentive-display">
    @php
        $type = \App\Enums\IncentiveType::tryFrom($study->incentive_type ?? '');
        $typeLabel = $type ? $type->label() : (is_string($study->incentive_type) ? ucwords(str_replace('_', ' ', $study->incentive_type)) : '—');
    @endphp

    <span class="type">{{ $typeLabel }}</span>

    @if(!empty($study->incentive_amount) && $type && $type->requiresAmount())
        <div class="amount">{{ $study->currency }} {{ number_format((float) $study->incentive_amount, 2) }}</div>
    @else
        <div class="amount">No monetary payment</div>
    @endif

    @if(!empty($study->escrow_locked_at))
        <div style="margin-top: 8px; font-size: 13px;">
            Escrow locked: {{ optional($study->escrow_locked_at)->diffForHumans() ?? $study->escrow_locked_at }}
        </div>
    @endif

    @if(!empty($study->course_credit_document_path))
        <div style="margin-top:8px; font-size:13px;">
            Document: <a href="{{ asset('storage/' . $study->course_credit_document_path) }}" target="_blank" rel="noopener">{{ $study->course_credit_document_name ?? basename($study->course_credit_document_path) }}</a>
        </div>
    @endif
</div>
