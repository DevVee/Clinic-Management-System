-- SSCMS PostgreSQL Schema (converted from MySQL)
-- Run this in Supabase → SQL Editor

-- ── Trigger helper for auto-updating updated_at ──────────────────────────────
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- ── appointments ─────────────────────────────────────────────────────────────
CREATE TABLE appointments (
    id               SERIAL PRIMARY KEY,
    patient_name     VARCHAR(255) NOT NULL,
    category         VARCHAR(50)  NOT NULL,
    phone            VARCHAR(11)  DEFAULT NULL,
    appointment_date DATE         NOT NULL,
    appointment_time TIME         NOT NULL,
    reason           TEXT         NOT NULL,
    appointee        VARCHAR(10)  NOT NULL CHECK (appointee IN ('Doctor','Nurse','Dentist')),
    status           VARCHAR(10)  DEFAULT 'pending' CHECK (status IN ('pending','approved','rejected')),
    created_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_date_status ON appointments(appointment_date, status);

CREATE TRIGGER appointments_updated_at
    BEFORE UPDATE ON appointments
    FOR EACH ROW EXECUTE PROCEDURE update_updated_at_column();

INSERT INTO appointments (id, patient_name, category, phone, appointment_date, appointment_time, reason, appointee, status, created_at, updated_at) VALUES
(4, 'Prince Arvee Avena', 'College', '09168927345', '2025-04-29', '13:00:00', 'Check up', 'Nurse', 'approved', '2025-04-29 15:09:40', '2025-04-29 15:14:40'),
(5, 'Prince Arvee Avena', 'College', '09168927345', '2025-04-30', '15:00:00', 'Check up', 'Nurse', 'pending',  '2025-04-29 15:24:17', '2025-04-29 15:24:17');

SELECT setval('appointments_id_seq', 6);

-- ── grade_years ──────────────────────────────────────────────────────────────
CREATE TABLE grade_years (
    id       SERIAL PRIMARY KEY,
    name     VARCHAR(50)  NOT NULL,
    category VARCHAR(100) NOT NULL
);

INSERT INTO grade_years (id, name, category) VALUES
(1,  'Kinder',   'Pre School'),
(2,  'Grade 1',  'Elementary'),
(3,  'Grade 2',  'Elementary'),
(4,  'Grade 3',  'Elementary'),
(5,  'Grade 4',  'Elementary'),
(6,  'Grade 5',  'Elementary'),
(7,  'Grade 6',  'Elementary'),
(8,  'Grade 7',  'JHS'),
(9,  'Grade 8',  'JHS'),
(10, 'Grade 9',  'JHS'),
(11, 'Grade 10', 'JHS'),
(12, 'Grade 11', 'SHS'),
(13, 'Grade 12', 'SHS'),
(14, '1st Year', 'College'),
(15, '2nd Year', 'College'),
(16, '3rd Year', 'College'),
(17, '4th Year', 'College'),
(18, 'Graduated','Alumni');

SELECT setval('grade_years_id_seq', 19);

-- ── medicines ────────────────────────────────────────────────────────────────
CREATE TABLE medicines (
    id         SERIAL PRIMARY KEY,
    name       VARCHAR(255) NOT NULL UNIQUE,
    quantity   INTEGER      NOT NULL DEFAULT 0,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO medicines (id, name, quantity, created_at) VALUES
(2, 'BIogesic', 13, '2025-04-29 16:27:36');

SELECT setval('medicines_id_seq', 3);

-- ── patients ─────────────────────────────────────────────────────────────────
CREATE TABLE patients (
    id               SERIAL PRIMARY KEY,
    last_name        VARCHAR(255) NOT NULL,
    first_name       VARCHAR(255) NOT NULL,
    middle_name      VARCHAR(255) DEFAULT NULL,
    gender           VARCHAR(10)  NOT NULL CHECK (gender IN ('Male','Female','Other')),
    category         VARCHAR(100) NOT NULL,
    grade_year       VARCHAR(50)  DEFAULT NULL,
    program_section  VARCHAR(100) DEFAULT NULL,
    guardian_contact VARCHAR(50)  DEFAULT NULL
);

INSERT INTO patients (id, last_name, first_name, middle_name, gender, category, grade_year, program_section, guardian_contact) VALUES
(1,  'Doe',     'John',          'A',       'Male',   'Pre School', 'Kinder',   'Section A', '123-456-7890'),
(2,  'Smith',   'Mary',          'B',       'Female', 'Elementary', 'Grade 1',  'Section B', '987-654-3210'),
(3,  'Johnson', 'Alice',         'C',       'Female', 'JHS',        'Grade 7',  'Section A', '555-123-4567'),
(4,  'Brown',   'Bob',           'D',       'Male',   'SHS',        'Grade 11', 'STEM',      '555-987-6543'),
(8,  'Avena',   'Prince Arvee',  'Flores',  'Male',   'College',    '3rd Year', 'BSCS',      '09383072884'),
(18, 'Avena',   'Christian',     'Baral',   'Male',   'Alumni',     NULL,       NULL,        '09168927348'),
(19, 'Ubaña',   'Juan Miguel',   'Bascuguin','Male',  'College',    '3rd Year', 'BSHM',      '09685669215'),
(20, 'Estilo',  'Ken',           'De Jesus','Male',   'College',    '3rd Year', 'BSHM',      '09917745994'),
(21, 'Rivera',  'Arabella',      'Bulan',   'Female', 'College',    '3rd Year', 'BSCS',      '09161819960'),
(22, 'Alday',   'Errol',         'Lasheras','Male',   'College',    '3rd Year', 'BSCS',      '09556622460');

SELECT setval('patients_id_seq', 23);

-- ── program_sections ─────────────────────────────────────────────────────────
CREATE TABLE program_sections (
    id       SERIAL PRIMARY KEY,
    name     VARCHAR(100) NOT NULL,
    category VARCHAR(100) NOT NULL
);

INSERT INTO program_sections (id, name, category) VALUES
(1,  'Section A', 'Pre School'),
(2,  'Section B', 'Pre School'),
(3,  'Section A', 'Elementary'),
(4,  'Section B', 'Elementary'),
(5,  'Section A', 'JHS'),
(6,  'Section B', 'JHS'),
(7,  'STEM',      'SHS'),
(8,  'ABM',       'SHS'),
(9,  'BSIT',      'College'),
(10, 'BSCS',      'College'),
(11, 'BSEd',      'College'),
(12, 'N/A',       'Alumni'),
(14, 'BSTM',      'College'),
(15, 'BSHM',      'College');

SELECT setval('program_sections_id_seq', 16);

-- ── users ────────────────────────────────────────────────────────────────────
CREATE TABLE users (
    id               SERIAL PRIMARY KEY,
    name             VARCHAR(100) NOT NULL,
    email            VARCHAR(100) NOT NULL UNIQUE,
    password         VARCHAR(255) NOT NULL,
    plain_password   VARCHAR(100) DEFAULT NULL,
    admin_category   VARCHAR(20)  NOT NULL CHECK (admin_category IN ('Nurse','Clinic Staff','Doctor','Dentist')),
    role             VARCHAR(50)  DEFAULT NULL,
    profile_picture  VARCHAR(255) DEFAULT NULL,
    created_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TRIGGER users_updated_at
    BEFORE UPDATE ON users
    FOR EACH ROW EXECUTE PROCEDURE update_updated_at_column();

INSERT INTO users (id, name, email, password, plain_password, admin_category, profile_picture) VALUES
(1, 'Arvee Avena',        'arveeavena@sscms.com',       '$2y$10$LL9hHRC1I0BTOmdVxLqEv.HCpjMQFFvk7yAbz2Kw9CBiHtFUUivrW', 'sscms_arvee',          'Clinic Staff', '/uploads/profiles/user_1_1745632534.jpg'),
(2, 'Andrei Espinar',     'andreiespinar@sscms.com',    '$2y$10$CjHJ6.4Jm5lmQYLwkbrTNe26fz9tB9B9cdASOpxUPbs1IhirotCX.', 'andreiespinar_sscms',  'Clinic Staff', NULL),
(3, 'Angelyn Panganiban', 'angelynpanganiban@sscms.com','$2y$10$Ur7REPJdb9oo1UKRZX9/ker3DJVsP7hLKQER62Gj5gt.intvyf7fS', NULL,                   'Clinic Staff', NULL);

SELECT setval('users_id_seq', 4);

-- ── sessions ─────────────────────────────────────────────────────────────────
CREATE TABLE sessions (
    id           SERIAL PRIMARY KEY,
    user_id      INTEGER      NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    session_id   VARCHAR(255) NOT NULL,
    remember_token VARCHAR(64) DEFAULT NULL,
    last_active  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_sessions_user_id ON sessions(user_id);

-- ── medicine_logs ────────────────────────────────────────────────────────────
CREATE TABLE medicine_logs (
    id            SERIAL PRIMARY KEY,
    medicine_id   INTEGER      NOT NULL REFERENCES medicines(id)  ON DELETE CASCADE,
    patient_id    INTEGER      NOT NULL REFERENCES patients(id)   ON DELETE CASCADE,
    quantity_used INTEGER      NOT NULL,
    visit_date    TIMESTAMP    NOT NULL,
    reason        VARCHAR(255) DEFAULT NULL
);

CREATE INDEX idx_medicine_logs_medicine ON medicine_logs(medicine_id);
CREATE INDEX idx_medicine_logs_patient  ON medicine_logs(patient_id);

INSERT INTO medicine_logs (id, medicine_id, patient_id, quantity_used, visit_date, reason) VALUES
(2, 2, 19, 1, '2025-05-14 15:23:00', 'Headache'),
(3, 2, 22, 1, '2025-05-18 13:35:00', 'Headache'),
(4, 2,  8, 1, '2025-05-21 22:20:00', 'Headache');

SELECT setval('medicine_logs_id_seq', 5);

-- ── log_visits ───────────────────────────────────────────────────────────────
CREATE TABLE log_visits (
    id               SERIAL PRIMARY KEY,
    patient_id       INTEGER      NOT NULL,
    reason           VARCHAR(255) NOT NULL,
    other_reason     VARCHAR(255) DEFAULT NULL,
    took_medicine    VARCHAR(3)   DEFAULT 'No' CHECK (took_medicine IN ('Yes','No')),
    medicine_id      INTEGER      DEFAULT NULL,
    medicine_name    VARCHAR(255) DEFAULT NULL,
    medicine_quantity INTEGER     DEFAULT NULL,
    visit_date       VARCHAR(50)  NOT NULL,
    visit_time       VARCHAR(50)  NOT NULL,
    created_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ── visits ───────────────────────────────────────────────────────────────────
CREATE TABLE visits (
    id               SERIAL PRIMARY KEY,
    patient_id       INTEGER      NOT NULL REFERENCES patients(id)  ON DELETE CASCADE,
    reason           VARCHAR(255) NOT NULL,
    other_reason     VARCHAR(255) DEFAULT NULL,
    took_medicine    VARCHAR(3)   NOT NULL DEFAULT 'No' CHECK (took_medicine IN ('Yes','No')),
    medicine_name    VARCHAR(255) DEFAULT NULL REFERENCES medicines(name) ON DELETE SET NULL,
    medicine_quantity INTEGER     DEFAULT NULL,
    visit_time       TIME         NOT NULL,
    visit_date       DATE         NOT NULL,
    created_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    medicine_id      INTEGER      DEFAULT NULL REFERENCES medicines(id) ON DELETE SET NULL
);

CREATE INDEX idx_visits_patient_id ON visits(patient_id);
CREATE INDEX idx_visits_date       ON visits(visit_date);
CREATE INDEX idx_visits_medicine   ON visits(medicine_name);
