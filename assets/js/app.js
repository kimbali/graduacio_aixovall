const state = {
  nia: '',
  alreadyReserved: 0,
  selectedZone: null,
  selectedSeats: [],
  reservedSeats: []
};

const selectors = {
  steps: document.querySelectorAll('.step'),
  niaForm: document.querySelector('#nia-form'),
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
  successMessage: document.querySelector('#success-message')
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
  selectors.steps.forEach(step => step.classList.toggle('is-active', step.id === stepId));
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

async function handleNiaSubmit(event) {
  event.preventDefault();
  const nia = selectors.niaInput.value.trim().toUpperCase();

  if (!/^\d{6}[A-Z]$/.test(nia)) {
    showMessage(selectors.niaMessage, 'El NIA ha de tenir 6 números i una lletra al final.', 'error');
    return;
  }

  try {
    const data = await postJson('api/check-student.php', { nia });
    state.nia = data.nia;
    state.alreadyReserved = data.reservedCount;

    if (state.alreadyReserved >= 4) {
      showMessage(selectors.niaMessage, 'Aquest NIA ja té 4 seients reservats.', 'error');
      return;
    }

    selectors.reservationSummary.textContent = `NIA ${state.nia}: ja té ${state.alreadyReserved} seient(s). En pot reservar ${4 - state.alreadyReserved} més.`;
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
    button.className = 'zone-card';
    button.type = 'button';
    button.innerHTML = `<strong>${zone.name}</strong><p>${zone.description}</p><small>Files ${zone.rows[0]}-${zone.rows.at(-1)}</small>`;
    button.addEventListener('click', () => selectZone(zone));
    selectors.zoneGrid.appendChild(button);
  });
}

async function selectZone(zone) {
  state.selectedZone = zone;
  state.selectedSeats = [];
  selectors.zoneSummary.textContent = `${zone.name} · ${zone.description}`;

  try {
    const data = await getJson(`api/seats.php?zone=${encodeURIComponent(zone.id)}`);
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

    seats.forEach(seat => line.appendChild(createSeatButton(zone.id, row, seat)));
    rowElement.append(rowNumber, line);
    selectors.seatMap.appendChild(rowElement);
  });
}

function buildSeats(zone) {
  return zone.rows.map(row => ({
    row,
    seats: getSeatNumbers(zone.side, zone.maxSeat)
  }));
}

function getSeatNumbers(side, maxSeat) {
  if (side === 'left') return oddNumbers(maxSeat).reverse();
  if (side === 'right') return evenNumbers(maxSeat);
  return [...oddNumbers(maxSeat).reverse(), ...evenNumbers(maxSeat)];
}

function oddNumbers(max) {
  return Array.from({ length: Math.ceil(max / 2) }, (_, index) => index * 2 + 1).filter(number => number <= max);
}

function evenNumbers(max) {
  return Array.from({ length: Math.floor(max / 2) }, (_, index) => (index + 1) * 2).filter(number => number <= max);
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
    state.selectedSeats = state.selectedSeats.filter(selected => selected.id !== seat.id);
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
    const selected = state.selectedSeats.some(seatItem => button.title === `${seatItem.zone}, fila ${seatItem.row}, seient ${seatItem.seat}`);
    button.classList.toggle('is-selected', selected);
  });
}

function updateSelectionPanel() {
  selectors.selectedCount.textContent = state.selectedSeats.length;
  selectors.reserveBtn.disabled = state.selectedSeats.length === 0;
  selectors.selectedSeats.textContent = state.selectedSeats.length
    ? state.selectedSeats.map(seat => `${seat.zone} fila ${seat.row} seient ${seat.seat}`).join(' · ')
    : 'Cap encara';
  showMessage(selectors.seatMessage, '', 'success');
}

async function reserveSelectedSeats() {
  try {
    selectors.reserveBtn.disabled = true;
    const data = await postJson('api/reserve.php', {
      nia: state.nia,
      seats: state.selectedSeats
    });

    selectors.successMessage.textContent = `Reserva feta per al NIA ${state.nia}: ${data.seats.join(', ')}.`;
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
  state.reservedSeats = [...new Set([...state.reservedSeats, ...reservedSeats])];
  state.selectedSeats = state.selectedSeats.filter(seat => !reservedSeats.includes(seat.id));

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
    body: JSON.stringify(payload)
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
