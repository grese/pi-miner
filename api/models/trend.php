<?php

use Phalcon\Mvc\Model,
    Phalcon\Mvc\Model\Message,
    Phalcon\Mvc\Model\Validator\InclusionIn,
    Phalcon\Mvc\Model\Validator\Uniqueness;

class Trend extends Model
{
	
	public $id;
	public $date;
	public $type;
	public $value;
	public $deviceID;
	public $deviceName;
	public $deviceEnabled;
	
	public function validation(){
		$this->validate(new InclusionIn(
            array(
                "field"  => "type",
                "domain" => array("SUMMARY", "MINER")
            )
        ));
        
        if ($this->validationHasFailed() == true) {
            return false;
        }
	}
	
	public function getSource(){
		return 'trends';
	}

}