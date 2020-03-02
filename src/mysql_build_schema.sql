-- create new tables for the extension
DROP TABLE IF EXISTS 0_pwe_config;
CREATE TABLE 0_pwe_config (
    okey varchar(50),
    val text
);
INSERT INTO 0_pwe_config (okey, val)
VALUES
    ('login_fail_threshold_count', 3),
    ('LOGIN_FAIL_LOCK_MINUTES', 15);

DROP TABLE IF EXISTS 0_pwe_user;
CREATE TABLE 0_pwe_user (
    oid integer PRIMARY KEY,
    pw_hash varchar(128),
    needs_pw_change boolean,
    is_locked boolean,
    ongoing_pw_fail_count integer,
    last_pw_fail_time timestamp
);
