<?php
/**
 * Message Broker configuration settings for mbp-user-digest_director
 */

use DoSomething\MB_Toolbox\MB_Configuration;
use DoSomething\StatHat\Client as StatHat;
use DoSomething\MB_Toolbox\MB_Toolbox;

// Load configuration settings common to the Message Broker system
// symlinks in the project directory point to the actual location of the files
require_once __DIR__ . '/messagebroker-config/mb-secure-config.inc';

// Settings
// Settings
$mbConfig->setProperty('rabbit_credentials', array(
  'host' =>  getenv("RABBITMQ_HOST"),
  'port' => getenv("RABBITMQ_PORT"),
  'username' => getenv("RABBITMQ_USERNAME"),
  'password' => getenv("RABBITMQ_PASSWORD"),
  'vhost' => getenv("RABBITMQ_VHOST"),
));
$mbRabbitConfig = $mbConfig->constructRabbitConfig('directUserDigestExchange', array('userDigestProducerQueue'));
$mbConfig->setProperty('rabbit_config', $mbRabbitConfig);

$mbConfig->setProperty('ds_drupal_api_config', array(
  'host' => getenv("DS_DRUPAL_API_HOST"),
  'port' => getenv('DS_DRUPAL_API_PORT')
]);
$mbConfig->setProperty('mb_user_api_config', array(
  'host' => getenv("MB_USER_API_HOST"),
  'port' => getenv('MB_USER_API_PORT')
));

// Create untility objects for access anywhere within the application as a single instance
$mbConfig->setProperty('messageBroker_config', $mbConfig->constructRabbitConfig('fanoutUserDigestExchange', array('digestUserQueue', 'digestUnsubscribeQueue', 'digestCampaignsQueue')));
$rabbitCredentials = $mbConfig->getProperty('rabbit_credentials');
$messageBrokerConfig = $mbConfig->getProperty('messageBroker_config');
$mbConfig->setProperty('messageBroker', new MessageBroker($rabbitCredentials, $messageBrokerConfig));

$mbConfig->setProperty('statHat', new StatHat([
  'ez_key' => getenv("STATHAT_EZKEY"),
  'debug' => getenv('DISABLE_STAT_TRACKING')
]));

$mbConfig->setProperty('mbToolbox', new MB_Toolbox());
