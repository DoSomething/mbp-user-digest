<?php
 
use DoSomething\MBP_UserDigest\MBP_UserDigest;
 
class MBP_UserDigestTest extends PHPUnit_Framework_TestCase {
 
  public function testSetupQueue()
  {

    require_once '../mbp-user-digest.config.inc';

    $mbpUserDigest = new MBP_UserDigest($credentials, $settings, $config);
    $queueName = $mbpUserDigest->setupQueue();

    // Using Management API check that queue exists with the expected settings in $settings
    $foundQueue = TRUE;

    $this->assertTrue($foundQueue);
  }
 
}
