-- Copies people, giving, banking, payroll, assets and SMS from pcg_ecm_legacy into the normalized pcg_ecm schema.
-- Rules: ids are preserved for members/users/media so old references keep working; zero dates become NULL;
-- references to rows that no longer exist become NULL (the raw id is kept in a legacy_* column where it matters).
USE pcg_ecm;
SET NAMES utf8mb4;
SET SESSION sql_mode = 'STRICT_TRANS_TABLES';
SET FOREIGN_KEY_CHECKS = 0;
START TRANSACTION;

-- settings --------------------------------------------------------------------
INSERT INTO church_settings (setting_key, setting_value)
SELECT CASE id WHEN 2 THEN 'church_name' WHEN 3 THEN 'congregation_name' WHEN 5 THEN 'currency' END, name
FROM pcg_ecm_legacy.churchnames WHERE id IN (2,3,5);
INSERT INTO church_settings (setting_key, setting_value)
SELECT 'birthday_sms_template', message FROM pcg_ecm_legacy.smsbirthday LIMIT 1;

-- media -----------------------------------------------------------------------
INSERT INTO media_files (id, filename, mime_type, size_bytes, directory, created_at)
SELECT id, filename, type, CAST(NULLIF(size,'') AS UNSIGNED), dir, NULLIF(created,'0000-00-00 00:00:00')
FROM pcg_ecm_legacy.images;

-- roles / users ------------------------------------------------------------------
-- Roles come from the app's access levels. Users come from clientusers (the app logins).
-- Legacy passwords were stored in PLAINTEXT and are deliberately NOT copied: every user must set a new password.
INSERT INTO roles (id, name) SELECT id, TRIM(name) FROM pcg_ecm_legacy.acceslevels;

INSERT INTO users (id, role_id, username, first_name, last_name, password_hash, must_reset_password, is_active, photo_id, created_at, legacy_id)
SELECT c.id,
       IF(c.acceslevel_id IN (SELECT id FROM roles), c.acceslevel_id, NULL),
       c.username,
       NULLIF(TRIM(c.firstname),''), NULLIF(TRIM(c.lastname),''),
       NULL, 1, 1,
       IF(c.image_id IN (SELECT id FROM media_files), c.image_id, NULL),
       NULLIF(c.created,'0000-00-00 00:00:00'), c.id
FROM pcg_ecm_legacy.clientusers c
WHERE c.id = (SELECT MIN(c2.id) FROM pcg_ecm_legacy.clientusers c2 WHERE c2.username = c.username);

-- lookups -----------------------------------------------------------------------
-- duplicate names (e.g. "Driver" twice) collapse onto the lowest id; members are remapped below
INSERT INTO professions (id, name)
SELECT MIN(id), MIN(TRIM(name)) FROM pcg_ecm_legacy.professions GROUP BY LOWER(TRIM(name));
INSERT INTO member_groups (id, name, short_name, photo_id)
SELECT g.id, TRIM(g.name), NULLIF(TRIM(g.shortname),''), IF(g.image_id IN (SELECT id FROM media_files), g.image_id, NULL)
FROM pcg_ecm_legacy.membergroups g WHERE g.id <> 1;   -- id 1 is the "Select Group" dropdown placeholder
INSERT INTO departments (id, name) SELECT id, TRIM(name) FROM pcg_ecm_legacy.departments;
INSERT INTO projects (id, name) SELECT id, TRIM(name) FROM pcg_ecm_legacy.projectname;
INSERT INTO asset_categories (id, name) SELECT id, TRIM(name) FROM pcg_ecm_legacy.categories;
INSERT INTO payees (id, name, telephone, location, created_at)
SELECT id, TRIM(name), NULLIF(TRIM(telephone),''), NULLIF(TRIM(location),''), NULLIF(created,'0000-00-00 00:00:00')
FROM pcg_ecm_legacy.clients;

-- members -------------------------------------------------------------------------
INSERT INTO members (id, member_number, title, full_name, first_name, last_name, maiden_name, sex, date_of_birth,
  place_of_birth, marital_status, marriage_type, spouse_name, hometown, father_name, mother_name, profession_id,
  employer_details, company_name, mobile, telephone, office_phone, email, facebook_id, joined_on, is_communicant,
  is_child, church_positions, status, is_verified, photo_id, created_at)
