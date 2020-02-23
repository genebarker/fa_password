<?php

namespace madman\Password;

class MySQLStore implements Datastore
{
    public $conn = null;

    public function openConnection($host, $username, $password, $db_name)
    {
        $link = mysql_connect($host, $username, $password);
        mysql_select_db($db_name);
        $this->conn = $link;
    }

    public function getConnection()
    {
        return $this->conn;
    }

    public function setConnection($link)
    {
        $this->conn = $link;
    }

    public function closeConnection()
    {
        mysql_close($this->conn);
    }

    public function getVersion()
    {
        $result = mysql_query('SELECT version();', $this->conn);
        $row = mysql_fetch_row($result);
        return 'MySQL ' . $row[0];
    }
}
