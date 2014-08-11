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
        sleep $seconds
        #running curl command every n seconds...
        curl -X GET $apiURL
        i=$[$i+1]
    done
    exit 0
fi