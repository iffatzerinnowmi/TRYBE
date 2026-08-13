<?php

namespace Database\Seeders;

use App\Enums\CredentialLevel;
use App\Models\Endorsement;
use App\Models\Follow;
use App\Models\NotificationPreference;
use App\Models\ParticipantProfile;
use App\Models\ResearcherProfile;
use App\Models\Study;
use App\Models\StudyParticipation;
use App\Models\StudyReview;
use App\Models\User;
use App\Models\VerificationRequest;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Everyone's password is "password"
        $pw = Hash::make('password');

        // ---------------------------------------------------------------
        // Admin
        // ---------------------------------------------------------------
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin@trybe.test', 'phone' => '01700000000',
            'password' => $pw, 'role' => 'admin', 'verification_status' => 'verified',
        ]);

        // ---------------------------------------------------------------
        // Researchers
        // ---------------------------------------------------------------
        $anisa = User::create([
            'name' => 'Dr. Anisa Rahman', 'email' => 'anisa@brac.test', 'phone' => '01710000001',
            'password' => $pw, 'role' => 'researcher', 'verification_status' => 'verified',
            'location' => 'Dhaka, Bangladesh',
        ]);
        ResearcherProfile::create([
            'user_id' => $anisa->id, 'title' => 'Assistant Professor',
            'institution' => 'BRAC University', 'department' => 'Computer Science & Engineering',
            'bio' => 'Dr. Rahman studies how people read, focus, and interact with interfaces on small screens. Her lab runs short, well-organised studies known for clear instructions and prompt compensation.',
            'linkedin' => 'linkedin.com/in/anisa-rahman', 'institutional_email' => 'anisa@brac.test',
            'research_areas' => 'Human–Computer Interaction, Attention & reading, Usability testing, Mobile UX, Cognitive load',
            'avg_rating' => 4.8, 'ratings_count' => 312,
            'rating_distribution' => ['5' => 86, '4' => 11, '3' => 2, '2' => 1, '1' => 0],
            'followers_count' => 1204,
        ]);

        $karim = User::create([
            'name' => 'Dr. Karim Hasan', 'email' => 'karim@brac.test', 'phone' => '01710000002',
            'password' => $pw, 'role' => 'researcher', 'verification_status' => 'verified',
        ]);
        ResearcherProfile::create([
            'user_id' => $karim->id, 'title' => 'Senior Lecturer', 'institution' => 'BRAC University',
            'department' => 'Psychology', 'bio' => 'Runs memory and language studies.',
            'institutional_email' => 'karim@brac.test', 'research_areas' => 'Memory, Language, Bilingualism',
            'avg_rating' => 4.6, 'ratings_count' => 88,
            'rating_distribution' => ['5' => 74, '4' => 18, '3' => 5, '2' => 2, '1' => 1], 'followers_count' => 210,
        ]);

        $nasrin = User::create([
            'name' => 'Dr. Nasrin Akter', 'email' => 'nasrin@du.test', 'phone' => '01710000003',
            'password' => $pw, 'role' => 'researcher', 'verification_status' => 'verified',
        ]);
        ResearcherProfile::create([
            'user_id' => $nasrin->id, 'title' => 'Associate Professor', 'institution' => 'University of Dhaka',
            'department' => 'Public Health', 'bio' => 'Sleep and wellbeing research.',
            'institutional_email' => 'nasrin@du.test', 'research_areas' => 'Sleep, Wellbeing, Health',
            'avg_rating' => 4.9, 'ratings_count' => 64,
            'rating_distribution' => ['5' => 91, '4' => 7, '3' => 1, '2' => 1, '1' => 0], 'followers_count' => 143,
        ]);

        // Pending researcher (shows in admin verification queue)
        $chowdhury = User::create([
            'name' => 'Dr. S. Chowdhury', 'email' => 'chowdhury@du.test', 'phone' => '01710000004',
            'password' => $pw, 'role' => 'researcher', 'verification_status' => 'pending',
        ]);
        ResearcherProfile::create([
            'user_id' => $chowdhury->id, 'title' => 'Lecturer', 'institution' => 'University of Dhaka',
            'department' => 'CSE', 'institutional_email' => 'chowdhury@du.test', 'research_areas' => 'Vision, Perception',
        ]);

        // ---------------------------------------------------------------
        // Organization (pending)
        // ---------------------------------------------------------------
        $org = User::create([
            'name' => 'Green Valley Research Lab', 'email' => 'org@greenvalley.test', 'phone' => '01720000000',
            'password' => $pw, 'role' => 'organization', 'verification_status' => 'pending',
            'location' => 'Chattogram, Bangladesh',
            'organization_name' => 'Green Valley Research Lab', 'organization_type' => 'lab',
            'registration_documents_path' => json_encode(['registration-documents/greenvalley-cert.pdf']),
        ]);

        // ---------------------------------------------------------------
        // Verification requests (pending queue + verified history)
        // ---------------------------------------------------------------
        VerificationRequest::create([
            'user_id' => $chowdhury->id, 'role' => 'researcher',
            'institutional_email' => 'chowdhury@du.test', 'institutional_affiliation' => 'University of Dhaka, CSE',
            'credential_document_path' => 'credential-documents/chowdhury-id.pdf', 'status' => 'pending',
        ]);
        VerificationRequest::create([
            'user_id' => $org->id, 'role' => 'organization',
            'organization_name' => 'Green Valley Research Lab', 'organization_type' => 'lab',
            'registration_documents_path' => json_encode(['registration-documents/greenvalley-cert.pdf']),
            'status' => 'pending',
        ]);
        foreach ([$anisa, $karim, $nasrin] as $r) {
            VerificationRequest::create([
                'user_id' => $r->id, 'role' => 'researcher',
                'institutional_email' => $r->email, 'institutional_affiliation' => 'Verified institution',
                'credential_document_path' => 'credential-documents/verified.pdf',
                'status' => 'verified', 'reviewed_by' => $admin->id, 'reviewed_at' => now()->subDays(20),
            ]);
        }

        // ---------------------------------------------------------------
        // Studies (some with IRB, one without -> warning demo)
        // ---------------------------------------------------------------
        $mk = fn (array $a) => Study::create($a);
        $s1 = $mk(['researcher_id'=>$anisa->id,'title'=>'Reading habits & attention span','description'=>'A short online survey on reading and focus.','category'=>'Survey','method'=>'online','duration_minutes'=>30,'incentive_type'=>'cash','compensation_amount'=>500,'slots'=>12,'status'=>'open','participants_count'=>84,'irb_document_path'=>'irb-documents/reading-irb.pdf','irb_flagged'=>false,'irb_board'=>'BRAC University IRB','irb_ref'=>'IRB-2026-114','irb_valid_until'=>'Dec 2026']);
        $s2 = $mk(['researcher_id'=>$anisa->id,'title'=>'Mobile UI usability test','description'=>'In-person usability session.','category'=>'Usability','method'=>'in_person','duration_minutes'=>45,'incentive_type'=>'voucher','compensation_amount'=>300,'slots'=>0,'status'=>'closed','participants_count'=>32,'irb_document_path'=>'irb-documents/mobile-irb.pdf','irb_flagged'=>false,'irb_board'=>'BRAC University IRB','irb_ref'=>'IRB-2026-101']);
        $s3 = $mk(['researcher_id'=>$anisa->id,'title'=>'Font legibility on small screens','description'=>'Online reading task.','category'=>'Perception','method'=>'online','duration_minutes'=>20,'incentive_type'=>'cash','compensation_amount'=>250,'slots'=>0,'status'=>'closed','participants_count'=>120,'irb_document_path'=>'irb-documents/font-irb.pdf','irb_flagged'=>false,'irb_board'=>'BRAC University IRB','irb_ref'=>'IRB-2025-088']);
        $s4 = $mk(['researcher_id'=>$anisa->id,'title'=>'Cognitive load & layout density','description'=>'In-person experiment.','category'=>'Cognition','method'=>'in_person','duration_minutes'=>60,'incentive_type'=>'cash','compensation_amount'=>700,'slots'=>0,'status'=>'closed','participants_count'=>28,'irb_document_path'=>'irb-documents/cogload-irb.pdf','irb_flagged'=>false,'irb_board'=>'BRAC University IRB','irb_ref'=>'IRB-2025-060']);
        $s5 = $mk(['researcher_id'=>$anisa->id,'title'=>'Quick colour perception task','description'=>'Short online task — ethics doc pending.','category'=>'Perception','method'=>'online','duration_minutes'=>10,'incentive_type'=>'volunteer','compensation_amount'=>0,'slots'=>20,'status'=>'open','participants_count'=>0,'irb_flagged'=>true]);
        $s6 = $mk(['researcher_id'=>$karim->id,'title'=>'Memory & recall survey','description'=>'Online recall study.','category'=>'Memory','method'=>'online','duration_minutes'=>25,'incentive_type'=>'cash','compensation_amount'=>400,'slots'=>0,'status'=>'closed','participants_count'=>40,'irb_document_path'=>'irb-documents/memory-irb.pdf','irb_flagged'=>false,'irb_board'=>'BRAC University IRB','irb_ref'=>'IRB-2026-070']);
        $s7 = $mk(['researcher_id'=>$nasrin->id,'title'=>'Sleep & focus diary','description'=>'Week-long diary study.','category'=>'Health','method'=>'online','duration_minutes'=>15,'incentive_type'=>'voucher','compensation_amount'=>200,'slots'=>0,'status'=>'closed','participants_count'=>25,'irb_document_path'=>'irb-documents/sleep-irb.pdf','irb_flagged'=>false,'irb_board'=>'DU IRB','irb_ref'=>'IRB-2026-033']);
        $s8 = $mk(['researcher_id'=>$karim->id,'title'=>'Bilingual reading study','description'=>'Open online study.','category'=>'Language','method'=>'online','duration_minutes'=>30,'incentive_type'=>'cash','compensation_amount'=>450,'slots'=>10,'status'=>'open','participants_count'=>12,'irb_document_path'=>'irb-documents/biling-irb.pdf','irb_flagged'=>false,'irb_board'=>'BRAC University IRB','irb_ref'=>'IRB-2026-119']);

        // ---------------------------------------------------------------
        // Participants
        // ---------------------------------------------------------------
        // Main participant — Iffat (Bronze, mid-journey to Gold)
        $iffat = User::create([
            'name' => 'Iffat Zerin Nowmi', 'email' => 'iffat@trybe.test', 'phone' => '01730000001',
            'password' => $pw, 'role' => 'participant', 'verification_status' => 'unverified',
            'location' => 'Dhaka, Bangladesh',
        ]);
        // Iffat completes 6 studies (drives credential count + list)
        $iffatCompleted = [$s2, $s3, $s4, $s6, $s7, $s8];
        foreach ($iffatCompleted as $i => $st) {
            StudyParticipation::create([
                'study_id' => $st->id, 'participant_id' => $iffat->id,
                'stage' => 'completed', 'completed_at' => now()->subWeeks($i),
            ]);
        }
        ParticipantProfile::create([
            'user_id' => $iffat->id, 'age' => 22, 'gender' => 'Woman',
            'occupation' => 'Undergraduate student, CSE', 'health_background' => 'None',
            'interests' => 'HCI, UX research, psychology', 'skills' => 'Python, Bangla, UI testing',
            'linkedin' => 'linkedin.com/in/iffat-zerin', 'github' => 'github.com/iffatzerinnowmi',
            'rel_attendance' => 92, 'rel_completion' => 88, 'rel_reviews' => 80, 'reliability_score' => 88,
            'completed_studies_count' => count($iffatCompleted),
            'credential_level' => CredentialLevel::fromCompletions(count($iffatCompleted))->value,
            'current_streak_weeks' => 2, 'longest_streak_weeks' => 4, 'last_active_week' => now()->startOfWeek(),
            'endorsement_count' => 2, 'is_verified_participant' => false,
        ]);
        NotificationPreference::create(['user_id' => $iffat->id]);

        // Rafi — endorsement demo (4 of 5 endorsements already)
        $rafi = User::create([
            'name' => 'Rafi Ahmed', 'email' => 'rafi@trybe.test', 'phone' => '01730000002',
            'password' => $pw, 'role' => 'participant', 'verification_status' => 'unverified',
        ]);
        ParticipantProfile::create([
            'user_id' => $rafi->id, 'occupation' => 'Student', 'skills' => 'Survey, Interviews',
            'rel_attendance' => 95, 'rel_completion' => 90, 'rel_reviews' => 88, 'reliability_score' => 92,
            'completed_studies_count' => 8, 'credential_level' => CredentialLevel::fromCompletions(8)->value,
            'endorsement_count' => 4, 'is_verified_participant' => false,
        ]);
        StudyParticipation::create(['study_id'=>$s1->id,'participant_id'=>$rafi->id,'stage'=>'completed','completed_at'=>now()->subDays(1)]);
        foreach ([$anisa, $karim, $nasrin, $chowdhury] as $j => $r) {
            Endorsement::create([
                'participant_id' => $rafi->id, 'researcher_id' => $r->id, 'study_id' => $s1->id + $j,
                'tags' => [['Punctual','Reliable'],['Prepared'],['Clear Communicator','Engaged'],['Detail-Oriented']][$j],
            ]);
        }

        // Endorsements for Iffat (2)
        Endorsement::create(['participant_id'=>$iffat->id,'researcher_id'=>$anisa->id,'study_id'=>$s2->id,'tags'=>['Punctual','Reliable']]);
        Endorsement::create(['participant_id'=>$iffat->id,'researcher_id'=>$karim->id,'study_id'=>$s6->id,'tags'=>['Prepared']]);

        // Endorsement-queue participants
        $sadia = $this->participant('Sadia Momo', 'sadia@trybe.test', '01730000003', $pw, 5);
        $tanvir = $this->participant('Tanvir Khan', 'tanvir@trybe.test', '01730000004', $pw, 3);

        // A few more participants for followers/reviews
        $extra = [];
        foreach (['Mim','Arafat','Nabila','Sabbir','Raisa','Fahim'] as $k => $nm) {
            $extra[] = $this->participant($nm, strtolower($nm).'@trybe.test', '017400000'.$k, $pw, rand(1, 20));
        }

        // ---------------------------------------------------------------
        // Follows (real rows) for Dr. Anisa
        // ---------------------------------------------------------------
        foreach ([$rafi, $sadia, $tanvir] as $f) {
            Follow::create(['follower_id' => $f->id, 'researcher_id' => $anisa->id]);
        }

        // ---------------------------------------------------------------
        // A few study reviews (real rows) for Anisa's studies
        // ---------------------------------------------------------------
        foreach ([$rafi, $sadia, $tanvir, $extra[0], $extra[1]] as $p) {
            StudyReview::create([
                'study_id' => $s3->id, 'researcher_id' => $anisa->id, 'participant_id' => $p->id,
                'reviewer_role' => 'researcher',
                'stars' => rand(4, 5), 'comment' => 'Clear instructions, quick payment.',
            ]);
        }

        // ---------------------------------------------------------------
        // Researcher reviews OF Iffat, on studies she actually completed.
        //
        // reviewer_role MUST be 'researcher'. The column defaults to
        // 'participant' (a participant reviewing the study), and the
        // ReliabilityService only counts reviews written BY researchers.
        // Without these three rows Iffat's rel_reviews computes to 0 and
        // her live score caps at 80.
        // ---------------------------------------------------------------
        StudyReview::create([
            'study_id' => $s2->id, 'researcher_id' => $anisa->id, 'participant_id' => $iffat->id,
            'reviewer_role' => 'researcher',
            'stars' => 5, 'comment' => 'Punctual and thorough throughout the session.',
        ]);
        StudyReview::create([
            'study_id' => $s6->id, 'researcher_id' => $karim->id, 'participant_id' => $iffat->id,
            'reviewer_role' => 'researcher',
            'stars' => 5, 'comment' => 'Excellent recall, followed instructions precisely.',
        ]);
        StudyReview::create([
            'study_id' => $s7->id, 'researcher_id' => $nasrin->id, 'participant_id' => $iffat->id,
            'reviewer_role' => 'researcher',
            'stars' => 4, 'comment' => 'Completed the full diary period without prompting.',
        ]);

        // ---------------------------------------------------------------
        // Per-member seeders. These attach to the users and studies created
        // above — they never create their own.
        // ---------------------------------------------------------------
        $this->call([
            TopicSeeder::class,           // Member 4 — shared topic vocabulary
            MatchingTopicsSeeder::class,  // Member 4 — tags, criteria, invitations
        ]);

        $this->command->info('TRYBE dummy data seeded: '.User::count().' users, '.Study::count().' studies.');
    }

    /** Helper: create a participant with a basic profile. */
    private function participant(string $name, string $email, string $phone, string $pw, int $completed): User
    {
        $u = User::create([
            'name' => $name, 'email' => $email, 'phone' => $phone,
            'password' => $pw, 'role' => 'participant', 'verification_status' => 'unverified',
        ]);
        ParticipantProfile::create([
            'user_id' => $u->id, 'occupation' => 'Student',
            'rel_attendance' => rand(70, 99), 'rel_completion' => rand(70, 99), 'rel_reviews' => rand(60, 95),
            'reliability_score' => rand(70, 95),
            'completed_studies_count' => $completed,
            'credential_level' => CredentialLevel::fromCompletions($completed)->value,
            'endorsement_count' => rand(0, 4),
        ]);
        return $u;
    }
}