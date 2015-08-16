<?php
/**
 * mbp-user-digest_director.php
 *
 * A producer to create entries in the userDigestQueue via the directUserDigest
 * exchange. The mbc-user-digest application will consume the entries in the
 * queue.
 */

date_default_timezone_set('America/New_York');
define('CONFIG_PATH',  __DIR__ . '/messagebroker-config');

// Load up the Composer autoload magic
require_once __DIR__ . '/vendor/autoload.php';
use DoSomething\MBP_UserDigest\MBP_UserDigest_DirectorConsumer;

require_once __DIR__ . '/mbp-user-digest_director.config.inc';


echo '------- mbp-user-digest_director START: ' . date('D M j G:i:s T Y') . ' -------', PHP_EOL;

// Kick off
$mb = $mbConfig->getProperty('messageBroker');
$mb->consumeMessage(array(new MBP_UserDigest_DirectorConsumer(), 'consumeDigestProducerQueue'));

echo '------- mbp-user-digest_director END: ' . date('D M j G:i:s T Y') . ' -------', PHP_EOL;
