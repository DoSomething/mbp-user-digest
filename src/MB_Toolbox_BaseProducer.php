<?php
/**
 * A template for all producer classes within the Message Broker system.
 */
namespace DoSomething\MB_Toolbox;

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
   * Message Broker Toolbox - collection of utility methods used by many of the
   * Message Broker producer and consumer applications.
   *
   * @var object
   */
  protected $toolbox;

  /**
   * Setting from external services - Mail chimp.
   *
   * @var array
   */
  protected $settings;

  /**
   * Constructor for MB_Toolbox_BaseConsumer - all consumer applications should extend this base class.
   *
   * @param object $messageBroker
   *   The Message Broker object used to interface the RabbitMQ server exchanges and related queues.
   * @param object $statHat
   *   Track application activity by triggering counters in StatHat service.
   * @param object $toolbox
   *   A collection of common tools for the Message Broker system.
   * @param array $settings
   *   Settings from internal and external services used by the application.
   */
  public function __construct($messageBroker, StatHat $statHat, MB_Toolbox $toolbox, $settings) {

    $this->messageBroker = $messageBroker;
    $this->statHat = $statHat;
    $this->toolbox = $toolbox;
    $this->settings = $settings;
  }

  /**
   * Initial method triggered by blocked call in base mbc-??-??.php file. The $payload is the
   * contents of the message being processed from the queue.
   *
   * @param string $payload
   *   The contents of a message to submit to the queue entry
   * @param string $routingKey
   *   The key to be applied to the exchange binding keys to direct the message between the bound queues.
   */
  public function produceQueue($payload, $routingKey) {

    $payload = json_encode($payload);
    $this->messageBroker->publishMessage($payload, $routingKey);
  }
  
}