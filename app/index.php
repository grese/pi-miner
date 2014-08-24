<?php
require_once(__DIR__.'/utils/helpers.php');
require_once(__DIR__.'/utils/auth.php');
require_once(__DIR__.'/utils/cgminer-api.php');
require_once(__DIR__.'/utils/crontab.php');
require_once(__DIR__.'/utils/mailer.php');

date_default_timezone_set('America/Los_Angeles');

// Use Loader() to autoload our model
$loader = new \Phalcon\Loader();
$loader->registerDirs(array(
    __DIR__ . '/models/'
))->register();

$di = new \Phalcon\DI\FactoryDefault();
//Set up the database service
$di->set('db', function(){
	$config = array(
		"dbname" => __DIR__ ."/../data/pi-miner.db"
	);
	return new Phalcon\Db\Adapter\Pdo\Sqlite($config);
});
//Start the session the first time when some component request the session service
$di->setShared('session', function() {
    $session = new Phalcon\Session\Adapter\Files();
    $session->start();
    return $session;
});

//Create and bind the DI to the application
$app = new \Phalcon\Mvc\Micro($di);

$app->get('/api/sessiontest', function() use ($app){
	if(checkAuthToken($app)){
		echo 'HERE IS YOUR RESPONSE!';
	}
});

$app->get('[/]{path}', function($path) use ($app){
	echo serveStaticFile(__DIR__.'/app.html');
});

$app->get('/api/cgminer/{command}', function($command) use ($app){
	if(checkAuthToken($app)){
		$result = null;
		$cgMinerAPI = new CGMinerAPI();
		
		if($command === 'restart'){
			$result = $cgMinerAPI->request($command);
			$dbInit = __DIR__.'/../dbinit.sh';
			exec($dbInit);
		}
		
		if($command === 'devices'){
			$devices = $cgMinerAPI->request('devs');	
			$details = $cgMinerAPI->request('devdetails');
			$devs = array();
			for($i=0; $i<count($devices); $i++){
				$dev = (object) $devices[$i];
				$det = null;
				for($j=0; $j<count($details); $j++){
					if($details[$j]['DeviceName'] === $dev->DeviceName){
						$det = (object) $details[$j];
					}
				}
				if($det != null){
					$dev->Driver = $det->Driver;
					$dev->Kernel = $det->Kernel;
					$dev->Model = $det->Model;
					$dev->{'Device Path'} = $det->{'Device Path'};
					$dev->DetailID = $det->DeviceID;
				}
				array_push($devs, $dev);
			}
			$result = $devs;
		}else{
			$result = $cgMinerAPI->request($command);
		}
		echo json_encode($result);	
	}
});

$app->post('/api/login', function() use ($app){
        $username = $app->request->get('username');
		$password = $app->request->get('password');
		$phql = "SELECT * FROM User WHERE username = :username: AND password = :password:";
		
		$user = $app->modelsManager->executeQuery($phql, array(
			'username'=>$username,
			'password'=>md5($password)
		))->getFirst();	
		
		$loggedInUser = null;
		$login_result = 'FAILURE';
		$token = null;
		if($user){
			$login_result = 'SUCCESS';
			$token = loginUser($app, $user);
			$loggedInUser = array('username'=>$user->username, 'id'=>$user->id);
		}
	
		$result = array('result' => $login_result,'token'=>$token,'user'=>$loggedInUser);
		$response = new Phalcon\Http\Response();
		$response->setJsonContent($result);
		return $response;
});
$app->get('/api/logout', function() use ($app){
    destroySession($app);
    return true;
});


