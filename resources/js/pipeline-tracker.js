/**
 * Pipeline Tracker - Manages participant stages in a visual pipeline view
 */

const pipelineTracker = {
  async loadPipeline(studyId, container) {
    try {
      const response = await fetch(`/api/v1/studies/${studyId}/pipeline`, {
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
      });

      if (!response.ok) {
        if (response.status === 401 || response.status === 403) {
          container.innerHTML = '<p class="text-dim text-[12.5px]">You don\'t have access to view the pipeline.</p>';
          return;
        }
        throw new Error(`Failed to load pipeline: ${response.statusText}`);
      }

      const data = await response.json();
      renderPipeline(studyId, data.data, container);
    } catch (error) {
      console.error('Pipeline load error:', error);
      container.innerHTML = `<p class="text-red-600 text-[12px]">Error loading pipeline: ${error.message}</p>`;
    }
  },
};

function xsrfToken() {
  const cookie = document.cookie.split('; ').find(value => value.startsWith('XSRF-TOKEN='));
  return cookie ? decodeURIComponent(cookie.split('=').slice(1).join('=')) : null;
}

async function postPipeline(url, body = {}) {
  const token = xsrfToken();
  const response = await fetch(url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      ...(token ? { 'X-XSRF-TOKEN': token } : {}),
    },
    credentials: 'same-origin',
    body: JSON.stringify(body),
  });

  const payload = await response.json();
  if (!response.ok) throw new Error(payload.message || 'Request failed.');
  return payload;
}

function renderPipeline(studyId, pipelineData, container) {
  const stages = [
    { value: 'applied', label: 'Applied' },
    { value: 'screened', label: 'Screened' },
    { value: 'confirmed', label: 'Confirmed' },
    { value: 'scheduled', label: 'Scheduled' },
    { value: 'completed', label: 'Completed' },
    { value: 'paid', label: 'Paid' },
  ];

  // Group participants by stage
  const participantsByStage = {};
  stages.forEach(stage => {
    participantsByStage[stage.value] = [];
  });

  pipelineData.participants.forEach(p => {
    if (participantsByStage[p.stage]) {
      participantsByStage[p.stage].push(p);
    }
  });

  // Build pipeline HTML
  const pipelineHTML = `
    <div class="space-y-4">
      <div class="flex gap-2 overflow-x-auto pb-2">
        ${stages.map(stage => `
          <div class="flex-shrink-0 w-40 rounded-lg border border-line bg-surface-soft p-3">
            <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-steel mb-3">${stage.label}</div>
            <div class="space-y-2" data-stage="${stage.value}">
              ${participantsByStage[stage.value].map(p => `
                <div class="rounded-lg bg-surface border border-line-hi p-2.5 text-[12px] cursor-move hover:border-line-hi hover:shadow-sm transition"
                     draggable="true"
                     data-participant-id="${p.participant_id}"
                     data-participant-name="${p.name || 'Unknown'}">
                  <div class="font-semibold text-ink truncate">${p.name || 'Unknown participant'}</div>
                  <div class="text-dim text-[11px] mt-1">${new Date(p.updated_at).toLocaleDateString()}</div>
                </div>
              `).join('')}
              ${participantsByStage[stage.value].length === 0 ? '<p class="text-dim text-[11px] italic">No participants</p>' : ''}
            </div>
          </div>
        `).join('')}
      </div>
      <div class="border-t border-line pt-4">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
          <div>
            <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-steel">Participants</div>
            <p class="mt-1 text-[12px] text-dim">Mark attendance before completing the study.</p>
          </div>
          <button type="button" data-complete-study
                  class="rounded-lg bg-ink px-3 py-2 text-[11px] font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50"
                  ${pipelineData.study_completed ? 'disabled' : ''}>
            ${pipelineData.study_completed ? 'Study completed' : 'Study completed'}
          </button>
        </div>
        <div class="space-y-2" data-participant-roster>
          ${pipelineData.participants.length ? pipelineData.participants.map(p => `
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-line bg-surface-soft p-3"
                 data-roster-participant="${p.participant_id}">
              <div class="min-w-0">
                <div class="truncate text-[13px] font-semibold text-ink">${p.name || 'Unknown participant'}</div>
                <div class="mt-1 text-[11px] text-steel" data-attendance-label>
                  ${p.attendance_status === 'participated' ? 'Participated' : p.attendance_status === 'not_participated' ? 'Not participated' : 'Attendance not marked'}
                </div>
              </div>
              <div class="flex gap-2">
                <button type="button" data-attendance="participated" data-participant-id="${p.participant_id}"
                        class="rounded-lg px-2.5 py-1.5 text-[11px] font-semibold ${p.attendance_status === 'participated' ? 'bg-ok/15 text-ok' : 'border border-line-hi text-dim'}">
                  Participated
                </button>
                <button type="button" data-attendance="not_participated" data-participant-id="${p.participant_id}"
                        class="rounded-lg px-2.5 py-1.5 text-[11px] font-semibold ${p.attendance_status === 'not_participated' ? 'bg-danger/10 text-danger' : 'border border-line-hi text-dim'}">
                  Not participated
                </button>
              </div>
            </div>
          `).join('') : '<p class="text-[12.5px] text-dim">No participants yet.</p>'}
        </div>
      </div>
    </div>
  `;

  container.innerHTML = pipelineHTML;

  // Initialize drag-and-drop
  initializeDragAndDrop(studyId, container);
  initializeAttendanceControls(studyId, container);
}

