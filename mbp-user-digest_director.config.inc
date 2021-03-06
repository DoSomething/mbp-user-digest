<?php
/**
 * Message Broker configuration settings for mbp-user-digest_director
 */

use DoSomething\MB_Toolbox\MB_Configuration;
use DoSomething\StatHat\Client as StatHat;
use DoSomething\MB_Toolbox\MB_Toolbox;
use DoSomething\MB_Toolbox\MB_Toolbox_cURL;
use DoSomething\MB_Toolbox\MB_RabbitMQManagementAPI;

// Load configuration settings common to the Message Broker system
// symlinks in the project directory point to the actual location of the files
require_once CONFIG_PATH . '/mb-secure-config/' . ENVIRONMENT . '/mb-secure-config.inc';

$mbConfig = MB_Configuration::getInstance();

// Settings
$mbConfig->setProperty('rabbit_credentials', array(
  'host' =>  getenv("RABBITMQ_HOST"),
  'port' => getenv("RABBITMQ_PORT"),
  'username' => getenv("RABBITMQ_USERNAME"),
  'password' => getenv("RABBITMQ_PASSWORD"),
  'vhost' => getenv("RABBITMQ_VHOST"),
));

$mbConfig->setProperty('ds_drupal_api_config', array(
  'host' => getenv("DS_DRUPAL_API_HOST"),
  'port' => getenv('DS_DRUPAL_API_PORT')
));
$mbConfig->setProperty('mb_user_api_config', array(
  'host' => getenv("MB_USER_API_HOST"),
  'port' => getenv('MB_USER_API_PORT')
));

$mbRabbitConfig = $mbConfig->constructRabbitConfig('directUserDigest', array('digestProducerQueue'));
$mbConfig->setProperty('messageBroker_config', $mbRabbitConfig);

// Create untility objects for access anywhere within the application as a single instance
$rabbitCredentials = $mbConfig->getProperty('rabbit_credentials');
$messageBrokerConfig = $mbConfig->getProperty('messageBroker_config');
$mbConfig->setProperty('messageBroker', new MessageBroker($rabbitCredentials, $messageBrokerConfig));

$mbRabbitConfig = $mbConfig->constructRabbitConfig('fanoutUserDigest', array('digestUserQueue', 'digestUnsubscribeQueue', 'digestCampaignsQueue'));
$mbConfig->setProperty('messageBroker_config-fanoutUserDigestExchange', $mbRabbitConfig);

// Create untility objects for access anywhere within the application as a single instance
$messageBrokerConfig = $mbConfig->getProperty('messageBroker_config-fanoutUserDigestExchange');
$mbConfig->setProperty('messageBroker_fanoutUserDigest', new MessageBroker($rabbitCredentials, $messageBrokerConfig));

$mbConfig->setProperty('statHat', new StatHat([
  'ez_key' => getenv("STATHAT_EZKEY"),
  'debug' => getenv("DISABLE_STAT_TRACKING")
]));

$mbConfig->setProperty('mbToolbox', new MB_Toolbox());
$mbConfig->setProperty('mbToolboxcURL', new MB_Toolbox_cURL());

$mbConfig->setProperty('mbRabbitMQManagementAPI', new MB_RabbitMQManagementAPI([
  'domain' => getenv("MB_RABBITMQ_MANAGEMENT_API_HOST"),
  'port' => getenv('MB_RABBITMQ_MANAGEMENT_API_PORT'),
  'vhost' => getenv('MB_RABBITMQ_MANAGEMENT_API_VHOST'),
  'password' => getenv('MB_RABBITMQ_MANAGEMENT_API_PASSWORD')
]));