SELECT m.id, TRIM(m.memberID), NULLIF(TRIM(m.title),''), TRIM(m.name), NULLIF(TRIM(m.firstname),''), NULLIF(TRIM(m.lastname),''),
  NULLIF(TRIM(m.maidenname),''),
  CASE LOWER(TRIM(m.sex)) WHEN 'male' THEN 'male' WHEN 'female' THEN 'female' END,
  NULLIF(m.dateofbirth,'0000-00-00'), NULLIF(TRIM(m.place_of_birth),''),
  CASE LOWER(TRIM(m.maritalS)) WHEN 'single' THEN 'single' WHEN 'married' THEN 'married' WHEN 'divorced' THEN 'divorced'
       WHEN 'widowed' THEN 'widowed' WHEN 'widower' THEN 'widowed' END,
  CASE LOWER(TRIM(m.ifmarried)) WHEN 'ordinance' THEN 'ordinance' WHEN 'customary' THEN 'customary' END,
  NULLIF(TRIM(m.spousename),''), NULLIF(TRIM(m.hometown),''), NULLIF(TRIM(m.fathername),''), NULLIF(TRIM(m.mothername),''),
  (SELECT MIN(p2.id) FROM pcg_ecm_legacy.professions p1
     JOIN pcg_ecm_legacy.professions p2 ON LOWER(TRIM(p2.name)) = LOWER(TRIM(p1.name)) WHERE p1.id = m.profession_id),
  NULLIF(TRIM(m.emp_details),''), NULLIF(TRIM(m.company_name),''),
  NULLIF(TRIM(m.mobile),''), NULLIF(TRIM(m.telephone),''), NULLIF(TRIM(m.officenumber),''), NULLIF(TRIM(m.email),''),
  NULLIF(TRIM(m.facebookId),''), NULLIF(m.datejoined,'0000-00-00'),
  CASE LOWER(TRIM(m.communicant)) WHEN 'yes' THEN 1 WHEN 'no' THEN 0 END,
  IFNULL(m.children,0) = 1, NULLIF(TRIM(m.position_church),''),
  CASE WHEN m.deleted <> 0 THEN 'deleted' WHEN m.death = 1 THEN 'deceased' WHEN m.transfered = 1 THEN 'transferred'
       WHEN m.invalid = 1 THEN 'invalid' ELSE 'active' END,
  IFNULL(m.verified,0) = 1,
  IF(m.image_id IN (SELECT id FROM media_files), m.image_id, NULL),
  NULLIF(m.created,'0000-00-00 00:00:00')
FROM pcg_ecm_legacy.members m;

-- up to four group columns -> one row per membership (group 1 is the placeholder)
INSERT INTO member_group_memberships (member_id, member_group_id)
SELECT DISTINCT member_id, gid FROM (
  SELECT id member_id, membergroup1_id gid FROM pcg_ecm_legacy.members UNION ALL
  SELECT id, membergroup2_id FROM pcg_ecm_legacy.members UNION ALL
  SELECT id, membergroup3_id FROM pcg_ecm_legacy.members UNION ALL
  SELECT id, membergroup4_id FROM pcg_ecm_legacy.members) x
WHERE gid IN (SELECT id FROM member_groups);

INSERT INTO member_next_of_kin (member_id, name, phone, residential_address, postal_address)
SELECT id, NULLIF(TRIM(nextofkin),''), NULLIF(TRIM(nextofkintel),''), NULLIF(TRIM(nextResAdd),''), NULLIF(TRIM(nextPostAdd),'')
FROM pcg_ecm_legacy.members
WHERE COALESCE(NULLIF(TRIM(nextofkin),''), NULLIF(TRIM(nextofkintel),''), NULLIF(TRIM(nextResAdd),''), NULLIF(TRIM(nextPostAdd),'')) IS NOT NULL;

INSERT INTO member_beneficiaries (member_id, slot, name, phone)
SELECT member_id, slot, name, phone FROM (
  SELECT id member_id, 1 slot, NULLIF(TRIM(namebene1),'') name, NULLIF(TRIM(telbene1),'') phone FROM pcg_ecm_legacy.members UNION ALL
  SELECT id, 2, NULLIF(TRIM(namebene2),''), NULLIF(TRIM(telbene2),'') FROM pcg_ecm_legacy.members UNION ALL
  SELECT id, 3, NULLIF(TRIM(namebene3),''), NULLIF(TRIM(telbene3),'') FROM pcg_ecm_legacy.members UNION ALL
  SELECT id, 4, NULLIF(TRIM(namebene4),''), NULLIF(TRIM(telbene4),'') FROM pcg_ecm_legacy.members) x
WHERE name IS NOT NULL OR phone IS NOT NULL;

