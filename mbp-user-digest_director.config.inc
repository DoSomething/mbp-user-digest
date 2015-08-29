<?php
/**
 * Message Broker configuration settings for mbp-user-digest_director
 */

use DoSomething\MB_Toolbox\MB_Configuration;
use DoSomething\StatHat\Client as StatHat;
use DoSomething\MB_Toolbox\MB_Toolbox;
use DoSomething\MB_Toolbox\MB_Toolbox_cURL;

// Load configuration settings common to the Message Broker system
// symlinks in the project directory point to the actual location of the files
require_once __DIR__ . '/messagebroker-config/mb-secure-config.inc';

$mbConfig = MB_Configuration::getInstance();

// Settings
$mbConfig->setProperty('rabbit_credentials_development', array(
  'host' =>  getenv("RABBITMQ_HOST_DEV"),
  'port' => getenv("RABBITMQ_PORT_DEV"),
  'username' => getenv("RABBITMQ_USERNAME_DEV"),
  'password' => getenv("RABBITMQ_PASSWORD_DEV"),
  'vhost' => getenv("RABBITMQ_VHOST_DEV"),
));
$mbConfig->setProperty('rabbit_credentials_test', array(
  'host' =>  getenv("RABBITMQ_HOST_TEST"),
  'port' => getenv("RABBITMQ_PORT_TEST"),
  'username' => getenv("RABBITMQ_USERNAME_TEST"),
  'password' => getenv("RABBITMQ_PASSWORD_TEST"),
  'vhost' => getenv("RABBITMQ_VHOST_TEST"),
));
$mbConfig->setProperty('rabbit_credentials_production', array(
  'host' =>  getenv("RABBITMQ_HOST"),
  'port' => getenv("RABBITMQ_PORT"),
  'username' => getenv("RABBITMQ_USERNAME"),
  'password' => getenv("RABBITMQ_PASSWORD"),
  'vhost' => getenv("RABBITMQ_VHOST"),
));

$mbConfig->setProperty('ds_drupal_api_config_production', array(
  'host' => getenv("DS_DRUPAL_API_HOST"),
  'port' => getenv('DS_DRUPAL_API_PORT')
));

$mbConfig->setProperty('mb_user_api_config_development', array(
  'host' => getenv("MB_USER_API_HOST_DEV"),
  'port' => getenv('MB_USER_API_PORT_DEV')
));
$mbConfig->setProperty('mb_user_api_config_production', array(
  'host' => getenv("MB_USER_API_HOST"),
  'port' => getenv('MB_USER_API_PORT')
));

// local, dev vs production.
if (isset($_ENV["APP_ENV"])) {
  $enviroment = $_ENV["APP_ENV"];
}
else {
  $enviroment = 'development';
}

$mbRabbitConfig = $mbConfig->constructRabbitConfig('directUserDigest', array('digestProducerQueue'));
$mbConfig->setProperty('messageBroker_config', $mbRabbitConfig);

// Create untility objects for access anywhere within the application as a single instance
$rabbitCredentials = $mbConfig->getProperty('rabbit_credentials_' . $enviroment);
$messageBrokerConfig = $mbConfig->getProperty('messageBroker_config');
$mbConfig->setProperty('messageBroker', new MessageBroker($rabbitCredentials, $messageBrokerConfig));

$mbRabbitConfig = $mbConfig->constructRabbitConfig('fanoutUserDigest', array('digestUserQueue', 'digestUnsubscribeQueue', 'digestCampaignsQueue'));
$mbConfig->setProperty('messageBroker_config-fanoutUserDigestExchange', $mbRabbitConfig);

// Create untility objects for access anywhere within the application as a single instance
$messageBrokerConfig = $mbConfig->getProperty('messageBroker_config-fanoutUserDigestExchange');
$mbConfig->setProperty('messageBroker_fanoutUserDigest', new MessageBroker($rabbitCredentials, $messageBrokerConfig));

$mbConfig->setProperty('statHat_config_development', array(
  'ez_key' => getenv("STATHAT_EZKEY"),
  'debug' => 1
));
$mbConfig->setProperty('statHat_config_test', array(
  'ez_key' => getenv("STATHAT_EZKEY"),
  'debug' => 1
));
$mbConfig->setProperty('statHat_config_production', array(
  'ez_key' => getenv("STATHAT_EZKEY"),
  'debug' => 0
));
$statHatConfig = $mbConfig->getProperty('statHat_config_' . $envroment);
$mbConfig->setProperty('statHat', new StatHat([
  'ez_key' => $statHatConfig['ez_key'],
  'debug' => $statHatConfig['debug']
]));

$mbConfig->setProperty('mbToolbox', new MB_Toolbox());
$mbConfig->setProperty('mbToolboxcURL', new MB_Toolbox_cURL());

