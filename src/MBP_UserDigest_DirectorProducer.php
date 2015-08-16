<?php
/**
 * MBP_UserDigestProducer - Static class of methods to create "snapshot" of mb-user collection. Generate
 * queue entries in userDigestProducerQueue to manage paged calls to mb-user collection.
 */
namespace DoSomething\MBP_UserDigest;

use DoSomething\StatHat\Client as StatHat;
use DoSomething\MB_Toolbox\MB_Toolbox;
// Adjust path when BaseProducer is moved into MB_Toolbox library
use DoSomething\MBP_UserDigest\MBP_UserDigest_BaseProducer;

/*
 * MBC_UserAPICampaignActivity.class.in: Used to process the transactionalQueue
 * entries that match the campaign.*.* binding.
 */
class MBP_UserDigest_DirectorProducer extends MBP_UserDigest_BaseProducer
{

  /**
   * 
   *
   * @var array
   */
  protected $user;

  /**
   * queueUser() : Produce user message for fanout exchange.
   */
  public function queueUser() {

    $payload = $this->generatePayload();
    $routingzKey = '';
    parent::produceMessage($payload, $routingKey);
  }

  /**
   * generatePayload: Format message payload
   */
  protected function generatePayload() {

    $payload = parent::generatePayload();

    $payload['email'] = $this->user['email'];
    $payload['first_name'] = '';
    $payload['campaigns'] = '';
    $payload['drupal_uid'] = '';

    return $payload;
  }

  /**
   * generatePayload: Format message payload
   */
  public function setUser($user) {

    $this->user['email'] = '';
    $this->user['first_name'] = '';
    $this->user['campaigns'] = '';
    $this->user['drupal_uid'] = '';
  }

}
