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
class MBP_UserDigest_DirectorConsumer extends MB_Toolbox_BaseConsumer
{
  // The amount of time before a message is expired (in seconds)
  const EXPIRED = 604800; // One week

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

    if ($this->canProcess()) {
      $this->setter($this->message);
      $this->process();
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

    // adjust $this->message['url'] based on enviroment: local, dev vs production. Point o local or remote
    // mb-users-api including tunnel settings for dev.
    $envroment = 'development';

    // production
    // http://10.241.0.20:4722/users?type=cursor&size=5000
    if ($envroment == 'production') {

      $mbUserAPIConfig = $this->mbConfig->getProperty('mb_user_api_config');
      $url = $mbUserAPIConfig['host'];
      $port = $mbUserAPIConfig['port'];
      if ($port != 0 && is_numeric($port)) {
        $url .= ':' . (int) $port;
      }
      $this->message['url'] = $url. $message['url'];

    }
    // dev
    // 127.0.0.1:4723
    elseif ($envroment == 'development') {

      $url = 'http://127.0.0.1';
      $port = '4723';
      $this->message['url'] = $url . ':' . $port . $message['url'];

    }
    // local
    // localhost:4722
    else {

    }

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

    // Request page from mb-users-api /users
    list($users, $meta) = $this->processPageRequest();

    $this->produceNextPageRequest($meta);
    $this->processUsers($users);
    
  }

  /**
   * 
   */
  protected function processPageRequest() {

    $mbToolboxcURL = $this->mbConfig->getProperty('mbToolboxcURL');
    $result = $mbToolboxcURL->curlGET($this->message['url']);

    if ($result[1] == 200) {
      $users = $result[0]->results;
      $meta = $result[0]->meta;
    }
    else {
      echo 'Failed to GET results from: ' . $this->message['url'], PHP_EOL;
      exit;
    }

    return array($users, $meta);
  }

  /**
   * produceNextPageRequest: Based on response from mb-users-api /users?type=cursor generate and
   * publish message in digestProducerQueue.
   * 
   */
  protected function produceNextPageRequest($meta) {

    // @todo: How to use MBP_UserDigest_BaseProducer->generatePayload()
    $message = array(
      'requested' => date('c'),
      'startTime' => '',
//      'startTime' => $meta->cursor_request_start_time,
    );

    if ($meta->direction == 1 && isset($meta->next_page_url)) {
      $message['url'] = $meta->next_page_url;
    }
    elseif ($meta->direction == -1 && isset($meta->previous_page_url)) {
      $message['url'] = $meta->previous_page_url;
    }

    if (isset($message['url'])) {
      $routingKey = 'digestProducer';

      // @todo: How to use MBP_UserDigest_BaseProducer->produceMessage()
      $payload = serialize($message);
      $this->messageBroker->publish($payload, $routingKey);
    }
    else {
      echo 'Last page in cursor request, ending process.', PHP_EOL;
      exit;
    }

  }

  /**
   * 
   */
  protected function processUsers($users) {

    // @todo: support option to collect specific user documents for testing.

    $mbpUserDigest_DirectorProducer = new MBP_UserDigest_DirectorProducer('messageBroker_fanoutUserDigest');

    foreach($users as $user) {
      $ok = $mbpUserDigest_DirectorProducer->setUser($user);
      if ($ok) {
        $mbpUserDigest_DirectorProducer->queueUser($user);
      }
      else {
        // remove message from queue
        // Disconnect channel
      }
    }

  }

}