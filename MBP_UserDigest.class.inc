<?php

use DoSomething\MBStatTracker\StatHat;

class MBP_UserDigest
{
  
  const DEFAULT_FIRST_NAME = "Doer";
  const PAGE_SIZE = 500;

  /**
   * Message Broker connection to RabbitMQ
   */
  private $messageBroker;

  /**
   * Setting from external services - Mailchimp.
   *
   * @var array
   */
  private $statHat;

  /**
   * Constructor - setup parameters to be accessed by class methods
   *
   * @param array $credentials
   *   Connection credentials for RabbotMQ
   *
   * @param array $config
   *   RabbitMQ related configuration environment settings.
   *
   * @param array $settings
   *  Additional configuration environment settings
   */
  public function __construct($credentials, $config, $settings) {
    $this->messageBroker = new MessageBroker($credentials, $config);

    $this->statHat = new StatHat($settings['stathat_ez_key'], 'mbp-user-digest:');
    $this->statHat->setIsProduction(FALSE);
  }
  
  /**
   * Collect users (email) for digest message batch
   */
  private function produceUserDigestQueue() {

    echo '------- mbp-user-digest->produceUserDigestQueue START: ' . date('D M j G:i:s T Y') . ' -------', "\n";

    $pageCount = 1;
    do {
  
      $userApiUrl = getenv('DS_USER_API_HOST') . ':' . getenv('DS_USER_API_PORT') . '/users?page=' . $pageCount . '&pageSize=' . $self::PAGE_SIZE;
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $userApiUrl);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
      $jsonResult = curl_exec($ch);
      curl_close($ch);
      $returned = json_decode($jsonResult);
      $results = $returned->results;

      foreach($results as $resultCount => $result) {
        if (!isset($result->first_name) ||  $result->first_name == '') {
          $result->first_name = $self::DEFAULT_FIRST_NAME;
        }
        $payload = array(
          'email' => $result->email,
          'drupal_uid' => $result->drupal_uid,
          'campaigns' => $result->campaigns,
          'merge_vars' => array(
            'FNAME' => ucwords(strtolower($result->first_name)),
          )
        );
        $payload = serialize($payload);
        $this->messageBroker->publishMessage($payload);
      }
      $pageCount++;

    } while ($resultCount == $self::PAGE_SIZE);

    $this->statHat->addStatName('produceUserDigestQueue');
    $this->statHat->reportCount($resultCount);

    echo '------- mbp-user-digest->produceUserDigestQueue END: ' . $resultCount . ' queued - ' . date('D M j G:i:s T Y') . ' -------', "\n";
  }
  
}
