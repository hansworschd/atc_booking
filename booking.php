<?php

class Booking
{
	var $name;
	var $abbreviation;
	var $time_start;
	var $time_end;
	var $callsign;
	var $training;
	var $event;

	function __construct($name, $time_start, $time_end, $callsign, $training, $event)
	{
		$this->name = $name . "";
		$this->time_start = substr($time_start, 0, -8);
		$this->time_end = substr($time_end, 0, -8);
		$this->callsign = $callsign;
		$this->abbreviation = $this->create_abbreviation($name);
		$this->training = $training;
		$this->event = $event;
	}

	function create_abbreviation($name)
	{
		$str_arr = explode(" ", $name);
		return substr($str_arr[0], 0, 3) . substr(end($str_arr), 0, 3);
	}
}

?>
