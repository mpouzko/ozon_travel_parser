<?php
include("ozontravelparser.php");
 
$startDate = "2017-04-01";
$endDate = "2017-04-30";
$interval = 1;
$parser = new OzonTravelParser( $startDate, $endDate, $interval );
$parser->extract_all_data();




$parser->write_data(); 
$parser->save_to_xls();


echo "<pre>";
var_dump ( $parser->get_log() );

