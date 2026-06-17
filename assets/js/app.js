const state = {
  nia: '',
  studentName: '',
  studentEmail: '',
  alreadyReserved: 0,
  selectedZone: null,
  selectedSeats: [],
  reservedSeats: [],
};

const selectors = {
  steps: document.querySelectorAll('.step'),
  niaForm: document.querySelector('#nia-form'),
  studentNameInput: document.querySelector('#student-name'),
  studentEmailInput: document.querySelector('#student-email'),
  niaInput: document.querySelector('#nia'),
  niaMessage: document.querySelector('#nia-message'),
  zoneGrid: document.querySelector('#zone-grid'),
  reservationSummary: document.querySelector('#reservation-summary'),
  zoneSummary: document.querySelector('#zone-summary'),
  seatMap: document.querySelector('#seat-map'),
  selectedCount: document.querySelector('#selected-count'),
  selectedSeats: document.querySelector('#selected-seats'),
  reserveBtn: document.querySelector('#reserve-btn'),
  seatMessage: document.querySelector('#seat-message'),
  successMessage: document.querySelector('#success-message'),
  pmrLegendPill: document.querySelector('#pmr-legend-pill'),
};

init();

function init() {
  document.querySelectorAll('[data-next]').forEach(button => {
    button.addEventListener('click', () => showStep(button.dataset.next));
  });

  document.querySelectorAll('[data-back]').forEach(button => {
    button.addEventListener('click', () => showStep(button.dataset.back));
  });

  selectors.niaForm.addEventListener('submit', handleNiaSubmit);
  selectors.reserveBtn.addEventListener('click', reserveSelectedSeats);
  renderZones();
}

function showStep(stepId) {
  selectors.steps.forEach(step =>
    step.classList.toggle('is-active', step.id === stepId),
  );
  window.scrollTo({ top: 0, behavior: 'smooth' });

  const activeStep = document.getElementById(stepId);
  const autofocusInput = activeStep?.querySelector('[autofocus]');
  if (autofocusInput) {
    setTimeout(() => autofocusInput.focus({ preventScroll: true }), 0);
  }
}

async function handleNiaSubmit(event) {
  event.preventDefault();
  const studentName = selectors.studentNameInput.value.trim();
  const studentEmail = selectors.studentEmailInput.value.trim();
  const nia = selectors.niaInput.value.trim().toUpperCase();

  if (studentName === '') {
    showMessage(selectors.niaMessage, 'Introdueix el nom i cognoms.', 'error');
    return;
  }

  if (!selectors.studentEmailInput.validity.valid) {
    showMessage(selectors.niaMessage, 'Introdueix un email vàlid.', 'error');
    return;
  }

  if (!/^\d{6}[A-Z]$/.test(nia)) {
    showMessage(
      selectors.niaMessage,
      'El NIA ha de tenir 6 números i una lletra al final.',
      'error',
    );
    return;
  }

  try {
    const data = await postJson('api/check-student.php', { nia });
    state.nia = data.nia;
    state.studentName = studentName;
    state.studentEmail = studentEmail;
    state.alreadyReserved = data.reservedCount;

    if (state.alreadyReserved >= 4) {
      showMessage(
        selectors.niaMessage,
        'Aquest NIA ja té 4 seients reservats. No es permet modificar la reserva.',
        'error',
      );
      return;
    }

    selectors.reservationSummary.innerHTML = `Reserva per a: <span>${escapeHtml(state.studentName)}</span> <span>${escapeHtml(state.studentEmail)}</span> <span>NIA: ${escapeHtml(state.nia)}</span>`;
    showMessage(selectors.niaMessage, '', 'success');
    showStep('step-zone');
  } catch (error) {
    showMessage(selectors.niaMessage, error.message, 'error');
  }
}

function renderZones() {
  selectors.zoneGrid.innerHTML = '';

  ZONES.forEach(zone => {
    const button = document.createElement('button');
    button.className = `zone-card zone-card-${zone.id.toLowerCase()}`;
    button.type = 'button';
    button.innerHTML = `<strong>${zone.name}</strong><p>${zone.description}</p>`;
    button.addEventListener('click', () => selectZone(zone));
    selectors.zoneGrid.appendChild(button);
  });
}

