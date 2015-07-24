#!/bin/bash
# Script to make "snapshot" / copy of mb-users collection as
# start of User Digest generation process.

echo "mbp-user-digest.sh START"
echo "Time: "$(date)

currentDate="$(date +'%Y_%m_%d')"
collectionName="digest-"$currentDate
echo "collectionName: "$collectionName

mongo 10.100.15.186:27017/mb-users --eval "rs.slaveOk(); db['mailchimp-users'].copyTo('$collectionName')"

echo "Time: "$(date)
echo "mbp-user-digest.sh END"