// ===================================================================== 
//   USERS ROUTES:
// ===================================================================== 
$app->get('/api/users', function() use ($app) {
	if(checkAuthToken($app)){
		$phql = "SELECT * FROM User";
		$users = $app->modelsManager->executeQuery($phql);
		$data = array();
		foreach($users as $user){
			$data[] = array(
				'id' => $user->id,
				'username' => $user->username,
				'password' => null,
			);
		}
		echo json_encode($data);
	}
});
$app->get('/api/users/{id:[0-9]+}', function($id) use ($app) {
	if(checkAuthToken($app)){
		$phql = "SELECT * FROM User WHERE id = :id:";
		$user = $app->modelsManager->executeQuery($phql, array(
			'id'=>$id
		))->getFirst();
		
		$response = new Phalcon\Http\Response();
		
		if($user == false){
			$response->setJsonContent(array());
		}else{
			$response->setJsonContent(array(
	                'id' => $user->id,
	                'username' => $user->username,
	                'password'=>null
	            ));
		}
		return $response;
	}
});
$app->put('/api/users/{id:[0-9]+}', function($id) use ($app) {
	if(checkAuthToken($app)){
		$body = $app->request->getJsonRawBody();
		$user = $body->user;
	    $values = array(
	        'id' => $id,
	        'username' => $user->username
	    );
	    $phql = "UPDATE User SET username = :username: ";
	    if($user->password != null){
	    	if(strlen($user->password) > 6){
				$phql .= ", password = :password: ";
				$values['password'] = md5($user->password);		    	
	    	}
	    }
	    $phql .= " WHERE id = :id:";
	    $status = $app->modelsManager->executeQuery($phql, $values);
	    
	    $response = new Phalcon\Http\Response();
		if ($status->success() == true) {
			$user->password = null;
			$user->id = $id;
	        $response->setJsonContent($user);
	    } else {
		    $response->setStatusCode(409, "Conflict");
			$errors = array();
	        foreach ($status->getMessages() as $message) {
	            $errors[] = $message->getMessage();
	        }
			$response->setJsonContent(array('status' => 'ERROR', 'messages' => $errors));
	    }
	    return $response;
	}
});



