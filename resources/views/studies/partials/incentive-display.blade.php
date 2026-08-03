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
    <span class="type">
        {{ $study->incentive_type instanceof \App\Enums\IncentiveType ? $study->incentive_type->label() : $study->incentive_type }}
    </span>

    @if(!empty($study->incentive_amount))
        <div class="amount">{{ $study->currency }} {{ number_format((float) $study->incentive_amount, 2) }}</div>
    @else
        <div class="amount">No monetary payment</div>
    @endif

    @if(!empty($study->escrow_locked_at))
        <div style="margin-top: 8px; font-size: 13px;">Escrow locked before publication</div>
    @endif
</div>
