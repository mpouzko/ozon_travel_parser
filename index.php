<?php
include("ozontravelparser.php");
 
$startDate = "2017-03-30";
$endDate = "2017-04-01";
$interval = 1;
$parser = new OzonTravelParser( $startDate, $endDate, $interval );

echo "<pre>";
var_dump ( $parser->extract_all_data() );
$parser->write_data(); 
