mbp-user-digest
===============

Producer for the Message Broker system to manage the production of user digest messages. The process consists of:

- initiating the generation of digest message (mbp-user-digest_producer.php):
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

Which creates a message in the digestProducerQueue defining how to call mb-user-api /users?type=cursor or /user.

```
a:3:{
  s:9:"requested";s:25:"2015-08-27T20:31:02-04:00";
  s:9:"startTime";s:25:"2015-08-27T20:31:02-04:00";
  s:3:"url";s:53:"/users?type=cursor&pageSize=5000&excludeNoCampaigns=1";
}
```

mbp-user-digest_director.php consumes digestProducerQueue as a daemon process to produce new messages in digestProducerQueue. The additional messages will trigger the subsequent paged cursor requests to mb-user-api. mbp-user-digest_director also sends messages to the fanout exchange (fanoutUserDigest) that results in duplicate messages in:
- digestCampaignsQueue (future release)
- digestUnsubscribeQueue (future release)
- digestUserQueue (mbc-digest-email)

Each queue has the same message detailing the object found by the call to /user on the mb-user-api.


A user digest message is a summary of the users campaign activity to inform them of the status of the campaigns
they're signed up for.

- General Message Broker details: https://github.com/DoSomething/message-broker/wiki
- Information about the digest functionality within the Message Broker system: https://github.com/DoSomething/message-broker/wiki/User-Digest
