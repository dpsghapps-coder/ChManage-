#!/bin/sh
# Moves every table in pcg_ecm into pcg_ecm_legacy so pcg_ecm can hold the normalized schema.
# Run once, on a fresh pcg_ecm copy of epc. The original `epc` database is never touched.
set -e
M="mysql -u root"
$M -e "CREATE DATABASE IF NOT EXISTS pcg_ecm_legacy CHARACTER SET utf8mb4"
SQL=$($M -N -e "SELECT CONCAT('RENAME TABLE pcg_ecm.\`',table_name,'\` TO pcg_ecm_legacy.\`',table_name,'\`;') FROM information_schema.tables WHERE table_schema='pcg_ecm' AND table_type='BASE TABLE'")
echo "$SQL" | $M
$M -N -e "SELECT 'legacy tables:',COUNT(*) FROM information_schema.tables WHERE table_schema='pcg_ecm_legacy'; SELECT 'pcg_ecm tables left:',COUNT(*) FROM information_schema.tables WHERE table_schema='pcg_ecm'"
