-- create tables
DROP TABLE IF EXISTS 0_pwe_user;
CREATE TABLE 0_pwe_user (
    oid integer PRIMARY KEY,
    pw_hash varchar(128),
    needs_pw_change boolean,
    is_locked boolean,
    ongoing_pw_fail_count integer,
    last_pw_fail_time timestamp
);
