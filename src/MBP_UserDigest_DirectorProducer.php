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
      $this->digestUser['first_name'] = $this->toolbox->DEFAULT_FIRST_NAME;
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

  /**
   * isValidEmail: Test basic email format rules and business logic to determine
   * if the email address is valid.
   */
  private function isValidEmail($email) {

  /*

    isset($user->email) && $user->email != ''
    if (isset($userApiResult->email) && (strpos($userApiResult->email, '@mobile') === FALSE ||
    strlen(substr($userApiResult->email, strpos($userApiResult->email, '@mobile'))) > 7)) {

    */

    return TRUE;
  }

  /**
   * isSubscribed:
   */
  private function isSubscribed($subscriptions) {

    /*

                // Exclude users who have have been banned OR no preference has been set for banning
              if ( isset($userApiResult->subscriptions) && !isset($userApiResult->subscriptions->banned) ) {

                // Exclude users who have unsubscribed from Digest messages OR no preference has been set of digest
                if ( (!isset($userApiResult->subscriptions->digest)) ||
                     (isset($userApiResult->subscriptions->digest) && $userApiResult->subscriptions->digest == TRUE) ) {

  */

    return TRUE;
  }

  /**
   *
   */
  private function scrubCampaigns($campaigns) {

    /*

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

    */

    return $campaigns;
  }

}
