-- Reconciliation: every check should return the expected/zero value described in the label.
USE pcg_ecm;

SELECT '--- row counts (legacy -> new) ---' AS section;
SELECT 'members' t, (SELECT COUNT(*) FROM pcg_ecm_legacy.members) old_n, (SELECT COUNT(*) FROM members) new_n UNION ALL
SELECT 'tithes+indoff+harvests -> contributions',
  (SELECT COUNT(*) FROM pcg_ecm_legacy.tithes)+(SELECT COUNT(*) FROM pcg_ecm_legacy.indoffertories)+(SELECT COUNT(*) FROM pcg_ecm_legacy.harvests),
  (SELECT COUNT(*) FROM contributions) UNION ALL
SELECT 'childrens -> member_children', (SELECT COUNT(*) FROM pcg_ecm_legacy.childrens), (SELECT COUNT(*) FROM member_children) UNION ALL
SELECT 'transfers', (SELECT COUNT(*) FROM pcg_ecm_legacy.transfers), (SELECT COUNT(*) FROM member_transfers) UNION ALL
SELECT 'deaths', (SELECT COUNT(*) FROM pcg_ecm_legacy.deaths), (SELECT COUNT(*) FROM member_deaths) UNION ALL
SELECT 'clientusers -> users', (SELECT COUNT(*) FROM pcg_ecm_legacy.clientusers), (SELECT COUNT(*) FROM users) UNION ALL
SELECT 'images -> media_files', (SELECT COUNT(*) FROM pcg_ecm_legacy.images), (SELECT COUNT(*) FROM media_files) UNION ALL
SELECT 'sms (4 tables)', (SELECT COUNT(*) FROM pcg_ecm_legacy.sendsms)+(SELECT COUNT(*) FROM pcg_ecm_legacy.sendbulksms)+(SELECT COUNT(*) FROM pcg_ecm_legacy.sendbulksmsgroups)+(SELECT COUNT(*) FROM pcg_ecm_legacy.sendsmsgroupservice), (SELECT COUNT(*) FROM sms_messages);

SELECT '--- money: legacy total vs new total (must be equal) ---' AS section;
SELECT 'tithes' t,
  (SELECT SUM(CAST(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(amount),',',''),'`',''),'\\',''),' ','') AS DECIMAL(12,2))) FROM pcg_ecm_legacy.tithes) old_total,
  (SELECT SUM(c.amount) FROM contributions c JOIN contribution_types y ON y.id=c.type_id WHERE y.code='tithe') new_total
UNION ALL SELECT 'harvests',
  (SELECT SUM(CAST(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(amount),',',''),'`',''),'\\',''),' ','') AS DECIMAL(12,2))) FROM pcg_ecm_legacy.harvests),
  (SELECT SUM(c.amount) FROM contributions c JOIN contribution_types y ON y.id=c.type_id WHERE y.code='harvest')
UNION ALL SELECT 'offerings (excl. 5 typo rows)',
  (SELECT SUM(CAST(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(amount),',',''),'`',''),'\\',''),' ','') AS DECIMAL(12,2))) FROM pcg_ecm_legacy.indoffertories WHERE id NOT IN (746,749,761,6254,9244)),
  (SELECT SUM(c.amount) FROM contributions c JOIN contribution_types y ON y.id=c.type_id WHERE y.code='offering' AND c.legacy_id NOT IN (746,749,761,6254,9244))
UNION ALL SELECT 'income (12 tables)',
  (SELECT SUM(a) FROM (
    SELECT SUM(amount) a FROM pcg_ecm_legacy.abume UNION ALL SELECT SUM(amount) FROM pcg_ecm_legacy.aged UNION ALL SELECT SUM(amount) FROM pcg_ecm_legacy.allnight
    UNION ALL SELECT SUM(amount) FROM pcg_ecm_legacy.almanac UNION ALL SELECT SUM(amount) FROM pcg_ecm_legacy.evening UNION ALL SELECT SUM(amount) FROM pcg_ecm_legacy.harvest
    UNION ALL SELECT SUM(amount) FROM pcg_ecm_legacy.jerico UNION ALL SELECT SUM(amount) FROM pcg_ecm_legacy.marriage UNION ALL SELECT SUM(amount) FROM pcg_ecm_legacy.morningdevo
    UNION ALL SELECT SUM(amount) FROM pcg_ecm_legacy.otherincome UNION ALL SELECT SUM(amount) FROM pcg_ecm_legacy.pgroup UNION ALL SELECT SUM(amount) FROM pcg_ecm_legacy.projecto) x),
  (SELECT SUM(amount) FROM income)
