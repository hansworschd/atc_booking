<?php 

	putenv('GDFONTPATH=' . realpath('.'));

	$MASTER_DATE = new DateTime();
	if(isset($_GET['nextWeek'])){
		$MASTER_DATE->add(new DateInterval('P7D'));
	}		

	include 'booking.php';
	
	/**
	* Get all bookings from specific stations
	* @return list with bookings from stations
	*/
	function get_bookings($stations){
		$xml = simplexml_load_file('http://vatbook.euroutepro.com/xml2.php');
			
		$atc_bookings = $xml->atcs->booking;
		$requested_bookings = [];
		for($i = 0; $i<count($atc_bookings); $i++){
			$station = $atc_bookings[$i]->callsign;
			for($j = 0; $j<count($stations); $j++){
				// check if booking station matches with list
				if(strpos($station,$stations[$j]) !== false){
					$requested_bookings[] = new Booking($atc_bookings[$i]->name, $atc_bookings[$i]->time_start, $atc_bookings[$i]->time_end, $atc_bookings[$i]->callsign);
					break;
				}
			}
		}
		
		return $requested_bookings;
	}
	
	function write_string($im, $font, $x, $y, $string, $color){
		imagettftext ($im , 20 , 0 , $x , $y , $color ,"MyriadProRegular" , $string );
	}
	
	function next_station_is_same($currentElement, $nextElement){
		if(substr($currentElement,0,4) === substr($nextElement,0,4)){
			return true;
		}
		return false;
	}
	
	$edff_main_stations = ["EDGG_CTR", "EDGG_E_CTR", "EDDF_N_APP", "EDDF_F_APP", "EDDF_U_APP", "EDDF_TWR", "EDDF_W_TWR", "EDDF_C_GND", "EDDF_DEL", "EDDS_N_APP", "EDDS_F_APP", "EDDS_TWR", "EDDS_GND"];
	
	$booked_stations = get_bookings($edff_main_stations);
	
	$booking_matrix = [];
	
	// Iterate over the stations and create a booking matrix
	for($i = 0; $i<count($edff_main_stations); $i++){
		// First row contains the station
		$booking_matrix[$i][0] = $edff_main_stations[$i]; 
		$day = clone $MASTER_DATE;
		// Loop over the next days
		for($j = 1; $j<8; $j++){
			$found_booking = 0;
			
			$cellObject = [];
			
			for($k = 0; $k<count($booked_stations); $k++){
				$booking_date_start = DateTime::createFromFormat('Y-m-d H:i:s', $booked_stations[$k]->time_start);
				$booking_date_end = DateTime::createFromFormat('Y-m-d H:i:s', $booked_stations[$k]->time_end);
				
				if($booking_date_start->format("Y-m-d") === $day->format("Y-m-d") && $edff_main_stations[$i] == $booked_stations[$k]->callsign){
					$cellObject[] = $booked_stations[$k]->abbreviation." ".$booking_date_start->format("H")."-".$booking_date_end->format("H");
					$found_booking++;
				}
			}
			if($found_booking === 0){
				$cellObject[] = "open";
			}
			
			
			
			
			$booking_matrix[$i][$j] = $cellObject;
	
			$day->add(new DateInterval("P1D"));
		}
	}
	
	
	$imageHeight = 500;
	$imageWidth = 800;
	
	
	$im = imagecreate($imageWidth, $imageHeight);
	$background_color = imagecolorallocate($im, 240, 240, 240);
	$color_withe = imagecolorallocate($im, 255, 255, 255);
	$color_black = imagecolorallocate($im, 0, 0, 0);
	$color_gray = imagecolorallocate($im, 210, 210, 210);
	$color_red = imagecolorallocate($im, 190, 40, 40);
	
	$row = 1;
	$lineHeight = 13;
	$cell_width = 104;
	
	
	$day = clone $MASTER_DATE;
	for($i = 1; $i<8; $i++){
		write_string($im, 3, $cell_width*$i, $row*$lineHeight, $day->format("D d.m."), $color_black);
		$day->add(new DateInterval('P1D'));
	}
	$row = 2;
	imageline($im, 0, $row*$lineHeight+3, $imageWidth, $row*$lineHeight+3, $color_gray );
	
	$row = 3;
	
	for($i = 0; $i<count($booking_matrix); $i++){
		$day = clone $MASTER_DATE;
		write_string($im, 3, 5, $lineHeight*$row, $booking_matrix[$i][0], $color_black);
		$maxHeight = 1;
		for($j = 1; $j<count($booking_matrix[$i]); $j++){
			
			if(count($booking_matrix[$i][$j]) > $maxHeight){
				$maxHeight = count($booking_matrix[$i][$j]);
			}
			for($k = 0; $k<count($booking_matrix[$i][$j]); $k++){
				$color = $color_gray;
				if($booking_matrix[$i][$j][$k] !== "open"){
					$color = $color_black;
				}
				if($day->format("N") === "4" && $booking_matrix[$i][$j][$k] === "open"){
					if(substr($booking_matrix[$i][0],0,4) === "EDDS" || substr($booking_matrix[$i][0],0,6) === "EDGG_E"){
						$color = $color_red;
					}
				}
				if($day->format("N") === "5" && $booking_matrix[$i][$j][$k] === "open"){
					if(substr($booking_matrix[$i][0],0,4) === "EDDF" || substr($booking_matrix[$i][0],0,6) === "EDGG_E"){
						$color = $color_red;
					}
				}
				write_string($im, 3, $cell_width*$j, $lineHeight*$row, $booking_matrix[$i][$j][$k], $color);
				$row++;
			}
			$row = $row - count($booking_matrix[$i][$j]);
			$day->add(new DateInterval('P1D'));
		}
		$row = $row + $maxHeight;
		if(isset($booking_matrix[$i+1]) && !next_station_is_same($booking_matrix[$i][0],$booking_matrix[$i+1][0])){
			$row += 2;
		}
	}
	
	$row = $row + 3;
	
	$all_users = [];
	for($i = 0; $i<count($booked_stations); $i++){
		$all_users[] = ["name" => $booked_stations[$i]->name, "abbreviation" => $booked_stations[$i]->abbreviation ];
	}
	
	$all_users = array_map("unserialize", array_unique(array_map("serialize", $all_users)));
	array_multisort($all_users);
	

	$column = 0;
	
	for($i = 0; $i<count($all_users); $i++){
		write_string($im, 3, $column*250+5, $lineHeight*$row, $all_users[$i]['abbreviation'].": ".$all_users[$i]['name'], $color_black);
		if($column === 2){
			$column = 0;
			$row++;
		}
		else{
			$column++;
		}
	}
	
	
	//imagestring($im, 1, 5, 5, "Ein Test-String", $text_color);
	
	header('Content-type: image/gif');
	imagegif($im);
?>