<?php

use Phalcon\Mvc\Model,
    Phalcon\Mvc\Model\Message,
    Phalcon\Mvc\Model\Validator\InclusionIn,
    Phalcon\Mvc\Model\Validator\Uniqueness;

class Setting extends Model
{
	
	public $id;
	public $type;
	public $value;
	
	public function validation(){
		$this->validate(new InclusionIn(
            array(
                "field"  => "type",
                "domain" => array("EMAIL_NOTIFICATION", "DEVICE_INFO", "MINER_CONFIG", "ANALYTICS_CONFIG", "PERFORMANCE_ALERT")
            )
        ));
        
        if ($this->validationHasFailed() == true) {
            return false;
        }
    }
	
	public function getSource(){
		return 'settings';
	}

}