<?php

interface Datastore
{
    public function openConnection($db_username, $db_password, $db_name);
    public function getConnection();
    public function setConnection($connection);
    public function closeConnection();
    public function getVersion();
}

?>