UNION ALL SELECT 'expenses',
  (SELECT SUM(a) FROM (
    SELECT SUM(amount) a FROM pcg_ecm_legacy.accomodation UNION ALL SELECT SUM(amount) FROM pcg_ecm_legacy.adverts UNION ALL SELECT SUM(amount) FROM pcg_ecm_legacy.appreciation
    UNION ALL SELECT SUM(amount) FROM pcg_ecm_legacy.assesother UNION ALL SELECT SUM(amount) FROM pcg_ecm_legacy.assetexp UNION ALL SELECT SUM(amount) FROM pcg_ecm_legacy.brigade
    UNION ALL SELECT SUM(amount) FROM pcg_ecm_legacy.cleaning UNION ALL SELECT SUM(amount) FROM pcg_ecm_legacy.communion UNION ALL SELECT SUM(amount) FROM pcg_ecm_legacy.cservice
    UNION ALL SELECT SUM(amount) FROM pcg_ecm_legacy.donations UNION ALL SELECT SUM(amount) FROM pcg_ecm_legacy.dues UNION ALL SELECT SUM(amount) FROM pcg_ecm_legacy.education
    UNION ALL SELECT SUM(amount) FROM pcg_ecm_legacy.esr UNION ALL SELECT SUM(amount) FROM pcg_ecm_legacy.evangelism UNION ALL SELECT SUM(amount) FROM pcg_ecm_legacy.fuel
    UNION ALL SELECT SUM(amount) FROM pcg_ecm_legacy.funeral UNION ALL SELECT SUM(amount) FROM pcg_ecm_legacy.generator UNION ALL SELECT SUM(amount) FROM pcg_ecm_legacy.harvestex
    UNION ALL SELECT SUM(amount) FROM pcg_ecm_legacy.imprest UNION ALL SELECT SUM(amount) FROM pcg_ecm_legacy.invalid UNION ALL SELECT SUM(amount) FROM pcg_ecm_legacy.junior
    UNION ALL SELECT SUM(amount) FROM pcg_ecm_legacy.lighting UNION ALL SELECT SUM(amount) FROM pcg_ecm_legacy.maintenance UNION ALL SELECT SUM(amount) FROM pcg_ecm_legacy.manse
    UNION ALL SELECT SUM(amount) FROM pcg_ecm_legacy.medication UNION ALL SELECT SUM(amount) FROM pcg_ecm_legacy.meetings UNION ALL SELECT SUM(amount) FROM pcg_ecm_legacy.mproject
    UNION ALL SELECT SUM(amount) FROM pcg_ecm_legacy.music UNION ALL SELECT SUM(amount) FROM pcg_ecm_legacy.newasset UNION ALL SELECT SUM(amount) FROM pcg_ecm_legacy.newspaper
    UNION ALL SELECT SUM(amount) FROM pcg_ecm_legacy.office UNION ALL SELECT SUM(amount) FROM pcg_ecm_legacy.otherdo UNION ALL SELECT SUM(amount) FROM pcg_ecm_legacy.prate
    UNION ALL SELECT SUM(amount) FROM pcg_ecm_legacy.printing UNION ALL SELECT SUM(amount) FROM pcg_ecm_legacy.refreshment UNION ALL SELECT SUM(amount) FROM pcg_ecm_legacy.savings
    UNION ALL SELECT SUM(amount) FROM pcg_ecm_legacy.telecome UNION ALL SELECT SUM(amount) FROM pcg_ecm_legacy.transport UNION ALL SELECT SUM(amount) FROM pcg_ecm_legacy.utility) x),
  (SELECT SUM(amount) FROM expenses);

SELECT '--- orphaned references (all must be 0) ---' AS section;
SELECT 'contributions.member_id' chk, COUNT(*) bad FROM contributions c LEFT JOIN members m ON m.id=c.member_id WHERE c.member_id IS NOT NULL AND m.id IS NULL UNION ALL
SELECT 'contributions.type_id', COUNT(*) FROM contributions c LEFT JOIN contribution_types y ON y.id=c.type_id WHERE y.id IS NULL UNION ALL
SELECT 'contributions.recorded_by', COUNT(*) FROM contributions c LEFT JOIN users u ON u.id=c.recorded_by WHERE c.recorded_by IS NOT NULL AND u.id IS NULL UNION ALL
SELECT 'members.profession_id', COUNT(*) FROM members x LEFT JOIN professions p ON p.id=x.profession_id WHERE x.profession_id IS NOT NULL AND p.id IS NULL UNION ALL
SELECT 'members.photo_id', COUNT(*) FROM members x LEFT JOIN media_files p ON p.id=x.photo_id WHERE x.photo_id IS NOT NULL AND p.id IS NULL UNION ALL
SELECT 'member_group_memberships', COUNT(*) FROM member_group_memberships g LEFT JOIN member_groups mg ON mg.id=g.member_group_id LEFT JOIN members m ON m.id=g.member_id WHERE mg.id IS NULL OR m.id IS NULL UNION ALL
SELECT 'expenses.category/payee/project', COUNT(*) FROM expenses e LEFT JOIN ledger_categories c ON c.id=e.category_id
  LEFT JOIN payees p ON p.id=e.payee_id LEFT JOIN projects j ON j.id=e.project_id WHERE c.id IS NULL OR (e.payee_id IS NOT NULL AND p.id IS NULL) OR (e.project_id IS NOT NULL AND j.id IS NULL) UNION ALL
SELECT 'income.category/project', COUNT(*) FROM income i LEFT JOIN ledger_categories c ON c.id=i.category_id LEFT JOIN projects j ON j.id=i.project_id WHERE c.id IS NULL OR (i.project_id IS NOT NULL AND j.id IS NULL) UNION ALL
SELECT 'users.role_id', COUNT(*) FROM users u LEFT JOIN roles r ON r.id=u.role_id WHERE u.role_id IS NOT NULL AND r.id IS NULL UNION ALL
SELECT 'assets.*', COUNT(*) FROM assets a LEFT JOIN asset_categories c ON c.id=a.category_id LEFT JOIN departments d ON d.id=a.department_id WHERE (a.category_id IS NOT NULL AND c.id IS NULL) OR (a.department_id IS NOT NULL AND d.id IS NULL);

SELECT '--- data-quality notes ---' AS section;
SELECT 'contributions with no member (orphan legacy ids)' note, COUNT(*) n FROM contributions WHERE member_id IS NULL UNION ALL
SELECT 'contributions with inferred date', COUNT(*) FROM contributions WHERE date_inferred=1 UNION ALL
SELECT 'members by status: ' , COUNT(*) FROM members UNION ALL
SELECT CONCAT('  ', status), COUNT(*) FROM members GROUP BY status UNION ALL
SELECT 'min/max contribution date', CONCAT(MIN(contributed_on),' .. ',MAX(contributed_on)) FROM contributions;
