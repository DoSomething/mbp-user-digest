mbp-user-digest
===============

A digest email message is a summary of the users campaign activity. A listing of their active campaigns informs them of the their status in the campaign.

####The Process

- Gather user documents from mb-user database which have campaign activity information.

- initiating the generation of digest messages (mbp-user-digest_producer.php):
```
$ php mbp-user-digest_producer.php
$ php mbp-user-digest_producer.php?targetUser=xxx@dosomething.org
$ php mbp-user-digest_producer.php?targetUsers=emails.csv
```
from the command line or as scheduled job in Jenkins.

Which creates a message in the digestProducerQueue with messages defining how to call mb-user-api using /users?type=cursor or /user. A queue entry will consist of a message with the payload of:

```
a:3:{
  s:9:"requested";s:25:"2015-08-27T20:31:02-04:00";
  s:9:"startTime";s:25:"2015-08-27T20:31:02-04:00";
  s:3:"url";s:53:"/users?type=cursor&pageSize=5000&excludeNoCampaigns=1";
}
```

mbp-user-digest_director.php consumes digestProducerQueue as a daemon process.  First it produces a new message in digestProducerQueue detailing the next paded request to mb-users-api. Second the daemon sends messages to the fanout exchange (fanoutUserDigest) that results in duplicate messages in:
- digestCampaignsQueue (future release)
- digestUnsubscribeQueue (future release)
- digestUserQueue (mbc-digest-email)

This process can be parallelized to increase the rate in with the producer part of the digest generatation process takes.

Each of queues connected to the fanout exchange will have the same message detailing the user documents found by the call to /user on the mb-user-api. The fanout allows for concurrency processing of the sperate parts of digest generation process.


####References
- General Message Broker details: https://github.com/DoSomething/message-broker/wiki
- Information about the digest functionality within the Message Broker system: https://github.com/DoSomething/message-broker/wiki/User-Digest
