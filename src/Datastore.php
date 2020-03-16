<?php

namespace madman\Password;

interface Datastore
{
    const QUERY_ERROR = 1000;
    const NO_MATCHING_ROW_FOUND = 1002;

    public function openConnection($host, $username, $password, $db_name);
    public function getConnection();
    public function setConnection($conn);
    public function closeConnection();
    public function getVersion();
    public function startTransaction();
    public function commitTransaction();
    public function rollbackTransaction();
    public function addExtensionTables();
    public function removeExtensionTables();
    public function getConfig();
    public function updateConfig($config);
    public function getUserByUsername($username);
    public function updateUser($user);
    public function userExists($username);
}
