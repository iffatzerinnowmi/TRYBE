<style>
    :root {
        --mint: #dff0ea;
        --steel: #95adbe;
        --slate: #574f7d;
        --deep: #4f3a65;
    }
    .incentive-panel {
        border: 1px solid #d8d8df;
        border-radius: 12px;
        padding: 16px;
        margin-bottom: 18px;
        background: linear-gradient(180deg, var(--mint), #ffffff 50%);
    }
    .incentive-panel .label {
        display: block;
        margin-bottom: 6px;
        font-weight: 700;
        color: var(--deep);
    }
    .incentive-panel .field {
        width: 100%;
        border: 1px solid #cfcfd4;
        border-radius: 8px;
        padding: 10px 12px;
        margin-bottom: 12px;
    }
    .incentive-note {
        background: var(--steel);
        color: #fff;
        border-radius: 8px;
        padding: 10px 12px;
        font-weight: 600;
    }
</style>

<div class="incentive-panel">
    <h3 style="margin-top: 0; color: var(--slate);">Participant Incentive</h3>

    <label class="label" for="incentive_type">Compensation Type</label>
    <select class="field" id="incentive_type" name="incentive_type" required>
        <option value="">Select one</option>
        @foreach(\App\Enums\IncentiveType::cases() as $type)
            <option value="{{ $type->value }}" @selected(old('incentive_type', $study->incentive_type ?? '') === $type->value)>
                {{ $type->label() }}
            </option>
        @endforeach
    </select>
    @error('incentive_type') <div style="color:#b00020; margin-top:-6px; margin-bottom:8px;">{{ $message }}</div> @enderror

    <div id="incentive-amount-group">
        <label class="label" for="incentive_amount">Amount</label>
        <input class="field" id="incentive_amount" type="number" step="0.01" min="0.01" name="incentive_amount" value="{{ old('incentive_amount', $study->incentive_amount ?? '') }}">
        @error('incentive_amount') <div style="color:#b00020; margin-top:-6px; margin-bottom:8px;">{{ $message }}</div> @enderror

        <label class="label" for="currency">Currency</label>
        <input class="field" id="currency" name="currency" maxlength="3" value="{{ old('currency', $study->currency ?? 'USD') }}">
        @error('currency') <div style="color:#b00020; margin-top:-6px; margin-bottom:8px;">{{ $message }}</div> @enderror
    </div>

    <div id="course-credit-document-group">
        <label class="label" for="course_credit_document">Institution Documentation (PDF/JPG/PNG)</label>
        <input class="field" id="course_credit_document" name="course_credit_document" type="file" accept=".pdf,.jpg,.jpeg,.png">
        @error('course_credit_document') <div style="color:#b00020; margin-top:-6px; margin-bottom:8px;">{{ $message }}</div> @enderror
    </div>

    <div class="incentive-note">
        Cash and voucher studies will only go live after escrow is locked.
    </div>
</div>

<script>
    (function () {
        const incentiveType = document.getElementById('incentive_type');
        const amountGroup = document.getElementById('incentive-amount-group');
        const creditDocGroup = document.getElementById('course-credit-document-group');

        function syncIncentiveUi() {
            const value = incentiveType.value;
            const requiresAmount = value === 'cash' || value === 'voucher';
            const requiresDoc = value === 'course_credit';

            amountGroup.style.display = requiresAmount ? 'block' : 'none';
            creditDocGroup.style.display = requiresDoc ? 'block' : 'none';
        }

        incentiveType.addEventListener('change', syncIncentiveUi);
        syncIncentiveUi();
    })();
</script>
