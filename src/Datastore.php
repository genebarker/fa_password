<?php

namespace madman\Password;

interface Datastore
{
    public function openConnection($host, $username, $password, $db_name);
    public function getConnection();
    public function setConnection($conn);
    public function closeConnection();
    public function getVersion();
    public function buildDatabaseSchema();
}
