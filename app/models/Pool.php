<?php

use Phalcon\Mvc\Model,
    Phalcon\Mvc\Model\Message,
    Phalcon\Mvc\Model\Validator\InclusionIn,
    Phalcon\Mvc\Model\Validator\Uniqueness;

class Pool extends Model
{
	
	public $id;
	public $name;
	public $url;
	public $username;
	public $password;
	public $enabled;
	
	public function getSource(){
		return 'pools';
	}

}
