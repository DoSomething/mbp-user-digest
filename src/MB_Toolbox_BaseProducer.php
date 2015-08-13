<?php
/**
 * A template for all producer classes within the Message Broker system.
 */
// Adjust to DoSomething\MBP_UserDigest when moved to MB_Toolbox
namespace DoSomething\MBP_UserDigest;

use DoSomething\StatHat\Client as StatHat;
use DoSomething\MB_Toolbox\MB_Toolbox;

/*
 * MBC_UserAPICampaignActivity.class.in: Used to process the transactionalQueue
 * entries that match the campaign.*.* binding.
 */
abstract class MB_Toolbox_BaseProducer
{

  /**
   * Message Broker connection to RabbitMQ
   *
   * @var object
   */
  protected $messageBroker;

  /**
   * StatHat object for logging of activity
   *
   * @var object
   */
  protected $statHat;

  /**
   * Message Broker Toolbox cURL - collection of utility cURL methods used
   * by many of the Message Broker producer and consumer applications.
   *
   * @var object
   */
  protected $toolboxCURL;

  /**
   * Constructor for MB_Toolbox_BaseConsumer - all consumer applications should extend this base class.
   */
  public function __construct() {

    $this->mbConfig = MB_Configuration::getInstance();
    $this->messageBroker = $this->mbConfig->getProperty('messageBroker');
    $this->statHat = $this->mbConfig->getProperty('statHat');
    $this->toolboxCURL = $this->mbConfig->getProperty('mbToolboxCURL');
  }

  /**
   * generatePayload: Basic format of message payload
   */
  protected function generatePayload() {

    // @todo: Use common message formatted for all producers and consumers in Message Broker system.
    // Ensures consistent message structure.
    $payload = array(
      'requested' => date('c'),
      'startTime' => $this->startTime,
    );
    return $payload;
  }

  /**
   * Initial method triggered by blocked call in base mbc-??-??.php file. The $payload is the
   * contents of the message being processed from the queue.
   *
   * @param string $message
   *   The contents of a message to submit to the queue entry
   * @param string $routingKey
   *   The key to be applied to the exchange binding keys to direct the message between the bound queues.
   */
  protected function produceMessage($message, $routingKey) {

    $payload = serialize($message);
    $this->messageBroker->publishMessage($payload, $routingKey);
  }
  
}