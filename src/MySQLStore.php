<?php

namespace madman\Password;

use Exception;
use madman\Password\User;

class MySQLStore implements Datastore
{
    const MYSQL_EXT_SCHEMA_FILE = __DIR__ . '/mysql_build_ext_schema.sql';
    const MYSQL_TIMESTAMP_FORMAT = 'Y-m-d H:i:s';

    public $conn = null;

    public function openConnection(
        $host,
        $username,
        $password,
        $db_name,
        $new_link = false
    ) {
        $link = mysql_connect($host, $username, $password, $new_link);
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
        $sql = "SELECT version()";
        $fail_message = "Could not get version from MySQL.";
        $row = $this->doQueryAndGetRow($sql, $fail_message);
        return 'MySQL ' . $row[0];
    }

    public function doQueryAndGetRow($sql, $fail_message)
    {
        $result = $this->doQuery($sql, $fail_message);
        if (mysql_num_rows($result) == 0) {
            throw new Exception($fail_message, self::NO_MATCHING_ROW_FOUND);
        }
        return mysql_fetch_row($result);
    }

    public function doQuery($sql, $fail_message)
    {
        $trimmed_sql = Dedenter::dedent($sql);
        $result = mysql_query($trimmed_sql, $this->conn);
        if (!$result) {
            $this->throwDatabaseException($trimmed_sql, $fail_message);
        }
        return $result;
    }

    public function throwDatabaseException($sql, $error_message)
    {
        $cause = mysql_error($this->conn) ?: 'Unknown';
        $sql = $this->collapseSQL($sql);
        $message = "$error_message Cause: $cause. SQL:\n$sql";
        throw new Exception($message, self::QUERY_ERROR);
    }

    public static function collapseSQL($sql)
    {
        $lines = explode("\n", $sql);
        $lines = array_map('trim', $lines);
        return implode(' ', $lines);
    }

    public function startTransaction()
    {
        $sql = "START TRANSACTION";
        $fail_message = "Could not start a MySQL transaction.";
        $this->doQuery($sql, $fail_message);
    }

    public function commitTransaction()
    {
        $sql = "COMMIT";
        $fail_message = "Could not commit a MySQL transaction.";
        $this->doQuery($sql, $fail_message);
    }

    public function rollbackTransaction()
    {
        $sql = "ROLLBACK";
        $fail_message = "Could not rollback a MySQL transaction.";
        $this->doQuery($sql, $fail_message);
    }

    public function buildDatabaseSchema()
    {
        $fail_message = 'Failed to build MySQL password extension tables.';
        $this->executeSQLFromFile(
            self::MYSQL_EXT_SCHEMA_FILE,
            $fail_message
        );
    }

    public function executeSQLFromFile($filepath, $fail_message)
    {
        $handle = @fopen($filepath, 'r');
        if (!$handle) {
            $cause = "Could not open file ($filepath).";
            $message = "$fail_message Cause: $cause";
            throw new Exception($message);
        }
        try {
            $this->processSQLFromStream($handle, $fail_message);
        } finally {
            fclose($handle);
        }
    }

    private function processSQLFromStream($handle, $fail_message)
    {
        $sql = '';
        while (($line = fgets($handle)) !== false) {
            if (self::isCommentLine($line) || self::isEmptyLine($line)) {
                continue;
            }
            $sql .= $line;
            if (self::endsWithSemicolon($line)) {
                $this->doQuery($sql, $fail_message);
                $sql = '';
            }
        }
    }

    private static function isCommentLine($line)
    {
        $tag_pos = strpos($line, '--');
        if (!$tag_pos) {
            return false;
        }
        return $tag_pos == 0;
    }

    private static function isEmptyLine($line)
    {
        return (trim($line) == '');
    }

    private static function endsWithSemicolon($line)
    {
        return (substr(trim($line), -1) == ';');
    }

    public function getConfig()
    {
        $config = new Config();
        $key = [
            'login_fail_threshold_count',
            'login_fail_lock_minutes',
        ];
        foreach ($key as $okey) {
            $sql = "SELECT val FROM 0_pwe_config WHERE okey = '$okey'";
            $fail_message = "Could not config value ($okey).";
            $row = $this->doQueryAndGetRow($sql, $fail_message);
            $config->$okey = $row[0];
        }
        return $config;
    }

    public function getUserByUsername($username)
    {
        $sql = "SELECT u.id, u.user_id, u2.pw_hash, u2.needs_pw_change,
                    u2.is_locked, u2.ongoing_pw_fail_count,
                    u2.last_pw_fail_time
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
        $user->needs_pw_change = $row[3];
        $user->is_locked = $row[4];
        $user->ongoing_pw_fail_count = $row[5];
        $user->last_pw_fail_time = (
            $row[6] == null ? null : self::convertToPHPDate($row[6])
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

    public function updateUser($user)
    {
        $sql = "UPDATE 0_pwe_user
                SET pw_hash = '%s',
                    needs_pw_change = %b,
                    is_locked = %b,
                    ongoing_pw_fail_count = %d,
                    last_pw_fail_time = '%s'
                WHERE oid = %d
        ";
        $query = sprintf(
            $sql,
            mysql_real_escape_string($user->pw_hash),
            $user->needs_pw_change,
            $user->is_locked,
            $user->ongoing_pw_fail_count,
            self::convertToSQLTimestamp($user->last_pw_fail_time),
            $user->oid
        );
        $result = mysql_query($query, $this->conn);
    }

    public static function convertToSQLTimestamp($php_date)
    {
        return date_format($php_date, self::MYSQL_TIMESTAMP_FORMAT);
    }
}
