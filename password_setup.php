<?php

require_once(__DIR__ . '/autoload.php');
use madman\Password\Config;
use madman\Password\MySQLStore;

$page_security = 'SA_PASSWORDSETUP';
$path_to_root = "../..";
require_once($path_to_root . "/includes/session.inc");
add_access_extensions();

page(_($help_context = "Password Security Setup"));

require_once($path_to_root . "/includes/ui.inc");

function ref_row_with_note($label, $value, $note = '')
{
    echo "<tr><td class='label'>$label</td>";
    ref_cells(null, $label, '', $value);
    echo "<td class='label'>$note</td>";
    echo "</tr>";
}

function save_password_config($store)
{
    $config = new Config();
    $key = $store->getConfigKeys();
    foreach ($key as $okey) {
        $config->$okey = $_POST[$okey];
    }
    $store->updateConfig($config);
}

$store = new MySQLStore();
$store->setConnection($db);

if (isset($_POST['set_pwe_config'])) {
    save_password_config($store);
    display_notification_centered(
        'Password security settings have been updated.'
    );
}

$pw_config = $store->getConfig();

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
    'login_fail_threshold_count',
    $pw_config->login_fail_threshold_count,
    "Account locks after consecutive password failures exceed this. Keep " .
    "this less than FA's \$login_max_attempts ($login_max_attempts)."
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
