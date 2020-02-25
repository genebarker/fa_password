-- create slim version of referenced tables in FA v2.3.x
CREATE TABLE IF NOT EXISTS 0_users (
    id smallint PRIMARY KEY,
    user_id varchar(60),
    password varchar(100)
);
DELETE FROM 0_users;

INSERT INTO 0_users (
    id, user_id, password
)
VALUE (
    101,
    'fmulder',
    'a3aab32ace277bdf141f92c1e68f6cef'
);

INSERT INTO 0_pwe_user (
    oid, pw_hash
)
VALUE (
    101,
    '$2y$10$5BEkSCYW3k//CaCIejTJNu7uHiGcyFHF9N9oDHCls7/qFSugv5GZu'
);
