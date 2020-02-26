<?php

namespace madman\Password;

use Exception;
use madman\Password\User;

class MySQLStore implements Datastore
{
    const MYSQL_TIMESTAMP_FORMAT = 'Y-m-d H:i:s';

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
        $result = mysql_query("SELECT version()", $this->conn);
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
        $sql = "SELECT u.id, u.user_id, u2.pw_hash,
                    u2.ongoing_pw_fail_count, u2.last_pw_fail_time
                FROM 0_users u, 0_pwe_user u2
                WHERE u.user_id = '%s'
                    AND u2.oid = u.id
        ";
        $query = sprintf($sql, mysql_real_escape_string($username));
        $result = mysql_query($query, $this->conn);
        if (mysql_num_rows($result) == 0) {
            throw new Exception(
                "Username ($username) does not exist.",
                self::UNKNOWN_USERNAME
            );
        }
        $row = mysql_fetch_row($result);

        $user = new User($row[0], $row[1]);
        $user->pw_hash = $row[2];
        $user->ongoing_pw_fail_count = $row[3];
        $user->last_pw_fail_time = (
            $row[4] == null ? null : self::convertToPHPDate($row[4])
        );
        return $user;
    }

    public static function convertToPHPDate($timestamp_string)
    {
        $php_date = date_create_from_format(
            self::MYSQL_TIMESTAMP_FORMAT,
            $timestamp_string
        );
        return $php_date;
    }
}
