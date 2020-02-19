<?php

namespace madman\Password;

class MySQLStore implements Datastore
{
    public function openConnection($host, $username, $password, $db_name)
    {
        $link = mysql_connect($host, $username, $password);
        mysql_select_db($db_name);
        return $link;
    }

    public function getConnection()
    {
    }

    public function setConnection($conn)
    {
    }

    public function closeConnection()
    {
    }

    public function getVersion()
    {
    }
}
