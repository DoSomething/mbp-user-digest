<?php
/**
 * MBP_UserDigestProducer - Static class of methods to create "snapshot" of mb-user collection. Generate
 * queue entries in userDigestProducerQueue to manage paged calls to mb-user collection.
 */
namespace DoSomething\MBP_UserDigest;

use DoSomething\StatHat\Client as StatHat;
use DoSomething\MB_Toolbox\MB_Toolbox;
// Adjust path when BaseProducer is moved into MB_Toolbox library
use DoSomething\MBP_UserDigest\MB_Toolbox_BaseProducer;

/*
 * MBC_UserAPICampaignActivity.class.in: Used to process the transactionalQueue
 * entries that match the campaign.*.* binding.
 */
class MBP_UserDigestProducer extends MB_Toolbox_BaseProducer
{

  // The number of user documents to collect in a singe page request to /users
  const PAGE_SIZE = 5000;

  /**
   * startTime - The date the request message started to be generated.
   *
   * @var string $startTime
   */
  public $startTime;

  public function __construct($messageBroker, StatHat $statHat, MB_Toolbox $toolbox, $settings) {

    parent::__construct($messageBroker, $statHat, $toolbox, $settings);
    $this->startTime = date('c');
  }

  /**
   * Create entries in userDigestProducerQueue for each of the paged queries to make
   * to mb-user-api. Additional consumers of the queue will increase the rate that
   * the user data for digest generation will be prepared for consumption by
   * mbc-digest-email.
   */
  public function producer() {

    $totalPages = self::gatherTotalPages();

    $pageCount = 0;
    do {
      $pageCount++;
      $usersPagedURL = $this->generatePageRequestsURL($pageCount);
      $payload = $this->generatePayload($usersPagedURL);
      parent::produceQueue($payload, 'userDigestProducer');

    } while ($pageCount < $totalPages);

  }

  /**
   * gatherTotalPages: Construct URL to send request for user documents
   *
   * @return integer $totalPages
   *   The total number of pages.
   */
  static private function gatherTotalPages() {

    // Request the total number of user documents that have campaign activity
    $totalDocuments = 100000;
    $totalPages = round($totalDocuments / self::PAGE_SIZE, 0, PHP_ROUND_HALF_ODD);

    return (int) $totalPages;
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
  private function generatePageRequestsURL($pageCount) {
    
    $curlUrl = $this->settings['ds_user_api_host'];
    $port = $this->settings['ds_user_api_port'];
    if ($port != 0 && is_numeric($port)) {
      $curlUrl .= ':' . (int) $port;
    }
    
    $usersPagedURL = $curlUrl . '/users?page=' . $pageCount . '&pageSize=' . self::PAGE_SIZE . '&excludeNoCampaigns=1';
    return $usersPagedURL;
  }
  
  /**
   * generatePayload: Format message payload
   *
   * @param string $usersPagedURL
   *   URL to add to message payload
   */
  public function generatePayload($usersPagedURL) {

    $payload = parent::generatePayload($usersPagedURL);
    $payload['url'] = $usersPagedURL;

    return $payload;
  }

}
