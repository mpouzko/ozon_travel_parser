<?php


include ("gazpromparser.php");
$startDate  = '2017-04-12';
$endDate  = '2017-10-12';
$interval = 1;

$parser = new GazpromParser ($startDate,$endDate,$interval);
  
$parser -> extract_all_data();
//$parser -> write_data();
//$parser->load("polyanaski_com_start2017-04-17_end2017-04-18_req2017-04-12-125857.json");

$parser->save_to_xls();
//echo "<PRE>";
var_dump($parser->get_log());