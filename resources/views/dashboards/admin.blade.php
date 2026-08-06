@extends('layouts.app')

@section('title', 'Admin overview')

@section('content')
<div class="wrap pb-20">

    <x-page-header
        eyebrow="Admin · Platform overview"
        title="Everything that needs your review."
        subtitle="Approving a request here is what puts the verified badge on a researcher's profile." />

    @if (session('status'))
        <x-alert type="success" class="mb-6">{{ session('status') }}</x-alert>
    @endif

    <div class="grid grid-cols-2 gap-4 py-2 md:grid-cols-4">
        <x-stat-card icon="📥" :value="$stats['pending']" label="PENDING VERIFICATIONS" class="reveal">
            <x-badge tone="flame">
                {{ $stats['researchers'] }} researchers · {{ $stats['organizations'] }} orgs
            </x-badge>
        </x-stat-card>

        <x-stat-card icon="🧑‍🤝‍🧑" :value="$stats['users']" label="TOTAL USERS" class="reveal reveal-d1" />
        <x-stat-card icon="📋" :value="$stats['studies']" label="STUDIES POSTED" class="reveal reveal-d2" />
        <x-stat-card icon="✅" :value="$stats['completed']" label="SESSIONS COMPLETED" class="reveal reveal-d3" />
    </div>

    <div class="mt-6 grid items-start gap-6 lg:grid-cols-[1.6fr_1fr]">

        {{-- ================= LEFT: the queue ================= --}}
        <div class="space-y-6">

            <x-panel label="Pending verifications" class="reveal reveal-d2">
                <x-slot:action>{{ $stats['pending'] }} waiting</x-slot:action>

                @forelse ($pending as $request)
                    <div class="mb-3 rounded-xl border border-line p-4 last:mb-0">
                        <div class="flex flex-wrap items-center gap-3.5">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl
                                        border border-line bg-surface-soft text-lg">
                                {{ $request->role->value === 'organization' ? '🏛️' : '🔬' }}
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <x-badge tone="plum">{{ strtoupper($request->role->label()) }}</x-badge>
                                    <span class="text-[14px] font-semibold text-ink">
                                        {{ $request->user->name }}
                                    </span>
                                </div>

                                <p class="mt-1 text-[12.5px] text-dim">
                                    {{ $request->institutional_affiliation
                                       ?? ($request->organization_name . ' · ' . str_replace('_', ' ', (string) $request->organization_type)) }}
                                </p>

                                <p class="font-mono text-[11px] text-steel">
                                    Submitted {{ $request->created_at->diffForHumans() }}
                                </p>
                            </div>
                        </div>

                        {{-- Attached documents --}}
                        <div class="mt-3 flex flex-wrap gap-2">
                            @if ($request->credential_document_path)
                                <a href="{{ asset('storage/' . $request->credential_document_path) }}" target="_blank"
                                   class="rounded-lg border border-line-hi px-3 py-1.5 text-[11.5px] text-ink
                                          transition hover:border-plum hover:text-plum">
                                    🪪 Credential document
                                </a>
                            @endif

                            @if ($request->registration_documents_path)
                                <a href="{{ asset('storage/' . $request->registration_documents_path) }}" target="_blank"
                                   class="rounded-lg border border-line-hi px-3 py-1.5 text-[11.5px] text-ink
                                          transition hover:border-plum hover:text-plum">
                                    📑 Registration documents
                                </a>
                            @endif

                            @if (! $request->credential_document_path && ! $request->registration_documents_path)
                                <span class="rounded-lg bg-flame/10 px-3 py-1.5 text-[11.5px] text-flame">
                                    ⚠ No documents attached
                                </span>
                            @endif
                        </div>

                        {{-- The two buttons that actually change the database --}}
                        <div class="mt-4 flex flex-wrap items-center gap-2.5 border-t border-line pt-4">
                            <form method="POST" action="{{ route('admin.verifications.approve', $request) }}">
                                @csrf
                                <x-btn type="submit" size="sm">Approve</x-btn>
                            </form>

                            <form method="POST" action="{{ route('admin.verifications.reject', $request) }}"
                                  class="flex flex-1 flex-wrap items-center gap-2.5">
                                @csrf
                                <input type="text" name="rejection_reason"
                                       placeholder="Reason (optional)"
                                       class="min-w-[160px] flex-1 rounded-xl border border-line-hi bg-surface-soft
                                              px-3.5 py-2.5 text-[12.5px] text-ink outline-none
                                              placeholder:text-steel focus:border-plum focus:bg-surface">
                                <x-btn type="submit" variant="ghost" size="sm">Reject</x-btn>
                            </form>
                        </div>
                    </div>
                @empty
                    <p class="py-3 text-[13.5px] text-dim">
                        Nothing waiting. Every verification request has been reviewed.
                    </p>
                @endforelse
            </x-panel>

            <x-panel label="Recently reviewed" class="reveal reveal-d3">
                @forelse ($recent as $item)
                    <div class="flex items-center gap-3 border-b border-line py-3 last:border-none last:pb-1">
                        <span class="font-mono text-[11px] text-steel">
                            {{ $item->reviewed_at?->format('d M H:i') }}
                        </span>

                        <p class="min-w-0 flex-1 truncate text-[13px] text-ink">
                            <b>{{ $item->reviewer->name ?? 'System' }}</b>
                            {{ $item->status->value === 'verified' ? 'approved' : 'rejected' }}
                            {{ $item->user->name }}
                        </p>

                        <x-badge tone="{{ $item->status->value === 'verified' ? 'ok' : 'danger' }}">
                            {{ $item->status->label() }}
                        </x-badge>
                    </div>
                @empty
                    <p class="py-3 text-[13.5px] text-dim">No decisions recorded yet.</p>
                @endforelse
            </x-panel>
        </div>

        {{-- ================= RIGHT: platform rules ================= --}}
        <div class="space-y-6">
            <x-panel label="Platform settings"
                     note="These are read from config/platform.php. Every module reads the same values."
                     class="reveal reveal-d2">

                @foreach ($settings as $setting)
                    <div class="border-b border-line py-4 last:border-none last:pb-0">
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-[13px] font-semibold text-ink">{{ $setting['label'] }}</span>
                            <span class="font-mono text-[13px] text-plum">{{ $setting['value'] }}</span>
                        </div>
                        <p class="mt-1 text-[12px] leading-relaxed text-dim">{{ $setting['desc'] }}</p>
                        <code class="mt-1.5 block font-mono text-[10.5px] text-steel">
                            platform.{{ $setting['key'] }}
                        </code>
                    </div>
                @endforeach

                <p class="mt-4 rounded-xl bg-surface-soft px-3.5 py-3 text-[12px] leading-relaxed text-dim">
                    Editing these from this page needs a settings table so the values
                    survive a restart. That's scheduled for Section 8.
                </p>
            </x-panel>
        </div>
    </div>
</div>
@endsection
