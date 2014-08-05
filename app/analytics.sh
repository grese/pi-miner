#!/bin/bash
seconds=$1
repeat=$2
i=0
while [ $i -lt $repeat ]; do
 sleep $seconds
 #running curl command every n seconds...
 curl -X GET http://piminer.local/api/trends/collect
 i=$[$i+1]
done