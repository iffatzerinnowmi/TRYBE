<?php

namespace Database\Seeders;

use App\Enums\KarmaSource;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\KarmaService;
use Illuminate\Database\Seeder;

class KarmaDemoSeeder extends Seeder
{
    public function run(): void
    {
        $karma = app(KarmaService::class);

        $participants = User::where('role', UserRole::PARTICIPANT)->get();

        foreach ($participants as $user) {

            $karma->earn(
                $user,
                KarmaSource::STUDY_COMPLETED,
                'Demo: Study Completed'
            );

            $karma->earn(
                $user,
                KarmaSource::SESSION_ON_TIME,
                'Demo: Session Attended'
            );

            $karma->earn(
                $user,
                KarmaSource::REVIEW_LEFT,
                'Demo: Post-Session Review'
            );

            $this->command->info(
                "{$user->name}: " . $karma->balanceFor($user) . " Karma"
            );
        }
    }
}