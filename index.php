<?php
$MASTER_DATE = new DateTime();
if (isset($_GET['nextWeek'])) {
	$MASTER_DATE->add(new DateInterval('P7D'));
}

include 'booking.php';




function isWeeklyPosition($weeklyObject, $day, $bookingStation){
	$isStation = false;
	foreach($weeklyObject as $event){
		if ($event->day === $day){
			foreach($event->booking as $station){
				$len = strlen($station);
				$isStation = $isStation || (substr($bookingStation, 0, $len) === $station);
			}
		}
	}
	return $isStation;
}


function do_curl_request($date_start)
{
	$curl = curl_init();
	$date_start_string = $date_start->format("d.m.Y");

	$date_end = $date_start->add(new DateInterval('P7D'));
	$date_end_string = $date_end->format("d.m.Y");

	$url = "https://vatsim-germany.org/api/booking/atc/daterange/$date_start_string/$date_end_string";
	curl_setopt_array($curl, array(
		CURLOPT_URL => $url,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_ENCODING => '',
		CURLOPT_MAXREDIRS => 10,
		CURLOPT_TIMEOUT => 0,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		CURLOPT_CUSTOMREQUEST => 'GET',
		CURLOPT_HTTPHEADER => array(
			'X-Requested-With: XMLHttpRequest',
			'Content-Type: application/json',
			'Sec-Fetch-Mode: cors',
			'Sec-Fetch-Site: same-origin',
			'Sec-Fetch-Dest: empty',
			'Accept-Language: de,en;q=0.9',
			'Accept: application/json, text/plain, */*'
		),
	));

	$response = curl_exec($curl);

	curl_close($curl);
	return $response;
}

/**
 * Get the bookings as json object
 * @param $date_start DateTime is the starting point
 * @return mixed
 */
function get_json($date_start)
{
	$fileName = "cache.json";
	if (isset($_GET['nextWeek'])) {
		$fileName = "cache_next_week.json";
	}

	if (file_exists($fileName)) {
		$fileTime = filemtime($fileName);
		if (time() >= $fileTime + 60) {
			// Request required
			$response_json = do_curl_request($date_start);
			file_put_contents($fileName, $response_json);
		}
	}
	else{
		$response_json = do_curl_request($date_start);
		file_put_contents($fileName, $response_json);
	}

	$file_content = file_get_contents($fileName);
	return json_decode($file_content);
}


/**
 * Get all bookings from specific stations
 * @param array containing atc stations
 * @param DateTime main start date
 * @return array with bookings from stations
 */
function get_bookings($stations, $start_date)
{
	$atc_bookings = get_json($start_date);

	$requested_bookings = [];
	foreach ($atc_bookings as $key => $booking) {
		if (isset($booking->station)) {
			$station = $booking->station->ident;
			for ($j = 0; $j < count($stations); $j++) {
				// check if booking station matches with list
				if (strpos($station, $stations[$j]) !== false) {
					$requested_bookings[] = new Booking(
						$booking->controller->firstname . " " . $booking->controller->lastname,
						$booking->starts_at,
						$booking->ends_at,
						$station,
						$booking->training,
						$booking->event
					);
					break;
				}
			}
		}

	}

	return $requested_bookings;
}

/**
 * Write a string with method; used to override later on
 * @param $im resource image
 * @param $font int font
 * @param $x int x-position
 * @param $y int y-positon
 * @param $string string content
 * @param $color int color
 */
function write_string($im, $font, $x, $y, $string, $color)
{
	putenv('GDFONTPATH=' . realpath('.'));
	if (file_exists(dirname(__FILE__) . "/MyriadProRegular.ttf")) {
		imagettftext($im, 10, 0, $x, $y, $color, dirname(__FILE__) . "/MyriadProRegular.ttf", $string);
	} else {
		imagestring($im, $font, $x, $y, $string, $color);
	}
}

/**
 * Check if next element is the same station-group (e.g. EDDF, EDDS,...)
 * @param $currentElement string the current callsing
 * @param $nextElement string the next callsign
 * @return bool true if is same
 */
function next_station_is_same($currentElement, $nextElement)
{
	if (substr($currentElement, 0, 4) === substr($nextElement, 0, 4)) {
		return true;
	}
	return false;
}

$stationsFile = fopen("allStations.csv", "r") or die("Unable to open file!");
$minStationsFile = fopen("minStations.csv", "r") or die("Unable to open file!");
$allStationsString = fgets($stationsFile);
$minStationsString = fgets($minStationsFile);