function initializeAttendanceControls(studyId, container) {
  container.querySelectorAll('[data-attendance]').forEach(button => {
    button.addEventListener('click', async () => {
      button.disabled = true;
      try {
        await postPipeline(`/api/v1/studies/${studyId}/pipeline/attendance`, {
          participant_id: Number(button.dataset.participantId),
          attendance_status: button.dataset.attendance,
        });
        await pipelineTracker.loadPipeline(studyId, container);
      } catch (error) {
        alert(error.message);
        button.disabled = false;
      }
    });
  });

  const completeButton = container.querySelector('[data-complete-study]');
  completeButton?.addEventListener('click', async () => {
    if (!confirm('Mark this study as completed? Only participants marked Participated will enter endorsements.')) return;
    completeButton.disabled = true;
    try {
      await postPipeline(`/api/v1/studies/${studyId}/pipeline/complete`);
      await pipelineTracker.loadPipeline(studyId, container);
    } catch (error) {
      alert(error.message);
      completeButton.disabled = false;
    }
  });
}

function initializeDragAndDrop(studyId, container) {
  const participants = container.querySelectorAll('[data-participant-id]');
  const stages = container.querySelectorAll('[data-stage]');

  participants.forEach(el => {
    el.addEventListener('dragstart', (e) => {
      e.dataTransfer.effectAllowed = 'move';
      e.dataTransfer.setData('participantId', el.dataset.participantId);
      e.dataTransfer.setData('participantName', el.dataset.participantName);
      el.style.opacity = '0.5';
    });

    el.addEventListener('dragend', (e) => {
      el.style.opacity = '1';
    });
  });

  stages.forEach(stageEl => {
    stageEl.addEventListener('dragover', (e) => {
      e.preventDefault();
      e.dataTransfer.dropEffect = 'move';
      stageEl.style.backgroundColor = 'var(--color-surface-hi, #f3f4f6)';
    });

    stageEl.addEventListener('dragleave', (e) => {
      stageEl.style.backgroundColor = '';
    });

    stageEl.addEventListener('drop', async (e) => {
      e.preventDefault();
      stageEl.style.backgroundColor = '';

      const participantId = e.dataTransfer.getData('participantId');
      const newStage = stageEl.dataset.stage;

      try {
        const xsrfToken = document.cookie
          .split('; ')
          .find(cookie => cookie.startsWith('XSRF-TOKEN='))
          ?.split('=').slice(1).join('=');

        const response = await fetch(`/api/v1/studies/${studyId}/pipeline/stage`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
            ...(xsrfToken ? { 'X-XSRF-TOKEN': decodeURIComponent(xsrfToken) } : {}),
          },
          body: JSON.stringify({
            participant_id: parseInt(participantId),
            stage: newStage,
          }),
          credentials: 'same-origin',
        });

        if (!response.ok) {
          const error = await response.json();
          alert(`Failed to update participant: ${error.message}`);
          return;
        }

        // Move element in DOM
        const participantEl = container.querySelector(`[data-participant-id="${participantId}"]`);
        if (participantEl) {
          stageEl.appendChild(participantEl);
        }

        console.log(`Participant ${participantId} moved to ${newStage}`);
      } catch (error) {
        console.error('Error updating participant stage:', error);
        alert('Failed to update participant stage');
      }
    });
  });
}

// Initialize when page loads
function initializePipelineTrackers() {
  const pipelineContainers = document.querySelectorAll('[data-pipeline-tracker]');
  pipelineContainers.forEach(container => {
    const studyId = container.dataset.studyId;
    pipelineTracker.loadPipeline(studyId, container);
  });
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initializePipelineTrackers, { once: true });
} else {
  initializePipelineTrackers();
}

export default pipelineTracker;
