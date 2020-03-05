-- create slim version of referenced tables in FA v2.3.x
CREATE TABLE IF NOT EXISTS 0_users (
    id smallint PRIMARY KEY,
    user_id varchar(60),
    password varchar(100)
);

-- insert default data expected by tests
DELETE FROM 0_users;
INSERT INTO 0_users (
    id, user_id, password
)
VALUES
    (101, 'fmulder', 'a3aab32ace277bdf141f92c1e68f6cef'),
    (102, 'dscully', '71720c4911b0c34c25ed4b3aa188bdb8');

DELETE FROM 0_pwe_user;
INSERT INTO 0_pwe_user (
    oid, pw_hash,
    needs_pw_change, is_locked, ongoing_pw_fail_count, last_pw_fail_time
)
VALUES
    (101, '$2y$10$5BEkSCYW3k//CaCIejTJNu7uHiGcyFHF9N9oDHCls7/qFSugv5GZu',
        FALSE, FALSE, 1, '2019-12-25 12:15:00'),
    (102, '$2y$10$vra/wVFQUZHlOaVYIqPew.SbYCmTJDdKmOXHPdq038d6z08xSe.4G',
        TRUE, TRUE, 99, '2020-01-01 00:00:00');
