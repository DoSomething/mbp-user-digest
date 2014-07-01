<?php
/**
 * mbp-user-digest.php
 *
 * A producer to create entries in the userDigestQueue via the directUserDigest
 * exchange. The mbc-user-digest application will consume the entries in the
 * queue.
 */

// Load up the Composer autoload magic
require_once __DIR__ . '/vendor/autoload.php';

// Load configuration settings common to the Message Broker system
// symlinks in the project directory point to the actual location of the files
require __DIR__ . '/mb-secure-config.inc';
require __DIR__ . '/mb-config.inc';

require __DIR__ . '/MBP_UserDigest.class.inc';

// Settings
$credentials = array(
  'host' =>  getenv("RABBITMQ_HOST"),
  'port' => getenv("RABBITMQ_PORT"),
  'username' => getenv("RABBITMQ_USERNAME"),
  'password' => getenv("RABBITMQ_PASSWORD"),
  'vhost' => getenv("RABBITMQ_VHOST"),
);
$config = array(
  'exchange' => array(
    'name' => getenv("MB_USER_DIGEST_EXCHANGE"),
    'type' => getenv("MB_USER_DIGEST_EXCHANGE_TYPE"),
    'passive' => getenv("MB_USER_DIGEST_EXCHANGE_PASSIVE"),
    'durable' => getenv("MB_USER_DIGEST_EXCHANGE_DURABLE"),
    'auto_delete' => getenv("MB_USER_DIGEST_EXCHANGE_AUTO_DELETE"),
  ),
  'queue' => array(
    array(
      'name' => getenv("MB_USER_DIGEST_QUEUE"),
      'passive' => getenv("MB_USER_DIGEST_QUEUE_PASSIVE"),
      'durable' => getenv("MB_USER_DIGEST_QUEUE_DURABLE"),
      'exclusive' => getenv("MB_USER_DIGEST_QUEUE_EXCLUSIVE"),
      'auto_delete' => getenv("MB_USER_DIGEST_QUEUE_AUTO_DELETE"),
      'bindingKey' => getenv("MB_USER_DIGEST_QUEUE_TOPIC_MB_TRANSACTIONAL_EXCHANGE_PATTERN"),
    ),
  ),
);
$settings = array(
  'stathat_ez_key' => getenv("STATHAT_EZKEY"),
);

echo '------- mbp-user-digest START: ' . date('D M j G:i:s T Y') . ' -------', "\n";

// Kick off
$mbpUserDigest = new MBP_UserDigest($credentials, $config, $settings);

// Create test entries
$testUsers = $mbpUserDigest->produceTestUserGroupDigestQueue();

// Gather digest message mailing list
$mbpUserDigest->produceUserDigestQueue($testUsers);

echo '------- mbp-user-digest END: ' . date('D M j G:i:s T Y') . ' -------', "\n";
