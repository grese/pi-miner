<?php

class CGMinerAPI{
	private function getsock($addr, $port){
		 $socket = null;
		 $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
		 if ($socket === false || $socket === null)
		 {
			$error = socket_strerror(socket_last_error());
			$msg = "socket create(TCP) failed";
			echo "ERR: $msg '$error'\n";
			return null;
		 }
		
		 $res = socket_connect($socket, $addr, $port);
		 if ($res === false)
		 {
			$error = socket_strerror(socket_last_error());
			$msg = "socket connect($addr,$port) failed";
			echo "ERR: $msg '$error'\n";
			socket_close($socket);
			return null;
		 }
		 return $socket;	
	}
	
	private function readsockline($socket){
		$line = '';
		 while (true)
		 {
			$byte = socket_read($socket, 1);
			if ($byte === false || $byte === '')
				break;
			if ($byte === "\0")
				break;
			$line .= $byte;
		 }
		 return $line;	
	}
	
	function request($cmd)
	{
	 $socket = $this->getsock('127.0.0.1', 4028);
	 if ($socket != null)
	 {
		socket_write($socket, $cmd, strlen($cmd));
		$line = $this->readsockline($socket);
		socket_close($socket);
	
		if (strlen($line) == 0)
		{
			echo "WARN: '$cmd' returned nothing\n";
			return $line;
		}
	
		if (substr($line,0,1) == '{')
			return json_decode($line, true);
	
		$data = array();
	
		$objs = explode('|', $line);
		
		
		foreach ($objs as $obj)
		{
			if (strlen($obj) > 0)
			{
				$items = explode(',', $obj);
				$item = $items[0];
				$id = explode('=', $items[0], 2);
				if (count($id) == 1 or !ctype_digit($id[1])){
					$name = $id[0];
				}else{
					$name = $id[0].$id[1];
				}
					
	
				if (strlen($name) == 0)
					$name = 'null';
	
				if (isset($data[$name]))
				{
					$num = 1;
					while (isset($data[$name.$num]))
						$num++;
					$name .= $num;
				}
	
				$counter = 0;
				foreach ($items as $item)
				{
					$id = explode('=', $item, 2);
					if (count($id) == 2)
						$data[$name][$id[0]] = $id[1];
					else
						$data[$name][$counter] = $id[0];
	
					$counter++;
				}
			}
		}
	
		if($cmd == 'summary'){
			return $this->parseSummary($data);
		}else if($cmd == 'devs' || $cmd == 'devdetails'){
			return $this->parseDevs($data, $cmd);
		}else{
			return $data;
		}
	 }
	
	 return null;
	}
	 
	 
	 private function parseSummary($result){
		 return $result['SUMMARY'];
	 }
	 
	 private function parseDevs($result, $cmd){
		 unset($result['STATUS']);
		 $devs = array();
		 foreach($result as $name => $dev){
			$dev['DeviceName'] = $dev['Name'].$dev['ID'];
		 	if($cmd == 'devdetails'){
			 	$dev['DeviceID'] = $dev['DEVDETAILS'];
		 	}else{
				$dev['DeviceID'] = $name;			 	
		 	}
			array_push($devs, $dev);
		 }
		 return $devs;
	 }
	  
}