$bookingsString = file_get_contents("weekly.json") or die("Unable to open file!");
$weeklyBookings = json_decode($bookingsString);


$min_stations = explode(',',$minStationsString);
$main_stations =  explode(',',$allStationsString);

$booked_stations = get_bookings($main_stations, clone $MASTER_DATE);

/* Booking matrix contains all stations and bookings.. Still in progress!

[STATION[DATE[BOOKINGS]]

e.g.
<Station>   <---- Day 1-----------> <-- Day 2--> <-- Day 3-->...
[EDDF_TWR,[[12-14 LuEw,16-18 PaBue],[18-20 KaWe],[10-20 LuEw]], NEXT_STATION,[ ... ]]

Day cell is again array to handle multiple bookings each day
 */
$booking_matrix = [];

// Iterate over the stations and create a booking matrix
for ($i = 0; $i < count($main_stations); $i++) {
	// First row contains the station
	$booking_matrix[$i][0] = $main_stations[$i];
	$day = clone $MASTER_DATE;
	// Loop over the next days
	for ($j = 1; $j < 8; $j++) {
		$found_booking = false;

		$cellObject = [];

		// Check if there is a booking at the specific date
		for ($k = 0; $k < count($booked_stations); $k++) {
			$booking_date_start = DateTime::createFromFormat('Y-m-d\TH:i:s', $booked_stations[$k]->time_start);
			$booking_date_end = DateTime::createFromFormat('Y-m-d\TH:i:s', $booked_stations[$k]->time_end);
			// append all bookings as array
			if ($booking_date_start->format("Y-m-d") === $day->format("Y-m-d") && $main_stations[$i] == $booked_stations[$k]->callsign) {
				$cellContent = $booked_stations[$k]->abbreviation . " " . $booking_date_start->format("H") . "-" . $booking_date_end->format("H");
				$isTraining = $booked_stations[$k]->training;
				$isEvent = $booked_stations[$k]->event;
				$cellObject[] = ["content" => $cellContent, "isTraining" => $isTraining, "isEvent" => $isEvent];
				$found_booking = true;
			}
		}
		// If no booking found, set it to open
		if (!$found_booking) {
			if(in_array($main_stations[$i],$min_stations)){
				$cellObject[] = ["content" => "open", "isTraining" => false, "isEvent" => false];
			}
			else {
				$cellObject[] = ["content" => null, "isTraining" => null, "isEvent" => null];
			}

		}

		$booking_matrix[$i][$j] = $cellObject;
		$day->add(new DateInterval("P1D"));
	}
}

$tempBookingMatrix = [];
$additionalBookings = 0;
for($i = 0; $i<count($booking_matrix); $i++){
	$hasBookings = false;
	for($j = 1; $j<count($booking_matrix[$i]); $j++){
		for($k = 0; $k<count($booking_matrix[$i][$j]);$k++){
			if($booking_matrix[$i][$j][$k]['content'] !== null){
				$hasBookings = true;
				break;
			}
		}

	}
	if($hasBookings){
		$tempBookingMatrix[] = $booking_matrix[$i];
		for ($k = 1; $k<count($booking_matrix[$i]); $k++){
			if(count($booking_matrix[$i][$k]) > 1){
				$additionalBookings = $additionalBookings + (count($booking_matrix[$i][$k]));
			}
		}
	}
}

// Set array with all users
$all_users = [];
for ($i = 0; $i < count($booked_stations); $i++) {
	$all_users[] = ["name" => $booked_stations[$i]->name, "abbreviation" => $booked_stations[$i]->abbreviation];
}

$booking_matrix = $tempBookingMatrix;
$booking_groups = 0;


$imageHeight = 5000; //temporary height
$imageWidth = 820;

// Create images
$im = imagecreate($imageWidth, $imageHeight);
$background_color = imagecolorallocate($im, 242, 242, 242);
$color_withe = imagecolorallocate($im, 255, 255, 255);
$color_black = imagecolorallocate($im, 0, 0, 0);
$color_gray = imagecolorallocate($im, 210, 210, 210);
$color_red = imagecolorallocate($im, 190, 40, 40);
$color_blue = imagecolorallocate($im, 072, 118, 255);
$color_orange = imagecolorallocate($im, 255, 140, 0);

$row = 1;
$lineHeight = 20;
$vertOffset = 4;
$cell_width = 104;

// Set date header columns
$day = clone $MASTER_DATE;
for ($i = 1; $i < 8; $i++) {
	write_string($im, 3, $cell_width * $i, $row * $lineHeight, $day->format("D d.m."), $color_black);

	$day->add(new DateInterval('P1D'));
}
$row = 2;
imageline($im, 0, $row * $lineHeight + 3, $imageWidth, $row * $lineHeight + 3, $color_gray);

