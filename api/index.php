<?php
require_once(__DIR__.'/cgminer-api.php');
// Use Loader() to autoload our model
$loader = new \Phalcon\Loader();
$loader->registerDirs(array(
    __DIR__ . '/models/'
))->register();

$di = new \Phalcon\DI\FactoryDefault();
//Set up the database service
$di->set('db', function(){
	$config = array(
		"dbname" => __DIR__ ."/db/pi-miner.db"
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


/*$app->before(function() use ($app) {
    if ($app->session->get('token') == false) {
        return false;
    }
    return true;
});*/


$app->get('[/]', function(){
	file_get_contents('./index.html');
});

$app->get('/cgminer/{command}', function($command){
	$cgMinerAPI = new CGMinerAPI();
	$result = $cgMinerAPI->request($command);
	echo json_encode($result);
});

$app->post('/login', function() use ($app){
		$salty = "GRESELIGHTNING_1211";
		$username = $app->request->get('username');
		$password = $app->request->get('password');
		$phql = "SELECT * FROM User WHERE username = :username: AND password = :password:";
		
		$user = $app->modelsManager->executeQuery($phql, array(
			'username'=>$username,
			'password'=>$password
		))->getFirst();	
		
		$login_result = 'FAILURE';
		$token = null;
		if($user){
			$login_result = 'SUCCESS';
			$token = $app->session->get('token');
			if(!$token){
				$token = md5($username.$salty);
				$app->session->set('token', $token);
			}
			$user = array('username'=>$user->username, 'id'=>$user->id);
		}else{
			$user = null;
		}
		$response = new Phalcon\Http\Response();
		$response->setJsonContent(array('result' => $login_result, 'token'=>$token, 'user'=>$user));
		return $response;
});
$app->get('/logout', function() use ($app){
	$app->session->destroy();
	return false;
});


// ===================================================================== 
//   USERS ROUTES:
// ===================================================================== 
$app->get('/users', function() use ($app) {
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
});
$app->get('/users/{id:[0-9]+}', function($id) use ($app) {
	$phql = "SELECT * FROM User WHERE id = :id:";
	$user = $app->modelsManager->executeQuery($phql, array(
		'id'=>$id
	))->getFirst();
	
	$response = new Phalcon\Http\Response();
	
	if($user == false){
		$response->setJsonContent(array('users'=>array()));
	}else{
		$response->setJsonContent(array(
            'user' => array(
                'id' => $user->id,
                'username' => $user->username,
                'password'=>null
            )
        ));
	}
	return $response;
});
$app->put('/users/{id:[0-9]+}', function($id) use ($app) {
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
});



// ===================================================================== 
//   POOLS ROUTES:
// ===================================================================== 
$app->get('/pools', function() use ($app) {
	$phql = "SELECT * FROM Pool";
	$pools = $app->modelsManager->executeQuery($phql);
	$data = array();
	foreach($pools as $pool){
		$data[] = array(
			'id' => $pool->id,
			'name'=>$pool->name,
			'url'=>$pool->url,
			'username' => $pool->username,
			'password' => $pool->password,
			'enabled' => $pool->enabled
		);
	}
	echo json_encode($data);
});
$app->get('/pools/{id:[0-9]+}', function($id) use ($app) {
	$phql = "SELECT * FROM Pool WHERE id = :id:";
	$pool = $app->modelsManager->executeQuery($phql, array(
		'id'=>$id
	))->getFirst();
	
	$response = new Phalcon\Http\Response();
	
	if($pool == false){
		$response->setJsonContent(array());
	}else{
		$response->setJsonContent(array(
            'pool' => array(
                'id' => $pool->id,
				'name'=>$pool->name,
				'url'=>$pool->url,
				'username' => $pool->username,
				'password' => $pool->password,
				'enabled' => $pool->enabled
            )
        ));
	}
	return $response;
});
$app->post('/pools', function() use ($app) {
	$body = $app->request->getJsonRawBody();
	$pool = $body->pool;

    $phql = "INSERT INTO Pool (name, url, username, password, enabled) VALUES (:name:, :url:, :username:, :password:, :enabled:)";

    $status = $app->modelsManager->executeQuery($phql, array(
        'name' => $pool->name,
        'url' => $pool->url,
        'username' => $pool->username,
        'password' => $pool->password,
        'enabled' => $pool->enabled
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
    return $response;
});
$app->put('/pools/{id:[0-9]+}', function($id) use ($app) {
	$body = $app->request->getJsonRawBody();
	$pool = $body->pool;
	
    $phql = "UPDATE Pool SET name = :name:, url = :url:, username = :username:, password = :password:, enabled = :enabled: WHERE id = :id:";
    $status = $app->modelsManager->executeQuery($phql, array(
        'id' => $id,
        'name' => $pool->name,
        'url' => $pool->url,
        'username' => $pool->username,
        'password' => $pool->password,
        'enabled' => $pool->enabled
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
    return $response;
});
$app->delete('/pools/{id:[0-9]+}', function($id) use ($app) {
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
    return $response;
});



// ===================================================================== 
//   SETTINGS ROUTES:
// ===================================================================== 
$app->get('/settings[/]?{type:[A-Z_]*}', function() use ($app) {
	
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
});
$app->get('/settings/{id:[0-9]+}', function($id) use ($app) {
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
});
$app->post('/settings', function() use ($app) {
	$body = $app->request->getJsonRawBody();
	$setting = $body->setting;
	$setting_value = addslashes(json_encode($setting->value));
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
    } else {
        $response->setStatusCode(409, "Conflict");
        $errors = array();
        foreach ($status->getMessages() as $message) {
            $errors[] = $message->getMessage();
        }
        $response->setJsonContent(array('status' => 'ERROR', 'messages' => $errors));
    }

    return $response;
});
$app->put('/settings/{id}', function($id) use ($app) {
	$body = $app->request->getJsonRawBody();
	$setting = $body->setting;
	$setting_value = addslashes(json_encode($setting->value));
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
    } else {
        $response->setStatusCode(409, "Conflict");
        $errors = array();
        foreach ($status->getMessages() as $message) {
            $errors[] = $message->getMessage();
        }
        $response->setJsonContent(array('status' => 'ERROR', 'messages' => $errors));
    }
    return $response;
});




// ===================================================================== 
//   TRENDS ROUTES:
// ===================================================================== 
$app->get('/trends[/]?{type:[A-Z_]*}?{startDate}?{endDate}', function() use ($app) {
	
	$type = $app->request->get('type');
	$start = $app->request->get('startDate');
	$end = $app->request->get('endDate');
	
	$phql = "SELECT * FROM Trend";
	if($type){
		$phql .= " WHERE type = '".$type."'";
	}
	$trends = $app->modelsManager->executeQuery($phql);
	$data = array();
	foreach($trends as $trend){
		$data[] = array(
			'id' => $trend->id,
			'date'=>$trend->date,
			'type' => $trend->type,
			'value' => $trend->value,
			'deviceID' => $trend->deviceID,
			'deviceName' => $trend->deviceName,
			'deviceEnabled' => $trend->deviceEnabled
		);
	}
	echo json_encode($data);
});



// ===================================================================== 
//   OTHER ROUTES:
// ===================================================================== 
$app->notFound(function () use ($app) {
    $app->response->setStatusCode(404, "Not Found")->sendHeaders();
    echo 'That api endpoint does not seem to exist :(';
});


$app->handle();