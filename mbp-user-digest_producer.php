<?php
/**
 * mbp-user-digest_producer.php
 *
 * A producer to create entries in the userDigestQueue via the directUserDigest
 * exchange. The mbc-user-digest application will consume the entries in the
 * queue.
 */

// The number of documents to request in each page request
define('PAGE_SIZE', 5);

date_default_timezone_set('America/New_York');
define('CONFIG_PATH',  __DIR__ . '/messagebroker-config');

// Load up the Composer autoload magic
require_once __DIR__ . '/vendor/autoload.php';
use DoSomething\MBP_UserDigest\MBP_UserDigest_Producer;

// Load configuration settings specific to this application
require_once __DIR__ . '/mbp-user-digest_producer.config.inc';


echo '------- mbp-user-digest_producer START: ' . date('D M j G:i:s T Y') . ' -------', PHP_EOL;

// Kick off
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
