<?php

use Phalcon\Mvc\Model,
    Phalcon\Mvc\Model\Message,
    Phalcon\Mvc\Model\Validator\InclusionIn,
    Phalcon\Mvc\Model\Validator\Uniqueness;

class User extends Model
{

	public $id;
	public $username;
	public $password;
	
	public function getSource(){
		return 'users';
	}
	
	

}
