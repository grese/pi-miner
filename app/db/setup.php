<?php
require_once(__DIR__.'/db.php');

$db = new PiMinerDB();
if(!$db){
	echo $db->lastErrorMsg();
} else {
    echo "====== Opened PiMiner Database ======\n";
    $db->setup_db();
}