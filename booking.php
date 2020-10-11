<?php

class Booking{
	var $name;
	var $abbreviation;
	var $time_start;
	var $time_end;
	var $callsign;
	
	function __construct($name, $time_start, $time_end, $callsign)
	{
       $this->name = $name."";
       $this->time_start = $time_start;
       $this->time_end = $time_end;
       $this->time_end = $time_end;
       $this->callsign = $callsign;
	   $this->abbreviation = $this->create_abbreviation($name);
	}
	
	function create_abbreviation($name){
		$str_arr = explode(" ", $name);
		return substr($str_arr[0], 0, 3).substr(end($str_arr), 0, 3);
	}
}
 
?>