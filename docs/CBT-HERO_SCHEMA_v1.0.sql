-- CBT-HERO SCHEMA v1.0
-- Baseline: 25 September 2026
-- Target: MySQL/MariaDB, InnoDB, utf8mb4

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE manager_users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(64) NOT NULL,
  nama VARCHAR(150) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role VARCHAR(20) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
  failed_login_count INT UNSIGNED NOT NULL DEFAULT 0,
  locked_until DATETIME NULL,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_manager_username (username),
  KEY idx_manager_role_status (role, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sys_settings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(100) NOT NULL,
  setting_value LONGTEXT NOT NULL,
  value_type VARCHAR(20) NOT NULL DEFAULT 'STRING',
  is_secret TINYINT(1) NOT NULL DEFAULT 0,
  updated_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_setting_key (setting_key),
  CONSTRAINT fk_setting_updated_by FOREIGN KEY (updated_by) REFERENCES manager_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE auth_login_attempts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  realm VARCHAR(20) NOT NULL,
  username_input VARCHAR(100) NOT NULL,
  account_ref_id BIGINT UNSIGNED NULL,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(500) NULL,
  success TINYINT(1) NOT NULL DEFAULT 0,
  failure_reason VARCHAR(100) NULL,
  attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_auth_user_time (realm, username_input, attempted_at),
  KEY idx_auth_ip_time (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  actor_realm VARCHAR(20) NOT NULL,
  actor_id BIGINT UNSIGNED NULL,
  action VARCHAR(80) NOT NULL,
  module VARCHAR(80) NOT NULL,
  entity_type VARCHAR(80) NULL,
  entity_id BIGINT UNSIGNED NULL,
  description VARCHAR(500) NULL,
  before_json LONGTEXT NULL,
  after_json LONGTEXT NULL,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_audit_module_time (module, created_at),
  KEY idx_audit_entity (entity_type, entity_id),
  KEY idx_audit_actor (actor_realm, actor_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ci_sessions (
  id VARCHAR(128) NOT NULL,
  ip_address VARCHAR(45) NOT NULL,
  timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  data BLOB NOT NULL,
  PRIMARY KEY (id),
  KEY ci_sessions_timestamp (timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rombel (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tingkat TINYINT UNSIGNED NOT NULL,
  kode_rombel VARCHAR(20) NOT NULL,
  display_name VARCHAR(50) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_rombel (tingkat, kode_rombel),
  KEY idx_rombel_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE mata_pelajaran (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kode_mapel VARCHAR(50) NOT NULL,
  nama_mapel VARCHAR(150) NOT NULL,
  singkatan VARCHAR(50) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
  urutan INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_mapel_kode (kode_mapel),
  KEY idx_mapel_status_order (status, urutan)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE peserta (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nisn VARCHAR(30) NOT NULL,
  nama VARCHAR(180) NOT NULL,
  jenis_kelamin CHAR(1) NOT NULL,
  rombel_id BIGINT UNSIGNED NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
  keterangan VARCHAR(255) NULL,
  username VARCHAR(64) NULL,
  password_hash VARCHAR(255) NULL,
  password_encrypted LONGTEXT NULL,
  credential_status VARCHAR(20) NOT NULL DEFAULT 'PENDING',
  failed_login_count INT UNSIGNED NOT NULL DEFAULT 0,
  locked_until DATETIME NULL,
  last_login_at DATETIME NULL,
  credential_changed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_peserta_nisn (nisn),
  UNIQUE KEY uq_peserta_username (username),
  KEY idx_peserta_rombel_status (rombel_id, status),
  CONSTRAINT fk_peserta_rombel FOREIGN KEY (rombel_id) REFERENCES rombel(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE kegiatan (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(180) NOT NULL,
  jenis VARCHAR(20) NOT NULL,
  tahun_pelajaran VARCHAR(20) NOT NULL,
  semester VARCHAR(20) NOT NULL,
  keterangan VARCHAR(500) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
  exam_browser_required TINYINT(1) NOT NULL DEFAULT 0,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_kegiatan_status (status),
  KEY idx_kegiatan_tahun_semester (tahun_pelajaran, semester),
  CONSTRAINT fk_kegiatan_creator FOREIGN KEY (created_by) REFERENCES manager_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ruang (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kode VARCHAR(50) NOT NULL,
  nama VARCHAR(150) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_ruang_kode (kode),
  KEY idx_ruang_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE peserta_kegiatan (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kegiatan_id BIGINT UNSIGNED NOT NULL,
  peserta_id BIGINT UNSIGNED NOT NULL,
  ruang_id BIGINT UNSIGNED NULL,
  nomor_peserta VARCHAR(64) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
  nisn_snapshot VARCHAR(30) NOT NULL,
  nama_snapshot VARCHAR(180) NOT NULL,
  jenis_kelamin_snapshot CHAR(1) NOT NULL,
  rombel_snapshot VARCHAR(50) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_pk_kegiatan_peserta (kegiatan_id, peserta_id),
  UNIQUE KEY uq_pk_kegiatan_nomor (kegiatan_id, nomor_peserta),
  KEY idx_pk_kegiatan_ruang (kegiatan_id, ruang_id),
  KEY idx_pk_peserta (peserta_id),
  CONSTRAINT fk_pk_kegiatan FOREIGN KEY (kegiatan_id) REFERENCES kegiatan(id) ON DELETE CASCADE,
  CONSTRAINT fk_pk_peserta FOREIGN KEY (peserta_id) REFERENCES peserta(id) ON DELETE RESTRICT,
  CONSTRAINT fk_pk_ruang FOREIGN KEY (ruang_id) REFERENCES ruang(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE media_assets (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  storage_type VARCHAR(20) NOT NULL,
  media_kind VARCHAR(20) NOT NULL,
  file_path VARCHAR(500) NULL,
  provider VARCHAR(50) NULL,
  external_id VARCHAR(255) NULL,
  external_url VARCHAR(1000) NULL,
  mime_type VARCHAR(150) NULL,
  size_bytes BIGINT UNSIGNED NULL,
  sha256 CHAR(64) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_media_kind_status (media_kind, status),
  KEY idx_media_sha256 (sha256),
  CONSTRAINT fk_media_creator FOREIGN KEY (created_by) REFERENCES manager_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE import_jobs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  import_type VARCHAR(30) NOT NULL,
  context_type VARCHAR(50) NULL,
  context_id BIGINT UNSIGNED NULL,
  original_filename VARCHAR(255) NOT NULL,
  stored_filename VARCHAR(255) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'UPLOADED',
  total_items INT UNSIGNED NOT NULL DEFAULT 0,
  valid_items INT UNSIGNED NOT NULL DEFAULT 0,
  invalid_items INT UNSIGNED NOT NULL DEFAULT 0,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  validated_at DATETIME NULL,
  committed_at DATETIME NULL,
  KEY idx_import_status (status, created_at),
  CONSTRAINT fk_import_creator FOREIGN KEY (created_by) REFERENCES manager_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE import_staging_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  import_job_id BIGINT UNSIGNED NOT NULL,
  item_no INT UNSIGNED NOT NULL,
  source_ref VARCHAR(255) NULL,
  payload_json LONGTEXT NOT NULL,
  validation_status VARCHAR(20) NOT NULL DEFAULT 'INVALID',
  errors_json LONGTEXT NULL,
  committed_entity_type VARCHAR(50) NULL,
  committed_entity_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_staging_job_item (import_job_id, item_no),
  KEY idx_staging_validation (import_job_id, validation_status),
  CONSTRAINT fk_staging_job FOREIGN KEY (import_job_id) REFERENCES import_jobs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE bank_soal (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kegiatan_id BIGINT UNSIGNED NOT NULL,
  mapel_id BIGINT UNSIGNED NOT NULL,
  tingkat TINYINT UNSIGNED NOT NULL,
  nama_bank VARCHAR(180) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
  version_no INT UNSIGNED NOT NULL DEFAULT 1,
  fingerprint VARCHAR(128) NULL,
  created_by BIGINT UNSIGNED NULL,
  updated_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_bank_context (kegiatan_id, mapel_id, tingkat, status),
  CONSTRAINT fk_bank_kegiatan FOREIGN KEY (kegiatan_id) REFERENCES kegiatan(id) ON DELETE CASCADE,
  CONSTRAINT fk_bank_mapel FOREIGN KEY (mapel_id) REFERENCES mata_pelajaran(id) ON DELETE RESTRICT,
  CONSTRAINT fk_bank_creator FOREIGN KEY (created_by) REFERENCES manager_users(id) ON DELETE SET NULL,
  CONSTRAINT fk_bank_updater FOREIGN KEY (updated_by) REFERENCES manager_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE bank_type_config (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  bank_soal_id BIGINT UNSIGNED NOT NULL,
  question_type VARCHAR(30) NOT NULL,
  selection_count INT UNSIGNED NULL,
  weight_percent DECIMAL(7,3) NOT NULL DEFAULT 0,
  shuffle_questions TINYINT(1) NOT NULL DEFAULT 0,
  shuffle_options TINYINT(1) NOT NULL DEFAULT 0,
  scoring_mode VARCHAR(30) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_bank_type (bank_soal_id, question_type),
  CONSTRAINT fk_bank_type_bank FOREIGN KEY (bank_soal_id) REFERENCES bank_soal(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE soal (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  bank_soal_id BIGINT UNSIGNED NOT NULL,
  stable_key VARCHAR(64) NOT NULL,
  current_revision_no INT UNSIGNED NOT NULL DEFAULT 1,
  status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_soal_stable_key (stable_key),
  KEY idx_soal_bank_status_order (bank_soal_id, status, sort_order),
  CONSTRAINT fk_soal_bank FOREIGN KEY (bank_soal_id) REFERENCES bank_soal(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE soal_revision (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  soal_id BIGINT UNSIGNED NOT NULL,
  revision_no INT UNSIGNED NOT NULL,
  question_type VARCHAR(30) NOT NULL,
  stimulus_html LONGTEXT NULL,
  question_html LONGTEXT NOT NULL,
  max_point DECIMAL(12,4) NOT NULL DEFAULT 1,
  scoring_mode VARCHAR(30) NULL,
  short_answer_mode VARCHAR(20) NULL,
  expected_numeric DECIMAL(24,8) NULL,
  numeric_tolerance DECIMAL(24,8) NULL,
  rubric_html LONGTEXT NULL,
  rubric_json LONGTEXT NULL,
  metadata_json LONGTEXT NULL,
  change_kind VARCHAR(30) NOT NULL DEFAULT 'INITIAL',
  change_note VARCHAR(500) NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_soal_revision (soal_id, revision_no),
  KEY idx_revision_type (question_type),
  CONSTRAINT fk_revision_soal FOREIGN KEY (soal_id) REFERENCES soal(id) ON DELETE CASCADE,
  CONSTRAINT fk_revision_creator FOREIGN KEY (created_by) REFERENCES manager_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE soal_opsi (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  soal_revision_id BIGINT UNSIGNED NOT NULL,
  option_key VARCHAR(64) NOT NULL,
  content_html LONGTEXT NOT NULL,
  is_correct TINYINT(1) NULL,
  point_value DECIMAL(12,4) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  metadata_json LONGTEXT NULL,
  UNIQUE KEY uq_opsi_key (soal_revision_id, option_key),
  KEY idx_opsi_order (soal_revision_id, sort_order),
  CONSTRAINT fk_opsi_revision FOREIGN KEY (soal_revision_id) REFERENCES soal_revision(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE soal_matching_pair (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  soal_revision_id BIGINT UNSIGNED NOT NULL,
  left_key VARCHAR(64) NOT NULL,
  left_html LONGTEXT NOT NULL,
  right_key VARCHAR(64) NOT NULL,
  right_html LONGTEXT NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  UNIQUE KEY uq_matching_left (soal_revision_id, left_key),
  UNIQUE KEY uq_matching_right (soal_revision_id, right_key),
  KEY idx_matching_order (soal_revision_id, sort_order),
  CONSTRAINT fk_matching_revision FOREIGN KEY (soal_revision_id) REFERENCES soal_revision(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE soal_short_answer_text (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  soal_revision_id BIGINT UNSIGNED NOT NULL,
  accepted_value VARCHAR(500) NOT NULL,
  normalized_value VARCHAR(500) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  KEY idx_short_answer_revision (soal_revision_id, sort_order),
  CONSTRAINT fk_short_answer_revision FOREIGN KEY (soal_revision_id) REFERENCES soal_revision(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE soal_revision_media (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  soal_revision_id BIGINT UNSIGNED NOT NULL,
  media_asset_id BIGINT UNSIGNED NOT NULL,
  media_role VARCHAR(30) NOT NULL,
  option_key VARCHAR(64) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  KEY idx_qmedia_revision (soal_revision_id, sort_order),
  KEY idx_qmedia_media (media_asset_id),
  CONSTRAINT fk_qmedia_revision FOREIGN KEY (soal_revision_id) REFERENCES soal_revision(id) ON DELETE CASCADE,
  CONSTRAINT fk_qmedia_asset FOREIGN KEY (media_asset_id) REFERENCES media_assets(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE psych_instrument (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kegiatan_id BIGINT UNSIGNED NOT NULL,
  nama VARCHAR(180) NOT NULL,
  version_label VARCHAR(50) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
  scoring_model VARCHAR(50) NOT NULL DEFAULT 'MATRIX',
  allow_shuffle TINYINT(1) NOT NULL DEFAULT 0,
  notes LONGTEXT NULL,
  created_by BIGINT UNSIGNED NULL,
  updated_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_psych_kegiatan_status (kegiatan_id, status),
  CONSTRAINT fk_psych_kegiatan FOREIGN KEY (kegiatan_id) REFERENCES kegiatan(id) ON DELETE CASCADE,
  CONSTRAINT fk_psych_creator FOREIGN KEY (created_by) REFERENCES manager_users(id) ON DELETE SET NULL,
  CONSTRAINT fk_psych_updater FOREIGN KEY (updated_by) REFERENCES manager_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE psych_dimension (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  psych_instrument_id BIGINT UNSIGNED NOT NULL,
  code VARCHAR(50) NOT NULL,
  name VARCHAR(150) NOT NULL,
  description LONGTEXT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  UNIQUE KEY uq_psych_dimension_code (psych_instrument_id, code),
  CONSTRAINT fk_dimension_instrument FOREIGN KEY (psych_instrument_id) REFERENCES psych_instrument(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE psych_item (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  psych_instrument_id BIGINT UNSIGNED NOT NULL,
  stable_key VARCHAR(64) NOT NULL,
  current_revision_no INT UNSIGNED NOT NULL DEFAULT 1,
  status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_psych_item_key (stable_key),
  KEY idx_psych_item_order (psych_instrument_id, status, sort_order),
  CONSTRAINT fk_psych_item_instrument FOREIGN KEY (psych_instrument_id) REFERENCES psych_instrument(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE psych_item_revision (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  psych_item_id BIGINT UNSIGNED NOT NULL,
  revision_no INT UNSIGNED NOT NULL,
  item_type VARCHAR(30) NOT NULL,
  content_html LONGTEXT NOT NULL,
  reverse_scoring TINYINT(1) NOT NULL DEFAULT 0,
  randomization_allowed TINYINT(1) NOT NULL DEFAULT 0,
  metadata_json LONGTEXT NULL,
  change_kind VARCHAR(30) NOT NULL DEFAULT 'INITIAL',
  change_note VARCHAR(500) NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_psych_item_revision (psych_item_id, revision_no),
  CONSTRAINT fk_psych_revision_item FOREIGN KEY (psych_item_id) REFERENCES psych_item(id) ON DELETE CASCADE,
  CONSTRAINT fk_psych_revision_creator FOREIGN KEY (created_by) REFERENCES manager_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE psych_option (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  psych_item_revision_id BIGINT UNSIGNED NOT NULL,
  option_key VARCHAR(64) NOT NULL,
  content_html LONGTEXT NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  metadata_json LONGTEXT NULL,
  UNIQUE KEY uq_psych_option_key (psych_item_revision_id, option_key),
  KEY idx_psych_option_order (psych_item_revision_id, sort_order),
  CONSTRAINT fk_psych_option_revision FOREIGN KEY (psych_item_revision_id) REFERENCES psych_item_revision(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE psych_scoring_matrix (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  psych_item_revision_id BIGINT UNSIGNED NOT NULL,
  psych_option_id BIGINT UNSIGNED NOT NULL,
  psych_dimension_id BIGINT UNSIGNED NOT NULL,
  response_role VARCHAR(20) NOT NULL DEFAULT 'SELECT',
  points DECIMAL(12,4) NOT NULL DEFAULT 0,
  KEY idx_psych_matrix_revision (psych_item_revision_id),
  KEY idx_psych_matrix_dimension (psych_dimension_id),
  CONSTRAINT fk_matrix_revision FOREIGN KEY (psych_item_revision_id) REFERENCES psych_item_revision(id) ON DELETE CASCADE,
  CONSTRAINT fk_matrix_option FOREIGN KEY (psych_option_id) REFERENCES psych_option(id) ON DELETE CASCADE,
  CONSTRAINT fk_matrix_dimension FOREIGN KEY (psych_dimension_id) REFERENCES psych_dimension(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE psych_norm (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  psych_instrument_id BIGINT UNSIGNED NOT NULL,
  psych_dimension_id BIGINT UNSIGNED NULL,
  norm_group VARCHAR(100) NULL,
  min_score DECIMAL(14,4) NULL,
  max_score DECIMAL(14,4) NULL,
  category VARCHAR(100) NOT NULL,
  interpretation LONGTEXT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  KEY idx_psych_norm (psych_instrument_id, psych_dimension_id, sort_order),
  CONSTRAINT fk_norm_instrument FOREIGN KEY (psych_instrument_id) REFERENCES psych_instrument(id) ON DELETE CASCADE,
  CONSTRAINT fk_norm_dimension FOREIGN KEY (psych_dimension_id) REFERENCES psych_dimension(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE psych_item_revision_media (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  psych_item_revision_id BIGINT UNSIGNED NOT NULL,
  media_asset_id BIGINT UNSIGNED NOT NULL,
  media_role VARCHAR(30) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  KEY idx_pmedia_revision (psych_item_revision_id, sort_order),
  CONSTRAINT fk_pmedia_revision FOREIGN KEY (psych_item_revision_id) REFERENCES psych_item_revision(id) ON DELETE CASCADE,
  CONSTRAINT fk_pmedia_asset FOREIGN KEY (media_asset_id) REFERENCES media_assets(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE jadwal (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kegiatan_id BIGINT UNSIGNED NOT NULL,
  bank_soal_id BIGINT UNSIGNED NULL,
  psych_instrument_id BIGINT UNSIGNED NULL,
  parent_jadwal_id BIGINT UNSIGNED NULL,
  jenis_jadwal VARCHAR(20) NOT NULL DEFAULT 'MAIN',
  mulai_at DATETIME NOT NULL,
  batas_mulai_at DATETIME NOT NULL,
  durasi_seconds INT UNSIGNED NOT NULL,
  access_state VARCHAR(20) NOT NULL DEFAULT 'BUKA',
  tampilkan_nilai_saat_selesai TINYINT(1) NOT NULL DEFAULT 0,
  result_visibility_locked_at DATETIME NULL,
  first_attempt_started_at DATETIME NULL,
  results_finalized_at DATETIME NULL,
  results_finalized_by BIGINT UNSIGNED NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_jadwal_kegiatan_time (kegiatan_id, mulai_at),
  KEY idx_jadwal_parent (parent_jadwal_id),
  KEY idx_jadwal_access (access_state, mulai_at, batas_mulai_at),
  CONSTRAINT fk_jadwal_kegiatan FOREIGN KEY (kegiatan_id) REFERENCES kegiatan(id) ON DELETE CASCADE,
  CONSTRAINT fk_jadwal_bank FOREIGN KEY (bank_soal_id) REFERENCES bank_soal(id) ON DELETE RESTRICT,
  CONSTRAINT fk_jadwal_psych FOREIGN KEY (psych_instrument_id) REFERENCES psych_instrument(id) ON DELETE RESTRICT,
  CONSTRAINT fk_jadwal_parent FOREIGN KEY (parent_jadwal_id) REFERENCES jadwal(id) ON DELETE RESTRICT,
  CONSTRAINT fk_jadwal_finalizer FOREIGN KEY (results_finalized_by) REFERENCES manager_users(id) ON DELETE SET NULL,
  CONSTRAINT fk_jadwal_creator FOREIGN KEY (created_by) REFERENCES manager_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE jadwal_peserta_target (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  jadwal_id BIGINT UNSIGNED NOT NULL,
  peserta_kegiatan_id BIGINT UNSIGNED NOT NULL,
  target_mode VARCHAR(30) NOT NULL DEFAULT 'FIRST_ATTEMPT',
  supersede_attempt_id BIGINT UNSIGNED NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'TARGETED',
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_jadwal_target (jadwal_id, peserta_kegiatan_id),
  KEY idx_target_participant (peserta_kegiatan_id, status),
  CONSTRAINT fk_target_jadwal FOREIGN KEY (jadwal_id) REFERENCES jadwal(id) ON DELETE CASCADE,
  CONSTRAINT fk_target_pk FOREIGN KEY (peserta_kegiatan_id) REFERENCES peserta_kegiatan(id) ON DELETE RESTRICT,
  CONSTRAINT fk_target_creator FOREIGN KEY (created_by) REFERENCES manager_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE prepared_assignment (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  generated_for_jadwal_id BIGINT UNSIGNED NOT NULL,
  peserta_kegiatan_id BIGINT UNSIGNED NOT NULL,
  assignment_seq INT UNSIGNED NOT NULL DEFAULT 1,
  source_version VARCHAR(128) NULL,
  fingerprint VARCHAR(128) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'READY',
  generated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  used_at DATETIME NULL,
  created_by BIGINT UNSIGNED NULL,
  UNIQUE KEY uq_assignment_seq (generated_for_jadwal_id, peserta_kegiatan_id, assignment_seq),
  KEY idx_assignment_lookup (peserta_kegiatan_id, status),
  KEY idx_assignment_jadwal_status (generated_for_jadwal_id, peserta_kegiatan_id, status),
  CONSTRAINT fk_assignment_jadwal FOREIGN KEY (generated_for_jadwal_id) REFERENCES jadwal(id) ON DELETE RESTRICT,
  CONSTRAINT fk_assignment_pk FOREIGN KEY (peserta_kegiatan_id) REFERENCES peserta_kegiatan(id) ON DELETE RESTRICT,
  CONSTRAINT fk_assignment_creator FOREIGN KEY (created_by) REFERENCES manager_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE prepared_assignment_item (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  prepared_assignment_id BIGINT UNSIGNED NOT NULL,
  sequence_no INT UNSIGNED NOT NULL,
  soal_id BIGINT UNSIGNED NULL,
  soal_revision_id BIGINT UNSIGNED NULL,
  psych_item_id BIGINT UNSIGNED NULL,
  psych_item_revision_id BIGINT UNSIGNED NULL,
  option_order_json LONGTEXT NULL,
  mapping_json LONGTEXT NULL,
  metadata_json LONGTEXT NULL,
  UNIQUE KEY uq_assignment_item_seq (prepared_assignment_id, sequence_no),
  KEY idx_pai_question (soal_revision_id),
  KEY idx_pai_psych (psych_item_revision_id),
  CONSTRAINT fk_pai_assignment FOREIGN KEY (prepared_assignment_id) REFERENCES prepared_assignment(id) ON DELETE CASCADE,
  CONSTRAINT fk_pai_soal FOREIGN KEY (soal_id) REFERENCES soal(id) ON DELETE RESTRICT,
  CONSTRAINT fk_pai_revision FOREIGN KEY (soal_revision_id) REFERENCES soal_revision(id) ON DELETE RESTRICT,
  CONSTRAINT fk_pai_psych_item FOREIGN KEY (psych_item_id) REFERENCES psych_item(id) ON DELETE RESTRICT,
  CONSTRAINT fk_pai_psych_revision FOREIGN KEY (psych_item_revision_id) REFERENCES psych_item_revision(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE prepared_assignment_media (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  prepared_assignment_id BIGINT UNSIGNED NOT NULL,
  media_asset_id BIGINT UNSIGNED NOT NULL,
  is_critical TINYINT(1) NOT NULL DEFAULT 0,
  prefetch_order INT NOT NULL DEFAULT 0,
  UNIQUE KEY uq_assignment_media (prepared_assignment_id, media_asset_id),
  KEY idx_assignment_media_order (prepared_assignment_id, is_critical, prefetch_order),
  CONSTRAINT fk_pam_assignment FOREIGN KEY (prepared_assignment_id) REFERENCES prepared_assignment(id) ON DELETE CASCADE,
  CONSTRAINT fk_pam_media FOREIGN KEY (media_asset_id) REFERENCES media_assets(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE attempt (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  peserta_id BIGINT UNSIGNED NOT NULL,
  peserta_kegiatan_id BIGINT UNSIGNED NOT NULL,
  jadwal_id BIGINT UNSIGNED NOT NULL,
  root_jadwal_id BIGINT UNSIGNED NOT NULL,
  prepared_assignment_id BIGINT UNSIGNED NOT NULL,
  attempt_no INT UNSIGNED NOT NULL DEFAULT 1,
  supersedes_attempt_id BIGINT UNSIGNED NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
  finish_reason VARCHAR(30) NULL,
  client_uuid VARCHAR(128) NULL,
  client_generation INT UNSIGNED NOT NULL DEFAULT 1,
  auth_generation INT UNSIGNED NOT NULL DEFAULT 1,
  start_at DATETIME NOT NULL,
  finish_at DATETIME NULL,
  deadline_at DATETIME NOT NULL,
  pause_started_at DATETIME NULL,
  duration_seconds_snapshot INT UNSIGNED NOT NULL,
  added_seconds INT UNSIGNED NOT NULL DEFAULT 0,
  paused_seconds INT UNSIGNED NOT NULL DEFAULT 0,
  server_sync_revision BIGINT UNSIGNED NOT NULL DEFAULT 0,
  last_sync_at DATETIME NULL,
  last_activity_at DATETIME NULL,
  scoring_status VARCHAR(30) NOT NULL DEFAULT 'NOT_SCORED',
  nomor_peserta_snapshot VARCHAR(64) NULL,
  username_snapshot VARCHAR(64) NULL,
  nama_snapshot VARCHAR(180) NOT NULL,
  rombel_snapshot VARCHAR(50) NOT NULL,
  ruang_snapshot VARCHAR(150) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_attempt_number (root_jadwal_id, peserta_kegiatan_id, attempt_no),
  KEY idx_attempt_participant_status (peserta_id, status),
  KEY idx_attempt_monitor (jadwal_id, status, last_sync_at),
  KEY idx_attempt_root_participant (root_jadwal_id, peserta_kegiatan_id),
  KEY idx_attempt_activity (status, last_activity_at),
  CONSTRAINT fk_attempt_peserta FOREIGN KEY (peserta_id) REFERENCES peserta(id) ON DELETE RESTRICT,
  CONSTRAINT fk_attempt_pk FOREIGN KEY (peserta_kegiatan_id) REFERENCES peserta_kegiatan(id) ON DELETE RESTRICT,
  CONSTRAINT fk_attempt_jadwal FOREIGN KEY (jadwal_id) REFERENCES jadwal(id) ON DELETE RESTRICT,
  CONSTRAINT fk_attempt_root_jadwal FOREIGN KEY (root_jadwal_id) REFERENCES jadwal(id) ON DELETE RESTRICT,
  CONSTRAINT fk_attempt_assignment FOREIGN KEY (prepared_assignment_id) REFERENCES prepared_assignment(id) ON DELETE RESTRICT,
  CONSTRAINT fk_attempt_supersedes FOREIGN KEY (supersedes_attempt_id) REFERENCES attempt(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE jadwal_peserta_target
  ADD CONSTRAINT fk_target_supersede_attempt FOREIGN KEY (supersede_attempt_id) REFERENCES attempt(id) ON DELETE RESTRICT;

CREATE TABLE attempt_active_lock (
  peserta_id BIGINT UNSIGNED NOT NULL,
  attempt_id BIGINT UNSIGNED NOT NULL,
  acquired_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (peserta_id),
  UNIQUE KEY uq_active_attempt (attempt_id),
  CONSTRAINT fk_active_lock_peserta FOREIGN KEY (peserta_id) REFERENCES peserta(id) ON DELETE CASCADE,
  CONSTRAINT fk_active_lock_attempt FOREIGN KEY (attempt_id) REFERENCES attempt(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE attempt_pause_event (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  attempt_id BIGINT UNSIGNED NOT NULL,
  started_at DATETIME NOT NULL,
  ended_at DATETIME NULL,
  paused_seconds INT UNSIGNED NULL,
  reason VARCHAR(255) NULL,
  started_by BIGINT UNSIGNED NULL,
  ended_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_pause_attempt (attempt_id, started_at),
  CONSTRAINT fk_pause_attempt FOREIGN KEY (attempt_id) REFERENCES attempt(id) ON DELETE CASCADE,
  CONSTRAINT fk_pause_started_by FOREIGN KEY (started_by) REFERENCES manager_users(id) ON DELETE SET NULL,
  CONSTRAINT fk_pause_ended_by FOREIGN KEY (ended_by) REFERENCES manager_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE attempt_time_adjustment (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  attempt_id BIGINT UNSIGNED NOT NULL,
  seconds_added INT UNSIGNED NOT NULL,
  reason VARCHAR(255) NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_time_adjust_attempt (attempt_id, created_at),
  CONSTRAINT fk_time_adjust_attempt FOREIGN KEY (attempt_id) REFERENCES attempt(id) ON DELETE CASCADE,
  CONSTRAINT fk_time_adjust_creator FOREIGN KEY (created_by) REFERENCES manager_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE attempt_client_event (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  attempt_id BIGINT UNSIGNED NOT NULL,
  client_generation INT UNSIGNED NOT NULL,
  event_type VARCHAR(50) NOT NULL,
  metadata_json LONGTEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_client_event_attempt (attempt_id, created_at),
  KEY idx_client_event_type (event_type, created_at),
  CONSTRAINT fk_client_event_attempt FOREIGN KEY (attempt_id) REFERENCES attempt(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE attempt_response (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  attempt_id BIGINT UNSIGNED NOT NULL,
  prepared_assignment_item_id BIGINT UNSIGNED NOT NULL,
  answer_payload LONGTEXT NULL,
  client_revision BIGINT UNSIGNED NOT NULL DEFAULT 0,
  server_revision BIGINT UNSIGNED NOT NULL DEFAULT 0,
  client_elapsed_ms BIGINT UNSIGNED NULL,
  answered_at_client DATETIME NULL,
  received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  is_flagged TINYINT(1) NOT NULL DEFAULT 0,
  auto_score DECIMAL(14,4) NULL,
  manual_score DECIMAL(14,4) NULL,
  effective_score DECIMAL(14,4) NULL,
  scoring_state VARCHAR(30) NOT NULL DEFAULT 'PENDING',
  last_mutation_id VARCHAR(100) NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_attempt_response (attempt_id, prepared_assignment_item_id),
  KEY idx_response_revision (attempt_id, server_revision),
  KEY idx_response_scoring (attempt_id, scoring_state),
  CONSTRAINT fk_response_attempt FOREIGN KEY (attempt_id) REFERENCES attempt(id) ON DELETE CASCADE,
  CONSTRAINT fk_response_item FOREIGN KEY (prepared_assignment_item_id) REFERENCES prepared_assignment_item(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE score_adjustment_log (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  attempt_response_id BIGINT UNSIGNED NOT NULL,
  old_manual_score DECIMAL(14,4) NULL,
  new_manual_score DECIMAL(14,4) NULL,
  old_effective_score DECIMAL(14,4) NULL,
  new_effective_score DECIMAL(14,4) NULL,
  reason VARCHAR(500) NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_score_adjust_response (attempt_response_id, created_at),
  CONSTRAINT fk_score_adjust_response FOREIGN KEY (attempt_response_id) REFERENCES attempt_response(id) ON DELETE CASCADE,
  CONSTRAINT fk_score_adjust_creator FOREIGN KEY (created_by) REFERENCES manager_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE result_snapshot (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  attempt_id BIGINT UNSIGNED NOT NULL,
  jadwal_id BIGINT UNSIGNED NOT NULL,
  snapshot_version INT UNSIGNED NOT NULL,
  result_type VARCHAR(20) NOT NULL,
  click_score DECIMAL(8,2) NULL,
  typed_score DECIMAL(8,2) NULL,
  final_score DECIMAL(8,2) NULL,
  scoring_status VARCHAR(30) NOT NULL,
  payload_json LONGTEXT NULL,
  is_final TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  finalized_at DATETIME NULL,
  UNIQUE KEY uq_result_snapshot_version (attempt_id, snapshot_version),
  KEY idx_result_jadwal (jadwal_id, scoring_status),
  CONSTRAINT fk_result_attempt FOREIGN KEY (attempt_id) REFERENCES attempt(id) ON DELETE RESTRICT,
  CONSTRAINT fk_result_jadwal FOREIGN KEY (jadwal_id) REFERENCES jadwal(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE result_item_snapshot (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  result_snapshot_id BIGINT UNSIGNED NOT NULL,
  prepared_assignment_item_id BIGINT UNSIGNED NOT NULL,
  question_type VARCHAR(30) NULL,
  raw_score DECIMAL(14,4) NULL,
  max_point DECIMAL(14,4) NULL,
  type_weight_percent DECIMAL(7,3) NULL,
  weighted_score DECIMAL(14,6) NULL,
  voided TINYINT(1) NOT NULL DEFAULT 0,
  payload_json LONGTEXT NULL,
  UNIQUE KEY uq_result_item (result_snapshot_id, prepared_assignment_item_id),
  CONSTRAINT fk_result_item_snapshot FOREIGN KEY (result_snapshot_id) REFERENCES result_snapshot(id) ON DELETE CASCADE,
  CONSTRAINT fk_result_item_prepared FOREIGN KEY (prepared_assignment_item_id) REFERENCES prepared_assignment_item(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE official_result_pointer (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  root_jadwal_id BIGINT UNSIGNED NOT NULL,
  peserta_kegiatan_id BIGINT UNSIGNED NOT NULL,
  attempt_id BIGINT UNSIGNED NOT NULL,
  result_snapshot_id BIGINT UNSIGNED NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_official_result (root_jadwal_id, peserta_kegiatan_id),
  KEY idx_official_attempt (attempt_id),
  CONSTRAINT fk_official_root_jadwal FOREIGN KEY (root_jadwal_id) REFERENCES jadwal(id) ON DELETE RESTRICT,
  CONSTRAINT fk_official_pk FOREIGN KEY (peserta_kegiatan_id) REFERENCES peserta_kegiatan(id) ON DELETE RESTRICT,
  CONSTRAINT fk_official_attempt FOREIGN KEY (attempt_id) REFERENCES attempt(id) ON DELETE RESTRICT,
  CONSTRAINT fk_official_snapshot FOREIGN KEY (result_snapshot_id) REFERENCES result_snapshot(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE psych_dimension_result (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  result_snapshot_id BIGINT UNSIGNED NOT NULL,
  psych_dimension_id BIGINT UNSIGNED NOT NULL,
  raw_score DECIMAL(14,4) NOT NULL,
  normalized_score DECIMAL(14,4) NULL,
  category VARCHAR(100) NULL,
  interpretation LONGTEXT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  UNIQUE KEY uq_psych_dimension_result (result_snapshot_id, psych_dimension_id),
  CONSTRAINT fk_psych_result_snapshot FOREIGN KEY (result_snapshot_id) REFERENCES result_snapshot(id) ON DELETE CASCADE,
  CONSTRAINT fk_psych_result_dimension FOREIGN KEY (psych_dimension_id) REFERENCES psych_dimension(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE token_control (
  id TINYINT UNSIGNED NOT NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 0,
  current_token VARCHAR(32) NULL,
  auto_rotate_minutes INT UNSIGNED NULL,
  rotated_at DATETIME NULL,
  next_rotate_at DATETIME NULL,
  updated_by BIGINT UNSIGNED NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_token_updated_by FOREIGN KEY (updated_by) REFERENCES manager_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE live_scoring_config (
  id TINYINT UNSIGNED NOT NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 0,
  jadwal_id BIGINT UNSIGNED NULL,
  public_token VARCHAR(128) NULL,
  public_token_version INT UNSIGNED NOT NULL DEFAULT 1,
  started_at DATETIME NULL,
  stopped_at DATETIME NULL,
  updated_by BIGINT UNSIGNED NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_live_public_token (public_token),
  CONSTRAINT fk_live_jadwal FOREIGN KEY (jadwal_id) REFERENCES jadwal(id) ON DELETE SET NULL,
  CONSTRAINT fk_live_updated_by FOREIGN KEY (updated_by) REFERENCES manager_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO token_control (id, enabled) VALUES (1, 0);
INSERT INTO live_scoring_config (id, enabled) VALUES (1, 0);

SET FOREIGN_KEY_CHECKS = 1;
