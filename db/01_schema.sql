-- pcg_ecm normalized schema. Conventions: snake_case plural tables, InnoDB, utf8mb4,
-- INT UNSIGNED ids, DECIMAL(12,2) money, real dates (NULL, never 0000-00-00), foreign keys.
-- legacy_* columns keep the original id/table so every row can be traced back to pcg_ecm_legacy.
USE pcg_ecm;
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE church_settings (
  setting_key VARCHAR(60) NOT NULL PRIMARY KEY,
  setting_value TEXT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- access ---------------------------------------------------------------------
CREATE TABLE roles (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(60) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE media_files (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  filename VARCHAR(255) NOT NULL,
  mime_type VARCHAR(100) NULL,
  size_bytes INT UNSIGNED NULL,
  directory VARCHAR(255) NULL,
  created_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  role_id INT UNSIGNED NULL,
  username VARCHAR(50) NOT NULL UNIQUE,
  first_name VARCHAR(100) NULL,
  last_name VARCHAR(100) NULL,
  email VARCHAR(150) NULL,
  password_hash VARCHAR(255) NULL COMMENT 'NULL = must set a password before first login',
  must_reset_password TINYINT(1) NOT NULL DEFAULT 1,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  photo_id INT UNSIGNED NULL,
  last_login_at DATETIME NULL,
  created_at DATETIME NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  legacy_id INT NULL,
  CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE SET NULL,
  CONSTRAINT fk_users_photo FOREIGN KEY (photo_id) REFERENCES media_files(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- lookups --------------------------------------------------------------------
CREATE TABLE professions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE member_groups (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL UNIQUE,
  short_name VARCHAR(50) NULL,
  photo_id INT UNSIGNED NULL,
  CONSTRAINT fk_mgroups_photo FOREIGN KEY (photo_id) REFERENCES media_files(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE departments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE projects (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- members --------------------------------------------------------------------
CREATE TABLE members (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  member_number VARCHAR(40) NOT NULL UNIQUE,
  title VARCHAR(20) NULL,
  full_name VARCHAR(200) NOT NULL COMMENT 'As registered: Surname Firstname Othernames',
  first_name VARCHAR(100) NULL,
  last_name VARCHAR(100) NULL,
  maiden_name VARCHAR(100) NULL,
  sex ENUM('male','female') NULL,
  date_of_birth DATE NULL,
  place_of_birth VARCHAR(150) NULL,
  marital_status ENUM('single','married','divorced','widowed') NULL,
  marriage_type ENUM('ordinance','customary') NULL,
  spouse_name VARCHAR(150) NULL,
  hometown VARCHAR(150) NULL,
  father_name VARCHAR(150) NULL,
  mother_name VARCHAR(150) NULL,
  profession_id INT UNSIGNED NULL,
  employer_details VARCHAR(250) NULL,
  company_name VARCHAR(250) NULL,
  mobile VARCHAR(30) NULL,
  telephone VARCHAR(30) NULL,
  office_phone VARCHAR(30) NULL,
  email VARCHAR(150) NULL,
  facebook_id VARCHAR(150) NULL,
  joined_on DATE NULL,
  is_communicant TINYINT(1) NULL,
  is_child TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Belongs to the children register category',
  church_positions TEXT NULL COMMENT 'Free text: offices held / committees',
  status ENUM('active','invalid','transferred','deceased','deleted') NOT NULL DEFAULT 'active',
  is_verified TINYINT(1) NOT NULL DEFAULT 0,
  photo_id INT UNSIGNED NULL,
  created_at DATETIME NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY ix_members_name (full_name),
  KEY ix_members_status (status),
  CONSTRAINT fk_members_profession FOREIGN KEY (profession_id) REFERENCES professions(id) ON DELETE SET NULL,
  CONSTRAINT fk_members_photo FOREIGN KEY (photo_id) REFERENCES media_files(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE member_group_memberships (
  member_id INT UNSIGNED NOT NULL,
  member_group_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (member_id, member_group_id),
  CONSTRAINT fk_mgm_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
  CONSTRAINT fk_mgm_group FOREIGN KEY (member_group_id) REFERENCES member_groups(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE member_next_of_kin (
  member_id INT UNSIGNED NOT NULL PRIMARY KEY,
  name VARCHAR(150) NULL,
  phone VARCHAR(30) NULL,
  residential_address TINYTEXT NULL,
  postal_address TINYTEXT NULL,
  CONSTRAINT fk_nok_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE member_beneficiaries (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  member_id INT UNSIGNED NOT NULL,
  slot TINYINT UNSIGNED NOT NULL,
  name VARCHAR(150) NULL,
  phone VARCHAR(30) NULL,
  UNIQUE KEY uq_bene_slot (member_id, slot),
  CONSTRAINT fk_bene_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE member_sacraments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  member_id INT UNSIGNED NOT NULL,
  kind ENUM('baptism','confirmation') NOT NULL,
  sacrament_date DATE NULL,
  place VARCHAR(150) NULL,
  minister VARCHAR(150) NULL,
  UNIQUE KEY uq_sacrament (member_id, kind),
  CONSTRAINT fk_sacr_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE member_children (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  member_id INT UNSIGNED NOT NULL COMMENT 'The parent',
  name VARCHAR(200) NOT NULL,
  date_of_birth DATE NULL,
  place_of_birth VARCHAR(150) NULL,
  photo_id INT UNSIGNED NULL,
  KEY ix_mchild_member (member_id),
  CONSTRAINT fk_mchild_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
  CONSTRAINT fk_mchild_photo FOREIGN KEY (photo_id) REFERENCES media_files(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE member_transfers (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  member_id INT UNSIGNED NOT NULL,
  transferred_on DATE NULL,
  church_name VARCHAR(200) NULL,
  location VARCHAR(200) NULL,
  KEY ix_mtransfer_member (member_id),
  CONSTRAINT fk_mtransfer_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE member_deaths (
  member_id INT UNSIGNED NOT NULL PRIMARY KEY,
  died_on DATE NULL,
  buried_on DATE NULL,
  burial_place VARCHAR(200) NULL,
  burial_town VARCHAR(200) NULL,
  pastor_in_charge VARCHAR(200) NULL,
  cause_of_death MEDIUMTEXT NULL,
  CONSTRAINT fk_mdeath_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE visitors (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  visited_on DATE NULL,
  name VARCHAR(200) NOT NULL,
  telephone VARCHAR(30) NULL,
  location VARCHAR(200) NULL,
  purpose VARCHAR(250) NULL,
  attended_by VARCHAR(200) NULL,
  remarks MEDIUMTEXT NULL,
  recorded_by INT UNSIGNED NULL,
  CONSTRAINT fk_visitors_user FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE attendance_counts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  attended_on DATE NOT NULL,
  age_group ENUM('children','junior_youth','young_adult','adult') NOT NULL,
  sex ENUM('male','female') NOT NULL,
  headcount INT UNSIGNED NOT NULL,
  UNIQUE KEY uq_attendance (attended_on, age_group, sex)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- giving (per member) --------------------------------------------------------
CREATE TABLE contribution_types (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(30) NOT NULL UNIQUE,
  name VARCHAR(80) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE contributions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  member_id INT UNSIGNED NULL COMMENT 'NULL when the original member no longer exists (see legacy_member_id)',
  type_id INT UNSIGNED NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  contributed_on DATE NOT NULL,
  date_inferred TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = original date was invalid; taken from the entry timestamp',
  invoice_no VARCHAR(40) NULL,
  purpose VARCHAR(250) NULL,
  recorded_by INT UNSIGNED NULL,
  created_at DATETIME NULL,
  legacy_table VARCHAR(30) NOT NULL,
  legacy_id INT NOT NULL,
  legacy_member_id INT NULL,
  legacy_user_id INT NULL,
  UNIQUE KEY uq_contrib_legacy (legacy_table, legacy_id),
  KEY ix_contrib_member_date (member_id, contributed_on),
  KEY ix_contrib_type_date (type_id, contributed_on),
  CONSTRAINT fk_contrib_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE RESTRICT,
  CONSTRAINT fk_contrib_type FOREIGN KEY (type_id) REFERENCES contribution_types(id),
  CONSTRAINT fk_contrib_user FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- church ledger (income / expenses) ------------------------------------------
CREATE TABLE ledger_categories (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  kind ENUM('income','expense') NOT NULL,
  code VARCHAR(40) NOT NULL,
  name VARCHAR(100) NOT NULL,
  UNIQUE KEY uq_ledger_cat (kind, code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE payees (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(200) NOT NULL,
  telephone VARCHAR(30) NULL,
  location VARCHAR(200) NULL,
  created_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE income (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  category_id INT UNSIGNED NOT NULL,
  received_on DATE NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  description VARCHAR(400) NULL,
  paid_by VARCHAR(200) NULL,
  invoice_no VARCHAR(40) NULL,
  project_id INT UNSIGNED NULL,
  recorded_by INT UNSIGNED NULL,
  created_at DATETIME NULL,
  legacy_table VARCHAR(30) NOT NULL,
  legacy_id INT NOT NULL,
  legacy_user_id INT NULL,
  UNIQUE KEY uq_income_legacy (legacy_table, legacy_id),
  KEY ix_income_cat_date (category_id, received_on),
  CONSTRAINT fk_income_cat FOREIGN KEY (category_id) REFERENCES ledger_categories(id),
  CONSTRAINT fk_income_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
  CONSTRAINT fk_income_user FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE expenses (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  category_id INT UNSIGNED NOT NULL,
  spent_on DATE NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  description VARCHAR(400) NULL,
  payee_id INT UNSIGNED NULL,
  cheque_no VARCHAR(40) NULL,
  pv_number VARCHAR(40) NULL,
  project_id INT UNSIGNED NULL,
  recorded_by INT UNSIGNED NULL,
  created_at DATETIME NULL,
  legacy_table VARCHAR(30) NOT NULL,
  legacy_id INT NOT NULL,
  legacy_user_id INT NULL,
  UNIQUE KEY uq_expenses_legacy (legacy_table, legacy_id),
  KEY ix_expenses_cat_date (category_id, spent_on),
  CONSTRAINT fk_exp_cat FOREIGN KEY (category_id) REFERENCES ledger_categories(id),
  CONSTRAINT fk_exp_payee FOREIGN KEY (payee_id) REFERENCES payees(id) ON DELETE SET NULL,
  CONSTRAINT fk_exp_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
  CONSTRAINT fk_exp_user FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE budget_lines (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  budget_date DATE NULL,
  direction ENUM('inflow','outflow') NOT NULL,
  item_code VARCHAR(40) NOT NULL COMMENT 'Column name in the old wide budget tables',
  amount DECIMAL(12,2) NOT NULL,
  note VARCHAR(400) NULL,
  legacy_table VARCHAR(30) NOT NULL,
  legacy_id INT NOT NULL,
  KEY ix_budget (budget_date, direction, item_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- banking, payroll, assets ---------------------------------------------------
CREATE TABLE bank_accounts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  bank_name VARCHAR(150) NOT NULL,
  account_number VARCHAR(40) NOT NULL UNIQUE,
  account_name VARCHAR(200) NULL,
  account_type VARCHAR(50) NULL,
  branch VARCHAR(150) NULL,
  balance DECIMAL(12,2) NOT NULL DEFAULT 0,
  created_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE bank_transactions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  bank_account_id INT UNSIGNED NOT NULL,
  transacted_on DATE NOT NULL,
  cheque_no VARCHAR(40) NULL,
  debit DECIMAL(12,2) NULL,
  credit DECIMAL(12,2) NULL,
  balance DECIMAL(12,2) NULL,
  transaction_type VARCHAR(50) NULL,
  description MEDIUMTEXT NULL,
  created_at DATETIME NULL,
  KEY ix_banktx (bank_account_id, transacted_on),
  CONSTRAINT fk_banktx_acct FOREIGN KEY (bank_account_id) REFERENCES bank_accounts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE employees (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(200) NOT NULL,
  ssnit_number VARCHAR(40) NULL,
  telephone VARCHAR(30) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE payroll_payments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  employee_id INT UNSIGNED NOT NULL,
  kind ENUM('salary','allowance') NOT NULL,
  paid_on DATE NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  cheque_no VARCHAR(40) NULL,
  pv_number VARCHAR(40) NULL,
  recorded_by INT UNSIGNED NULL,
  KEY ix_payroll (employee_id, paid_on),
  CONSTRAINT fk_payroll_emp FOREIGN KEY (employee_id) REFERENCES employees(id),
  CONSTRAINT fk_payroll_user FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE asset_categories (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE assets (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(200) NULL,
  category_id INT UNSIGNED NULL,
  department_id INT UNSIGNED NULL,
  assigned_to VARCHAR(200) NULL,
  vendor VARCHAR(200) NULL,
  model_number VARCHAR(100) NULL,
  serial_number VARCHAR(100) NULL,
  purchase_price DECIMAL(12,2) NULL,
  purchased_on DATE NULL,
  sold_on DATE NULL,
  next_maintenance_on DATE NULL,
  status_code VARCHAR(50) NULL COMMENT 'Raw legacy status code; meaning not documented',
  description MEDIUMTEXT NULL,
  photo_id INT UNSIGNED NULL,
  created_at DATETIME NULL,
  CONSTRAINT fk_assets_cat FOREIGN KEY (category_id) REFERENCES asset_categories(id) ON DELETE SET NULL,
  CONSTRAINT fk_assets_dept FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
  CONSTRAINT fk_assets_photo FOREIGN KEY (photo_id) REFERENCES media_files(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE asset_history (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  asset_id INT UNSIGNED NOT NULL,
  event_date DATE NULL,
  content MEDIUMTEXT NOT NULL,
  created_at DATETIME NULL,
  CONSTRAINT fk_ahist_asset FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- SMS ------------------------------------------------------------------------
CREATE TABLE sms_messages (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  kind ENUM('single','bulk','group') NOT NULL,
  member_id INT UNSIGNED NULL,
  recipient_phone VARCHAR(30) NULL,
  audience VARCHAR(250) NULL COMMENT 'Group / audience label for bulk and group sends',
  member_group_id INT UNSIGNED NULL,
  subject VARCHAR(250) NULL,
  body VARCHAR(500) NULL,
  status VARCHAR(100) NULL,
  sent_by INT UNSIGNED NULL,
  created_at DATETIME NULL,
  legacy_table VARCHAR(30) NOT NULL,
  legacy_id INT NOT NULL,
  legacy_user_id INT NULL,
  UNIQUE KEY uq_sms_legacy (legacy_table, legacy_id),
  CONSTRAINT fk_sms_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE SET NULL,
  CONSTRAINT fk_sms_group FOREIGN KEY (member_group_id) REFERENCES member_groups(id) ON DELETE SET NULL,
  CONSTRAINT fk_sms_user FOREIGN KEY (sent_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
