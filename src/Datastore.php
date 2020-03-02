<?php

namespace madman\Password;

interface Datastore
{
    const QUERY_ERROR = 1000;
    const UNKNOWN_USERNAME = 1001;

    public function openConnection($host, $username, $password, $db_name);
    public function getConnection();
    public function setConnection($conn);
    public function closeConnection();
    public function getVersion();
    public function buildDatabaseSchema();
    public function getConfig();
    public function getUserByUsername($username);
    public function updateUser($user);
}
