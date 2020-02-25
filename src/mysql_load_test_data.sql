-- to simulate tables created at install time by FA v2.3.x
DROP TABLE IF EXISTS 0_users;
CREATE TABLE 0_users (
    id smallint PRIMARY KEY,
    user_id varchar(60),
    password varchar(100)
);

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
