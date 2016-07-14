<?php
/**
 * mbp-user-digest_producer.php
 *
 * A producer to create entries in the userDigestQueue via the directUserDigest
 * exchange. The mbc-user-digest application will consume the entries in the
 * queue.
 */
    
use DoSomething\MBP_UserDigest\MBP_UserDigest_Producer;

date_default_timezone_set('America/New_York');
define('CONFIG_PATH',  __DIR__ . '/messagebroker-config');

// The number of documents to request in each page request
define('PAGE_SIZE', 5000);
    
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
require_once __DIR__ . '/mbp-user-digest_producer.config.inc';

// Kick off
echo '------- mbp-user-digest_producer START: ' . date('D M j G:i:s T Y') . ' -------', PHP_EOL;
    
// Collect targetCSV / targetUsers parameters
$targetUsers = NULL;
$mbpUserDigestProducer = new MBP_UserDigest_Producer();

if (isset($_GET['targetUsers']) || isset($_GET['targetUser']) || isset($argv[1])) {

  if (isset($_GET['targetUsers']) && strpos($_GET['targetUsers'], '.csv') !== FALSE) {
    $target = $mbpUserDigestProducer::produceUsersFromCSV($_GET['targetUsers']);
  }
  elseif (isset($argv[1]) && strpos($argv[1], '.csv') !== FALSE) {
    $target = $mbpUserDigestProducer::produceUsersFromCSV($argv[1]);
  }
  elseif (isset($_GET['targetUser']) && strpos($_GET['targetUser'], '@') !== FALSE) {
    $target = [$_GET['targetUser']];
  }
  elseif (isset($argv[1])) {
    $target = [$argv[1]];
  }
  $mbpUserDigestProducer->userKickoff($target);

}
else {

  // Create initial message in digestProducerQueue to start digest
  // generation process.
  $mbpUserDigestProducer->kickoff(PAGE_SIZE);

}

echo '------- mbp-user-digest_producer END: ' . date('D M j G:i:s T Y') . ' -------', PHP_EOL;
    
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
