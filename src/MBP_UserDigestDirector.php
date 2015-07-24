<?php
/**
 * MBP_UserDigestDirector - Consumer application as part of user digest producer process. Consumes entries
 * in the userDigestProducerQueue to make paged calls to mb-user-api for user documents to process as
 * precipitants of campaign digest email messages.
 *
 * Processing of the userDigestProducerQueue results in message entries in the userDigestQueue for
 * mbc-digest-email consumption.
 */