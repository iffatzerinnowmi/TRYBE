<?php

namespace App\Http\Requests;

use App\Enums\IncentiveType;
use App\Enums\CredentialLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStudyRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Gated by the 'role:researcher,organization' route middleware.
        return true;
    }

    public function rules(): array
    {
        return [
            'title'            => ['required', 'string', 'max:180'],
            'description'      => ['nullable', 'string', 'max:3000'],
            'category'         => ['nullable', 'string', 'max:100'],
            'method'           => ['required', Rule::in(['online', 'in_person'])],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:600'],
            'slots'            => ['required', 'integer', 'min:1', 'max:5000'],
            'deadline'         => ['nullable', 'date', 'after:today'],

            /* ---- Incentive Variety Settings ---- */
            'incentive_type' => ['required', Rule::enum(IncentiveType::class)],

            'compensation_amount' => [
                Rule::requiredIf(fn () => in_array($this->input('incentive_type'), ['cash', 'voucher'], true)),
                'nullable', 'numeric', 'min:1', 'max:100000',
            ],

            'course_credit_institution' => [
                Rule::requiredIf(fn () => $this->input('incentive_type') === 'course_credit'),
                'nullable', 'string', 'max:180',
            ],

            'course_credit_document' => [
                Rule::requiredIf(fn () => $this->input('incentive_type') === 'course_credit'),
                'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096',
            ],

            /* ---- Limited Seat Auctions (Member 3) ---- */
            'auction_mode' => ['nullable', 'boolean'],

            /* ---- Optional eligibility criteria (Module 2) ---- */
            'age_min'           => ['nullable', 'integer', 'between:0,120'],
            'age_max'           => ['nullable', 'integer', 'between:0,120'],
            'location'          => ['nullable', 'string', 'max:120'],
            'credential_min'    => ['nullable', Rule::in(array_column(CredentialLevel::cases(), 'value'))],
            'required_skills'   => ['nullable'],
            'availability_days' => ['nullable', 'integer', 'between:1,365'],
            'topic_ids'         => ['nullable', 'array', 'max:10'],
            'topic_ids.*'       => ['integer', 'exists:topics,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'compensation_amount.required'       => "Enter the amount you're paying per participant.",
            'course_credit_institution.required' => 'Tell participants which institution is awarding the credit.',
            'course_credit_document.required'    => 'Upload supporting documentation from your institution.',
        ];
    }
}