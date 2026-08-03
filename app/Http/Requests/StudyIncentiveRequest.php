<?php

namespace App\Http\Requests;

use App\Enums\IncentiveType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StudyIncentiveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'incentive_type' => ['required', Rule::in(IncentiveType::values())],
            'incentive_amount' => ['nullable', 'numeric', 'min:0.01'],
            'currency' => ['nullable', 'string', 'size:3'],
            'course_credit_document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $type = IncentiveType::tryFrom((string) $this->input('incentive_type'));

            if (!$type) {
                return;
            }

            if ($type->requiresAmount() && !$this->filled('incentive_amount')) {
                $validator->errors()->add('incentive_amount', 'Amount is required for cash and voucher studies.');
            }

            if (!$type->requiresAmount() && $this->filled('incentive_amount')) {
                $validator->errors()->add('incentive_amount', 'Amount is only allowed for cash and voucher studies.');
            }

            if ($type->requiresDocumentation() && !$this->hasFile('course_credit_document')) {
                $validator->errors()->add('course_credit_document', 'Institution documentation is required for course credit studies.');
            }
        });
    }
}
