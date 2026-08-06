{{--
    Shared fields for both the create and edit study forms.
    Expects: $methods, $incentiveTypes, optionally $study (edit mode).

    FIELD NAMES ARE FROZEN: title, description, category, eligibility_criteria,
    method, duration_minutes, incentive_type, compensation_amount, slots, deadline.
--}}
@php
    $study = $study ?? null;

    $input = 'w-full rounded-xl border bg-surface-soft px-4 py-3 text-sm text-ink
              outline-none transition placeholder:text-steel focus:border-plum focus:bg-surface';

    $val = fn ($field, $default = '') => old($field, $study?->{$field} ?? $default);
@endphp

<div>
    <label for="title" class="mb-2 block text-[13px] font-semibold text-ink">
        Study title <span class="text-danger">*</span>
    </label>
    <input type="text" id="title" name="title" value="{{ $val('title') }}" required maxlength="180"
           placeholder="Eye-tracking study on mobile reading habits"
           class="{{ $input }} {{ $errors->has('title') ? 'border-danger' : 'border-line-hi' }}">
    @error('title') <p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p> @enderror
</div>

<div>
    <label for="description" class="mb-2 block text-[13px] font-semibold text-ink">
        Description <span class="text-danger">*</span>
    </label>
    <textarea id="description" name="description" rows="5" required maxlength="5000"
              placeholder="What will participants actually do, and why does it matter?"
              class="{{ $input }} {{ $errors->has('description') ? 'border-danger' : 'border-line-hi' }}">{{ $val('description') }}</textarea>
    @error('description') <p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p> @enderror
</div>

<div>
    <label for="eligibility_criteria" class="mb-2 block text-[13px] font-semibold text-ink">
        Eligibility criteria
        <span class="ml-1 font-normal text-steel">who should apply</span>
    </label>
    <textarea id="eligibility_criteria" name="eligibility_criteria" rows="3" maxlength="3000"
              placeholder="e.g. Ages 18–30, fluent in Bangla, owns an Android phone"
              class="{{ $input }} {{ $errors->has('eligibility_criteria') ? 'border-danger' : 'border-line-hi' }}">{{ $val('eligibility_criteria') }}</textarea>
    @error('eligibility_criteria') <p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p> @enderror
</div>

<div class="grid gap-5 sm:grid-cols-2">
    <div>
        <label for="category" class="mb-2 block text-[13px] font-semibold text-ink">Research category</label>
        <input type="text" id="category" name="category" list="category-suggestions" value="{{ $val('category') }}" maxlength="80"
               placeholder="Psychology, UX, Medicine…"
               class="{{ $input }} {{ $errors->has('category') ? 'border-danger' : 'border-line-hi' }}">
        <datalist id="category-suggestions">
            <option value="Psychology"></option>
            <option value="UX Research"></option>
            <option value="Medicine"></option>
            <option value="Linguistics"></option>
            <option value="Computer Science"></option>
            <option value="Social Science"></option>
            <option value="Market Research"></option>
        </datalist>
        @error('category') <p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="method" class="mb-2 block text-[13px] font-semibold text-ink">
            Participation method <span class="text-danger">*</span>
        </label>
        <select id="method" name="method" required
                class="{{ $input }} {{ $errors->has('method') ? 'border-danger' : 'border-line-hi' }}">
            @foreach ($methods as $value => $label)
                <option value="{{ $value }}" @selected($val('method', 'online') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('method') <p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p> @enderror
    </div>
</div>

<div class="grid gap-5 sm:grid-cols-3">
    <div>
        <label for="duration_minutes" class="mb-2 block text-[13px] font-semibold text-ink">Estimated duration</label>
        <div class="relative">
            <input type="number" id="duration_minutes" name="duration_minutes" min="1" max="1440"
                   value="{{ $val('duration_minutes') }}" placeholder="30"
                   class="{{ $input }} {{ $errors->has('duration_minutes') ? 'border-danger' : 'border-line-hi' }}">
            <span class="pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 text-[12px] text-steel">min</span>
        </div>
        @error('duration_minutes') <p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="slots" class="mb-2 block text-[13px] font-semibold text-ink">
            Available slots <span class="text-danger">*</span>
        </label>
        <input type="number" id="slots" name="slots" min="1" max="100000" required
               value="{{ $val('slots', 10) }}"
               class="{{ $input }} {{ $errors->has('slots') ? 'border-danger' : 'border-line-hi' }}">
        @error('slots') <p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="deadline" class="mb-2 block text-[13px] font-semibold text-ink">Application deadline</label>
        <input type="date" id="deadline" name="deadline"
               value="{{ $val('deadline') ? \Illuminate\Support\Carbon::parse($val('deadline'))->format('Y-m-d') : '' }}"
               min="{{ now()->toDateString() }}"
               class="{{ $input }} {{ $errors->has('deadline') ? 'border-danger' : 'border-line-hi' }}">
        @error('deadline') <p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p> @enderror
    </div>
</div>

<div class="grid gap-5 sm:grid-cols-2">
    <div>
        <label for="incentive_type" class="mb-2 block text-[13px] font-semibold text-ink">
            Compensation type <span class="text-danger">*</span>
        </label>
        <select id="incentive_type" name="incentive_type" required
                class="{{ $input }} {{ $errors->has('incentive_type') ? 'border-danger' : 'border-line-hi' }}">
            @foreach ($incentiveTypes as $value => $label)
                <option value="{{ $value }}" @selected($val('incentive_type', 'volunteer') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('incentive_type') <p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="compensation_amount" class="mb-2 block text-[13px] font-semibold text-ink">
            Amount
            <span class="ml-1 font-normal text-steel">leave 0 for volunteer / unpaid</span>
        </label>
        <div class="relative">
            <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-[13px] text-steel">৳</span>
            <input type="number" id="compensation_amount" name="compensation_amount" min="0" max="99999.99" step="0.01"
                   value="{{ $val('compensation_amount', 0) }}"
                   class="{{ $input }} pl-8 {{ $errors->has('compensation_amount') ? 'border-danger' : 'border-line-hi' }}">
        </div>
        @error('compensation_amount') <p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p> @enderror
    </div>
</div>

@if ($study)
    <div>
        <label for="status" class="mb-2 block text-[13px] font-semibold text-ink">Listing status</label>
        <select id="status" name="status"
                class="{{ $input }} {{ $errors->has('status') ? 'border-danger' : 'border-line-hi' }}">
            @foreach ($statuses as $value => $label)
                <option value="{{ $value }}" @selected($val('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('status') <p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p> @enderror
    </div>
@endif
