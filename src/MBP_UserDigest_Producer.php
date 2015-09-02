<?php
/**
 * MBP_UserDigestProducer - Static class of methods to create "snapshot" of mb-user collection. Generate
 * queue entries in userDigestProducerQueue to manage paged calls to mb-user collection.
 */
namespace DoSomething\MBP_UserDigest;

use DoSomething\StatHat\Client as StatHat;
use DoSomething\MB_Toolbox\MB_Toolbox_BaseProducer;

/*
 * MBC_UserAPICampaignActivity.class.in: Used to process the transactionalQueue
 * entries that match the campaign.*.* binding.
 */
class MBP_UserDigest_Producer extends MB_Toolbox_BaseProducer
{

  /**
   *  kickoff() - Create initial entry in digestProducerQueue to trigger
   *  production of user messages based on cursor based requests to
   *  mb-users-api.
   *
   *  @param integer $pageSize
   *    The number of documents to request per page.
   */
  public function kickoff($pageSize) {
    
    $url = '/users';
    $parameters = array(
      'type' => 'cursor',
      'pageSize' => $pageSize,
      'excludeNoCampaigns' => 1
    );
    $url .= '?' . http_build_query($parameters);
    $this->usersPagedURL = $url;

    $routingKey = 'digestProducer';
    $payload = $this->generatePayload();
    $payload = parent::produceMessage($payload, $routingKey);
  }

  /**
   *  userKickoff() - Create entry in digestProducerQueue for specific user lookups
   *  in mb-users-api.
   *
   *  @param array $targetUsers
   *    A list of users to generate messages for specific users rather than user
   *    cursor pages.
   */
  public function userKickoff($targetUsers) {

    foreach ($targetUsers as $email) {

      $url = '/user';
      $parameters = array(
        'email' => $email,
      );
      $url .= '?' . http_build_query($parameters);
      $this->usersPagedURL = $url;

      $routingKey = 'digestProducer';
      $payload = $this->generatePayload();
      $payload = parent::produceMessage($payload, $routingKey);
    }
  }
  
  /**
   * generatePageRequestsURL(): Construct URL to send request for user documents
   *
   * @param integer $page
   *   The page of user documents to request.
   *
   * @return string $usersPagedURL
   *   The URL to request a page of user documents.
   */
  static public function generateCursorRequestsURL($_id) {
    
    $usersPagedURL = '/users?page=' . $pageCount . '&pageSize=' . self::PAGE_SIZE . '&excludeNoCampaigns=1';
    return $usersPagedURL;
  }
  
  /**
   * generatePayload(): Format message payload
   *
   * @return array
   *   Formatted payload
   */
  protected function generatePayload() {

    $payload = parent::generatePayload();
    $payload['url'] = $this->usersPagedURL;

    return $payload;
  }

  /**
   * produceUsersFromCSV() : Use email addresses defined in CSV file to create digest batch.
   *
   * @param string $targetFile
   *   The name of the CSV file which will contain email address in comma seperated, one
   *   per line format.
   *
   * @return array
   */
  static public function produceUsersFromCSV($targetFile) {

    $targetFile = __DIR__ . '/../' . $targetFile;
    $emails = file($targetFile, FILE_IGNORE_NEW_LINES);

    return $emails;
  }

}
