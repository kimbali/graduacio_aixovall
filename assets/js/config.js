const ZONES = [
  {
    id: 'A',
    name: 'Zona A',
    description: 'Bloc lateral esquerre superior',
    rows: rowsRange(2, 19),
    layout: [
      { rows: rowsRange(2, 7), seats: oddDescending(29, 15) },
      { rows: rowsRange(8, 15), seats: oddDescending(31, 17) },
      { rows: rowsRange(16, 19), seats: oddDescending(33, 19) },
    ],
  },
  {
    id: 'B',
    name: 'Zona B',
    description: 'Bloc central superior',
    rows: rowsRange(2, 19),
    layout: [
      { rows: rowsRange(2, 3), seats: centerSeats(13, 12) },
      { rows: rowsRange(4, 7), seats: centerSeats(13, 14) },
      { rows: rowsRange(8, 11), seats: centerSeats(15, 14) },
      { rows: rowsRange(12, 15), seats: centerSeats(15, 16) },
      { rows: rowsRange(16, 19), seats: centerSeats(17, 16) },
    ],
  },
  {
    id: 'C',
    name: 'Zona C',
    description: 'Bloc lateral dret superior',
    rows: rowsRange(2, 19),
    layout: [
      { rows: rowsRange(2, 3), seats: evenAscending(14, 28) },
      { rows: rowsRange(4, 11), seats: evenAscending(16, 30) },
      { rows: rowsRange(12, 19), seats: evenAscending(18, 32) },
    ],
  },
  {
    id: 'D',
    name: 'Zona D',
    description: 'Bloc inferior esquerre',
    rows: [20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30],
    side: 'left',
    maxSeat: 31,
  },
  {
    id: 'E',
    name: 'Zona E',
    description: 'Bloc inferior dret',
    rows: [20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30],
    side: 'right',
    maxSeat: 32,
  },
];

const BLOCKED_SEATS = [
  // Autoritats
  ...rangeSeats('B', 2, zoneRowSeats('B', 2)),

  // Alumnes graduats
  ...rowsRange(3, 13).flatMap(row =>
    rangeSeats('B', row, zoneRowSeats('B', row)),
  ),

  // Docents
  ...rowsRange(2, 12).flatMap(row =>
    rangeSeats('C', row, zoneRowSeats('C', row)),
  ),
];

const ACCESSIBLE_SEATS = [
  ...rangeSeats('D', 20, [1, 3, 5, 7]),
  ...rangeSeats('E', 20, [2, 4, 6, 8]),
];

function rowsRange(start, end) {
  return Array.from({ length: end - start + 1 }, (_, index) => start + index);
}

function oddDescending(max, min) {
  return rowsRange(min, max)
    .filter(number => number % 2 === 1)
    .reverse();
}

function evenAscending(min, max) {
  return rowsRange(min, max).filter(number => number % 2 === 0);
}

function centerSeats(leftMax, rightMax) {
  return [...oddDescending(leftMax, 1), ...evenAscending(2, rightMax)];
}

function zoneRowSeats(zoneId, row) {
  const zone = ZONES.find(zoneItem => zoneItem.id === zoneId);
  const section = zone?.layout?.find(layoutSection =>
    layoutSection.rows.includes(row),
  );

  if (section) return section.seats;
  if (zone?.side === 'left') return oddDescending(zone.maxSeat, 1);
  if (zone?.side === 'right') return evenAscending(2, zone.maxSeat);
  return [];
}

function rangeSeats(zone, row, seats) {
  return seats.map(seat => `${zone}-${row}-${seat}`);
}
