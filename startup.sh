#!/bin/bash
cgminer_binary="/home/pi/cgminer/cgminer"
config_dir="/home/pi/pi-miner/config"
config_file="$config_dir/miner.conf"
custom_file="$config_dir/custom.conf"
custom_config=""

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

    # Start the miner with default config file...
    nohup  $cgminer_binary --config /home/pi/cgminer/cgminer.conf > /dev/null 2>&1&
fi