INSERT INTO member_sacraments (member_id, kind, sacrament_date, place, minister)
SELECT * FROM (
  SELECT id member_id, 'baptism' kind, NULLIF(baptism_date,'0000-00-00') d, NULLIF(TRIM(baptism_place),'') p, NULLIF(TRIM(baptism_minister),'') mn FROM pcg_ecm_legacy.members
  UNION ALL
  SELECT id, 'confirmation', NULLIF(confirmation_date,'0000-00-00'), NULLIF(TRIM(confirmation_place),''), NULLIF(TRIM(confirmation_minister),'') FROM pcg_ecm_legacy.members) s
WHERE d IS NOT NULL OR p IS NOT NULL OR mn IS NOT NULL;

INSERT INTO member_children (id, member_id, name, date_of_birth, place_of_birth, photo_id)
SELECT c.id, c.member_id, TRIM(c.name), NULLIF(c.dob,'0000-00-00'), NULLIF(TRIM(c.place_of_birth),''),
       IF(c.image_id IN (SELECT id FROM media_files), c.image_id, NULL)
FROM pcg_ecm_legacy.childrens c
WHERE c.member_id IN (SELECT id FROM members) AND TRIM(IFNULL(c.name,'')) <> '';

INSERT INTO member_transfers (id, member_id, transferred_on, church_name, location)
SELECT id, member_id, NULLIF(date,'0000-00-00'), NULLIF(TRIM(churchname),''), NULLIF(TRIM(location),'')
FROM pcg_ecm_legacy.transfers WHERE member_id IN (SELECT id FROM members);

INSERT INTO member_deaths (member_id, died_on, buried_on, burial_place, burial_town, pastor_in_charge, cause_of_death)
SELECT member_id, NULLIF(datedied,'0000-00-00'), NULLIF(dateburied,'0000-00-00'), NULLIF(TRIM(placeofburial),''),
       NULLIF(TRIM(townofburial),''), NULLIF(TRIM(pastorincharge),''), reasonofdeath
FROM pcg_ecm_legacy.deaths d
WHERE member_id IN (SELECT id FROM members)
  AND d.id = (SELECT MAX(d2.id) FROM pcg_ecm_legacy.deaths d2 WHERE d2.member_id = d.member_id);

INSERT INTO visitors (id, visited_on, name, telephone, location, purpose, attended_by, remarks, recorded_by)
SELECT v.id, NULLIF(v.date,'0000-00-00'), TRIM(v.name), NULLIF(TRIM(v.tel),''), NULLIF(TRIM(v.location),''),
       NULLIF(TRIM(v.purpose),''), NULLIF(TRIM(v.attendedby),''), v.remarks,
       IF(v.user_id IN (SELECT id FROM users), v.user_id, NULL)
FROM pcg_ecm_legacy.visitors v;

-- attendance: 8 columns -> rows ------------------------------------------------------
INSERT INTO attendance_counts (attended_on, age_group, sex, headcount)
SELECT date, g, s, n FROM (
  SELECT date, 'children' g, 'male' s, cmale n FROM pcg_ecm_legacy.attendances UNION ALL
  SELECT date, 'children', 'female', cfemale FROM pcg_ecm_legacy.attendances UNION ALL
  SELECT date, 'junior_youth', 'male', jymale FROM pcg_ecm_legacy.attendances UNION ALL
  SELECT date, 'junior_youth', 'female', jyfemale FROM pcg_ecm_legacy.attendances UNION ALL
  SELECT date, 'young_adult', 'male', ypgmale FROM pcg_ecm_legacy.attendances UNION ALL
  SELECT date, 'young_adult', 'female', ypgfemale FROM pcg_ecm_legacy.attendances UNION ALL
  SELECT date, 'adult', 'male', adultmale FROM pcg_ecm_legacy.attendances UNION ALL
  SELECT date, 'adult', 'female', adultfemale FROM pcg_ecm_legacy.attendances) a
WHERE date <> '0000-00-00';

-- giving: tithes + offerings + harvest levy -> contributions -----------------------------
INSERT INTO contribution_types (code, name) VALUES ('tithe','Tithe'), ('offering','Offering'), ('harvest','Harvest');

-- Amounts were varchar: strip thousands separators, stray backticks/backslashes/spaces, trailing dots.
INSERT INTO contributions (member_id, type_id, amount, contributed_on, date_inferred, invoice_no, purpose, recorded_by,
                           created_at, legacy_table, legacy_id, legacy_member_id, legacy_user_id)
