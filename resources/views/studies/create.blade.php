@extends('layouts.app')

@section('title', 'Post a study')

@section('content')
@php
    $input = 'w-full rounded-xl border bg-surface-soft px-4 py-3 text-sm text-ink
              outline-none transition placeholder:text-steel focus:border-plum focus:bg-surface';

    $icons = [
        'cash'          => '💵',
        'voucher'       => '🎁',
        'course_credit' => '🎓',
        'volunteer'     => '🤝',
    ];
@endphp

<div class="wrap pb-20">

    <x-page-header eyebrow="Post a study" title="Set up your listing."
        subtitle="Choose how you'll compensate participants — this is shown on the listing so people can decide before they apply." />

    @if ($errors->any())
        <x-alert type="error" class="mb-6">
            Please fix the {{ $errors->count() }} highlighted {{ $errors->count() === 1 ? 'field' : 'fields' }}.
        </x-alert>
    @endif

    <form method="POST" action="{{ route('studies.store') }}" enctype="multipart/form-data"
          class="grid gap-6 lg:grid-cols-[1.4fr_1fr]">
        @csrf

        {{-- ================= LEFT: basic details ================= --}}
        <div class="space-y-6">
            <x-panel label="Study details" class="reveal">
                <div class="space-y-5">
                    <div>
                        <label for="title" class="mb-2 block text-[13px] font-semibold text-ink">
                            Title <span class="text-danger">*</span>
                        </label>
                        <input type="text" id="title" name="title" value="{{ old('title') }}"
                               placeholder="e.g. Reading habits & attention span"
                               class="{{ $input }} {{ $errors->has('title') ? 'border-danger' : 'border-line-hi' }}">
                        @error('title') <p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="description" class="mb-2 block text-[13px] font-semibold text-ink">
                            Description <span class="text-danger">*</span>
                        </label>
                        <textarea id="description" name="description" rows="4"
                                  placeholder="What will participants actually do?"
                                  class="{{ $input }} {{ $errors->has('description') ? 'border-danger' : 'border-line-hi' }}">{{ old('description') }}</textarea>
                        @error('description') <p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="eligibility_criteria" class="mb-2 block text-[13px] font-semibold text-ink">
                            Eligibility criteria
                        </label>
                        <textarea id="eligibility_criteria" name="eligibility_criteria" rows="3"
                                  placeholder="Who can take part? Leave blank if open to everyone."
                                  class="{{ $input }} {{ $errors->has('eligibility_criteria') ? 'border-danger' : 'border-line-hi' }}">{{ old('eligibility_criteria') }}</textarea>
                        @error('eligibility_criteria') <p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label for="category" class="mb-2 block text-[13px] font-semibold text-ink">Category</label>
                            <input type="text" id="category" name="category" value="{{ old('category') }}"
                                   placeholder="Survey, Usability, Cognition…"
                                   class="{{ $input }} {{ $errors->has('category') ? 'border-danger' : 'border-line-hi' }}">
                            @error('category') <p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="method" class="mb-2 block text-[13px] font-semibold text-ink">
                                Method <span class="text-danger">*</span>
                            </label>
                            <select id="method" name="method"
                                    class="{{ $input }} {{ $errors->has('method') ? 'border-danger' : 'border-line-hi' }}">
                                <option value="online" @selected(old('method') === 'online')>Online</option>
                                <option value="in_person" @selected(old('method') === 'in_person')>In person</option>
                            </select>
                            @error('method') <p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="grid gap-5 sm:grid-cols-3">
                        <div>
                            <label for="duration_minutes" class="mb-2 block text-[13px] font-semibold text-ink">
                                Duration (min)
                            </label>
                            <input type="number" min="1" id="duration_minutes" name="duration_minutes"
                                   value="{{ old('duration_minutes') }}"
                                   class="{{ $input }} {{ $errors->has('duration_minutes') ? 'border-danger' : 'border-line-hi' }}">
                            @error('duration_minutes') <p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="slots" class="mb-2 block text-[13px] font-semibold text-ink">
                                Slots <span class="text-danger">*</span>
                            </label>
                            <input type="number" min="1" id="slots" name="slots" value="{{ old('slots') }}"
                                   class="{{ $input }} {{ $errors->has('slots') ? 'border-danger' : 'border-line-hi' }}">
                            @error('slots') <p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="deadline" class="mb-2 block text-[13px] font-semibold text-ink">Deadline</label>
                            <input type="date" id="deadline" name="deadline" value="{{ old('deadline') }}"
                                   class="{{ $input }} {{ $errors->has('deadline') ? 'border-danger' : 'border-line-hi' }}">
                            @error('deadline') <p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>
            </x-panel>
        </div>

        {{-- ================= RIGHT: the incentive feature ================= --}}
        <div class="space-y-6">
            <x-panel label="Incentive type"
                     note="Shown on the listing so participants can decide before applying."
                     class="reveal reveal-d1">

                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach ($incentiveTypes as $value => $label)
                        <label for="incentive_{{ $value }}"
                               class="incentive-card cursor-pointer rounded-xl border border-line-hi bg-surface-soft p-4 transition hover:border-plum"
                               data-value="{{ $value }}">
                            <input type="radio" id="incentive_{{ $value }}" name="incentive_type"
                                   value="{{ $value }}" class="hidden incentive-radio"
                                   @checked(old('incentive_type', 'volunteer') === $value)>
                            <div class="flex items-center gap-2 text-[14px] font-semibold text-ink">
                                <span>{{ $icons[$value] ?? '🔹' }}</span> {{ $label }}
                            </div>
                        </label>
                    @endforeach
                </div>
                @error('incentive_type') <p class="mt-2 text-[12px] text-danger">{{ $message }}</p> @enderror

                {{-- Cash / Voucher amount + escrow note --}}
                <div id="incentive-amount-block" class="mt-5 hidden">
                    <label for="compensation_amount" class="mb-2 block text-[13px] font-semibold text-ink">
                        Amount per participant (৳) <span class="text-danger">*</span>
                    </label>
                    <input type="number" min="1" step="0.01" id="compensation_amount" name="compensation_amount"
                           value="{{ old('compensation_amount') }}" placeholder="e.g. 500"
                           class="{{ $input }} {{ $errors->has('compensation_amount') ? 'border-danger' : 'border-line-hi' }}">
                    @error('compensation_amount') <p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p> @enderror

                    <p class="mt-2.5 flex items-start gap-2 rounded-lg bg-plum/10 px-3 py-2.5 text-[12px] text-plum">
                        🔒 This amount is locked into escrow the moment you submit — the listing
                        can't go live otherwise.
                    </p>
                </div>

                {{-- Course credit: institution + document --}}
                <div id="incentive-credit-block" class="mt-5 hidden space-y-4">
                    <div>
                        <label for="course_credit_institution" class="mb-2 block text-[13px] font-semibold text-ink">
                            Institution awarding credit <span class="text-danger">*</span>
                        </label>
                        <input type="text" id="course_credit_institution" name="course_credit_institution"
                               value="{{ old('course_credit_institution') }}"
                               placeholder="e.g. BRAC University, Dept. of Psychology"
                               class="{{ $input }} {{ $errors->has('course_credit_institution') ? 'border-danger' : 'border-line-hi' }}">
                        @error('course_credit_institution') <p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="mb-2 block text-[13px] font-semibold text-ink">
                            Supporting documentation <span class="text-danger">*</span>
                            <span class="ml-1 font-normal text-steel">Letter from your institution · PDF/JPG/PNG</span>
                        </label>

                        <label for="course_credit_document"
                               class="flex cursor-pointer flex-col items-center gap-2 rounded-xl border
                                      border-dashed border-line-hi bg-surface px-4 py-8 text-center transition hover:border-plum">
                            <span class="text-2xl">📄</span>
                            <span class="text-[13px] text-dim"><b class="text-ink">Click to upload</b> or drag a file here</span>
                            <input type="file" id="course_credit_document" name="course_credit_document"
                                   accept=".pdf,.jpg,.jpeg,.png" class="hidden">
                        </label>
                        <p id="course_credit_document-name" class="mt-2 text-[12px] text-ok"></p>
                        @error('course_credit_document') <p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p> @enderror
                    </div>
                </div>
            </x-panel>

            <div class="flex items-center gap-3">
                <x-btn type="submit" class="flex-1 justify-center">Post study</x-btn>
                <x-btn href="{{ route('studies.index') }}" variant="ghost">Cancel</x-btn>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
    var amountBlock = document.getElementById('incentive-amount-block');
    var creditBlock = document.getElementById('incentive-credit-block');
    var cards = document.querySelectorAll('.incentive-card');

    function syncIncentiveUI() {
        var selected = document.querySelector('.incentive-radio:checked');
        var value = selected ? selected.value : null;

        cards.forEach(function (card) {
            var active = card.dataset.value === value;
            card.classList.toggle('border-ink', active);
            card.classList.toggle('bg-surface', active);
        });

        amountBlock.classList.toggle('hidden', !(value === 'cash' || value === 'voucher'));
        creditBlock.classList.toggle('hidden', value !== 'course_credit');
    }

    cards.forEach(function (card) {
        card.addEventListener('click', function () {
            document.getElementById('incentive_' + card.dataset.value).checked = true;
            syncIncentiveUI();
        });
    });

    var fileInput = document.getElementById('course_credit_document');
    if (fileInput) {
        fileInput.addEventListener('change', function () {
            var label = document.getElementById('course_credit_document-name');
            if (label) label.textContent = this.files[0] ? '✓ ' + this.files[0].name : '';
        });
    }

    syncIncentiveUI();
</script>
@endpush