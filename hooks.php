<?php

require_once(__DIR__ . '/autoload.php');

use madman\Password\MySQLStore;
use madman\Password\Authenticator;

define('SS_PASSWORD', 181 << 8);

class hooks_password extends hooks
{
    var $module_name = 'password';

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
        global $db;
    
        $store = new MySQLStore($company);
        $store->setConnection($db);
        $store->startTransaction();
        try {
            $store->addExtensionTables();
        } catch (Exception $e) {
            $store->rollbackTransaction();
            return false;
        }
        $check_only ? $store->rollbackTransaction() : $store->commitTransaction();
        return true;
    }

    function authenticate($username, $password)
    {
        global $db;

        $company = $_SESSION['wa_current_user']->company;
        $store = new MySQLStore($company);
        $store->setConnection($db);
        $auth = new Authenticator($store);
        $loginAttempt = $auth->login($username, $password);

        return !$loginAttempt->has_failed;
    }
}