$row = 3;

// Draw matrix
for ($i = 0; $i < count($booking_matrix); $i++) {
	$day = clone $MASTER_DATE;
	// Write station
	imageline($im, 0, ($row) * $lineHeight, $imageWidth, ($row) * $lineHeight, $color_gray);
	imageline($im, 0, ($row+1) * $lineHeight, $imageWidth, ($row+1) * $lineHeight, $color_gray);

	write_string($im, 3, 5, ($lineHeight * $row) + $vertOffset, $booking_matrix[$i][0], $color_black);
	$maxHeight = 1;
	//Loop over the days from the stations
	for ($j = 1; $j < count($booking_matrix[$i]); $j++) {
		// Check if there was a cell with more than one booking (so maxHeight is > 1)
		if (count($booking_matrix[$i][$j]) > $maxHeight) {
			$maxHeight = count($booking_matrix[$i][$j]);
		}
		// Write bookings
		for ($k = 0; $k < count($booking_matrix[$i][$j]); $k++) {
			$color = $color_gray;
			if ($booking_matrix[$i][$j][$k] === null || $booking_matrix[$i][$j][$k]['content'] !== "open"){
				$color = $color_black;
			}
			if($booking_matrix[$i][$j][$k]['content'] === "open" && isWeeklyPosition($weeklyBookings, $day->format("N"), $booking_matrix[$i][0])){
				$color = $color_red;
			}

			if (isset($booking_matrix[$i][$j][$k]['isTraining']) && $booking_matrix[$i][$j][$k]['isTraining']) {
				$color = $color_blue;
			}

			if (isset($booking_matrix[$i][$j][$k]['isEvent']) && $booking_matrix[$i][$j][$k]['isEvent']) {
				$color = $color_orange;
			}
			// If is requested but open than make it to WANTED
			if ($color === $color_red && $booking_matrix[$i][$j][$k]['content'] == "open") {
				$booking_matrix[$i][$j][$k]['content'] = "WANTED";
			}

			write_string($im, 3, $cell_width * $j, ($lineHeight * $row) + $vertOffset, $booking_matrix[$i][$j][$k]['content'], $color);
			$row++;
		}
		// Set row back to the first row if the current station for the next day
		$row = $row - count($booking_matrix[$i][$j]);
		$day->add(new DateInterval('P1D'));
	}
	// Add the maximum rows from the specific station at the specific date to the current row to be in the correct row again
	$row = $row + $maxHeight;

	// if next station is set and has other airport or center designator add empty line to it
	if (isset($booking_matrix[$i + 1]) && !next_station_is_same($booking_matrix[$i][0], $booking_matrix[$i + 1][0])) {
		$row += 2;
	}
}
for ($i = 1; $i < 8; $i++) {
	imageline($im, ($cell_width *$i)-10 , 0, ($cell_width *$i)-10, $lineHeight*$row, $color_gray);
}	


write_string($im, 3, 5, ($lineHeight * $row) + $vertOffset, "Wanted", $color_red);
write_string($im, 3, $cell_width, ($lineHeight * $row) + $vertOffset, "Training", $color_blue);
write_string($im, 3, $cell_width * 2 , ($lineHeight * $row) + $vertOffset, "Event", $color_orange);


$row = $row + 3;



// Remove duplicated users and sort them by their first names
$all_users = array_map("unserialize", array_unique(array_map("serialize", $all_users)));
array_multisort($all_users);

$column = 0;

// Draw Users
for ($i = 0; $i < count($all_users); $i++) {
	write_string($im, 3, $column * 250 + 5, $lineHeight * $row, $all_users[$i]['abbreviation'] . ": " . $all_users[$i]['name'], $color_black);
	if ($column === 2) {
		$column = 0;
		$row++;
	} else {
		$column++;
	}
}

$row += 2;

$generated_time = new DateTime();
write_string($im, 2, 5, $lineHeight * $row, "Generated " . $generated_time->format("d.m.Y H:i:s")." ".count($booking_matrix), $color_gray);

$newHeight = ($row+1)*$lineHeight;
$dstim = imagecreatetruecolor($imageWidth, $newHeight);
imagecopyresized($dstim,$im,0,0,0,0,$imageWidth,$newHeight,$imageWidth,$newHeight);

// Set content to png and create image
header('Content-type: image/png');
imagepng($dstim);
imagedestroy($im);
imagedestroy($dstim);
