<?php

use DoSomething\MB_Toolbox\MB_Toolbox;
use DoSomething\MBStatTracker\StatHat;
use DoSomething\MB_Toolbox\MB_Configuration;

class MBP_UserDigest
{

  const DEFAULT_FIRST_NAME = "Doer";
  const PAGE_SIZE = 5000;

  /**
   * Setting from external services - Mailchimp.
   *
   * @var array
   */
  private $settings;

  /**
   * RabbitMQ configuration settings.
   *
   * @var array
   */
  private $config;

  /**
   * Message Broker connection to RabbitMQ
   */
  private $messageBroker;

  /**
   * Message Broker connection to RabbitMQ chasnnel
   */
  private $channel;

  /**
   * Collection of helper methods
   *
   * @var object
   */
  private $toolbox;

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
  public function __construct($credentials, $settings) {
    $this->credentials = $credentials;
    $this->settings = $settings;

    $this->toolbox = new MB_Toolbox($settings);

    $this->statHat = new StatHat($settings['stathat_ez_key'], 'mbp-user-digest:');
    $this->statHat->setIsProduction($this->settings['use_stathat_tracking']);
  }

  /**
   * Collect users (email) for digest message batch.
   *
   * @param array $testUsers
   *   An optional list of test users to user rather than using the /users results
   */
  public function produceUserDigestQueue($testUsers = array()) {

    echo PHP_EOL . '------- mbp-user-digest->produceUserDigestQueue START: ' . date('D j M Y G:i:s T') . ' -------', PHP_EOL . PHP_EOL;

    $testUserCount = count($testUsers);
    $deleteCount = 0;
    $mobileCount = 0;
    $publishCount = 0;
    $recordsProcessed = 0;
    $fetched = 0;
    $pageCount = 1;

    $config = array();
    $configSource = __DIR__ . '/messagebroker-config/mb_config.json';
    $mb_config = new MB_Configuration($configSource, $this->settings);
    $userDigestExchange = $mb_config->exchangeSettings('directUserDigestExchange');

    $this->config = array(
      'exchange' => array(
        'name' => $userDigestExchange->name,
        'type' => $userDigestExchange->type,
        'passive' => $userDigestExchange->passive,
        'durable' => $userDigestExchange->durable,
        'auto_delete' => $userDigestExchange->auto_delete,
      ),
      'queue' => array(
        array(
          'passive' => $userDigestExchange->queues->userDigestQueue->passive,
          'durable' =>  $userDigestExchange->queues->userDigestQueue->durable,
          'exclusive' =>  $userDigestExchange->queues->userDigestQueue->exclusive,
          'auto_delete' =>  $userDigestExchange->queues->userDigestQueue->auto_delete,
        ),
      ),
    );

    $curlUrl = $this->settings['ds_user_api_host'];
    $port = $this->settings['ds_user_api_port'];
    if ($port != 0 && is_numeric($port)) {
      $curlUrl .= ':' . (int) $port;
    }

    do {

      $userApiUrl = $curlUrl . '/users?page=' . $pageCount . '&pageSize=' . self::PAGE_SIZE . '&excludeNoCampaigns=1';
      echo '->produceUserDigestQueue curlGET: ' . $userApiUrl . ' - ' . date('D j M Y G:i:s T') . ' -------', PHP_EOL;
      $result = $this->toolbox->curlGET($userApiUrl);
      $fetched += count($result[0]->results);
      echo '->produceUserDigestQueue page: ' . $pageCount . ', results: ' . count($result[0]->results) . ', fetched: ' . $fetched . ' - ' . date('D j M Y G:i:s T') . ' -------', PHP_EOL;

      if ($result[1] == 200) {

        $queueName = 'digest-' . time();

        $this->config['queue'][0]['name'] = $queueName;
        $this->config['queue'][0]['bindingKey'] = $queueName;
        $this->config['routingKey'] = $queueName;
        $this->messageBroker = new MessageBroker($this->credentials, $this->config);
        $connection = $this->messageBroker->connection;
        $this->channel = $connection->channel();

        foreach($result[0]->results as $resultCount => $userApiResult) {

          // Only process non "@mobile" addresses
          if (isset($userApiResult->email) && (strpos($userApiResult->email, '@mobile') === FALSE || strlen(substr($userApiResult->email, strpos($userApiResult->email, '@mobile'))) > 7)) {

            // If specific test users are defined only create queue entries
            // for those users
            if ( (isset($testUsers) && $testUserCount > 0 && in_array($userApiResult->email, $testUsers)) ||
                 ($testUserCount == 0) ) {

              // Exclude users who have have been banned OR no preference has been set for banning
              if ( (!isset($userApiResult->subscriptions)) ||
                   (isset($userApiResult->subscriptions->banned) && $userApiResult->subscriptions->banned != TRUE) ) {

                // Exclude users who have unsubscribed from Digest messages OR no preference has been set of digest
                if ( (!isset($userApiResult->subscriptions->digest)) ||
                     (isset($userApiResult->subscriptions->digest) && $userApiResult->subscriptions->digest == TRUE) ) {

                  if (!isset($userApiResult->first_name) ||  $userApiResult->first_name == '') {
                    $userApiResult->first_name = self::DEFAULT_FIRST_NAME;
                    $this->statHat->addStatName('produceUserDigestQueue - DEFAULT_FIRST_NAME used');
                  }
                  $campaigns = array();
                  foreach ($userApiResult->campaigns as $campaignCount => $campaign) {
                    if (isset($campaign->nid)) {
                      $campaigns[$campaignCount] = array(
                        'nid' => $campaign->nid
                      );
                      if (isset($campaign->signup)) {
                        $campaigns[$campaignCount]['signup'] = strtotime($campaign->signup);
                      }
                      if (isset($campaign->reportback)) {
                        $campaigns[$campaignCount]['reportback'] = strtotime($campaign->reportback);
                      }
                    }
                    else {
                      echo 'Missing campaign activity nid!', PHP_EOL;
                      echo('<pre>' . print_r($userApiResult, TRUE) . '</pre>');
                    }
                  }

                  $payload = array(
                    'email' => $userApiResult->email,
                    'campaigns' => $campaigns,
                    'merge_vars' => array(
                      'FNAME' => ucwords(strtolower($userApiResult->first_name)),
                    )
                  );
                  if (isset($userApiResult->drupal_uid)) {
                    $payload['drupal_uid'] = $userApiResult->drupal_uid;
                  }

                  echo '------- mbp-user-digest->produceUserDigestQueue Message Queued: ' . $userApiResult->email . ' ('. count($campaigns) . ' campaigns) - ' . date('D j M Y G:i:s T') . ' -------', PHP_EOL;

                  $payload = json_encode($payload);
                  $this->messageBroker->publishMessage($payload);

                  $this->statHat->addStatName('produceUserDigestQueue - queued digest');
                  $publishCount++;
                }

              }

            }

          }
          else {
            $this->statHat->addStatName('produceUserDigestQueue - @mobile number, skipped');
            $mobileCount++;
          }

          $recordsProcessed++;
          $this->statHat->reportCount(1);
          $this->statHat->clearAddedStatNames();

        }
        unset($this->channel);
        unset($this->messageBroker);
        $pageCount++;

        echo '->produceUserDigestQueue batch END: ' . date('D M j G:i:s T Y'), PHP_EOL;
        echo '- ' .  $publishCount . ' queued', PHP_EOL;
        echo '- @mobile: ' . $mobileCount . ' skipped', PHP_EOL;
        echo '- ' . $deleteCount . ' deleted', PHP_EOL;
        echo '- records processed: ' . $recordsProcessed, PHP_EOL;
        echo '- Total processed: ' . ($publishCount + $mobileCount +  $deleteCount), PHP_EOL;
        echo PHP_EOL;
      }
      else {
        echo 'ERROR - $this->toolbox->curlGET failed.', PHP_EOL;
        echo '- retrying page: ' . $pageCount . ' in one minute...', PHP_EOL;
        sleep(60);
      }

    } while ($resultCount + 1 == self::PAGE_SIZE);

    $this->statHat->addStatName('produceUserDigestQueue');
    $this->statHat->reportCount($publishCount);
    $total = $publishCount + $mobileCount +  $deleteCount;
    echo '------- mbp-user-digest->produceUserDigestQueue END: ' . $publishCount . ' queued - @mobile: ' . $mobileCount . ' skipped.' . $deleteCount . ' deleted. Total processed: ' . $total . ' - ' . date('D j M Y G:i:s T') . ' -------', PHP_EOL;

  }

