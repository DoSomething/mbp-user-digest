mbp-user-digest
===============

A user digest message is a summary of the users campaign activity to inform them of the status of the campaigns
they're signed up for. The producer micro-service of digest generation process is responsible for gathering the user documents and directing the results as messages for the consumer part of the generation process.

####The Process
Producer for the Message Broker system to manage the production of user digest messages. The process consists of:

- initiating the generation of digest messages (mbp-user-digest_producer.php):
```
$ php mbp-user-digest_producer.php
$ php mbp-user-digest_producer.php?targetUser=xxx@dosomething.org
$ php mbp-user-digest_producer.php?targetUsers=emails.csv
```

or

```
http://xx.xx.xx.xx/mbp-user-digest_producer.php
http://xx.xx.xx.xx/mbp-user-digest_producer.php?targetUser=xxx@dosomething.org
http://xx.xx.xx.xx/mbp-user-digest_producer.php?targetUsers=emails.csv.org
```

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
