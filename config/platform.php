<?php

return [
    /* ---------------- existing keys (unchanged) ---------------- */
    'free_forms_to_unlock_paid' => env('TRYBE_FREE_FORMS_TO_UNLOCK_PAID', 5),
    'credential_thresholds' => ['bronze' => 1, 'gold' => 10, 'expert' => 25],
    'streak_badge_weeks' => env('TRYBE_STREAK_BADGE_WEEKS', 1),
    'streak_karma_bonus_weeks' => env('TRYBE_STREAK_KARMA_WEEKS', 3),
    'endorsements_for_verified_badge' => env('TRYBE_ENDORSEMENTS_REQUIRED', 5),

    /* ---------------- NEW in Section 6 (Member 1) ----------------
     | How the reliability score is weighted. The three numbers are
     | percentages and must add up to 100. ReliabilityService reads
     | them, and the reliability page displays them, so changing a
     | weight here changes the maths and the labels together.
     */
    'reliability_weights' => [
        'attendance' => env('TRYBE_WEIGHT_ATTENDANCE', 20),
        'completion' => env('TRYBE_WEIGHT_COMPLETION', 40),
        'reviews'    => env('TRYBE_WEIGHT_REVIEWS', 40),
    ],
];
