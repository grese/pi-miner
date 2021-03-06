#!/bin/bash
db_setup="/home/pi/pi-miner/db/setup.php"
bfgminer_binary="/home/pi/bfgminer/bfgminer"
config_dir="/home/pi/pi-miner/config"
config_file="$config_dir/miner-config.json"
config_args="$config_dir/miner.args"
custom_file="$config_dir/custom.conf"
custom_config=""

# Reset the database & config file...
/home/pi/pi-miner/dbinit.sh

if [ ! -f $custom_file ]; then
    touch $custom_file
fi
read -r $custom_config<$custom_file

if [ -n "$custom_config" ]; then
    # If the miner config is not empty... Use it instead of default.
    # Start the miner...
    nohup  $custom_config > /dev/null 2>&1&
else
    # The custom_config file is empty so we will use the miner config...
    
	bfgminer_args=`head -n 1 $config_args`

    # Start the miner with default config file...
    nohup  $bfgminer_binary $bfgminer_args --config $config_file -S all > /dev/null 2>&1&
fi
