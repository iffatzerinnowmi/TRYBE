<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Competition & hackathon listings  (Member 4)
 *
 * A listing is a POINTER, not a registration. TRYBE does not run the
 * competition, take entries or handle money — it helps people find one and
 * then sends them to wherever it actually lives (Devpost, MLH, a university
 * page). Everything here supports that and nothing more.
 *
 * WHY posted_by IS NOT NULLABLE
 * -----------------------------
 * Only verified researchers, organizations and admins may post, and every
 * listing carries the name and badge of whoever posted it. That accountability
 * is what replaces link moderation: a bad link has an owner. Making the column
 * required means a listing can never become anonymous.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competitions', function (Blueprint $t) {
            $t->id();

            $t->foreignId('posted_by')->constrained('users')->cascadeOnDelete();

            $t->string('name');
            $t->string('organizer')->nullable();
            $t->text('description')->nullable();

            // Validated as https-only at the API. A javascript: URL stored
            // here would execute in the clicking user's session — that is
            // stored XSS, not merely an unverified link.
            $t->string('external_url', 2048);

            $t->date('deadline')->nullable();

            // Displayed, never enforced. There are no teams on the platform.
            $t->unsignedTinyInteger('team_min')->nullable();
            $t->unsignedTinyInteger('team_max')->nullable();

            $t->string('prize')->nullable();
            $t->string('location')->nullable();

            $t->timestamps();

            // The board's only query: not past its deadline, newest first.
            $t->index(['deadline', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competitions');
    }
};
