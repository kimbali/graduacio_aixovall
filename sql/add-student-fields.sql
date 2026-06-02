USE graduacio_aixovall_2026;

SET @student_name_exists = (
  SELECT COUNT(*)
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'reservations'
    AND column_name = 'student_name'
);

SET @add_student_name_sql = IF(
  @student_name_exists = 0,
  'ALTER TABLE reservations ADD COLUMN student_name VARCHAR(120) NOT NULL AFTER nia',
  'SELECT ''student_name already exists'''
);

PREPARE add_student_name_stmt FROM @add_student_name_sql;
EXECUTE add_student_name_stmt;
DEALLOCATE PREPARE add_student_name_stmt;

SET @student_email_exists = (
  SELECT COUNT(*)
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'reservations'
    AND column_name = 'student_email'
);

SET @add_student_email_sql = IF(
  @student_email_exists = 0,
  'ALTER TABLE reservations ADD COLUMN student_email VARCHAR(160) NOT NULL AFTER student_name',
  'SELECT ''student_email already exists'''
);

PREPARE add_student_email_stmt FROM @add_student_email_sql;
EXECUTE add_student_email_stmt;
DEALLOCATE PREPARE add_student_email_stmt;
