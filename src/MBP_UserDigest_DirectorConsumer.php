<?php
namespace DoSomething\MBP_UserDigest;

use DoSomething\StatHat\Client as StatHat;
use DoSomething\MB_Toolbox\MB_Toolbox_cURL;
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

  /*
   * CURL related functionality used by many Message Broker applications.
   * @var object
   */
  private $mbToolboxcURL;

  /**
   * Setup MBP_UserDigest_DirectorConsumer basic functionality.
   */
  public function __construct() {

    parent::__construct();
    $this->messageBroker_fanoutUserDigest = $this->mbConfig->getProperty('messageBroker_fanoutUserDigest');
    $this->mbToolboxcURL = $this->mbConfig->getProperty('mbToolboxcURL');
  }

  /**
   * Initial method triggered by blocked call in mbc-registration-mobile.php. The $payload is the
   * contents of the message being processed from the queue.
   *
   * @param array $payload
   *   The contents of the queue entry
   */
  public function consumeDigestProducerQueue($payload) {

    echo '- mbc-user-digest_director - MBP_UserDigest_DirectorConsumer->consumeDigestProducerQueue() START', PHP_EOL;

    parent::consumeQueue($payload);

    // Watch for specific user messages
    if (strpos($this->message['url'], '/user?email=') !== FALSE) {

      if ($this->canProcess()) {
        $this->setter($this->message);
        $this->processUser();
      }

    }
    else {
      if ($this->canProcess()) {
        $this->setter($this->message);
        $this->process();
      }
    }

    echo '- mbc-user-digest_director - MBP_UserDigest_DirectorConsumer->consumeDigestProducerQueue() END', PHP_EOL;
  }

  /**
   * Sets values for processing based on contents of message from consumed queue.
   *
   * @param array $message
   *  The payload of the message being processed.
   */
  protected function setter($message) {

    // Remove encoding to support email addresses which get encoded, example: "@" encodes to %40
    $this->message['url'] = urldecode($message['url']);
    $message['url'] = urldecode($message['url']);

    $mbUserAPIConfig = $this->mbConfig->getProperty('mb_user_api_config');
    $domain = $mbUserAPIConfig['host'];
    if ($mbUserAPIConfig['port'] > 0 && is_numeric($mbUserAPIConfig['port'])) {
      $domain .= ':' . (int) $mbUserAPIConfig['port'];
    }
    $this->message['url'] = $domain . $message['url'];
    echo 'MBP_UserDigest_DirectorConsumer - setter() url: ' . $this->message['url'], PHP_EOL . PHP_EOL;
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
      echo 'mbc-user-digest_director canProcess() - message older than a week. Removing from queue.', PHP_EOL;
      $this->messageBroker->sendAck($this->message['payload']);
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
   * Gather next page of user documents from mb-user-api based on cursor paging.
   *
   * @return array
   *  array $users: user documents
   *  array $meta: response header values that are used to naviagate through the pages in cusor based
   *  listing of user documents.
   */
  protected function processPageRequest() {

    $result = $this->mbToolboxcURL->curlGET($this->message['url']);
    if ($result[1] == 200) {
      $users = $result[0]->results;
      $meta = $result[0]->meta;

      // Remove message from queue
      $this->messageBroker->sendAck($this->message['payload']);
    }
    else {
      echo 'Failed to GET results from: ' . $this->message['url'] . ' Status Code: ' . $result[1], PHP_EOL;
    }

    return array($users, $meta);
  }

  /**
   * produceNextPageRequest: Based on response from mb-users-api /users?type=cursor generate and
   * publish message in digestProducerQueue.
   *
   * @param array $meta
   *   Meta values sent in header of response from /users. Used to naviage through pages in cursor
   *   based paged results of requeues to /users?type=cursor.
   * 
   */
  protected function produceNextPageRequest($meta) {

    // @todo: How to use MBP_UserDigest_BaseProducer->generatePayload()
    $message = array(
      'requested' => date('c'),
      'startTime' => date('c', $meta->cursor_start_time),
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

      // Close connection to allow other consumers to get in line for message.
      $channel = $this->messageBroker->connection->channel();
      $channel->close();
    }
    else {
      echo 'Last page in cursor request, ending cursur requests.', PHP_EOL;
    }

  }

  /**
   *
   */
  protected function processUsers($users) {

    $mbpUserDigest_DirectorProducer = new MBP_UserDigest_DirectorProducer('messageBroker_fanoutUserDigest');

    foreach($users as $user) {
      $ok = $mbpUserDigest_DirectorProducer->setUser($user);
      if ($ok) {
        $mbpUserDigest_DirectorProducer->queueUser($user);
      }
      else {
        echo 'MBP_UserDigest_DirectorConsumer->processUsers() rejected user removed from queue.', PHP_EOL;
      }
    }
  }

  /**
   *
   */
  protected function processUser() {

    $result = $this->mbToolboxcURL->curlGET($this->message['url']);
    if ($result[1] == 200) {
      $user = $result[0];

      // Remove message from queue
      $this->messageBroker->sendAck($this->message['payload']);
    }
    else {
      echo 'Failed to GET results from: ' . $this->message['url'], PHP_EOL;
      exit;
    }

    $mbpUserDigest_DirectorProducer = new MBP_UserDigest_DirectorProducer('messageBroker_fanoutUserDigest');

    $ok = $mbpUserDigest_DirectorProducer->setUser($user);
    if ($ok) {
      $mbpUserDigest_DirectorProducer->queueUser($user);
    }
    else {
      echo 'MBP_UserDigest_DirectorConsumer->processUsers() rejected user removed from queue.', PHP_EOL;
    }
  }
}
