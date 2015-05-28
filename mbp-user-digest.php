<?php
/**
 * mbp-user-digest.php
 *
 * A producer to create entries in the userDigestQueue via the directUserDigest
 * exchange. The mbc-user-digest application will consume the entries in the
 * queue.
 */

date_default_timezone_set('America/New_York');

// Load up the Composer autoload magic
require_once __DIR__ . '/vendor/autoload.php';

// Load configuration settings common to the Message Broker system
// symlinks in the project directory point to the actual location of the files
// Load configuration settings common to the Message Broker system
// symlinks in the project directory point to the actual location of the files
require_once __DIR__ . '/messagebroker-config/mb-secure-config.inc';
require_once __DIR__ . '/MBP_UserDigest.class.inc';

// Settings
$credentials = array(
  'host' =>  getenv("RABBITMQ_HOST"),
  'port' => getenv("RABBITMQ_PORT"),
  'username' => getenv("RABBITMQ_USERNAME"),
  'password' => getenv("RABBITMQ_PASSWORD"),
  'vhost' => getenv("RABBITMQ_VHOST"),
);
$settings = array(
  'stathat_ez_key' => getenv("STATHAT_EZKEY"),
  'use_stathat_tracking' => getenv('USE_STAT_TRACKING'),
  'ds_drupal_api_host' => getenv('DS_DRUPAL_API_HOST'),
  'ds_drupal_api_port' => getenv('DS_DRUPAL_API_PORT'),
  'ds_user_api_host' => getenv('DS_USER_API_HOST'),
  'ds_user_api_port' => getenv('DS_USER_API_PORT'),
);

$config = array();
$configSource = __DIR__ . '/messagebroker-config/mb_config.json';
$mb_config = new MB_Configuration($configSource, $this->settings);
$userDigestExchange = $mb_config->exchangeSettings('directUserDigestExchange');

$this->config = array(
  'exchange' => array(
    'name' => $userDigestExchange->name,
    'type' => $userDigestExchange->type,
    'passive' => $userDigestExchange->passive,
    'durable' => $userDigestExchange->durable,
    'auto_delete' => $userDigestExchange->auto_delete,
  ),
  'queue' => array(
    array(
      'passive' => $userDigestExchange->queues->userDigestQueue->passive,
      'durable' =>  $userDigestExchange->queues->userDigestQueue->durable,
      'exclusive' =>  $userDigestExchange->queues->userDigestQueue->exclusive,
      'auto_delete' =>  $userDigestExchange->queues->userDigestQueue->auto_delete,
    ),
  ),
);


echo '------- mbp-user-digest START: ' . date('D M j G:i:s T Y') . ' -------', PHP_EOL;

// Kick off
$mbpUserDigest = new MBP_UserDigest($credentials, $settings, $config);

$targetUsers = NULL;

// Collect targetCSV / targetUsers parameters
$targetCSV = NULL;
if ((isset($_GET['targetUsers']) && $_GET['targetUsers'] == 'testUsers') || (isset($argv[1]) && $argv[1] == 'testUsers')) {
  $targetUsers = $mbpUserDigest->produceTestUserGroupDigestQueue();
}
elseif (isset($_GET['targetUsers'])) {
  $targetUsers = $mbpUserDigest->produceUserGroupFromCSV($_GET['targetUsers']);
}
elseif (isset($argv[1])) {
  $targetUsers = $mbpUserDigest->produceUserGroupFromCSV($argv[1]);
}

// Gather digest message mailing list
$mbpUserDigest->produceUserDigestQueue($targetUsers);

echo '------- mbp-user-digest END: ' . date('D M j G:i:s T Y') . ' -------', PHP_EOL;
