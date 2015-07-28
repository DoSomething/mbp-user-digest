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
use DoSomething\MBP_UserDigest\MBP_UserDigestDirector;

use DoSomething\StatHat\Client as StatHat;
use DoSomething\MB_Toolbox\MB_Toolbox;

require_once __DIR__ . '/mbp-user-digest_director.config.inc';

// Create objects for injection into MBC_ImageProcessor
$mb = new MessageBroker($credentials, $config);
$sh = new StatHat([
  'ez_key' => $settings['stathat_ez_key'],
  'debug' => $settings['stathat_disable_tracking']
]);
$tb = new MB_Toolbox($settings);


echo '------- mbp-user-digest START: ' . date('D M j G:i:s T Y') . ' -------', PHP_EOL;

// Kick off
$mbpUserDigestDirector = new MBP_UserDigestDirector($mb, $sh, $tb, $settings);
$mbpUserDigestDirector->producer();

echo '------- mbp-user-digest END: ' . date('D M j G:i:s T Y') . ' -------', PHP_EOL;