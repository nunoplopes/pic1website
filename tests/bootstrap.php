<?php
// Bootstrap file for PHPUnit tests

use Tests\Mocks\MockGitHubClient;

// Load Composer autoloader
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Load project includes
require_once dirname(__DIR__) . '/include.php';

// Load validation and database functions required by entity tests
require_once dirname(__DIR__) . '/validation.php';
require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/video.php';

// Define test environment
define('TEST_MODE', true);

// Define helper functions from auth.php needed for User tests
function auth_user_at_least(User $user, $role) {
    return $user->role <= $role;
}

function get_all_roles($include_sudo) {
    $roles = [
        1 => 'Professor',    // ROLE_PROF
        2 => 'TA',           // ROLE_TA
        3 => 'Student'       // ROLE_STUDENT
    ];
    if ($include_sudo)
        $roles[0] = 'Sudo';  // ROLE_SUDO
    return $roles;
}

// Define helper functions from fenix.php needed for User tests
function get_current_year() {
    $year = (int)date('Y');
    return date('n') >= 9 ? $year : ($year-1);  // MONTH_NEW_YEAR = 9
}

// Define helper functions needed by entity tests
function format_big_number($n) {
    if ($n < 1000)
        return $n;
    if ($n < 1000000)
        return round($n / 1000) . "\u{202F}k";
    return round($n / 1000000, 1) . "\u{202F}M";
}

function github_parse_date($date) {
    if ($ret = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:sp', $date))
        return $ret;
    throw new Exception("Couldn't parse github date: $date");
}

// Set reusable fake clients as globals for entity code to access.
$GLOBALS['github_client_cached'] = new MockGitHubClient();
$GLOBALS['github_client'] = new MockGitHubClient();

// Suppress output from config loading if needed
error_reporting(E_ALL);
ini_set('display_errors', '1');
