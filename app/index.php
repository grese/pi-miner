<?php
require_once(__DIR__.'/utils/helpers.php');
require_once(__DIR__.'/utils/auth.php');
require_once(__DIR__.'/utils/cgminer-api.php');

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
		$cgMinerAPI = new CGMinerAPI();
		$result = $cgMinerAPI->request($command);
		echo json_encode($result);	
	}
});

$app->post('/api/login', function() use ($app){
        $username = $app->request->get('username');
		$password = $app->request->get('password');
		$phql = "SELECT * FROM User WHERE username = :username: AND password = :password:";
		
		$user = $app->modelsManager->executeQuery($phql, array(
			'username'=>$username,
			'password'=>$password
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
				'password' => $user->password,
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
	    $phql = "UPDATE User SET username = :username:, password = :password: WHERE id = :id:";
	    $status = $app->modelsManager->executeQuery($phql, array(
	        'id' => $id,
	        'username' => $user->username,
	        'password' => $user->password,
	    ));
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
	$phql = "SELECT * FROM Pool WHERE enabled = 1";
	$pools = $app->modelsManager->executeQuery($phql);
	$data = array();
	foreach($pools as $pool){
		$url = preg_replace('#^https?://#', '', $pool->url);
		$data[] = array(
			'id' => $pool->id,
			'name'=>$pool->name,
			'url'=>$url,
			'username' => $pool->username,
			'password' => $pool->password,
			'enabled' => $pool->enabled
		);
	}
	$config = array(
		"pools"=> $data,
        "api-listen" => true,
        "api-port" => "4028",
        "expiry" => "120",
        "failover-only" => true,
        "log" => "5",
        "queue" => "2",
        "scan-time" => "60",
        "worktime" => true,
        "shares" => "0",
        "kernel-path" => "/usr/local/bin",
        "api-allow" => "W:127.0.0.1"
	);
	file_put_contents(__DIR__.'/../config/miner.config', json_encode($config));
}
$app->get('/api/pools', function() use ($app) {
	if(checkAuthToken($app)){
		$phql = "SELECT * FROM Pool";
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
				'enabled' => $enabled
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
					'enabled' => $enabled
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
	
	    $phql = "INSERT INTO Pool (name, url, username, password, enabled) VALUES (:name:, :url:, :username:, :password:, :enabled:)";
		$enabled = ($pool->enabled == true) ? "1" : "0";
	    $status = $app->modelsManager->executeQuery($phql, array(
	        'name' => $pool->name,
	        'url' => $pool->url,
	        'username' => $pool->username,
	        'password' => $pool->password,
	        'enabled' => $enabled
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
		$enabled = ($pool->enabled == true) ? "1" : "0";
		$phql = "UPDATE Pool SET name = :name:, url = :url:, username = :username:, password = :password:, enabled = :enabled: WHERE id = :id:";
		$status = $app->modelsManager->executeQuery($phql, array(
	        'id' => $id,
	        'name' => $pool->name,
	        'url' => $pool->url,
	        'username' => addslashes($pool->username),
	        'password' => $pool->password,
	        'enabled' => $enabled
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
function write_setting_to_file($type, $setting){
	$config_DIR = __DIR__.'/../config';

	$script = __DIR__.'/analytics.sh';
	if($type === 'MINER_CONFIG'){
		file_put_contents(__DIR__.'/../config/custom.config', $setting->config);
	}else if($type === 'ANALYTICS_CONFIG'){
			$enabled = $setting->dataCollectionEnabled;
			$seconds = $setting->dataInterval;
			
			if($enabled){
				if($seconds <= 60){
					$repeats = 60 / $seconds;
					$cron = "*/1 * * * * ".$script." ".$seconds." ".$repeats;
				}else{
					$minutes = floor($seconds / 60);
					$cron = $minutes." * * * * ".$script;	
				}	
			}else{
				$cron = "";
			}
		if (!file_exists($config_DIR)) {
			mkdir($config_DIR, 0755, true);
		}
		file_put_contents($config_DIR.'/trend.cron', $cron);
	}
}

$app->get('/api/settings[/]?{type:[A-Z_]*}', function() use ($app) {
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
	                'value'=>$setting->value
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
	        write_setting_to_file($setting->type, $setting->value);
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
			write_setting_to_file($setting->type, $setting->value);
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
			$unixStart = time($start);
			$unixEnd = time($end);
			$phql .= " AND (CAST(collected AS INTEGER) > ".$unixStart." ) AND (CAST(collected AS INTEGER) < ".$unixEnd." )";
		}
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
	if(checkAuthToken($app)){
		$cgMinerAPI = new CGMinerAPI();
		$summaryTrend = $cgMinerAPI->request('summary');
		$devTrends = $cgMinerAPI->request('devs');
		
		$phql = "INSERT INTO Trend (type, value, collected, deviceID, deviceName, deviceEnabled) VALUES (:type:, :value:, :collected:, :deviceID:, :deviceName:, :deviceEnabled:)";
		
		$summary_str = json_encode($summaryTrend);
		$app->modelsManager->executeQuery($phql, array(
			'type'=>'SUMMARY',
			'value'=>$summary_str,
			'collected'=>time(),
			'deviceID'=>null,
			'deviceName'=>null,
			'deviceEnabled'=>null
		));
			
			
		for($i=0; $i<count($devTrends); $i++){
			$dev = $devTrends[$i];
			$dev_str = json_encode($dev);
			$Enabled = $dev->Enabled;
			$Name = $dev->Name;
			$ID = $dev->ID;
			$app->modelsManager->executeQuery($phql, array(
				'type'=>'MINER',
				'value'=>$dev_str,
				'collected'=>time(),
				'deviceID'=>$ID,
				'deviceName'=>$Name,
				'deviceEnabled'=>$Enabled
			));
		}
		echo 'Inserted summary and miner trends to DB';
	}
});

// ===================================================================== 
//   OTHER ROUTES:
// ===================================================================== 
$app->notFound(function () use ($app) {
    $app->response->setStatusCode(404, "Not Found")->sendHeaders();
    echo 'That api endpoint does not seem to exist :(';
});


$app->handle();