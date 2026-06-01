CREATE DATABASE IF NOT EXISTS graduacio_aixovall_2026
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE graduacio_aixovall_2026;

CREATE TABLE IF NOT EXISTS reservations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nia VARCHAR(12) NOT NULL,
  zone CHAR(1) NOT NULL,
  row_number TINYINT UNSIGNED NOT NULL,
  seat_number TINYINT UNSIGNED NOT NULL,
  seat_code VARCHAR(12) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_seat (seat_code),
  INDEX idx_nia (nia),
  INDEX idx_zone (zone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
