<?php

use DoSomething\MB_Toolbox\MB_Toolbox;
use DoSomething\MBStatTracker\StatHat;

class MBP_UserDigest
{

  const DEFAULT_FIRST_NAME = "Doer";
  const PAGE_SIZE = 500;

  /**
   * Setting from external services - Mailchimp.
   *
   * @var array
   */
  private $settings;

  /**
   * Message Broker connection to RabbitMQ
   */
  private $messageBroker;

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
  public function __construct($credentials, $config, $settings) {
    $this->settings = $settings;
    $this->messageBroker = new MessageBroker($credentials, $config);

    $this->toolbox = new MB_Toolbox($settings);

    $this->statHat = new StatHat($settings['stathat_ez_key'], 'mbp-user-digest:');
    $this->statHat->setIsProduction(FALSE);
  }

  /**
   * Collect users (email) for digest message batch
   */
  public function produceUserDigestQueue($testUsers = array()) {

    echo '------- mbp-user-digest->produceUserDigestQueue START: ' . date('D M j G:i:s T Y') . ' -------', PHP_EOL;

    $testUserCount = count($testUsers);
    $deleteCount = 0;
    $mobileCount = 0;
    $publishCount = 0;
    $pageCount = 1;
    $baseUrl = $this->settings['ds_user_api_host'] . ':' . $this->settings['ds_user_api_p'];
    do {

      $userApiUrl = $baseUrl . '/users?page=' . $pageCount . '&pageSize=' . self::PAGE_SIZE . '&excludeNoCampaigns=1';
      $results = $this->toolbox->curlGET($userApiUrl);

      foreach($results as $resultCount => $result) {

        // Remove user entries that have a mixed case email. These are duplicates from a resolved bug. The mb-user-api ensures and
        // new and updated documents have lower case email values.
        if (preg_match('/[A-Z]/', $result->email)) {
          $deleteUserApiUrl = $baseUrl . '/user?email=' . $result->email . '&exactCase=1';
          $ch = curl_init();
          curl_setopt($ch, CURLOPT_URL,$deleteUserApiUrl);
          curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
          $cURLResult = curl_exec($ch);
          curl_close($ch);

          echo '** mbp-user-digest->produceUserDigestQueue ' . $result->email . ' REMOVED from mb-user database. **', PHP_EOL;
          $deleteCount++;
        }
        else {

          // Only process non "@mobile" addresses
          if (isset($result->email) && (strpos($result->email, '@mobile') === FALSE || strlen(substr($result->email, strpos($result->email, '@mobile'))) > 7)) {

            // If specific test users are defined only create queue entries
            // for those users

            if ( (isset($testUsers) && $testUserCount > 0 && in_array($result->email, $testUsers)) ||
                 ($testUserCount == 0) ) {
  
              if (!isset($result->first_name) ||  $result->first_name == '') {
                $result->first_name = self::DEFAULT_FIRST_NAME;
              }
              $campaigns = array();
              foreach ($result->campaigns as $campaignCount => $campaign) {
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
                  echo('<pre>' . print_r($result, TRUE) . '</pre>');
                }
              }

              $payload = array(
                'email' => $result->email,
                'campaigns' => $campaigns,
                'merge_vars' => array(
                  'FNAME' => ucwords(strtolower($result->first_name)),
                )
              );
              if (isset($result->drupal_uid)) {
                $payload['drupal_uid'] = $result->drupal_uid;
              }

              echo '------- mbp-user-digest->produceUserDigestQueue Message Queued: <pre>' . print_r($payload, TRUE) . '</pre> - ' . date('D M j G:i:s T Y') . ' -------', PHP_EOL;

              $payload = json_encode($payload);
              $this->messageBroker->publishMessage($payload);

              $publishCount++;

            }

          }
          else {
            echo '------- mbp-user-digest->produceUserDigestQueue ERROR - missing or @mobile email: ' . print_r($result, TRUE) . ' - ' . date('D M j G:i:s T Y') . ' -------', PHP_EOL;
            $mobileCount++;
          }

        }

      }

      $pageCount++;

    } while ($resultCount + 1 == self::PAGE_SIZE);

    $this->statHat->addStatName('produceUserDigestQueue');
    $this->statHat->reportCount($publishCount);

    $total = $publishCount + $mobileCount +  $deleteCount;
    echo '------- mbp-user-digest->produceUserDigestQueue END: ' . $publishCount . ' queued - @mobile: ' . $mobileCount . ' skipped.' . $deleteCount . ' deleted. Total processed: ' . $total . ' - ' . date('D M j G:i:s T Y') . ' -------', "\n<br />";
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
      'mlidey@dosomething.org',
      'dlee+update-test01@dosomething.org',
    );

    return $testUsers;
  }

}
