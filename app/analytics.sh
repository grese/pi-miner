#!/bin/bash
seconds=$1
repeat=$2
i=0

apiURL="http://localhost/api/trends/collect"

if[ -n "$seconds" && -n "$repeat" ]; then
    curl -X GET $apiURL
else
    while [ $i -lt $repeat ]; do
        sleep $seconds
        #running curl command every n seconds...
        curl -X GET $apiURL
        i=$[$i+1]
    done
fi