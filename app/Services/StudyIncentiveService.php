<?php

namespace App\Services;

use App\Enums\IncentiveType;
use App\Http\Requests\StudyIncentiveRequest;
use App\Models\Study;
use App\Models\StudyIncentiveAudit;
use App\Models\StudyIncentiveDocument;
use App\Models\StudyEscrow;
use Illuminate\Support\Carbon;

class StudyIncentiveService
{
    public function __construct(private readonly EscrowService $escrowService)
    {
    }

    /**
     * Persist an escrow record for a study.
     * Call this after the study has been created so we have a study id.
     */
    public function persistEscrowForStudy(int $studyId, array $escrow): StudyEscrow
    {
        $study = Study::query()->findOrFail($studyId);

        $record = $study->escrows()->create([
            'study_id' => $studyId,
            'amount' => $escrow['amount'] ?? null,
            'currency' => $escrow['currency'] ?? null,
            'status' => $escrow['status'] ?? 'locked',
            'provider_reference' => $escrow['provider_reference'] ?? null,
            'locked_at' => $escrow['locked_at'] ?? null,
        ]);

        // update denormalized columns on studies for fast reads
        $study->update([
            'escrow_provider_reference' => $record->provider_reference ?? $escrow['provider_reference'] ?? null,
            'escrow_status' => $record->status ?? $escrow['status'] ?? 'locked',
            'escrow_amount' => $record->amount ?? $escrow['amount'] ?? null,
            'escrow_locked_at' => $record->locked_at ?? $escrow['locked_at'] ?? null,
        ]);

        return $record;
    }

    public function persistCourseCreditDocument(
        int $studyId,
        array $studyPayload,
        ?array $documentPayload = null
    ): ?StudyIncentiveDocument
    {
        if (empty($studyPayload['course_credit_document_path'])) {
            return null;
        }

        return StudyIncentiveDocument::create([
            'study_id' => $studyId,
            'document_type' => IncentiveType::COURSE_CREDIT->value,
            'storage_path' => $studyPayload['course_credit_document_path'],
            'original_name' => $studyPayload['course_credit_document_name'] ?? null,
            'mime_type' => $documentPayload['mime_type'] ?? null,
            'size_bytes' => $documentPayload['size_bytes'] ?? null,
            'uploaded_at' => Carbon::now(),
        ]);
    }

    public function createAuditEvent(int $studyId, array $studyPayload, array $metadata = []): StudyIncentiveAudit
    {
        return StudyIncentiveAudit::create([
            'study_id' => $studyId,
            'event_type' => 'incentive_configured',
            'incentive_type' => $studyPayload['incentive_type'] ?? null,
            'amount' => $studyPayload['incentive_amount'] ?? null,
            'currency' => $studyPayload['currency'] ?? null,
            'metadata' => $metadata,
            'happened_at' => Carbon::now(),
        ]);
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

        // course credit documentation handling
        if ($request->hasFile('course_credit_document')) {
            $document = $request->file('course_credit_document');
            // store with the public disk and preserve original filename separately
            $documentPath = $document->store('course-credit-documents', 'public');
            $documentOriginalName = $document->getClientOriginalName();
            $documentPayload = [
                'mime_type' => $document->getMimeType(),
                'size_bytes' => $document->getSize(),
            ];
        } else {
            $documentPayload = null;
        }

        // normalize currency and lock escrow when required
        $currency = strtoupper(trim($validated['currency'] ?? 'USD'));

        if ($incentiveType->requiresEscrow()) {
            $amount = isset($validated['incentive_amount']) ? (float) $validated['incentive_amount'] : 0.0;
            $escrowPayload = $this->escrowService->lockFunds($amount, $currency);
        }

        return [
            'study' => [
                'incentive_type' => $incentiveType->value,
                'incentive_amount' => $incentiveType->requiresAmount() ? ($validated['incentive_amount'] ?? null) : null,
                'currency' => $currency,
                'course_credit_document_path' => $documentPath,
                'course_credit_document_name' => $documentOriginalName,
                'escrow_locked_at' => $escrowPayload['locked_at'] ?? null,
            ],
            'escrow' => $escrowPayload
                ? [
                    'amount' => isset($validated['incentive_amount']) ? (float) $validated['incentive_amount'] : null,
                    'currency' => $currency,
                    'status' => $escrowPayload['status'],
                    'provider_reference' => $escrowPayload['provider_reference'],
                    'locked_at' => $escrowPayload['locked_at'],
                ]
                : null,
            'document' => $documentPayload,
        ];
    }
}
