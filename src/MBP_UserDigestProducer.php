<?php
/**
 * MBP_UserDigestProducer - Static class of methods to create "snapshot" of mb-user collection. Generate
 * queue entries in userDigestProducerQueue to manage paged calls to mb-user collection.
 */
namespace DoSomething\MBC_UserDigest;

use DoSomething\StatHat\Client as StatHat;
use DoSomething\MB_Toolbox\MB_Toolbox;
use DoSomething\MB_Toolbox\MB_Toolbox_BaseProducer;

/*
 * MBC_UserAPICampaignActivity.class.in: Used to process the transactionalQueue
 * entries that match the campaign.*.* binding.
 */
class MBC_UserDigestProducer extends MB_Toolbox_BaseProducer
{
  
  // The number of user documents to collect in a singe page request to /users
  const PAGE_SIZE = 5000;
  
  /**
   * Create entries in userDigestProducerQueue for each of the paged queries to make
   * to mb-user-api. Additional consumers of the queue will increate the rate that
   * the user data for digest generation will be prepared for consumption by
   * mbc-digest-email. php 
   */
  static public function producer() {

    $pageCount = 0;
    do {
      $pageCount++;
      $userAPIPageURL = self::generatePageRequests($pageCount);
      self::produceQueue($payload);




        
    } while ($resultCount + 1 == self::PAGE_SIZE);

  }
  
  /**
   * generatePageRequestsURL: Construct URL to send request for user documents
   *
   * @param integer $page
   *   The page of user documents to request.
   *
   * @return string $usersPagedURL
   *   The URL to request a page of user documents.
   */
  static public function generatePageRequestsURL($page) {
    
    $curlUrl = $this->settings['ds_user_api_host'];
    $port = $this->settings['ds_user_api_port'];
    if ($port != 0 && is_numeric($port)) {
      $curlUrl .= ':' . (int) $port;
    }
    
    $usersPagedURL = $curlUrl . '/users?page=' . $pageCount . '&pageSize=' . self::PAGE_SIZE . '&excludeNoCampaigns=1';
    return $usersPagedURL;
  }

  
  /**
   * produceQueue: 
   */
  static public function produceQueue($payload) {

    parent::produceQueue($payload);
  }
}