<?php

namespace madman\Password;

interface Datastore
{
    // phpcs:disable
    const UNKNOWN_USERNAME = 1001;
    // phpcs:enable

    public function openConnection($host, $username, $password, $db_name);
    public function getConnection();
    public function setConnection($conn);
    public function closeConnection();
    public function getVersion();
    public function buildDatabaseSchema();
    public function getUserByUsername($username);
}
