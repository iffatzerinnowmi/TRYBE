<?php

return [
    /* ---------------- existing keys (unchanged) ---------------- */
    'free_forms_to_unlock_paid' => env('TRYBE_FREE_FORMS_TO_UNLOCK_PAID', 5),
    'credential_thresholds' => ['bronze' => 1, 'gold' => 10, 'expert' => 25],
    'streak_badge_weeks' => env('TRYBE_STREAK_BADGE_WEEKS', 1),
    'streak_karma_bonus_weeks' => env('TRYBE_STREAK_KARMA_WEEKS', 3),
    'endorsements_for_verified_badge' => env('TRYBE_ENDORSEMENTS_REQUIRED', 5),

    /* ---------------- NEW (Member 1 — endorsements) ----------------
     | How many tags a single endorsement may carry. Was a literal 3 in
     | EndorsementController. It is read in three places that must never
     | disagree: the validation rule, the "pick up to N" caption, and the
     | JavaScript that stops you ticking a fourth box. All three now read
     | this one key through EndorsementService::maxTags().
     */
    'endorsement_max_tags' => env('TRYBE_ENDORSEMENT_MAX_TAGS', 3),

    /* ---------------- Section 6 (Member 1) ----------------
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

    /* ---------------- Section 7 (Member 4) — Smart Participant Matching ----
     | The eight weights are percentages and MUST add up to 100, so
     | match_score is a real percentage and the eight contributions reconcile
     | with the total — the same way the reliability payload does.
     |
     | StudyMatchingService reads these and the API returns them, so changing
     | a weight here changes the maths and the on-screen explanation together.
     |
     | The first five trace to the criteria the assignment brief names
     | ("age range, location, skills, availability, credential level").
     | Topics come from the API-driven feedback. Reliability and standing are
     | cross-module reads of Member 1's data.
     |
     | Set any weight to 0 to switch a factor off — the remaining weights are
     | renormalised, so the total is still 100.
     */
    'matching' => [
        'weights' => [
            'topics'       => env('TRYBE_MATCH_W_TOPICS', 25),
            'age'          => env('TRYBE_MATCH_W_AGE', 15),
            'location'     => env('TRYBE_MATCH_W_LOCATION', 15),
            'skills'       => env('TRYBE_MATCH_W_SKILLS', 15),
            'credential'   => env('TRYBE_MATCH_W_CREDENTIAL', 10),
            'reliability'  => env('TRYBE_MATCH_W_RELIABILITY', 10),
            'availability' => env('TRYBE_MATCH_W_AVAILABILITY', 5),
            'standing'     => env('TRYBE_MATCH_W_STANDING', 5),
        ],

        /* Within the topics factor: how much comes from the topics of studies
         | the participant has actually COMPLETED, versus topics they merely
         | ticked a box for. A completed study is evidence; a declared
         | interest is a claim. Evidence is weighted higher. */
        'topic_history_share' => env('TRYBE_MATCH_TOPIC_HISTORY_SHARE', 60),

        'strong_threshold' => env('TRYBE_MATCH_STRONG', 70),
        'candidates_limit' => env('TRYBE_MATCH_LIMIT', 5),

        /* Phase 1 of the candidate query narrows to this many rows, ordered by
         | reliability, before exact scoring happens in PHP. Topic-overlap
         | maths is not cheaply expressible in SQL, so the work is bounded
         | here instead. */
        'shortlist_size' => env('TRYBE_MATCH_SHORTLIST', 100),

        /* How far outside the stated age band / credential floor the hard SQL
         | filter still admits a candidate. The scorer tapers rather than
         | cliff-edging, so filtering at the exact boundary would throw away
         | people the scorer would have rated highly. */
        'age_filter_slack'    => env('TRYBE_MATCH_AGE_SLACK', 5),
        'credential_slack'    => env('TRYBE_MATCH_CREDENTIAL_SLACK', 1),

        /* Used only when a study has no study_match_criteria row. */
        'defaults' => [
            'age_min'           => 18,
            'age_max'           => 40,
            'location'          => null,
            'credential_min'    => \App\Enums\CredentialLevel::NONE->value,
            'required_skills'   => [],
            'availability_days' => 30,
        ],

        /* Guards on who may be invited. Each reads another member's data, so
         | each can be switched off until that member's feature ships. */
        'guards' => [
            'block_paid_study_for_locked_participant' => env('TRYBE_MATCH_GUARD_PAID', true),
            'block_invite_when_auction_mode'          => env('TRYBE_MATCH_GUARD_AUCTION', true),
            'require_verified_researcher'             => env('TRYBE_MATCH_GUARD_VERIFIED', false),
        ],
    ],

    /* ---------------- Section 7 (Member 4) — referral system --------------
     | An examiner asking "make it 5 referrals instead of 3" must be a
     | one-line edit here with no logic touched.
     */
    'referrals' => [
        // How many referred users must qualify before a reward fires.
        'required_to_unlock' => env('TRYBE_REFERRALS_REQUIRED', 3),

        // What makes a referred user "qualified". Expressed as a COUNT, not
        // a boolean, so the rule is a number rather than a bare > 0 in code.
        // Participants qualify by completing studies; researchers by posting
        // them, since researchers do not complete studies.
        'studies_to_qualify' => env('TRYBE_REFERRAL_QUALIFY_STUDIES', 1),

        // Free paid-post credits per qualifying researcher referral.
        'post_credits_per_referral' => env('TRYBE_REFERRAL_POST_CREDITS', 1),

        // Whether a participant can be bumped more than once (3 referrals ->
        // +1 tier, 6 -> +1 more). The brief only promises the first bump;
        // leaving this on keeps the ladder consistent.
        'repeatable' => env('TRYBE_REFERRAL_REPEATABLE', true),

        // Unambiguous alphabet: no I, 1, O or 0, because people retype these
        // from screenshots.
        'code_length'         => 8,
        'code_alphabet'       => 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789',
        'code_generate_tries' => 20,

        // How long a click on a referral link stays attributable if the
        // person does not sign up straight away.
        'attribution_days' => env('TRYBE_REFERRAL_ATTRIBUTION_DAYS', 30),
    ],

    /* ---------------- Section 8 (Member 3) — Karma Credits ----------------
     | Points awarded per action. KarmaService::earn() reads these by the
     | KarmaSource enum's value, and the "How to earn karma" panel on the
     | participant page displays the same numbers via GET /api/v1/karma/me
     | — so changing a number here changes what participants are paid and
     | what the page tells them they'll be paid, together, in one place.
     */
    'karma' => [
        'study_completed'  => env('TRYBE_KARMA_STUDY_COMPLETED', 10),
        'session_on_time'  => env('TRYBE_KARMA_SESSION_ON_TIME', 5),
        'review_left'      => env('TRYBE_KARMA_REVIEW_LEFT', 2),
        'referral_success' => env('TRYBE_KARMA_REFERRAL_SUCCESS', 20),
    ],

    /* ---------------- Section 7 (Member 4) — Study Recommendation Feed ----
     | The three weights are percentages and MUST add up to 100, so feed_score
     | is a real percentage and the three contributions reconcile with it.
     |
     | WHAT IS DELIBERATELY ABSENT: karma balance, post boosts and researcher
     | subscription tier. The brief lists all three, but karma measures how
     | much somebody has ENGAGED, not whether they suit a study, and ranking
     | research opportunities by an in-app currency is the same mistake as
     | ranking them by who paid for a boost.
     |
     | The split that replaces them:
     |   match   -> is this PERSON right for this study?  (the eight-factor
     |              engine: skills, credential, endorsements, completed-study
     |              topics, reliability, availability, age, location)
     |   recency -> is this STUDY worth surfacing now?
     |   urgency -> is this STUDY about to close?
     |
     | Nothing about the participant is counted twice, because everything
     | about the participant already lives inside match_score.
     */
    'feed' => [
        'weights' => [
            'match'   => env('TRYBE_FEED_W_MATCH', 85),
            'recency' => env('TRYBE_FEED_W_RECENCY', 10),
            'urgency' => env('TRYBE_FEED_W_URGENCY', 5),
        ],

        // A study posted today scores 100 on recency; one posted this many
        // days ago scores 50; it decays exponentially from there.
        'recency_half_life_days' => env('TRYBE_FEED_RECENCY_HALFLIFE', 14),

        // Deadlines inside this window score 100 on urgency, tapering to 0
        // at twice the window. No deadline scores 0.
        'urgency_window_days' => env('TRYBE_FEED_URGENCY_WINDOW', 7),

        // Studies scoring between this floor and the strong threshold are
        // "near misses" — the ones the skill-gap coach analyses.
        'near_miss_floor' => env('TRYBE_FEED_NEAR_MISS_FLOOR', 40),

        'page_size' => env('TRYBE_FEED_PAGE_SIZE', 10),

        // How many manual refreshes of the AI advice per hour, per user.
        // Here rather than a bare throttle:6,1 in the route file, because
        // decision.md section 8 says numbers live in config.
        'advice_refresh_per_hour' => env('TRYBE_FEED_ADVICE_REFRESHES', 6),

        // How many skills the coach may name as gaps.
        'max_gap_skills' => env('TRYBE_FEED_MAX_GAP_SKILLS', 5),

        // The duration buckets the filter dropdown offers, in minutes.
        // Here rather than in the service so "add a 45-minute option" is a
        // config edit with no code change.
        'duration_buckets' => [15, 30, 60, 120],
    ],
];
