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
  public function produceUserDigestQueue($testUsers = array()) {

    echo '------- mbp-user-digest->produceUserDigestQueue START: ' . date('D M j G:i:s T Y') . ' -------', "\n<br />";

    $publishCount = 0;
    $pageCount = 1;
    do {

      $userApiUrl = getenv('DS_USER_API_HOST') . ':' . getenv('DS_USER_API_PORT') . '/users?page=' . $pageCount . '&pageSize=' . self::PAGE_SIZE . '&excludeNoCampaigns=1';
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $userApiUrl);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
      $jsonResult = curl_exec($ch);
      curl_close($ch);
      $returned = json_decode($jsonResult);
      $results = $returned->results;

      foreach($results as $resultCount => $result) {

        if (isset($result->email) && strpos($result->email, '@mobile') === FALSE) {

          if (!isset($result->first_name) ||  $result->first_name == '') {
            $result->first_name = self::DEFAULT_FIRST_NAME;
          }
          $campaigns = array();
          foreach ($result->campaigns as $campaignCount => $campaign) {
            if (isset($campaign->nid)) {
              $campaigns[$campaignCount] = array(
                'nid' => $campaign->nid
              );
            }
            else {
              echo("<br />Missing campaign activity nid!<br />");
              echo('<pre>' . print_r($result, TRUE) . '</pre>');
            }

            if (isset($campaign->signup)) {
              $campaigns[$campaignCount]['signup'] = strtotime($campaign->signup);
            }
            if (isset($campaign->reportback)) {
              $campaigns[$campaignCount]['reportback'] = strtotime($campaign->reportback);
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

          if ((isset($testUsers) && in_array($payload['email'], $testUsers))
              || count($testUsers) == 0) {
            echo '------- mbp-user-digest->produceUserDigestQueue Message SENT: <pre>' . print_r($payload, TRUE) . '</pre> - ' . date('D M j G:i:s T Y') . ' -------', "\n<br />";
            $payload = json_encode($payload);
            $this->messageBroker->publishMessage($payload);
            $publishCount++;
          }
        }
        else {
          echo '------- mbp-user-digest->produceUserDigestQueue ERROR - missing or @mobile email: ' . print_r($result, TRUE) . ' - ' . date('D M j G:i:s T Y') . ' -------', "\n<br />";
        }
      }
      $pageCount++;

    } while ($resultCount + 1 == self::PAGE_SIZE);

    $this->statHat->addStatName('produceUserDigestQueue');
    $this->statHat->reportCount($publishCount);

    echo '------- mbp-user-digest->produceUserDigestQueue END: ' . $publishCount . ' queued - ' . date('D M j G:i:s T Y') . ' -------', "\n<br />";
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

    // All Staff
    $testUsers = array(
      'aberneking@dosomething.org',
      'aschachter@dosomething.org',
      'abologno@dosomething.org',
      'aruderman@dosomething.org',
      'agaither@dosomething.org',
      'awright@dosomething.org',
      'afinger@dosomething.org',
      'bclark@dosomething.org',
      'bgreenberg@dosomething.org',
      'bkassoy@dosomething.org',
      'bmathias@dosomething.org',
      'cstowell@dosomething.org',
      'clyons@dosomething.org',
      'clee@dosomething.org',
      'charbur@dosomething.org',
      'cwormsley@dosomething.org',
      'cbell@dosomething.org',
      'dmastropaolo@dosomething.org',
      'ddeluca@dosomething.org',
      'dfurnes@dosomething.org',
      'dlorenzo@dosomething.org',
      'gboundin@dosomething.org',
      'hgridley@dosomething.org',
      'jbladt@dosomething.org',
      'juy@dosomething.org',
      'cusano@dosomething.org',
      'jlorch@dosomething.org',
      'kradford@dosomething.org',
      'kwagner@dosomething.org',
      'kortiz@dosomething.org',
      'kgoff@dosomething.org',
      'eeddy@dosomething.org',
      'lpatton@dosomething.org',
      'mlidey@dosomething.org',
      'mholford@dosomething.org',
      'mnelson@dosomething.org',
      'mfantini@dosomething.org',
      'mrich@dosomething.org',
      'mpanjwani@dosomething.org',
      'nmody@dosomething.org',
      'nlublin@dosomething.org',
      'nhirabayashi@dosomething.org',
      'rkaushal@dosomething.org',
      'spipergoldberg@dosomething.org',
      'edivine@tmiagency.org',
      'gperlstein@tmiagency.org',
      'gthomas@tmiagency.org',
      'lboyd@tmiagency.org'
    );

    return $testUsers;
  }

}