async function selectZone(zone) {
  state.selectedZone = zone;
  state.selectedSeats = [];
  selectors.zoneSummary.innerHTML = `${zone.name} · ${zone.description}`;
  selectors.pmrLegendPill?.classList.toggle(
    'is-visible',
    ['D', 'E'].includes(zone.id),
  );

  try {
    const data = await getJson(
      `api/seats.php?zone=${encodeURIComponent(zone.id)}`,
    );
    state.reservedSeats = data.reservedSeats;
    renderSeatMap(zone);
    updateSelectionPanel();
    showStep('step-seats');
  } catch (error) {
    selectors.zoneSummary.textContent = error.message;
  }
}

function renderSeatMap(zone) {
  selectors.seatMap.innerHTML = '';
  const seatsByRow = buildSeats(zone);

  seatsByRow.forEach(({ row, seats }) => {
    const rowElement = document.createElement('div');
    rowElement.className = 'seat-row';

    const rowNumber = document.createElement('div');
    rowNumber.className = 'row-number';
    rowNumber.textContent = row;

    const line = document.createElement('div');
    line.className = 'seats-line';

    seats.forEach(seat =>
      line.appendChild(createSeatButton(zone.id, row, seat)),
    );
    rowElement.append(rowNumber, line);
    selectors.seatMap.appendChild(rowElement);
  });
}

function buildSeats(zone) {
  return zone.rows.map(row => ({
    row,
    seats: getSeatNumbers(zone, row),
  }));
}

function getSeatNumbers(zone, row) {
  return zoneRowSeats(zone.id, row);
}

function createSeatButton(zone, row, seat) {
  const id = `${zone}-${row}-${seat}`;
  const button = document.createElement('button');
  button.className = 'seat';
  button.type = 'button';
  button.textContent = seat;
  button.title = `${zone}, fila ${row}, seient ${seat}`;

  const isBlocked = BLOCKED_SEATS.includes(id);
  const isReserved = state.reservedSeats.includes(id);
  const isAccessible = ACCESSIBLE_SEATS.includes(id);

  button.classList.toggle('is-blocked', isBlocked);
  button.classList.toggle('is-reserved', isReserved);
  button.classList.toggle('is-accessible', isAccessible);
  button.disabled = isBlocked || isReserved;

  if (!button.disabled) {
    button.addEventListener('click', () => toggleSeat({ id, zone, row, seat }));
  }

  return button;
}

function toggleSeat(seat) {
  const exists = state.selectedSeats.some(selected => selected.id === seat.id);

  if (exists) {
    state.selectedSeats = state.selectedSeats.filter(
      selected => selected.id !== seat.id,
    );
  } else if (state.selectedSeats.length + state.alreadyReserved < 4) {
    state.selectedSeats.push(seat);
  } else {
    showMessage(selectors.seatMessage, 'Màxim 4 seients per NIA.', 'error');
    return;
  }

  syncSelectedSeatButtons();
  updateSelectionPanel();
}

function syncSelectedSeatButtons() {
  document.querySelectorAll('.seat').forEach(button => {
    const selected = state.selectedSeats.some(
      seatItem =>
        button.title ===
        `${seatItem.zone}, fila ${seatItem.row}, seient ${seatItem.seat}`,
    );
    button.classList.toggle('is-selected', selected);
  });
}

function updateSelectionPanel() {
  selectors.selectedCount.textContent = state.selectedSeats.length;
  selectors.reserveBtn.disabled = state.selectedSeats.length === 0;
  selectors.selectedSeats.classList.add('selected-seats-list');
  selectors.selectedSeats.classList.toggle(
    'empty-pills',
    state.selectedSeats.length === 0,
  );
  selectors.selectedSeats.innerHTML = renderSeatPills(state.selectedSeats);
  showMessage(selectors.seatMessage, '', 'success');
}

