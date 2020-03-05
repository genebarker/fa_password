-- create slim version of referenced tables in FA v2.3.x
CREATE TABLE IF NOT EXISTS 0_users (
    id smallint PRIMARY KEY,
    user_id varchar(60),
    password varchar(100)
);
