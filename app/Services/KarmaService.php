<?php

namespace App\Services;

use App\Enums\KarmaSource;
use App\Models\KarmaTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * KarmaService — the single entry point for every karma write on the
 * platform. Nothing outside this class ever calls
 * KarmaTransaction::create() directly.
 */
class KarmaService
{
    public function balanceFor(User $user): int
    {
        return (int) KarmaTransaction::where('user_id', $user->id)->sum('amount');
    }

    public function ledgerFor(User $user, int $limit = 20)
    {
        return KarmaTransaction::where('user_id', $user->id)
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function earn(User $user, KarmaSource $source, ?string $note = null): KarmaTransaction
    {
        abort_unless($source->isEarn(), 500, "{$source->value} is not an earn source.");

        $points = (int) data_get(config('platform.karma'), $source->value, 0);

        return KarmaTransaction::create([
            'user_id'     => $user->id,
            'amount'      => $points,
            'source'      => $source,
            'metadata'  => $note ? ['note' => $note] : null,
        ]);
    }

    /** Wrapped in a locked transaction so two simultaneous spends can't both pass the balance check. */
    public function spend(User $user, int $points, KarmaSource $source, ?string $note = null): ?KarmaTransaction
    {
        abort_if($source->isEarn(), 500, "{$source->value} is not a spend source.");
        abort_if($points <= 0, 500, 'Spend amount must be positive.');

        return DB::transaction(function () use ($user, $points, $source, $note) {
            $balance = (int) KarmaTransaction::where('user_id', $user->id)
                ->lockForUpdate()
                ->sum('amount');

            if ($balance < $points) {
                return null;
            }

        return KarmaTransaction::create([
            'user_id'   => $user->id,
            'amount'    => $points,
            'source'    => $source,
            'metadata'  => $note ? ['note' => $note] : null,
        ]);
        });
    }

    public function rates(): array
    {
        return collect(config('platform.karma'))
            ->map(fn ($points, $key) => [
                'source' => $key,
                'label'  => KarmaSource::from($key)->label(),
                'icon'   => KarmaSource::from($key)->icon(),
                'points' => $points,
            ])->values()->all();
    }
}