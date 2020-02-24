<?php

namespace madman\Password;

use Exception;

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

    public function buildDatabaseSchema()
    {
        $result = $this->executeSQLFromFile('mysql_build_schema.sql');
        if (!$result) {
            $cause = empty(mysql_error()) ? 'Unknown' : mysql_error();
            throw new Exception('Failed to build schema. Cause: ' . $cause);
        }
    }

    public function executeSQLFromFile($filename)
    {
        $filepath = __DIR__ . '/' . $filename;
        $handle = @fopen($filepath, 'r');
        if (!$handle) {
            return false;
        }
        $sql = '';
        $result = false;
        while (($line = fgets($handle)) !== false) {
            $sql .= $line;
            $comment_pos = strpos($line, '--');
            if ($comment_pos !== false && $comment_pos == 0) {
                continue;
            }
            $cmd_end_pos = strpos($line, ';');
            if ($cmd_end_pos !== false) {
                $result = mysql_query($sql, $this->conn);
                if (!$result) {
                    break;
                }
                $sql = '';
            }
        }
        fclose($handle);
        return $result;
    }

    public function getUserByUsername($username)
    {
    }
}