SELECT IF(t.member_id IN (SELECT id FROM members), t.member_id, NULL),
       (SELECT id FROM contribution_types WHERE code='tithe'),
       CAST(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(t.amount),',',''),'`',''),'\\',''),' ','') AS DECIMAL(12,2)),
       IF(t.date < '2000-01-01' OR t.date > CURDATE(), DATE(t.created), t.date),
       (t.date < '2000-01-01' OR t.date > CURDATE()),
       NULLIF(t.invoicenum,0), NULL,
       IF(t.user_id IN (SELECT id FROM users), t.user_id, NULL),
       NULLIF(t.created,'0000-00-00 00:00:00'), 'tithes', t.id, t.member_id, NULLIF(t.user_id,0)
FROM pcg_ecm_legacy.tithes t;

INSERT INTO contributions (member_id, type_id, amount, contributed_on, date_inferred, invoice_no, purpose, recorded_by,
                           created_at, legacy_table, legacy_id, legacy_member_id, legacy_user_id)
SELECT IF(t.member_id IN (SELECT id FROM members), t.member_id, NULL),
       (SELECT id FROM contribution_types WHERE code='offering'),
       -- five hand-typed typos, corrected by id: '$5.00' '.5.00'(read as 5.00, ambiguous) 'S10.00' '10.(00' '5.00.00'
       CASE t.id WHEN 746 THEN 5.00 WHEN 749 THEN 10.00 WHEN 761 THEN 10.00 WHEN 6254 THEN 5.00 WHEN 9244 THEN 5.00
         ELSE CAST(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(t.amount),',',''),'`',''),'\\',''),' ','') AS DECIMAL(12,2)) END,
       IF(t.date < '2000-01-01' OR t.date > CURDATE(), DATE(t.created), t.date),
       (t.date < '2000-01-01' OR t.date > CURDATE()),
       NULLIF(t.invoicenum,0),
       -- ~99.9% of rows say "Offertory" (with typos) - that is just the type; keep only genuinely different text
       CASE WHEN t.purpose IS NULL OR TRIM(t.purpose) = '' OR TRIM(t.purpose) LIKE 'Of%' THEN NULL ELSE TRIM(t.purpose) END,
       IF(t.user_id IN (SELECT id FROM users), t.user_id, NULL),
       NULLIF(t.created,'0000-00-00 00:00:00'), 'indoffertories', t.id, t.member_id, NULLIF(t.user_id,0)
FROM pcg_ecm_legacy.indoffertories t;

INSERT INTO contributions (member_id, type_id, amount, contributed_on, date_inferred, invoice_no, purpose, recorded_by,
                           created_at, legacy_table, legacy_id, legacy_member_id, legacy_user_id)
SELECT IF(t.member_id IN (SELECT id FROM members), t.member_id, NULL),
       (SELECT id FROM contribution_types WHERE code='harvest'),
       CAST(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(t.amount),',',''),'`',''),'\\',''),' ','') AS DECIMAL(12,2)),
       IF(t.date < '2000-01-01' OR t.date > CURDATE(), DATE(t.created), t.date),
       (t.date < '2000-01-01' OR t.date > CURDATE()),
       NULLIF(t.invoicenum,0), NULL, NULL,
       NULLIF(t.created,'0000-00-00 00:00:00'), 'harvests', t.id, t.member_id, NULL
FROM pcg_ecm_legacy.harvests t;

-- banking, payroll, assets ---------------------------------------------------------------
INSERT INTO bank_accounts (id, bank_name, account_number, account_name, account_type, branch, balance, created_at)
SELECT id, TRIM(name), TRIM(accno), NULLIF(TRIM(accname),''), NULLIF(TRIM(acctype),''), NULLIF(TRIM(branch),''), balance,
       NULLIF(created,'0000-00-00 00:00:00')
FROM pcg_ecm_legacy.banks;

INSERT INTO bank_transactions (id, bank_account_id, transacted_on, cheque_no, debit, credit, balance, transaction_type, description, created_at)
SELECT id, bank_id, date, NULLIF(TRIM(chequeno),''), debit, credit, balance, NULLIF(TRIM(transtype),''), description,
       NULLIF(created,'0000-00-00 00:00:00')
FROM pcg_ecm_legacy.banktransactions;

INSERT INTO employees (id, full_name, ssnit_number, telephone)
SELECT id, TRIM(name), NULLIF(TRIM(ssnitno),''), NULLIF(TRIM(telephone),'') FROM pcg_ecm_legacy.employees;

