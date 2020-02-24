-- create tables
DROP TABLE IF EXISTS 0_pwe_user;
CREATE TABLE 0_pwe_user (
    oid integer PRIMARY KEY,
    password_hash varchar(128),
    needs_password_change boolean,
    is_locked boolean,
    ongoing_password_failure_count integer,
    last_password_failure_time timestamp
);
