<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\KarmaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API — Karma Credits (Member 3)
 * No logic here. Guard, call KarmaService, shape JSON — same split as
 * ReferralApiController.
 */
class KarmaApiController extends Controller
{
    public function __construct(private KarmaService $karma) {}

    /** GET /api/v1/karma/me */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => [
                'balance' => $this->karma->balanceFor($user),
                'rates'   => $this->karma->rates(),
                'transactions' => $this->karma->ledgerFor($user, 20)
                    ->map(fn ($t) => [
                        'id'          => $t->id,
                        'source'      => $t->source->value,
                        'icon'        => $t->source->icon(),
                        'description' => $t->description,
                        'delta'       => $t->delta,
                        'created_on'  => $t->created_at?->format('d M Y, g:i A'),
                    ])->all(),
            ],
        ], 200);
    }

    /** GET /api/v1/karma/me/transactions — "View All" */
    public function transactions(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => $this->karma->ledgerFor($user, 100)
                ->map(fn ($t) => [
                    'id'          => $t->id,
                    'source'      => $t->source->value,
                    'icon'        => $t->source->icon(),
                    'description' => $t->description,
                    'delta'       => $t->delta,
                    'created_on'  => $t->created_at?->format('d M Y, g:i A'),
                ])->all(),
        ], 200);
    }
}