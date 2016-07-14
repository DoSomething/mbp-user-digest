<?php
/**
 * mbp-user-digest_director.php
 *
 * A producer to create entries in the userDigestQueue via the directUserDigest
 * exchange. The mbc-user-digest application will consume the entries in the
 * queue.
 */
    
use DoSomething\MBP_UserDigest\MBP_UserDigest_DirectorConsumer;

date_default_timezone_set('America/New_York');
define('CONFIG_PATH',  __DIR__ . '/messagebroker-config');
    
// Manage $enviroment setting
if (isset($_GET['environment']) && allowedEnviroment($_GET['environment'])) {
    define('ENVIRONMENT', $_GET['environment']);
} elseif (isset($argv[1])&& allowedEnviroment($argv[1])) {
    define('ENVIRONMENT', $argv[1]);
} elseif ($env = loadConfig()) {
    echo 'environment.php exists, ENVIRONMENT defined as: ' . ENVIRONMENT, PHP_EOL;
} elseif (allowedEnviroment('local')) {
    define('ENVIRONMENT', 'local');
}

// Load up the Composer autoload magic
require_once __DIR__ . '/vendor/autoload.php';

// Load configuration settings specific to this application
require_once __DIR__ . '/mbp-user-digest_director.config.inc';

// Kick off
echo '------- mbp-user-digest_director START: ' . date('j D M Y G:i:s T') . ' -------', PHP_EOL;
$mb = $mbConfig->getProperty('messageBroker');
$mb->consumeMessage(array(new MBP_UserDigest_DirectorConsumer(), 'consumeDigestProducerQueue'));
echo '------- mbp-user-digest_director END: ' . date('j D M Y G:i:s T') . ' -------', PHP_EOL;
    
/**
 * Test if enviroment setting is a supported value.
 *
 * @param string $setting Requested enviroment setting.
 *
 * @return boolean
 */
function allowedEnviroment($setting)
{
    
    $allowedEnviroments = [
        'local',
        'dev',
        'prod'
    ];
    
    if (in_array($setting, $allowedEnviroments)) {
        return true;
    }
    
    return false;
}

/**
 * Gather configuration settings for current application enviroment.
 *
 * @return boolean
 */
function loadConfig() {
    
    // Check that environment config file exists
    if (!file_exists ('./environment.php')) {
        return false;
    }
    include('./environment.php');
    
    return true;
}