// ===================================================================== 
//   POOLS ROUTES:
// ===================================================================== 
function write_pools_config_to_file($app){
	$phql = "SELECT * FROM Pool WHERE enabled = 1 ORDER BY priority";
	$pools = $app->modelsManager->executeQuery($phql);
	
	$phql = "SELECT * FROM Setting WHERE type = :type:";
	$setting = $app->modelsManager->executeQuery($phql, array(
		'type'=>'POOL_STRATEGY'
	))->getFirst();
	$setting_value = json_decode(stripslashes($setting->value), true);
	$no_quota = ($setting_value->strategy === 'LOAD_BALANCE') ? false : true;
	
	$data = array();
	foreach($pools as $pool){
		$url = preg_replace('#^https?://#', '', $pool->url);
		$pool_data = array(
			'user' => $pool->username,
			'pass' => $pool->password
		);
		if($no_quota){
			$pool_data['url']=$url;
		}else{
			$quota = $pool->quota > 0 ? $pool->quota : 1;
			$quota = $quota.';'.$url;
			$pool_data['quota']=$quota;
		}
		$data[] = $pool_data;
	}
	$config = array(
	"pools"=> $data,
        "api-listen" => true,
        "api-port" => "4028",
        "expiry" => "120",
        "failover-only" => true,
        "log" => "5",
        "no-pool-disable" => true,
        "queue" => "2",
        "scan-time" => "60",
        "worktime" => true,
        "shares" => "0",
        "kernel-path" => "/usr/local/bin",
        "api-allow" => "W:127.0.0.1",
        "icarus-options" => "115200:1:1",
		"icarus-timing" => "3.0=100"
	);
	
	$configDIR = __DIR__.'/../config';
	$configFile = $configDIR.'/miner.config';
	
	if (!file_exists($configDIR)) {
    	mkdir($configDIR, 0777, true);
	}
	if (!file_exists($configFile)) {
    	touch($configFile);
	}
	
	file_put_contents($configFile, json_encode($config, JSON_UNESCAPED_SLASHES));
}
$app->get('/api/pools', function() use ($app) {
	if(checkAuthToken($app)){
		$phql = "SELECT * FROM Pool ORDER BY priority";
		$pools = $app->modelsManager->executeQuery($phql);
		$data = array();
		foreach($pools as $pool){
			$enabled = ($pool->enabled == "1") ? true : false;
			$data[] = array(
				'id' => $pool->id,
				'name'=>$pool->name,
				'url'=>$pool->url,
				'username' => $pool->username,
				'password' => $pool->password,
				'enabled' => $enabled,
				'quota'=> $pool->quota,
				'priority'=> $pool->priority
			);
		}
		echo json_encode($data);
	}
});
$app->get('/api/pools/{id:[0-9]+}', function($id) use ($app) {
	if(checkAuthToken($app)){
		$phql = "SELECT * FROM Pool WHERE id = :id:";
		$pool = $app->modelsManager->executeQuery($phql, array(
			'id'=>$id
		))->getFirst();
		
		$response = new Phalcon\Http\Response();
		
		if($pool == false){
			$response->setJsonContent(array());
		}else{
			$enabled = ($pool->enabled == "1") ? true : false;
			$response->setJsonContent(array(
	            'pool' => array(
	                'id' => $pool->id,
					'name'=>$pool->name,
					'url'=>$pool->url,
					'username' => $pool->username,
					'password' => $pool->password,
					'enabled' => $enabled,
					'quota'=> $pool->quota,
					'priority'=> $pool->priority
	            )
	        ));
		}
		return $response;
	}
});
$app->post('/api/pools', function() use ($app) {
	if(checkAuthToken($app)){
		$body = $app->request->getJsonRawBody();
		$pool = $body->pool;
	
	    $phql = "INSERT INTO Pool (name, url, username, password, enabled, priority) VALUES (:name:, :url:, :username:, :password:, :enabled:, :priority:)";
	    $pass = ($pool->password != null) ? $pool->password : "";
	    $enabled = $pool->enabled == true ? "1" : "0";
	    $status = $app->modelsManager->executeQuery($phql, array(
	        'name' => $pool->name,
	        'url' => $pool->url,
	        'username' => $pool->username,
	        'password' => $pass,
	        'enabled' => $enabled,
	        'quota'=> $pool->quota,
	        'priority'=> $pool->priority
	    ));
	
	    $response = new Phalcon\Http\Response();
	    if ($status->success() == true) {
	        $response->setStatusCode(201, "Created");
	        $pool->id = $status->getModel()->id;
	        $response->setJsonContent($pool);
	    } else {
	        $response->setStatusCode(409, "Conflict");
	        $errors = array();
	        foreach ($status->getMessages() as $message) {
	            $errors[] = $message->getMessage();
	        }
	        $response->setJsonContent(array('status' => 'ERROR', 'messages' => $errors));
	    }
	    
	    write_pools_config_to_file($app);
	    return $response;
	}
});
$app->put('/api/pools/{id:[0-9]+}', function($id) use ($app) {
	if(checkAuthToken($app)){
		$body = $app->request->getJsonRawBody();
		$pool = $body->pool;
		$pass = ($pool->password != null) ? $pool->password : "";
		$phql = "UPDATE Pool SET name = :name:, url = :url:, username = :username:, password = :password:, enabled = :enabled:, priority = :priority: WHERE id = :id:";
		$enabled = $pool->enabled == true ? "1" : "0";
		$status = $app->modelsManager->executeQuery($phql, array(
	        'id' => $id,
	        'name' => $pool->name,
	        'url' => $pool->url,
	        'username' => $pool->username,
	        'password' => $pass,
	        'enabled' => $enabled,
	        'quota'=> $pool->quota,
	        'priority'=> $pool->priority
	    ));
	    $response = new Phalcon\Http\Response();
	    if ($status->success() == true) {
	    	$pool->id = $id;
	        $response->setJsonContent($pool);
	    } else {
	        $response->setStatusCode(409, "Conflict");
	        $errors = array();
	        foreach ($status->getMessages() as $message) {
	            $errors[] = $message->getMessage();
	        }
	        $response->setJsonContent(array('status' => 'ERROR', 'messages' => $errors));
	    }
	    write_pools_config_to_file($app);
	    return $response;
	}
});
$app->delete('/api/pools/{id:[0-9]+}', function($id) use ($app) {
	if(checkAuthToken($app)){
		$phql = "DELETE FROM Pool WHERE id = :id:";
	    $status = $app->modelsManager->executeQuery($phql, array(
	        'id' => $id
	    ));
	
	    $response = new Phalcon\Http\Response();
	    if ($status->success() == true) {
	        $response->setJsonContent(array('status' => 'OK'));
	    } else {
	        $response->setStatusCode(409, "Conflict");
	        $errors = array();
	        foreach ($status->getMessages() as $message) {
	            $errors[] = $message->getMessage();
	        }
	        $response->setJsonContent(array('status' => 'ERROR', 'messages' => $errors));
	    }
	    write_pools_config_to_file($app);
	    return $response;
	}
});



// ===================================================================== 
//   SETTINGS ROUTES:
// ===================================================================== 
function write_setting_to_file($app, $type, $setting){
	$configDIR = __DIR__.'/../config';
	$customFile = $configDIR.'/custom.config';
	$argsFile = $configDIR.'/miner.args';

	$script = __DIR__.'/analytics.sh';
	if($type === 'MINER_CONFIG'){
		file_put_contents($customFile, $setting->config);
	}else if($type === 'POOL_STRATEGY'){
		$miner_args = "";
		if($setting->strategy === 'ROUND_ROBIN'){
			$miner_args = "--round-robin";
		}else if($setting->strategy === 'ROTATE'){
			$interval = $setting->interval;
			$miner_args = "--rotate ".$interval;
		}else if($setting->strategy === 'LOAD_BALANCE'){
			$miner_args = "--load-balance";
		}else if($setting->strategy === 'BALANCE'){
			$miner_args = "--balance";
		}
		file_put_contents($argsFile, $miner_args);
		write_pools_config_to_file($app);
	}else if($type === 'ANALYTICS_CONFIG'){
			$enabled = $setting->dataCollectionEnabled;
			$seconds = $setting->dataInterval;
			
			if($enabled){
				$schedule = array();
				if($seconds < 60){
					$repeats = 60 / $seconds;
					$schedule = array(
						"*/1",
						"*",
						"*",
						"*",
						"*"
					);
					$script .= " ".$seconds." ".$repeats;
				}else{
					$minutes = floor($seconds / 60);
					$schedule = array(
						"*/".$minutes,
						"*",
						"*",
						"*",
						"*"
					);
				}
				if(count($schedule) == 5){
					write_crontab_config($schedule, $script, false);
				}	
			}else{
				clear_crontab_config();
			}
	}
}

