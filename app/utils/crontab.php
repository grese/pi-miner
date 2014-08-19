<?php

function write_crontab_config($schedule, $command, $append=false){
	$configDIR = __DIR__.'/../../config';
	$cronFile = $configDIR.'/trend.cron';
	if(count($schedule) == 5){
		$jobs = '';
		$new_job .= implode(' ', $schedule).' '.$command.PHP_EOL;
		if($append){
			$old_jobs = shell_exec('crontab -l');
			$jobs = $old_jobs.$new_job;		
		}else{
			$jobs = $new_job;
		}
		
		if (!file_exists($configDIR)) {
			mkdir($configDIR, 0777, true);
		}
		file_put_contents($cronFile, $jobs);
		chmod($cronFile,0777);
		echo exec('crontab '.$cronFile);
	}
}

function clear_crontab_config(){
	$configDIR = __DIR__.'/../../config';
	$cronFile = $configDIR.'/trend.cron';
	if (!file_exists($configDIR)) {
		mkdir($configDIR, 0777, true);
	}
	file_put_contents($cronFile, "");
	chmod($cronFile,0777);
	echo exec('crontab '.$cronFile);
}