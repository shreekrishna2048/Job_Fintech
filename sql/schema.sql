-- KaamKhoj Job Portal - database schema (MySQL 5.7+/MariaDB 10.3+)
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS settings (
  `key` VARCHAR(80) PRIMARY KEY,
  `value` MEDIUMTEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  role ENUM('seeker','employer','admin') NOT NULL DEFAULT 'seeker',
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NULL,
  phone VARCHAR(30) NULL,
  avatar VARCHAR(255) NULL,
  google_id VARCHAR(80) NULL,
  microsoft_id VARCHAR(80) NULL,
  email_verified_at DATETIME NULL,
  status ENUM('active','suspended') NOT NULL DEFAULT 'active',
  admin_perms VARCHAR(500) NULL,
  email_notifications TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  last_login_ip VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_role (role), KEY idx_google (google_id), KEY idx_ms (microsoft_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_tokens (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  type ENUM('verify','reset','remember') NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_token (token_hash),
  CONSTRAINT fk_tok_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS locations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  slug VARCHAR(120) NOT NULL UNIQUE,
  province VARCHAR(60) NULL,
  country VARCHAR(60) NOT NULL DEFAULT 'Nepal',
  image VARCHAR(255) NULL,
  sort INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  slug VARCHAR(120) NOT NULL UNIQUE,
  icon VARCHAR(10) NULL,
  image VARCHAR(255) NULL,
  sort INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS industries (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  slug VARCHAR(120) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS seeker_profiles (
  user_id INT UNSIGNED PRIMARY KEY,
  headline VARCHAR(160) NULL,
  summary TEXT NULL,
  location_id INT UNSIGNED NULL,
  experience_years DECIMAL(4,1) NULL,
  current_salary INT UNSIGNED NULL,
  expected_salary INT UNSIGNED NULL,
  skills VARCHAR(1000) NULL,
  preferred_category_id INT UNSIGNED NULL,
  preferred_job_type VARCHAR(30) NULL,
  resume_path VARCHAR(255) NULL,
  resume_name VARCHAR(190) NULL,
  linkedin VARCHAR(255) NULL,
  portfolio_url VARCHAR(255) NULL,
  open_to_work TINYINT(1) NOT NULL DEFAULT 1,
  visible_to_employers TINYINT(1) NOT NULL DEFAULT 1,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  FULLTEXT KEY ft_seeker (headline, summary, skills),
  CONSTRAINT fk_sp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS seeker_experience (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  title VARCHAR(150) NOT NULL,
  company VARCHAR(150) NOT NULL,
  start_date DATE NULL,
  end_date DATE NULL,
  is_current TINYINT(1) NOT NULL DEFAULT 0,
  description TEXT NULL,
  CONSTRAINT fk_exp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS seeker_education (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  degree VARCHAR(150) NOT NULL,
  institution VARCHAR(190) NOT NULL,
  year_completed SMALLINT NULL,
  grade VARCHAR(40) NULL,
  CONSTRAINT fk_edu_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS companies (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  owner_id INT UNSIGNED NOT NULL,
  name VARCHAR(160) NOT NULL,
  slug VARCHAR(190) NOT NULL UNIQUE,
  tagline VARCHAR(200) NULL,
  logo VARCHAR(255) NULL,
  cover VARCHAR(255) NULL,
  industry_id INT UNSIGNED NULL,
  company_size VARCHAR(30) NULL,
  founded_year SMALLINT NULL,
  website VARCHAR(255) NULL,
  email VARCHAR(190) NULL,
  phone VARCHAR(40) NULL,
  location_id INT UNSIGNED NULL,
  address VARCHAR(255) NULL,
  about TEXT NULL,
  culture TEXT NULL,
  benefits TEXT NULL,
  facebook VARCHAR(255) NULL,
  linkedin VARCHAR(255) NULL,
  instagram VARCHAR(255) NULL,
  verified TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('active','suspended') NOT NULL DEFAULT 'active',
  imported TINYINT(1) NOT NULL DEFAULT 0,
  views INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_owner (owner_id),
  CONSTRAINT fk_co_owner FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS company_gallery (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id INT UNSIGNED NOT NULL,
  image VARCHAR(255) NOT NULL,
  caption VARCHAR(190) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_gal_co FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS jobs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id INT UNSIGNED NOT NULL,
  posted_by INT UNSIGNED NOT NULL,
  title VARCHAR(190) NOT NULL,
  slug VARCHAR(220) NOT NULL UNIQUE,
  category_id INT UNSIGNED NULL,
  location_id INT UNSIGNED NULL,
  job_type ENUM('full-time','part-time','contract','internship','freelance','temporary') NOT NULL DEFAULT 'full-time',
  work_mode ENUM('onsite','remote','hybrid') NOT NULL DEFAULT 'onsite',
  job_level ENUM('entry','mid','senior','lead','executive') NOT NULL DEFAULT 'mid',
  experience_min TINYINT UNSIGNED NOT NULL DEFAULT 0,
  experience_max TINYINT UNSIGNED NULL,
  salary_min INT UNSIGNED NULL,
  salary_max INT UNSIGNED NULL,
  salary_period ENUM('month','year','hour') NOT NULL DEFAULT 'month',
  currency VARCHAR(5) NULL,
  salary_negotiable TINYINT(1) NOT NULL DEFAULT 0,
  hide_salary TINYINT(1) NOT NULL DEFAULT 0,
  vacancies SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  education VARCHAR(120) NULL,
  skills VARCHAR(1000) NULL,
  description MEDIUMTEXT NOT NULL,
  requirements MEDIUMTEXT NULL,
  benefits MEDIUMTEXT NULL,
  apply_method ENUM('portal','email','url') NOT NULL DEFAULT 'portal',
  apply_target VARCHAR(255) NULL,
  contact_email VARCHAR(190) NULL,
  contact_phone VARCHAR(60) NULL,
  source_url VARCHAR(500) NULL,
  imported TINYINT(1) NOT NULL DEFAULT 0,
  deadline DATE NULL,
  status ENUM('draft','pending','active','closed','rejected') NOT NULL DEFAULT 'pending',
  reject_reason VARCHAR(255) NULL,
  featured TINYINT(1) NOT NULL DEFAULT 0,
  urgent TINYINT(1) NOT NULL DEFAULT 0,
  views INT UNSIGNED NOT NULL DEFAULT 0,
  applications_count INT UNSIGNED NOT NULL DEFAULT 0,
  published_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_status (status, published_at),
  KEY idx_company (company_id),
  KEY idx_cat (category_id), KEY idx_loc (location_id),
  KEY idx_deadline (deadline),
  FULLTEXT KEY ft_jobs (title, skills, description),
  CONSTRAINT fk_job_co FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS job_questions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  job_id INT UNSIGNED NOT NULL,
  question VARCHAR(255) NOT NULL,
  required TINYINT(1) NOT NULL DEFAULT 0,
  CONSTRAINT fk_q_job FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS applications (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  job_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  cover_letter TEXT NULL,
  resume_path VARCHAR(255) NULL,
  resume_name VARCHAR(190) NULL,
  answers TEXT NULL,
  expected_salary INT UNSIGNED NULL,
  match_score TINYINT UNSIGNED NULL,
  status ENUM('applied','viewed','shortlisted','interview','offered','hired','rejected','withdrawn') NOT NULL DEFAULT 'applied',
  rating TINYINT UNSIGNED NULL,
  employer_note TEXT NULL,
  interview_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_app (job_id, user_id),
  KEY idx_user (user_id), KEY idx_status (status),
  CONSTRAINT fk_app_job FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
  CONSTRAINT fk_app_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS application_events (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  application_id INT UNSIGNED NOT NULL,
  status VARCHAR(30) NOT NULL,
  note VARCHAR(500) NULL,
  actor_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ev_app FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS saved_jobs (
  user_id INT UNSIGNED NOT NULL,
  job_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, job_id),
  CONSTRAINT fk_sv_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_sv_job FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS company_follows (
  user_id INT UNSIGNED NOT NULL,
  company_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, company_id),
  CONSTRAINT fk_fl_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_fl_co FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS job_alerts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  keyword VARCHAR(120) NULL,
  category_id INT UNSIGNED NULL,
  location_id INT UNSIGNED NULL,
  job_type VARCHAR(30) NULL,
  frequency ENUM('instant','daily','weekly') NOT NULL DEFAULT 'daily',
  active TINYINT(1) NOT NULL DEFAULT 1,
  last_sent_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_al_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  type VARCHAR(40) NOT NULL,
  title VARCHAR(190) NOT NULL,
  body VARCHAR(500) NULL,
  link VARCHAR(255) NULL,
  read_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_user_read (user_id, read_at),
  CONSTRAINT fk_nt_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_templates (
  `key` VARCHAR(60) PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  subject VARCHAR(255) NOT NULL,
  body MEDIUMTEXT NOT NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_queue (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  to_email VARCHAR(190) NOT NULL,
  to_name VARCHAR(120) NULL,
  subject VARCHAR(255) NOT NULL,
  body_html MEDIUMTEXT NOT NULL,
  template VARCHAR(60) NULL,
  attachments TEXT NULL,
  status ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  error VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sent_at DATETIME NULL,
  KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activity_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  action VARCHAR(60) NOT NULL,
  entity VARCHAR(40) NULL,
  entity_id INT UNSIGNED NULL,
  description VARCHAR(500) NULL,
  ip VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  url VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_user (user_id), KEY idx_action (action), KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  email VARCHAR(190) NULL,
  method ENUM('password','google','microsoft') NOT NULL DEFAULT 'password',
  success TINYINT(1) NOT NULL,
  ip VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  device VARCHAR(60) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ip_time (ip, created_at), KEY idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS search_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  keyword VARCHAR(190) NULL,
  location_id INT UNSIGNED NULL,
  category_id INT UNSIGNED NULL,
  results INT UNSIGNED NOT NULL DEFAULT 0,
  ip VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_kw (keyword), KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS job_views (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  job_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NULL,
  ip VARCHAR(45) NULL,
  referrer VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_job_time (job_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  feature VARCHAR(40) NOT NULL,
  tokens INT UNSIGNED NULL,
  success TINYINT(1) NOT NULL DEFAULT 1,
  provider VARCHAR(20) NULL,
  model VARCHAR(80) NULL,
  prompt_tokens INT UNSIGNED NULL,
  completion_tokens INT UNSIGNED NULL,
  cost_usd DECIMAL(10,6) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_user_time (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(120) NOT NULL UNIQUE,
  title VARCHAR(190) NOT NULL,
  content MEDIUMTEXT NULL,
  meta_description VARCHAR(255) NULL,
  published TINYINT(1) NOT NULL DEFAULT 1,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contact_messages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL,
  subject VARCHAR(190) NULL,
  message TEXT NOT NULL,
  ip VARCHAR(45) NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS job_reports (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  job_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NULL,
  reason VARCHAR(500) NOT NULL,
  ip VARCHAR(45) NULL,
  resolved TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_rp_job FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS import_batches (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  created_by INT UNSIGNED NULL,
  source_name VARCHAR(190) NULL,
  chunks MEDIUMTEXT NULL,
  total_chunks INT UNSIGNED NOT NULL DEFAULT 0,
  parsed_chunks INT UNSIGNED NOT NULL DEFAULT 0,
  options TEXT NULL,
  status ENUM('parsing','review','done') NOT NULL DEFAULT 'parsing',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS import_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  batch_id INT UNSIGNED NOT NULL,
  data MEDIUMTEXT NOT NULL,
  status ENUM('pending','published','skipped','error') NOT NULL DEFAULT 'pending',
  note VARCHAR(255) NULL,
  job_id INT UNSIGNED NULL,
  company_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_batch (batch_id),
  CONSTRAINT fk_ii_batch FOREIGN KEY (batch_id) REFERENCES import_batches(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