$app->get('/api/settings[/]?{type:[a-zA-Z_]*}', function() use ($app) {
	if(checkAuthToken($app)){
		$type = $app->request->get('type');
	
		$phql = "SELECT * FROM Setting";
		if($type){
			$phql .= " WHERE type = '".$type."'";
		}
		$settings = $app->modelsManager->executeQuery($phql);
		$data = array();
		foreach($settings as $setting){
			$value_json = json_decode(stripslashes($setting->value), true);
			$data[] = array(
				'id' => $setting->id,
				'type' => $setting->type,
				'value' => $value_json
			);
		}
		echo json_encode($data);
	}
});
$app->get('/api/settings/{id:[0-9]+}', function($id) use ($app) {
	if(checkAuthToken($app)){
		$phql = "SELECT * FROM Setting WHERE id = :id:";
		$setting = $app->modelsManager->executeQuery($phql, array(
			'id'=>$id
		))->getFirst();
		
		$response = new Phalcon\Http\Response();
		
		if($setting == false){
			$response->setJsonContent(array());
		}else{
			$value_json = json_decode(stripslashes($setting->value), true);
			$response->setJsonContent(array(
	            'setting' => array(
	                'id' => $setting->id,
	                'type' => $setting->type,
	                'value'=>$value_json
	            )
	        ));
		}
		return $response;
	}
});
$app->post('/api/settings', function() use ($app) {
	if(checkAuthToken($app)){
		$body = $app->request->getJsonRawBody();
		$setting = $body->setting;
		$setting_value = json_encode($setting->value);
		$phql = "INSERT INTO Setting (type, value) VALUES (:type:, :value:)";
		$status = $app->modelsManager->executeQuery($phql, array(
	        'type' => $setting->type,
	        'value' => $setting_value
	    ));
	
	    $response = new Phalcon\Http\Response();
	    if ($status->success() == true) {
	        $response->setStatusCode(201, "Created");
	        $setting->id = $status->getModel()->id;
	        $response->setJsonContent($setting);
	        write_setting_to_file($app, $setting->type, $setting->value);
	    } else {
	        $response->setStatusCode(409, "Conflict");
	        $errors = array();
	        foreach ($status->getMessages() as $message) {
	            $errors[] = $message->getMessage();
	        }
	        $response->setJsonContent(array('status' => 'ERROR', 'messages' => $errors));
	    }
	
	    return $response;
	}
});
$app->put('/api/settings/{id}', function($id) use ($app) {
	if(checkAuthToken($app)){
		$body = $app->request->getJsonRawBody();
		$setting = $body->setting;
		$setting_value = json_encode($setting->value);
	    $phql = "UPDATE Setting SET type = :type:, value = :value: WHERE id = :id:";
	    $status = $app->modelsManager->executeQuery($phql, array(
	        'id' => $id,
	        'type' => $setting->type,
	        'value' => $setting_value
	    ));
		$response = new Phalcon\Http\Response();
	    if ($status->success() == true) {
	    	$setting->id = $id;
			$response->setJsonContent($setting);
			write_setting_to_file($app, $setting->type, $setting->value);
	    } else {
	        $response->setStatusCode(409, "Conflict");
	        $errors = array();
	        foreach ($status->getMessages() as $message) {
	            $errors[] = $message->getMessage();
	        }
	        $response->setJsonContent(array('status' => 'ERROR', 'messages' => $errors));
	    }
	    return $response;
	}
});




