<?php

namespace madman\Password;

use Exception;
use madman\Password\User;

class MySQLStore implements Datastore
{
    const MYSQL_EXT_BUILD_FILE = __DIR__ . '/mysql_build_ext_schema.sql';
    const MYSQL_EXT_REMOVE_FILE = __DIR__ . '/mysql_remove_ext_schema.sql';
    const MYSQL_TIMESTAMP_FORMAT = 'Y-m-d H:i:s';

    public $company;
    public $conn;
    public $last_query;

    public function __construct($company = 0)
    {
        $this->company = $company;
        $this->conn = null;
        $this->last_query = '';
    }
   
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

    public function doQuery($sql, $fail_message = 'The query failed.')
    {
        $preparred_sql = $this->prepareSQLforSubmission($sql);
        $this->last_query = $preparred_sql;
        $result = mysql_query($preparred_sql, $this->conn);
        if (!$result) {
            $this->throwDatabaseException($preparred_sql, $fail_message);
        }
        return $result;
    }

    public function prepareSQLforSubmission($sql)
    {
        $sql_for_company = $this->applyCompanyTablePrefix($sql);
        $tidy_sql = Dedenter::dedent($sql_for_company);
        return $tidy_sql;
    }

    public function applyCompanyTablePrefix($sql)
    {
        $default_prefix = ' 0_';
        $target_prefix = ' ' . $this->company . '_';
        return str_replace($default_prefix, $target_prefix, $sql);
    }

    public function throwDatabaseException($sql, $error_message)
    {
        $cause = mysql_error($this->conn) ?: 'Unknown';
        $message = "$error_message Cause: $cause. SQL:\n$sql";
        throw new Exception($message, self::QUERY_ERROR);
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

    public function addExtensionTables()
    {
        $fail_message = 'Failed to add MySQL password extension tables.';
        $this->executeSQLFromFile(
            self::MYSQL_EXT_BUILD_FILE,
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

    public function removeExtensionTables()
    {
        $fail_message = 'Failed to remove MySQL password extension tables.';
        $this->executeSQLFromFile(
            self::MYSQL_EXT_REMOVE_FILE,
            $fail_message
        );
    }

    public function getConfig()
    {
        $config = new Config();
        $key = $this->getConfigKeys();
        foreach ($key as $okey) {
            $sql = "SELECT val FROM 0_pwe_config WHERE okey = '$okey'";
            $fail_message = "Could not load config value ($okey).";
            $row = $this->doQueryAndGetRow($sql, $fail_message);
            $config->$okey = $row[0];
        }
        return $config;
    }

    public function getConfigKeys()
    {
        return [
            'login_fail_threshold_count',
            'login_fail_lock_minutes',
            'minimum_password_strength',
        ];
    }

    public function updateConfig($config)
    {
        $key = $this->getConfigKeys();
        foreach ($key as $okey) {
            $sql = "UPDATE 0_pwe_config
                    SET val = '%s'
                    WHERE okey = '%s'
            ";
            $query = sprintf(
                $sql,
                mysql_real_escape_string($config->$okey),
                $okey
            );
            $fail_message = "Could not update config value ($okey).";
            $this->doQuery($query, $fail_message);
        }
    }

    public function getUserByUsername($username)
    {
        $sql = "SELECT u.id, u.user_id, u.password, u2.pw_hash,
                    u2.needs_pw_change, u2.is_locked,
                    u2.ongoing_pw_fail_count, u2.last_pw_fail_time
                FROM 0_users u, 0_pwe_user u2
                WHERE u.user_id = '%s'
                    AND u2.oid = u.id
        ";
        $query = sprintf($sql, mysql_real_escape_string($username));
        $fail_message = "Could not get user (username=$username).";
        $row = $this->doQueryAndGetRow($query, $fail_message);

        $user = new User($row[0], $row[1]);
        $user->fa_pw_hash = $row[2];
        $user->pw_hash = $row[3];
        $user->needs_pw_change = ($row[4] != 0);
        $user->is_locked = ($row[5] != 0);
        $user->ongoing_pw_fail_count = $row[6];
        $user->last_pw_fail_time = (
            $row[7] == null ? null : self::convertToPHPDate($row[7])
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

    public function getBaseUserByUsername($username)
    {
        $sql = "SELECT id, user_id, password
                FROM 0_users
                WHERE user_id = '%s'
        ";
        $query = sprintf($sql, mysql_real_escape_string($username));
        $fail_message = "Could not get FA user (username=$username).";
        $row = $this->doQueryAndGetRow($query, $fail_message);

        $user = new User($row[0], $row[1]);
        $user->fa_pw_hash = $row[2];
        return $user;
    }

    public function updateUser($user)
    {
        $sql = "UPDATE 0_pwe_user
                SET pw_hash = '%s',
                    needs_pw_change = %b,
                    is_locked = %b,
                    ongoing_pw_fail_count = %d,
                    last_pw_fail_time = %s
                WHERE oid = %d
        ";
        $query = sprintf(
            $sql,
            mysql_real_escape_string($user->pw_hash),
            $user->needs_pw_change,
            $user->is_locked,
            $user->ongoing_pw_fail_count,
            (
                $user->last_pw_fail_time == null ? 'null' : "'" .
                self::convertToSQLTimestamp($user->last_pw_fail_time) . "'"
            ),
            $user->oid
        );
        $fail_message = "Could not update user (oid={$user->oid}, " .
                        "username={$user->username}).";
        $this->doUpdateQuery($query, $fail_message);
        $sql = "UPDATE 0_users
                SET password = '%s'
                WHERE id = %d
        ";
        $query = sprintf(
            $sql,
            mysql_real_escape_string($user->fa_pw_hash),
            $user->oid
        );
        $this->doUpdateQuery($query, $fail_message);
    }

    public static function convertToSQLTimestamp($php_date)
    {
        return date_format($php_date, self::MYSQL_TIMESTAMP_FORMAT);
    }

    public function doUpdateQuery($sql, $fail_message)
    {
        $result = $this->doQuery($sql, $fail_message);
        $rows_matched = $this->getRowsMatchedForUpdate();
        if ($rows_matched == 0) {
            throw new Exception($fail_message, self::NO_MATCHING_ROW_FOUND);
        }
        return $rows_matched;
    }

    public function getRowsMatchedForUpdate()
    {
        $info = mysql_info($this->conn);
        preg_match('/Rows matched: (\d+)/', $info, $regex_match);
        return $regex_match[1];
    }

    public function insertUser($user)
    {
        $sql = "INSERT INTO 0_pwe_user (oid) VALUES (%d);";
        $query = sprintf($sql, $user->oid);
        $fail_message = "Could not insert user (oid={$user->oid}, " .
                        "username={$user->username}).";
        $this->doQuery($query, $fail_message);
        $this->updateUser($user);
    }
}
