#!/bin/bash

apiURL="http://localhost/api/trends/collect"

if [ -z "$1" ]; then
	curl -X GET $apiURL
	exit 0
else
	seconds=$1
	repeat=$2
	i=0
	
    while [ $i -lt $repeat ]; do
        #running curl command to api & increment counter...
        curl -X GET $apiURL
        i=$[$i+1]
        #sleep for n seconds, then rinse & repeat if i < $repeat...
        sleep $seconds
    done
    exit 0
fi
