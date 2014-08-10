#!/bin/bash

config_dir="./config"
config_file="$config_dir/miner.conf"
default_config='{"pools":[{"url":"stratum.bitcoin.cz:3333","user":"grese.piminer","pass":"schroeder"}],"api-listen":true,"api-port":"4028","expiry":"120","failover-only":true,"log":"5","queue":"2","scan-time":"60","worktime":true,"shares":"0","api-allow":"W:127.0.0.1"}'
custom_file="$config_dir/custom.conf"
custom_config=""
miner_log="./logs/cgminer.log"

if [ ! -f $custom_file ]; then
    touch $custom_file
fi
read -r $custom_config<$custom_file


if [ -n "$custom_config" ]; then
    # If the miner config is not empty... Use it instead of default.

    # Start the miner...
    eval `sudo nohup $custom_config &> $miner_log`
else
    # The custom_config file is empty so we will use the miner config...

    # If config file doesn't exist, go ahead and create it with default config...
    if [ ! -f $config_file ]; then
        [ -d $config_dir ] || mkdir $config_dir
        echo $default_config > $config_file
    fi

    # Start the miner...
    sudo nohup ../cgminer/cgminer-4.4.1/cgminer --config $config_file >$miner_log 2>&1&
fi
