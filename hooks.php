<?php

require_once(__DIR__ . '/autoload.php');

use madman\Password\MySQLStore;
use madman\Password\Authenticator;

define('SS_PASSWORD', 181 << 8);

class hooks_password extends hooks
{
    var $module_name = 'password';
    public $lastLoginAttempt = null;

    function install_options($app)
    {
        global $path_to_root;

        switch ($app->id) {
            case 'system':
                $app->add_lapp_function(
                    0,
                    _('Password Security Setup'),
                    $path_to_root . '/modules/password/password_setup.php',
                    'SA_PASSWORDSETUP'
                );
                break;
        }
    }

    function install_access()
    {
        $security_sections[SS_PASSWORD] = _("Password security");
        $security_areas['SA_PASSWORDSETUP'] = array(
            SS_PASSWORD|1,
            _("Configure password security")
        );
        return array($security_areas, $security_sections);
    }

    function activate_extension($company, $check_only = true) {
        $store = $this->get_datastore();
        if (!$check_only)
        {
            try {
                $store->addExtensionTables();
            } catch (Exception $e) {
                return false;
            }
        }
        return true;
    }

    private function get_datastore()
    {
        global $db;

        $company = $_SESSION['wa_current_user']->company;
        $store = new MySQLStore($company);
        $store->setConnection($db);

        return $store;
    }

    function deactivate_extension($company, $check_only = true) {
        $store = $this->get_datastore();
        if (!$check_only)
        {
            try {
                $store->removeExtensionTables();
            } catch (Exception $e) {
                return false;
            }
        }
        return true;
    }

    function authenticate($username, $password)
    {
        $store = $this->get_datastore();
        $auth = new Authenticator($store);
        if (is_array($password)) {
            $curr_password = $password[0];
            $new_password = $password[1];
            $is_temporary = (
                array_key_exists(2, $password) ? $password[2]: false
            );
        } else {
            $curr_password = $password;
            $new_password = null;
            $is_temporary = false;
        }
        $loginAttempt = $auth->login(
            $username,
            $curr_password,
            $new_password,
            $is_temporary
        );
        $this->lastLoginAttempt = $loginAttempt;

        return !$loginAttempt->has_failed;
    }

    function update_password(
        $username,
        $curr_password,
        $new_password
    ) {
        $store = $this->get_datastore();
        $auth = new Authenticator($store);
        $result = $auth->updatePassword(
            $username,
            $curr_password,
            $new_password
        );
        $this->lastLoginAttempt = $result;

        return !$result->has_failed;
    }

    function reset_password($username, $temp_password)
    {
        $store = $this->get_datastore();
        $auth = new Authenticator($store);
        $result = $auth->resetPassword($username, $temp_password);
        $this->lastLoginAttempt = $result;

        return !$result->has_failed;
    }
}