  /**
   * Import target test users from CSV file
   *
   * @param string $targetCSVFile
   *   The filename for the CSV file to import that contains target email
   *   address.
   */
  public function produceUserGroupFromCSV($targetCSVFile = NULL) {

    $testUsers = array();

    // Open CSV file
    if ($targetCSVFile != NULL) {

      $targetCSVFile = __DIR__ . '/' . $targetCSVFile;
      $targetUsers = file($targetCSVFile);
      $targetUsers = explode("\r", $targetUsers[0]);
      $count = 0;

      if ($targetUsers != FALSE) {
        foreach ($targetUsers as $userCount => $targetUser) {
          if ($userCount > 0) {
            $userData = explode(',', $targetUser);
            if ($userData[1] != '') {
              $testUsers[] = strtolower($userData[1]);
            }
          }
        }
      }
      else {
        echo 'Target CSV file empty.', PHP_EOL;
      }

    }
    else {
      echo 'Target CSV file not set.', PHP_EOL;
    }

    return $testUsers;
  }
  
  /**
   * Test groups
   */
  public function produceTestUserGroupDigestQueue() {

    $testUsers = array(
      'cstowell@dosomething.org',
      'kradford@dosomething.org',
      'qaasst@dosomething.org',
      'lpatton@dosomething.org',
      'mnelson@dosomething.org',
      'mfantini@dosomething.org',
      'nhirabayashi@dosomething.org',
      'aruderman@dosomething.org',
      'nmody@dosomething.org',
      'bkassoy@dosomething.org',
      'jlorch@dosomething.org',
      'dlee@dosomething.org',
      'mlidey@dosomething.org',
      'qaasst@dosomething.org',
      'qualityassuranceqa@yahoo.com',
      'qualityassuranceqa@aol.com',
      'qualityassuranceqa@hotmail.com',
      'apatheticslacker@gmail.com',
    );

    // Developers
    $testUsers = array(
      'dlorenzo@dosomething.org',
      'mholford@dosomething.org',
      'dfurnes@dosomething.org',
      'mrich@dosomething.org',
      'jcusano@dosomething.org',
      'joshcusano@gmail.com',
      'qaasst@dosomething.org',
      'agaither@dosomething.org',
      'barry@dosomething.org',
      'bclark@dosomething.org',
      'developerasst1@dosomething.org',
    );

    // Dev team
    $testUsers = array(
      'dlee@dosomething.org',
      'bclark@dosomething.org',
      'dlee+update-test01@dosomething.org',
      'apatheticslacker@gmail.com',
      'jvicente@nyit.edu',
      'joe@dosomething.org',
      'mfantini@dosomething.org',
      'lpatton@dosomething.org',
      'nmody@dosomething.org',
      'qualityassuranceqa@yahoo.com',
      'qualityassuranceqa@aol.com',
      'qualityassuranceqa@hotmail.com',
      'digestplswork@gmail.com',
      'ascottdicker@dosomething.org',
      'mfantini+digest@dosomething.org',
      'bkassoy@dosomething.org',
      'bkassoy1@gmail.com',
    );

    return $testUsers;
  }

}