INSERT INTO payroll_payments (employee_id, kind, paid_on, amount, cheque_no, pv_number, recorded_by)
SELECT employee_id, 'salary', date, amount, NULLIF(TRIM(chequeno),''), NULLIF(TRIM(pvnumber),''),
       IF(user_id IN (SELECT id FROM users), user_id, NULL)
FROM pcg_ecm_legacy.salaries WHERE employee_id IN (SELECT id FROM employees);
INSERT INTO payroll_payments (employee_id, kind, paid_on, amount)
SELECT employee_id, 'allowance', date, amount FROM pcg_ecm_legacy.allowances WHERE employee_id IN (SELECT id FROM employees);

INSERT INTO assets (id, name, category_id, department_id, assigned_to, vendor, model_number, serial_number, purchase_price,
                    purchased_on, sold_on, next_maintenance_on, status_code, description, photo_id, created_at)
SELECT a.id, NULLIF(TRIM(a.name),''),
       IF(a.category_id IN (SELECT id FROM asset_categories), a.category_id, NULL),
       IF(a.department_id IN (SELECT id FROM departments), a.department_id, NULL),
       NULLIF(TRIM(a.employee),''), NULLIF(TRIM(a.vendor),''), NULLIF(TRIM(a.modelnumber),''), NULLIF(TRIM(a.serialnumber),''),
       a.purchaseprice, NULLIF(a.datepurchase,'0000-00-00'), NULLIF(a.datesold,'0000-00-00'), NULLIF(a.nextmaindate,'0000-00-00'),
       NULLIF(TRIM(a.status),''), a.description,
       IF(a.image_id IN (SELECT id FROM media_files), a.image_id, NULL), NULLIF(a.created,'0000-00-00 00:00:00')
FROM pcg_ecm_legacy.assets a;

INSERT INTO asset_history (id, asset_id, event_date, content, created_at)
SELECT id, asset_id, NULLIF(date,'0000-00-00'), content, NULLIF(created,'0000-00-00 00:00:00')
FROM pcg_ecm_legacy.assethistory WHERE asset_id IN (SELECT id FROM assets);

-- SMS: four tables -> one -------------------------------------------------------------------
INSERT INTO sms_messages (kind, member_id, recipient_phone, subject, body, status, sent_by, created_at, legacy_table, legacy_id, legacy_user_id)
SELECT 'single', IF(member_id IN (SELECT id FROM members), member_id, NULL), NULLIF(TRIM(telnumber),''), NULLIF(TRIM(subject),''),
       message, NULLIF(TRIM(status),''), IF(user_id IN (SELECT id FROM users), user_id, NULL),
       NULLIF(created,'0000-00-00 00:00:00'), 'sendsms', id, NULLIF(user_id,0)
FROM pcg_ecm_legacy.sendsms;
INSERT INTO sms_messages (kind, subject, body, status, sent_by, created_at, legacy_table, legacy_id, legacy_user_id)
SELECT 'bulk', NULLIF(TRIM(subject),''), message, NULLIF(TRIM(status),''), IF(user_id IN (SELECT id FROM users), user_id, NULL),
       NULLIF(created,'0000-00-00 00:00:00'), 'sendbulksms', id, NULLIF(user_id,0)
FROM pcg_ecm_legacy.sendbulksms;
INSERT INTO sms_messages (kind, audience, subject, body, status, sent_by, created_at, legacy_table, legacy_id, legacy_user_id)
SELECT 'group', NULLIF(TRIM(gengroup),''), NULLIF(TRIM(subject),''), message, NULLIF(TRIM(status),''),
       IF(user_id IN (SELECT id FROM users), user_id, NULL), NULLIF(created,'0000-00-00 00:00:00'), 'sendbulksmsgroups', id, NULLIF(user_id,0)
FROM pcg_ecm_legacy.sendbulksmsgroups;
INSERT INTO sms_messages (kind, member_group_id, subject, body, status, sent_by, created_at, legacy_table, legacy_id, legacy_user_id)
SELECT 'group', IF(membergroup_id IN (SELECT id FROM member_groups), membergroup_id, NULL), NULLIF(TRIM(subject),''), message,
       NULLIF(TRIM(status),''), IF(user_id IN (SELECT id FROM users), user_id, NULL), NULLIF(created,'0000-00-00 00:00:00'),
       'sendsmsgroupservice', id, NULLIF(user_id,0)
FROM pcg_ecm_legacy.sendsmsgroupservice;

COMMIT;
SET FOREIGN_KEY_CHECKS = 1;
