<?php
/**
 * mbp-user-digest_producer.php
 *
 * A producer to create entries in the userDigestQueue via the directUserDigest
 * exchange. The mbc-user-digest application will consume the entries in the
 * queue.
 */

// The number of documents to request in each page request
define('PAGE_SIZE', 5000);

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
$targetCSV = NULL;
$mbpUserDigestProducer = new MBP_UserDigest_Producer();
if ((isset($_GET['targetUsers']) && $_GET['targetUsers'] == 'testUsers') || (isset($argv[1]) && $argv[1] == 'testUsers')) {
  $targetUsers = $mbpUserDigestProducer::produceTestUserGroupDigestQueue();
}
elseif (isset($_GET['targetUsers'])) {
  $targetUsers = $mbpUserDigestProducer::produceUserGroupFromCSV($_GET['targetUsers']);
}
elseif (isset($argv[1])) {
  $targetUsers = $mbpUserDigestProducer::produceUserGroupFromCSV($argv[1]);
}

if ($targetUsers != NULL) {
  $mbpUserDigestProducer->setUsers($targetUsers);
}

// Create initial message in digestProducerQueue to start digest
// generation process.
$mbpUserDigestProducer->kickoff(PAGE_SIZE);

echo '------- mbp-user-digest_producer END: ' . date('D M j G:i:s T Y') . ' -------', PHP_EOL;