<?php
//https://polyana1389.ru/booking_nf/rooms.php?arrive=01.08.2017&depart=10.08.2017&adult=2&child=0

/* $url = 'https://polyana1389.ru/booking_nf/rooms.php';
$data = array('arrive' => '01.08.2017', 'depart' => '04.08.2017');

// use key 'http' even if you send the request to https://...
$options = array(
    'http' => array(
        'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
        'method'  => 'POST',
        'content' => http_build_query($data)
    )
);
$context  = stream_context_create($options);
$result = file_get_contents($url, false, $context);
if ($result === FALSE) {  }

var_dump($result);*/



 
$postfields = array('arrive' => '01.08.2017', 'depart' => '04.08.2017');
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://polyana1389.ru/booking_nf/rooms.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_POST, 1);
// Edit: prior variable $postFields should be $postfields;
curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); // On dev server only!

$result = curl_exec($ch);

print_r(curl_getinfo($ch));
echo curl_errno($ch) . '-' . 
curl_error($ch);
var_dump($result);

