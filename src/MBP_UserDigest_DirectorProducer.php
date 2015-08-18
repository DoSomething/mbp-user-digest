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
  protected $digestUser;

  /**
   * queueUser() : Produce user message for fanout exchange.
   */
  public function queueUser() {

    $payload = $this->generatePayload();
    parent::produceMessage($payload);
  }

  /**
   * generatePayload: Format message payload
   */
  protected function generatePayload() {

    $payload = parent::generatePayload();

    $payload['email'] = $this->digestUser['email'];
    $payload['first_name'] = $this->digestUser['first_name'];
    $payload['campaigns'] = $this->digestUser['campaigns'];
    $payload['drupal_uid'] = $this->digestUser['drupal_uid'];

    return $payload;
  }

  /**
   * generatePayload: Format message payload
   */
  public function setUser($user) {

    if (isset($user->subscriptions) && $this->isSubscribed($user->subscriptions) ||
        (!isset($user->subscriptions))) {

      // @todo: Add email format validation
      if ($this->isValidEmail($user->email)) {
        $this->digestUser['email'] = $user->email;
      }
      else {
        echo 'MBP_UserDigest_DirectorProducer->setUser(): No email found, skipping user document.', PHP_EOL;
        return FALSE;
      }

      if (isset($user->first_name) && $user->first_name != '') {
        $this->digestUser['first_name'] = $user->first_name;
      }
      else {
        echo 'MBP_UserDigest_DirectorProducer->setUser(): Using default first name for ' . $user->email, PHP_EOL;
        // Some PHP OO funyiness: http://stackoverflow.com/questions/5447541/accessing-php-class-constants
        $tempToolbox = $this->toolbox;
        $this->digestUser['first_name'] = $tempToolbox::DEFAULT_FIRST_NAME;
      }

      if (count($user->campaigns) > 0) {
        $this->digestUser['campaigns'] = $this->scrubCampaigns($user->campaigns);
      }
      else {
        echo 'MBP_UserDigest_DirectorProducer->setUser(): no campaigns found for ' . $user->email, PHP_EOL;
        return FALSE;
      }

      if (isset($user->drupal_uid)) {
        $this->digestUser['drupal_uid'] = $user->drupal_uid;
      }
      else {
        echo 'MBP_UserDigest_DirectorProducer->setUser(): drupal_uid not set for ' . $user->email, PHP_EOL;
        return FALSE;
      }

      return TRUE;
    }
    else {
      return FALSE;
    }

  }

  /**
   * isValidEmail: Test basic email format rules and business logic to determine
   * if the email address is valid.
   */
  private function isValidEmail($email) {

    // Test 1
    if (isset($email) && $email == '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
      echo 'MBP_UserDigest_DirectorProducer->isValidEmail() failed test 1: ' . $email, PHP_EOL;
      return FALSE;
    }
    // Test 2
    if (isset($email) && strlen(substr($email, strpos($email, '@mobile'))) <= 7) {
      echo 'MBP_UserDigest_DirectorProducer->isValidEmail() failed test 2: ' . $email, PHP_EOL;
      return FALSE;
    }
    // Test 3
    elseif (isset($email) && strpos($email, '@mobile') != FALSE) {
      echo 'MBP_UserDigest_DirectorProducer->isValidEmail() failed test 3: ' . $email, PHP_EOL;
      return FALSE;
    }

    return TRUE;
  }

  /**
   * isSubscribed(): Evaluate user subscription settings including the lack of a preference.
   *
   * @param object $subscriptions
   *   All of the user subscription settings based on user preferences managed at
   *   subscriptions.dosomething.org.
   */
  private function isSubscribed($subscriptions) {

    // Exclude users who have have been banned OR no preference has been set for banning
    if ( isset($subscriptions) && !isset($subscriptions->banned) ) {

      // Include users who have no digest unsubscription setting or the subscription for digest
      // messages is true.
      if ( (!isset($subscriptions->digest)) ||
           (isset($subscriptions->digest) && $userApiResult->subscriptions->digest == TRUE) ) {
        return TRUE;
      }
      // Exclude users who have unsubscribed from Digest messages
      elseif (isset($subscriptions->digest)) {
        return FALSE;
      }

    }
    elseif (isset($subscriptions->banned) && $subscriptions->banned == TRUE) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * scrubCampaigns() : Check for required campaign nid and remove campaigns that have been completed.
   * Compleation is defined as a campaign that has been signed up for and a report as been completed.
   *
   * @param array $campaigns
   *   A list of all the campaigns a user has signed up for and reported back on.
   *
   * @return array $cleanCampaigns
   *   A list of all of the user campaigns that are eligible for digest content processing.
   */
  private function scrubCampaigns($campaigns) {

    $cleanCampaigns = array();
    foreach ($campaigns as $campaignCount => $campaign) {

      if (isset($campaign->nid)) {

        $cleanCampaigns[$campaignCount] = array(
          'nid' => $campaign->nid
        );

        if (isset($campaign->signup)) {
          $cleanCampaigns[$campaignCount]['signup'] = strtotime($campaign->signup);
          $signupFound = TRUE;
        }
        else {
          $signupFound = FALSE;
        }

        if (isset($campaign->reportback)) {
          $cleanCampaigns[$campaignCount]['reportback'] = strtotime($campaign->reportback);
          $reportbackFound = TRUE;
        }
        else {
          $reportbackFound = FALSE;
        }

        // Remove campaign activity that's complete
        if (($signupFound && $reportbackFound) || (!$signupFound && $reportbackFound)) {
          unset($cleanCampaigns[$campaignCount]);
        }

      }
      else {
        echo 'MBP_UserDigest_DirectorProducer->scrubCampaigns(): Missing campaign activity nid: ' . print_r($cmmpaign), PHP_EOL;
      }

    }

    return $cleanCampaigns;
  }

}