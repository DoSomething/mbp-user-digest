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
class MBP_UserDigest_Producer extends MBP_UserDigest_BaseProducer
{

  /**
   * startTime - The date the request message started to be generated.
   *
   * @var string $startTime
   */
  protected $startTime;

  public function __construct() {

    parent::__construct();
    $this->startTime = date('c');
  }

  /**
   *  kickoff() - Create initial entry in digestProducerQueue to trigger
   *  production of user messages based on cursor based requests to
   *  mb-users-api.
   *
   *  @param integer $pageSize
   *    The number of documents to request per page.
   */
  public function kickoff($pageSize) {
    
    $mbUserAPIConfig = $mbConfig->getProperty('mb_user_api_config');
    
    $url = $mbUserAPIConfig['host'];
    $port = $mbUserAPIConfig['port'];
    if ($port != 0 && is_numeric($port)) {
      $url .= ':' . (int) $port;
    }
    $url .= '/users';

    $parameters = array(
      'type' => 'cursor',
      'size' => $pageSize,
    );
    $url .= http_build_query($parameters);
    $this->usersPagedURL = $url;

    $routingKey = 'digestProducer';
    $payload = $this->generatePayload();
    $payload = parent::produceMessage($payload, $routingKey);
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
  static public function generateCursorRequestsURL($_id) {
    
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
   */
  protected function generatePayload() {

    $payload = parent::generatePayload();
    $payload['url'] = $this->usersPagedURL;

    return $payload;
  }



}
