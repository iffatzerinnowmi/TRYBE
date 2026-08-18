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
        if (response.status === 403) {
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
    </div>
  `;

  container.innerHTML = pipelineHTML;

  // Initialize drag-and-drop
  initializeDragAndDrop(studyId, container);
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
        const response = await fetch(`/api/v1/studies/${studyId}/pipeline/stage`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
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
document.addEventListener('DOMContentLoaded', () => {
  const pipelineContainers = document.querySelectorAll('[data-pipeline-tracker]');
  pipelineContainers.forEach(container => {
    const studyId = container.dataset.studyId;
    pipelineTracker.loadPipeline(studyId, container);
  });
});

export default pipelineTracker;
