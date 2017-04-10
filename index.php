<?php

//echo file_get_contents("https://shop.rosaski.com/hotels/park-inn-by-radisson-rosa-khutor/?action=searching&dateFrom=15.04.2017&dateTo=16.04.2017&adultCount=2&childCount=0&foodTypes%5B%5D=18168&foodTypes%5B%5D=18177&foodTypes%5B%5D=18166");
include ("rosaparser.php");
$startDate  = '2017-09-06';
$endDate  = '2017-09-10';
$interval = 1;

$parser = new RosaParser ($startDate,$endDate,$interval);

$parser -> extract_all_data();
echo "<PRE>";
var_dump($parser->get_log());