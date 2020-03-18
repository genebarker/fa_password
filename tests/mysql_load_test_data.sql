-- insert default data expected by tests
-- (passwords in order: scully, mulder, smoking, marine)
DELETE FROM 0_users;
INSERT INTO 0_users (
    id, user_id, password
)
VALUES
    (101, 'fmulder', 'a3aab32ace277bdf141f92c1e68f6cef'),
    (102, 'dscully', '71720c4911b0c34c25ed4b3aa188bdb8'),
    (103, 'skinner', '5cc224100427d254d62b1fe5fc7883b3'),
    (104, 'doggett', 'b329f324cc17d6221a385ea1afb3a289');

DELETE FROM 0_pwe_user;
INSERT INTO 0_pwe_user (
    oid, pw_hash,
    needs_pw_change, is_locked, ongoing_pw_fail_count, last_pw_fail_time,
    last_pw_update_time
)
VALUES
    (101, '$2y$10$5BEkSCYW3k//CaCIejTJNu7uHiGcyFHF9N9oDHCls7/qFSugv5GZu',
        FALSE, FALSE, 1, '2019-12-25 12:15:00', current_timestamp()),
    (102, '$2y$10$vra/wVFQUZHlOaVYIqPew.SbYCmTJDdKmOXHPdq038d6z08xSe.4G',
        TRUE, TRUE, 99, '2020-01-01 00:00:00', current_timestamp());

-- fmulder's passwords: ufos, aliens, scully
DELETE FROM 0_pwe_history;
INSERT INTO 0_pwe_history (user_oid, pw_hash, dob)
VALUES
    (101, '$2y$10$hr33frHko.wW4z2nmFKXc.nuOsYzRggAkM1rFXNvfOt5RoowBfscS',
        '2019-07-04 00:00:00'),
    (101, '$2y$10$ThepYMecfxatoYQbjJ1g4uvlRzfDd.CEUVrsgkqj/BMb5HMT8JcRa',
        '2019-12-07 00:00:00'),
    (101, '$2y$10$5BEkSCYW3k//CaCIejTJNu7uHiGcyFHF9N9oDHCls7/qFSugv5GZu',
        current_timestamp()),
    (102, '$2y$10$vra/wVFQUZHlOaVYIqPew.SbYCmTJDdKmOXHPdq038d6z08xSe.4G',
        current_timestamp());
