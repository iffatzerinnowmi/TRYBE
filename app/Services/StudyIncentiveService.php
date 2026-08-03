<?php

namespace App\Services;

use App\Enums\IncentiveType;
use App\Http\Requests\StudyIncentiveRequest;

class StudyIncentiveService
{
    public function __construct(private readonly EscrowService $escrowService)
    {
    }

    /**
     * Returns incentive attributes for studies table and optional escrow payload.
     */
    public function buildPayload(StudyIncentiveRequest $request): array
    {
        $validated = $request->validated();
        $incentiveType = IncentiveType::from($validated['incentive_type']);
        $documentPath = null;
        $documentOriginalName = null;
        $escrowPayload = null;

        if ($request->hasFile('course_credit_document')) {
            $document = $request->file('course_credit_document');
            $documentPath = $document->store('course-credit-documents', 'public');
            $documentOriginalName = $document->getClientOriginalName();
        }

        if ($incentiveType->requiresEscrow()) {
            $escrowPayload = $this->escrowService->lockFunds(
                (float) $validated['incentive_amount'],
                strtoupper($validated['currency'] ?? 'USD')
            );
        }

        return [
            'study' => [
                'incentive_type' => $incentiveType->value,
                'incentive_amount' => $incentiveType->requiresAmount() ? $validated['incentive_amount'] : null,
                'currency' => strtoupper($validated['currency'] ?? 'USD'),
                'course_credit_document_path' => $documentPath,
                'course_credit_document_name' => $documentOriginalName,
                'escrow_locked_at' => $escrowPayload['locked_at'] ?? null,
            ],
            'escrow' => $escrowPayload
                ? [
                    'amount' => $validated['incentive_amount'],
                    'currency' => strtoupper($validated['currency'] ?? 'USD'),
                    'status' => $escrowPayload['status'],
                    'provider_reference' => $escrowPayload['provider_reference'],
                    'locked_at' => $escrowPayload['locked_at'],
                ]
                : null,
        ];
    }
}
