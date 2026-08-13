<?php

namespace App\Console\Commands;

use App\Models\Referral;
use App\Models\User;
use App\Services\ReferralService;
use Illuminate\Console\Command;

/**
 * FEATURE — Referral system  (Member 4)
 *
 *   php artisan trybe:referrals:sync
 *   php artisan trybe:referrals:sync --email=ayesha@trybe.test
 *
 * The specification says referral rewards are applied automatically, "with
 * no manual claim required". ReferralService::sync() already runs whenever
 * somebody opens their referral page, which covers the common case. This
 * command covers the rest: a referrer who never opens the page still gets
 * their reward.
 *
 * Safe to run on a schedule, or twice by accident. sync() is idempotent —
 * the unique index on (user_id, type, milestone) means running it a hundred
 * times grants each reward exactly once.
 */
class SyncReferrals extends Command
{
    protected $signature = 'trybe:referrals:sync {--email= : Only this referrer}';

    protected $description = 'Refresh referral qualification and grant any rewards that are now due.';

    public function handle(ReferralService $referrals): int
    {
        $query = User::query()
            ->whereIn('id', Referral::select('referrer_id')->distinct());

        if ($email = $this->option('email')) {
            $query->where('email', $email);
        }

        $referrers = $query->with('participantProfile')->get();

        if ($referrers->isEmpty()) {
            $this->info('No referrers to sync.');

            return self::SUCCESS;
        }

        $granted = 0;

        foreach ($referrers as $referrer) {
            $result = $referrals->sync($referrer);
            $count  = $result['granted']->count();
            $granted += $count;

            $this->line(sprintf(
                '  %-32s %s  %s',
                $referrer->email,
                $result['progress']['eligible_count'] . '/' . $result['progress']['required_to_unlock'],
                $count > 0 ? '-> granted ' . $count . ' reward(s)' : ''
            ));
        }

        $this->info(sprintf(
            'Synced %d referrer(s); %d new reward(s) granted.',
            $referrers->count(),
            $granted
        ));

        return self::SUCCESS;
    }
}
