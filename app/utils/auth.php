<?php
function isLoggedIn($app, $token){
    return $app->session->get('token') == $token;
}
function loginUser($app, $user){
	$token_salt = "greselightning_1211";
    $token = md5($user->username.$token_salt);
    $app->session->set('token', $token);
    $app->session->set('user', (object) array('username'=>$user->username, 'id'=>$user->id));
    return $token;
}
function destroySession($app){
    if($app->session->isStarted()){
        $app->session->remove('user');
        $app->session->remove('token');
        $app->session->destroy();
    }
    return false;
}
function checkAuthToken($app){
	$token = $app->request->getHeader('APITOKEN');
	$sessionToken = $app->session->get('token');
	if($token != $sessionToken){
		$app->response->setStatusCode(401, "Unauthorized")->sendHeaders();	
	}else{
		return true;
	}
}