<?php
$MASTER_DATE = new DateTime();
if (isset($_GET['nextWeek'])) {
    $MASTER_DATE->add(new DateInterval('P7D'));
}

include 'booking.php';

/**
 * Get the cached xml data
 * @return data
 */
function get_xml()
{
    if (!file_exists("cache.xml")) {
        file_put_contents("cache.xml", file_get_contents("http://vatbook.euroutepro.com/xml2.php"));
    }
    $xml = simplexml_load_file('cache.xml');
    if (time() >= strtotime($xml->timestamp) + 60) {
        file_put_contents("cache.xml", file_get_contents("http://vatbook.euroutepro.com/xml2.php"));
        $xml = simplexml_load_file('cache.xml');
    }
    return $xml;
}

/**
 * Get all bookings from specific stations
 * @param array containing atc stations
 * @return array with bookings from stations
 */
function get_bookings($stations)
{
    $xml = get_xml();
    $atc_bookings = $xml->atcs->booking;
    $requested_bookings = [];
    for ($i = 0; $i < count($atc_bookings); $i++) {
        $station = $atc_bookings[$i]->callsign;
        for ($j = 0; $j < count($stations); $j++) {
            // check if booking station matches with list
            if (strpos($station, $stations[$j]) !== false) {
                $requested_bookings[] = new Booking($atc_bookings[$i]->name, $atc_bookings[$i]->time_start, $atc_bookings[$i]->time_end, $atc_bookings[$i]->callsign);
                break;
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

$edff_main_stations = ["EDGG_CTR", "EDGG_E_CTR", "EDDF_N_APP", "EDDF_F_APP", "EDDF_U_APP", "EDDF_TWR", "EDDF_W_TWR", "EDDF_C_GND", "EDDF_DEL", "EDDS_N_APP", "EDDS_F_APP", "EDDS_TWR", "EDDS_GND", "EDFH_APP", "EDFH_TWR", "EDDR_APP", "EDDR_TWR", "EDFM_TWR", "EDSB_TWR"];

$booked_stations = get_bookings($edff_main_stations);

/* Booking matrix contains all stations and bookings.. Still in progress!

[STATION[DATE[BOOKINGS]]

e.g.
<Station>   <---- Day 1-----------> <-- Day 2--> <-- Day 3-->...
[EDDF_TWR,[[12-14 LuEw,16-18 PaBue],[18-20 KaWe],[10-20 LuEw]], NEXT_STATION,[ ... ]]

Day cell is again array to handle multiple bookings each day
 */
$booking_matrix = [];

// Iterate over the stations and create a booking matrix
for ($i = 0; $i < count($edff_main_stations); $i++) {
    // First row contains the station
    $booking_matrix[$i][0] = $edff_main_stations[$i];
    $day = clone $MASTER_DATE;
    // Loop over the next days
    for ($j = 1; $j < 8; $j++) {
        $found_booking = false;

        $cellObject = [];

        // Check if there is a booking at the specific date
        for ($k = 0; $k < count($booked_stations); $k++) {
            $booking_date_start = DateTime::createFromFormat('Y-m-d H:i:s', $booked_stations[$k]->time_start);
            $booking_date_end = DateTime::createFromFormat('Y-m-d H:i:s', $booked_stations[$k]->time_end);

            // append all bookings as array
            if ($booking_date_start->format("Y-m-d") === $day->format("Y-m-d") && $edff_main_stations[$i] == $booked_stations[$k]->callsign) {
                $cellObject[] = $booked_stations[$k]->abbreviation . " " . $booking_date_start->format("H") . "-" . $booking_date_end->format("H");
                $found_booking = true;
            }
        }
        // If no booking found, set it to open
        if (!$found_booking) {
            $cellObject[] = "open";
        }

        $booking_matrix[$i][$j] = $cellObject;
        $day->add(new DateInterval("P1D"));
    }
}

$imageHeight = 700;
$imageWidth = 800;

// Create images
$im = imagecreate($imageWidth, $imageHeight);
$background_color = imagecolorallocate($im, 242, 242, 242);
$color_withe = imagecolorallocate($im, 255, 255, 255);
$color_black = imagecolorallocate($im, 0, 0, 0);
$color_gray = imagecolorallocate($im, 210, 210, 210);
$color_red = imagecolorallocate($im, 190, 40, 40);

$row = 1;
$lineHeight = 15;
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
    write_string($im, 3, 5, $lineHeight * $row, $booking_matrix[$i][0], $color_black);
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
            if ($booking_matrix[$i][$j][$k] !== "open") {
                $color = $color_black;
            }
            if ($day->format("N") === "4" && $booking_matrix[$i][$j][$k] === "open"
                && (substr($booking_matrix[$i][0], 0, 4) === "EDDS" || substr($booking_matrix[$i][0], 0, 6) === "EDGG_E")) {
                $color = $color_red;
            }
            if ($day->format("N") === "5" && $booking_matrix[$i][$j][$k] === "open"
                && (substr($booking_matrix[$i][0], 0, 4) === "EDDF" || substr($booking_matrix[$i][0], 0, 6) === "EDGG_E")) {
                $color = $color_red;
            }

            // If is requested but open than make it to WANTED
            if ($color === $color_red && $booking_matrix[$i][$j][$k] == "open") {
                $booking_matrix[$i][$j][$k] = "WANTED";
            }

            write_string($im, 3, $cell_width * $j, $lineHeight * $row, $booking_matrix[$i][$j][$k], $color);
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

$row = $row + 3;

// Set array with all users
$all_users = [];
for ($i = 0; $i < count($booked_stations); $i++) {
    $all_users[] = ["name" => $booked_stations[$i]->name, "abbreviation" => $booked_stations[$i]->abbreviation];
}

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
write_string($im, 2, 5, $lineHeight * $row, "Generated " . $generated_time->format("d.m.Y H:i:s"), $color_gray);

// Set content to gif and create image
header('Content-type: image/png');
imagepng($im);
imagedestroy($im);
