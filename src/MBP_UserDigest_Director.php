<?php
namespace DoSomething\MBP_UserDigest;

use DoSomething\StatHat\Client as StatHat;
use DoSomething\MB_Toolbox\MB_Toolbox;
use DoSomething\MB_Toolbox\MB_Toolbox_BaseConsumer;

/**
 * MBP_UserDigest_Director - Consumer application as part of user digest producer process. Consumes entries
 * in the userDigestProducerQueue to make paged calls to mb-user-api for user documents to process as
 * precipitants of campaign digest email messages.
 *
 * Processing of the userDigestProducerQueue results in message entries in the userDigestQueue for
 * mbc-digest-email consumption.
 */
class MBP_UserDigest_Director extends MB_Toolbox_BaseConsumer
{
  // The amount of time before a message is expired (in seconds)
  const EXPIRED = 604800; // One week
  
  /**
   * User values to process as potential digest recipient.
   */
  protected $digestUser;
  
  /**
   * Initial method triggered by blocked call in mbc-registration-mobile.php. The $payload is the
   * contents of the message being processed from the queue.
   *
   * @param array $payload
   *   The contents of the queue entry
   */
  public function consumeDigestProducerQueue($payload) {

    echo '- mbc-user-digest_director - MBC_RegistrationMobile_Consumer->consumeDigestProducerQueue() START', PHP_EOL;

    parent::consumeQueue($payload);
    $this->setter($this->message);
    
    if (self::canProcess($this->digestUser)) {
      self::process();
    }
    
    echo '- mbc-user-digest_director - MBC_RegistrationMobile_Consumer->consumeDigestProducerQueue() END', PHP_EOL;
    
  }
  
  /**
   * Sets values for processing based on contents of message from consumed queue.
   *
   * @param array $message
   *  The payload of the message being processed.
   */
  protected function setter($message) {
    
    $this->digestUser = '';
    
  }
  
  /**
   * Method to determine if message can / should be processed. Conditions based on requirments of
   * mb-user-api /users?type=cursor endpoint and business logic for generating .
   *
   * @retun boolean
   */
  protected function canProcess() {
    
    if (!isset($this->message['url'])) {
      echo 'mbc-user-digest_director canProcess() - url not defined, skipping message.', PHP_EOL;
      return FALSE;
    }
    
    if (isset($this->message['startTime']) && strtotime($this->message['startTime']) < (time() - self::EXPIRED)) {
      echo 'mbc-user-digest_director canProcess() - message older than a week. Removing from queue..', PHP_EOL;
      $this->messageBroker->ack_back($this->message['original']);
      return FALSE;
    }

    return TRUE;
  }
  
  /**
   * Process message from consumed queue.
   */
  protected function process() {
    
    $mbpUserDigest_DirectorProducer = new MBP_UserDigest_DirectorProducer('messageBroker_fanoutUserDigest');
    $mbpUserDigest_DirectorProducer->setUser = $this->digestUser;
    
    $payload = $mbpUserDigest_DirectorProducer->generatePayload();
    $routingKey = '';
    $mbpUserDigest_DirectorProducer->produceMessage($payload, $routingKey);
  }
  
}