async function reserveSelectedSeats() {
  if (state.selectedSeats.length === 0) {
    selectors.reserveBtn.disabled = true;
    showMessage(
      selectors.seatMessage,
      'Selecciona com a mínim una butaca per confirmar la reserva.',
      'error',
    );
    return;
  }

  const studentName =
    state.studentName || selectors.studentNameInput.value.trim();
  const studentEmail =
    state.studentEmail || selectors.studentEmailInput.value.trim();

  if (studentName === '') {
    showMessage(
      selectors.seatMessage,
      'El nom i cognoms no són vàlids. Torna al pas anterior i revisa les dades.',
      'error',
    );
    selectors.reserveBtn.disabled = state.selectedSeats.length === 0;
    return;
  }

  if (studentEmail === '' || !selectors.studentEmailInput.validity.valid) {
    showMessage(
      selectors.seatMessage,
      'L’email no és vàlid. Torna al pas anterior i revisa les dades.',
      'error',
    );
    selectors.reserveBtn.disabled = state.selectedSeats.length === 0;
    return;
  }

  try {
    selectors.reserveBtn.disabled = true;
    const data = await postJson('api/reserve.php', {
      nia: state.nia,
      student_name: studentName,
      student_email: studentEmail,
      seats: state.selectedSeats,
    });

    renderSuccessMessage({
      studentName,
      studentEmail,
      nia: state.nia,
      seats: state.selectedSeats,
      fallbackSeats: data.seats,
    });
    resetSelection();
    showStep('step-success');
  } catch (error) {
    selectors.reserveBtn.disabled = false;
    if (error.status === 409 && Array.isArray(error.data?.reservedSeats)) {
      markSeatsAsReserved(error.data.reservedSeats);
    }
    showMessage(selectors.seatMessage, error.message, 'error');
  }
}

function markSeatsAsReserved(reservedSeats) {
  state.reservedSeats = [
    ...new Set([...state.reservedSeats, ...reservedSeats]),
  ];
  state.selectedSeats = state.selectedSeats.filter(
    seat => !reservedSeats.includes(seat.id),
  );

  if (state.selectedZone) {
    renderSeatMap(state.selectedZone);
    syncSelectedSeatButtons();
  }
  updateSelectionPanel();
}

function resetSelection() {
  state.selectedZone = null;
  state.selectedSeats = [];
  state.reservedSeats = [];
  state.studentName = '';
  state.studentEmail = '';
  selectors.studentNameInput.value = '';
  selectors.studentEmailInput.value = '';
  selectors.niaInput.value = '';
}

async function getJson(url) {
  const response = await fetch(url);
  const data = await response.json();
  if (!response.ok) throw new Error(data.message || 'Error carregant dades.');
  return data;
}

async function postJson(url, payload) {
  const response = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  });
  const data = await response.json();
  if (!response.ok) {
    const error = new Error(data.message || 'Error processant la petició.');
    error.status = response.status;
    error.data = data;
    throw error;
  }
  return data;
}

function showMessage(element, text, type) {
  element.textContent = text;
  element.className = `message ${type || ''}`;
}

function renderSeatPills(seats) {
  const filledPills = seats.map(
    seat => `<span class="pill">Fila ${seat.row} · Butaca ${seat.seat}</span>`,
  );
  const emptyPills = Array.from(
    { length: Math.max(0, 4 - seats.length) },
    (_, index) =>
      `<span class="pill empty-pill" aria-label="Butaca pendent ${index + 1}"></span>`,
  );
  return [...filledPills, ...emptyPills].join('');
}

function renderSuccessMessage({
  studentName,
  studentEmail,
  nia,
  seats,
  fallbackSeats,
}) {
  const seatPills = seats.length
    ? renderSeatPills(seats)
    : fallbackSeats
        .map(seat => `<span class="pill">${escapeHtml(seat)}</span>`)
        .join('');

  selectors.successMessage.innerHTML = `
    <p class="success-lead">🎉 Reserva confirmada per a <strong>${escapeHtml(studentName)}</strong>.</p>
    <p>Hem guardat les butaques familiars vinculades al NIA <strong>${escapeHtml(nia)}</strong>. L’equip de programació ja pot deixar de picar tecles: les teves places són a la nostra llista.</p>
    <div class="selected-seats-list success-seat-pills" aria-label="Butaques confirmades">${seatPills}</div>
    <p>Hauries de rebre un email a <strong>${escapeHtml(studentEmail)}</strong> amb els detalls de la reserva. Si no el veus, revisa la carpeta de correu brossa.</p>
  `;
}

function escapeHtml(value) {
  return String(value).replace(
    /[&<>"']/g,
    character =>
      ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;',
      })[character],
  );
}
