<?php

use Phalcon\Mvc\Model,
    Phalcon\Mvc\Model\Message,
    Phalcon\Mvc\Model\Validator\InclusionIn,
    Phalcon\Mvc\Model\Validator\Uniqueness;

class Trend extends Model
{
	
	public $id;
	public $collected;
	public $type;
	public $value;
	public $deviceID;
	public $deviceName;
	public $deviceEnabled;
	
	public function getSource(){
		return 'trends';
	}

}