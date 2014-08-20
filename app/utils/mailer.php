<?php
require_once "Mail.php";

class Mailer{

	public $auth = false;
	public $to;
	public $from;
	public $subject;
	public $body;
	public $host;
	public $username;
	public $password;
	public $port;

	function __construct($settings_array = array(), $auth = false){
		$this->auth = $auth;
		$this->to = $settings_array['to'];
		$this->from = $settings_array['from'];
		$this->subject = $settings_array['subject'];
		$this->body = $settings_array['body'];
		$this->host = 'ssl://'.$settings_array['host'];
		if($auth){
			$this->username = $settings_array['username']; 
			$this->password = $settings_array['password'];
			$this->port = $settings_array['port'];
		}
	}
	
	public function send(){
		$headers = array ('From' => $this->from,   'To' => $this->to,   'Subject' => $this->subject);
		$config = array (
			'host' => $this->host, 
			'auth' => $this->auth
			);
		if($this->auth){
			$config['port'] = $this->port;
			$config['username'] = $this->username;
			$config['password'] = $this->password;
		}
		
		$smtp = Mail::factory('smtp', $config);
		$mail = $smtp->send($this->to, $headers, $this->body);  
		if (PEAR::isError($mail)) {   
			echo("<p>" . $mail->getMessage() . "</p>");  
		} else {   
			echo("<p>Message successfully sent!</p>");  
		}
	}
}

function test_mailer(){
	$test = array(
		'to'=>'johngrese@me.com',
		'from'=>'johngrese@me.com',
		'subject'=>'PI MAIL TEST',
		'body'=>'Hi John, the test succeeded!',
		'host'=>'smtp.mail.me.com',
		'username'=>'johngrese@me.com',
		'password'=>'$chroederRock5',
		'port'=>"587"
	);
	$mailer = new Mailer($test, true);
	$mailer->send();
}
