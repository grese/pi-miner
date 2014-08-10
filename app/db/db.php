<?php
   class PiMinerDB extends SQLite3
   {
        function __construct()
		{
		  $dbDir = __DIR__."/../../data";
          $dbFile = __DIR__."/../../data/pi-miner.db";
          if (!file_exists($dbDir)) {
               mkdir($dbDir, 0755, true);
          }
          $this->open($dbFile);
       }
       
	   function __destruct() {
        	$this->close();
		}

       private $setting_schema = " CREATE TABLE IF NOT EXISTS settings
	      		(id INTEGER PRIMARY KEY AUTOINCREMENT,
	      		type CHAR(25) NOT NULL,
		  		value TEXT NOT NULL); ";
       private $pool_schema = " CREATE TABLE IF NOT EXISTS pools
    			(id INTEGER PRIMARY KEY AUTOINCREMENT,
    			name VARCHAR(50), 
    			url VARCHAR(255),
    			username VARCHAR(50),
				password VARCHAR(50),
				enabled BOOLEAN); ";
	   private $trend_schema = " DROP TABLE IF EXISTS trends;
	   			CREATE TABLE trends
    			(id INTEGER PRIMARY KEY AUTOINCREMENT,
    			collected DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    			type CHAR(25),
    			value TEXT,
    			deviceID INT,
    			deviceName VARCHAR(20),
    			deviceEnabled CHAR(1)); ";
       private $user_schema = " CREATE TABLE IF NOT EXISTS users
   				(id INTEGER PRIMARY KEY AUTOINCREMENT,
   				username VARCHAR(30),
   				password VARCHAR(50)); "; 
       
       
       
       public function table_exists($table){
	   	   $sql = "SELECT name FROM sqlite_master WHERE type='table' AND name='$table'";
		   $result = $this->query($sql);
		   $has_rows = false;
		   if($result->numColumns() && $result->columnType(0) != SQLITE3_NULL){
			   $has_rows = true;
		   }
		   $rows = $result->fetchArray();
		   return $rows;
	   }
       
       public function setup_db(){
       	   $create_sql = "";
       	   $insert_sql = array();
       	   if(!$this->table_exists('users')){
       	   		echo "CREATING users...\n";
		   		$create_sql .= $this->user_schema;
		   		array_push($insert_sql, " INSERT INTO users (username, password) VALUES ('".$this->escapeString('grese')."', '".md5('schmiles')."'); ");		   			
	       }
	       
	       if(!$this->table_exists('pools')){
	       		echo "CREATING pools...\n";
		   		$create_sql .= $this->pool_schema;
		   		$pools = array(
		   			array('name'=>"Slush's Pool", 'url'=>"http://stratum.bitcoin.cz:3333", 'username'=>"grese.piminer", 'password'=>'schroeder', "enabled"=>true));
		   		foreach($pools as $pool){
			   		array_push($insert_sql, " INSERT INTO pools (name, url, username, password, enabled) VALUES ('".$this->escapeString($pool['name'])."', '".$this->escapeString($pool['url'])."', '".$this->escapeString($pool['username'])."', '".$this->escapeString($pool['password'])."', ".$pool['enabled']."); ");
		   		}		       
	       }
	       
	       if(!$this->table_exists('settings')){
	       	   echo "CREATING settings...\n";
		       $create_sql .= $this->setting_schema;
			   $settings = array(
			   		array('type'=>'DEVICE_INFO', 'value'=>"{\"name\": \"Pi Miner\" }"),
			   		array('type'=>'MINER_CONFIG', 'value'=>"{\"miner\": \"cgminer\" }"),
			   		array('type'=>'EMAIL_NOTIFICATION', 'value'=>"{\"toAddress\": \"johngrese@me.com\",\"fromAddress\": \"johngrese@me.com\", \"smtpServer\": \"smtp.mail.me.com\", \"smtpAuth\": true, \"smtpAuthUsername\": \"johngrese@me.com\", \"smtpAuthPassword\": \"SchroederRock5\", \"smtpAuthPort\": 587 }"),
			   		array('type'=>'ANALYTICS_CONFIG', 'value'=>"{\"dataCollectionEnabled\": true, \"dataInterval\": 30 }"),
			   		array('type'=>'PERFORMANCE_ALERT', 'value'=>"{\"enabled\": false, \"numDevices\": 1, \"numMhs\": 330}"),
		);
		   		foreach($settings as $setting){
			   		array_push($insert_sql, " INSERT INTO settings (type, value) VALUES ('".$this->escapeString($setting['type'])."', '".$this->escapeString($setting['value'])."'); ");
		   		}
	       }
	       
	       $create_sql .= $this->trend_schema;
	       
	       $ret = $this->exec($create_sql);
	       if(!$ret){
			   echo $this->lastErrorMsg();
		   } else {
			   echo "====== Tables initialized successfully ======\n";
			   $valid = true;
			   $err_msg = "";
			   foreach($insert_sql as $sql){
					$ret = $this->exec($sql);
					if(!$ret){
						$valid = false;
			   			$err_msg .= $this->lastErrorMsg()."\n";
			   		}		   
			   }
			   if($valid){
			   		if(count($insert_sql) > 0){
				   		echo "====== Tables populated successfully ======\n";	
			   		}
			   }else{
				   echo $err_msg;
			   }
		   }
	       
       }
   }