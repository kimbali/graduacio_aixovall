const ZONES = [
  { id: 'A', name: 'Zona A', description: 'Bloc lateral esquerre superior', rows: [2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19], side: 'left', maxSeat: 33 },
  { id: 'B', name: 'Zona B', description: 'Bloc central superior', rows: [2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19], side: 'center', maxSeat: 16 },
  { id: 'C', name: 'Zona C', description: 'Bloc lateral dret superior', rows: [2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19], side: 'right', maxSeat: 32 },
  { id: 'D', name: 'Zona D', description: 'Bloc inferior esquerre', rows: [20,21,22,23,24,25,26,27,28,29,30], side: 'left', maxSeat: 31 },
  { id: 'E', name: 'Zona E', description: 'Bloc inferior dret', rows: [20,21,22,23,24,25,26,27,28,29,30], side: 'right', maxSeat: 32 }
];

const BLOCKED_SEATS = [
  // Autoritats: zona vermella
  ...rangeSeats('B', 2, [1,2,3,4,5,6,7,8,9,10,11,12,13]),

  // Alumnes graduats: zona groga aproximada segons el mapa adjunt
  ...rowsRange(3, 13).flatMap(row => rangeSeats('B', row, [1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16])),

  // Docents: zona verda aproximada
  ...rowsRange(2, 12).flatMap(row => rangeSeats('C', row, [14,16,18,20,22,24,26,28,30])),
];

const ACCESSIBLE_SEATS = [
  ...rangeSeats('D', 20, [1,3,5,7]),
  ...rangeSeats('E', 20, [2,4,6,8])
];

function rowsRange(start, end) {
  return Array.from({ length: end - start + 1 }, (_, index) => start + index);
}

function rangeSeats(zone, row, seats) {
  return seats.map(seat => `${zone}-${row}-${seat}`);
}
