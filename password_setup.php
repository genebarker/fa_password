<?php // phpcs:ignore

use madman\Password\Config;
use madman\Password\MySQLStore;

$page_security = 'SA_PASSWORDSETUP';
$path_to_root = "../..";

require_once(__DIR__ . '/autoload.php');
require_once($path_to_root . "/includes/ui.inc");

// Set page security
require_once($path_to_root . "/includes/session.inc");
add_access_extensions();
page(_($help_context = "Password Security Setup"));

function ref_row_with_note($label, $value, $note = '')
{
    echo "<tr><td class='label'>$label</td>";
    ref_cells(null, $label, '', $value);
    echo "<td class='label'>$note</td>";
    echo "</tr>";
}

function get_password_config_values($store)
{
    $config = new Config();
    $key = $store->getConfigKeys();
    foreach ($key as $okey) {
        $config->$okey = intval($_POST[$okey]);
    }
    return $config;
}

function render_password_setup_form($pw_config)
{
    global $login_max_attempts, $login_delay;

    start_form();
    start_outer_table(TABLESTYLE2);
    table_section(1);

    $th = ['Setting', 'Value', 'Description'];
    table_header($th);

    ref_row_with_note(
        'minimum_password_strength',
        $pw_config->minimum_password_strength,
        "Minimum zxcvbn password strength score (0-4): where 0 is too " .
        "guessable and 4 is very unguessable."
    );
    ref_row_with_note(
        'maximum_password_age_days',
        $pw_config->maximum_password_age_days,
        "Requires password change when its age in days exceeds this."
    );
    ref_row_with_note(
        'password_history_count',
        $pw_config->password_history_count,
        "New passwords must be different than those found in a user's " .
        "password history."
    );
    ref_row_with_note(
        'login_fail_threshold_count',
        $pw_config->login_fail_threshold_count,
        "Account locks after consecutive password failures exceed this. " .
        "Keep this less than FA's \$login_max_attempts " .
        "($login_max_attempts)."
    );
    ref_row_with_note(
        'login_fail_lock_minutes',
        $pw_config->login_fail_lock_minutes,
        "Login attempts are rejected until lock expires. Keep this value " .
        "less than FA's \$login_delay ($login_delay)."
    );

    end_outer_table(1);
    submit_center('set_pwe_config', _("Update"), true, '', 'default');
    end_form(2);
    end_page();
}

$store = new MySQLStore();
$store->setConnection($db);

if (isset($_POST['set_pwe_config'])) {
    $config = get_password_config_values($store);
    if ($config->hasValidValues()) {
        $store->updateConfig($config);
        display_notification(
            'Password security settings have been updated.'
        );
    } else {
        display_error(
            'Invalid password security settings. Each setting must be a ' .
            'positive integer.'
        );
    }
}

$pw_config = $store->getConfig();
render_password_setup_form($pw_config);
