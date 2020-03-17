-- create new tables for the extension
DROP TABLE IF EXISTS 0_pwe_config;
CREATE TABLE 0_pwe_config (
    okey varchar(50) PRIMARY KEY,
    val text
);
INSERT INTO 0_pwe_config (okey, val)
VALUES
    ('login_fail_threshold_count', 3),
    ('login_fail_lock_minutes', 15),
    ('minimum_password_strength', 2),
    ('maximum_password_age_days', 90),
    ('password_history_count', 10);

DROP TABLE IF EXISTS 0_pwe_user;
CREATE TABLE 0_pwe_user (
    oid integer PRIMARY KEY,
    pw_hash varchar(128),
    needs_pw_change boolean,
    is_locked boolean,
    ongoing_pw_fail_count integer,
    last_pw_fail_time timestamp
);

DROP TABLE IF EXISTS 0_pwe_history;
CREATE TABLE 0_pwe_history (
    oid integer PRIMARY KEY,
    pw_hash varchar(128),
    dob date
);