// ===================================================================== 
//   TRENDS ROUTES:
// ===================================================================== 
$app->get('/api/trends[/]?{type:[A-Z_]*}?{startDate}?{endDate}', function() use ($app) {
	if(checkAuthToken($app)){
		$type = $app->request->get('type');
		$start = $app->request->get('startDate');
		$end = $app->request->get('endDate');
		
		$phql = "SELECT * FROM Trend";
		if($type){
			$phql .= " WHERE type = '".$type."'";
		}
		if($start && $end){
			$phql .= " AND ((CAST(collected AS INTEGER) > ".$start." ) AND (CAST(collected AS INTEGER) < ".$end." ))";
		}
		
		$phql.=' ORDER BY collected ASC';
		
		$trends = $app->modelsManager->executeQuery($phql);
		$data = array();
		foreach($trends as $trend){
			$data[] = array(
				'id' => $trend->id,
				'collected'=>$trend->collected,
				'type' => $trend->type,
				'value' => json_decode($trend->value),
				'deviceID' => $trend->deviceID,
				'deviceName' => $trend->deviceName,
				'deviceEnabled' => $trend->deviceEnabled
			);
		}
		echo json_encode($data);
	}
});

$app->get('/api/trends/collect', function() use ($app){
		$cgMinerAPI = new CGMinerAPI();
		$summaryTrend = $cgMinerAPI->request('summary');
		$devTrends = $cgMinerAPI->request('devs');

		$phql = "INSERT INTO Trend (type, value, collected, deviceID, deviceName, deviceEnabled) VALUES (:type:, :value:, :collected:, :deviceID:, :deviceName:, :deviceEnabled:)";
		
		if($summaryTrend != null && $summaryTrend != 'ERROR' && count($summaryTrend) > 0){
			$summary_str = json_encode($summaryTrend);
			$app->modelsManager->executeQuery($phql, array(
				'type'=>'SUMMARY',
				'value'=>$summary_str,
				'collected'=>time(),
				'deviceID'=>'SUMMARY',
				'deviceName'=>'SUMMARY',
				'deviceEnabled'=>null
			));	
		}
		if($devTrends != null && $devTrends != 'ERROR' && count($devTrends) > 0){
			for($i=0; $i<count($devTrends); $i++){
				$dev = (object) $devTrends[$i];
				$dev_str = json_encode($dev);
				$Enabled = $dev->Enabled;
				$Name = $dev->DeviceName;
				$ID = $dev->DeviceID;
				$app->modelsManager->executeQuery($phql, array(
					'type'=>'MINER',
					'value'=>$dev_str,
					'collected'=>time(),
					'deviceID'=>$ID,
					'deviceName'=>$Name,
					'deviceEnabled'=>$Enabled
				));
			}	
		}
		echo 'Inserted summary and miner trends to DB';
});

// ===================================================================== 
//   OTHER ROUTES:
// ===================================================================== 
$app->get('/api/reboot', function() use ($app){
	if(checkAuthToken($app)){
		$reboot = __DIR__.'/../reboot.sh';
		exec('sudo '.$reboot);
		echo 'REBOOTING';
	}

});
$app->get('/api/notify', function() use ($app){
	$phql = "SELECT * FROM Setting WHERE type = :type: || type = :type2: || type = :type3:";
	$settings = $app->modelsManager->executeQuery($phql, array(
		'type'=>'PERFORMANCE_ALERT',
		'type2'=>'EMAIL_NOTIFICATION',
		'type2'=>'DEVICE_INFO',
	));
	
	$perf_config = null;
	$info_config = null;
	$notify_config = null;
	
	foreach($settings as $setting){
		if($setting->type == 'PERFORMANCE_ALERT'){
			$perf_config = json_decode($setting->value);
		}else if($setting->type == 'EMAIL_NOTIFICATION'){
			$notify_config = json_decode($setting->value);
		}else if($setting->type == 'DEVICE_INFO'){
			$info_config = json_decode($setting->value);
		}
	}
		
	$response = new Phalcon\Http\Response();
		
	if($notify_config == false || $perf_config == false){
		$response->setJsonContent("<ERROR>: No PERFORMANCE_ALERT email configuration found.");
	}else{
		$device_name = ($info_config && $info_config->name) ? $info_config->name : 'Pi Miner';
		$auth = $notify_config->smtpAuth;
		$auth = (!$auth || $auth == 'false' || $auth == 0) ? false : true;
		$config = array(
			'to'=>$notify_config->toAddress,
			'subject'=>'['.$device_name.'] - Performance Alert',
			'body'=>'HELLEAU WHIRRLED!',
			'host'=>$notify_config->smtpServer,
			'username'=>$notify_config->smtpAuthUsername,
			'password'=>$notify_config->smtpAuthPassword,
			'port'=>$notify_config->smtpAuthPort
		);
		$mailer = new Mailer($config, $auth);
		$mailer->send();
	}
});
$app->notFound(function () use ($app) {
    $app->response->setStatusCode(404, "Not Found")->sendHeaders();
    echo 'That api endpoint does not seem to exist :(';
});


$app->handle();
