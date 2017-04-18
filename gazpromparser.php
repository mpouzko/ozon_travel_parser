<?php

class GazpromParser
{
    
    
    private $hotelIds = [
         
        [
            "name" => "Гранд Отель Поляна",
            "id"   => 'grandhotelpolyana',
            "url1" => 'https://grandhotelpolyana.ru/booking_nf/rooms.php',
            "url2" => 'https://grandhotelpolyana.ru/booking_nf/rooms.php?building=B',
            "encoding" => "Windows-1251",
            "param1" => 566,
            "param2" => 8,
        ], 
        [
            "name" => "Поляна 1389",
            "id"   => 'polyana1389',
            "url1" => 'https://polyana1389.ru/booking_nf/rooms.php',
            "url2" => 'https://polyana1389.ru/booking_nf/rooms.php?building=main',
            "encoding" => false,
            "param1" => 565,
            "param2" => 2,
        ], 
        [
            "name" => "Пик Отель",
            "id"   => 'peakhotel',
            "url1" => 'https://peakhotel.ru/booking_nf/rooms.php',
            "encoding" => "Windows-1251",
            "param1" => 572,
            "param2" => false,

            //"url2" => 'https://peakhotel.ru/booking_nf/rooms.php',
        ],

        
    ];

    private $dateStart, $dateEnd, $interval;
    private $log;

    public function __construct($dateStart, $dateEnd = null,   $interval)
    {
        include_once __DIR__ . '/lib/html_parser/simple_html_dom.php';

        $this->set_dates($dateStart, $dateEnd, $interval);
        $this->log = [
            "source"         => "polyanaski.com",
            "requestDate"    => date("Y-m-d"),
            "data"           => [],
            "rooms_category" => [],
            "tariffs" => [],
        ];
    }

    public function __get($var)
    {
        return false;
    }

    public function load($filename)
    {
        $this->log       = json_decode(file_get_contents($filename));
        $this->dateStart = $this->log["requestDate"];
        $this->dateEnd   = $this->log["requestDate"];
        $this->interval  = 1;
    }

    /*
    returns list of hotels to set proper Id to fetch data
     */

    public function get_hotels()
    {
        return $this->hotelIds;
    }

    public function get_hotel_name(  $id)
    {
        if (array_key_exists($id, $this->$hotelIds)) {
            return $this->hotelIds[$id]["name"];
        } else {
            throw new Exception("ERROR: hotel not found. Use get_hotels() to see proper IDs");
        }
    }

    public function set_dates($dateStart, $dateEnd = null,   $interval=1)
    {
        
        $this->dateStart = $dateStart;
        $this->dateEnd   = $dateEnd;
        $this->interval  = $interval;
    }
   
   

    public function extract_all_data($test = false)
    {
        $dateStart = date_create_from_format('Y-m-d', $this->dateStart);
        $dateEnd   = date_create_from_format('Y-m-d', $this->dateEnd);
        
        $interval  = $this->interval;

        for ($i = clone ($dateStart); $i <= $dateEnd; $i->modify("+1 day")) {
            $date2 = clone ($i);
            $date2->modify("+{$interval} day");
            foreach ($this->get_hotels() as $hotel) {
                $hotelId   = $hotel["id"];
                $hotelName = $hotel["name"];
                $encoding = $hotel ["encoding"];
                $parse_param1 = $hotel ["param1"];
                $parse_param2 = $hotel ["param2"];

                //echo $hotelName;
                   
                        for ($person = 1; $person < 3; $person++) {
                                       try {
                                            $rooms = ["hotel"=> $hotelName];
                                            $room_name = '';

                                            $path = __DIR__."/cache/".md5($hotel["url1"].$i->format("d.m.Y").$person)."_".date("d-m-Y").".cache";
                                            if (!is_file($path) || filesize($path) <= 0) {
                                                $params = array('arrive' => $i->format("d.m.Y"), 'depart' => $date2->format("d.m.Y"),'adult'=>2,'child'=>0);
                                                $ch = curl_init();
                                                curl_setopt($ch, CURLOPT_URL, $hotel["url1"]);
                                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                                                curl_setopt($ch, CURLOPT_POST, 1);
                                                curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
                                                curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookie_'.$hotelId.'.txt'); 
                                                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); 
                                                $result = curl_exec($ch);

                                                //now fetch html
                                                if (array_key_exists("url2", $hotel)) {
                                                    curl_setopt($ch, CURLOPT_URL, $hotel["url2"]);
                                                    $result = curl_exec($ch);
                                                }
                                                if (!$result) {
                                                    vprintf("%s: %s person:%d \n<BR>",array($hotel["name"],$i->format("d.m.Y"), $person));
                                                        //print_r(curl_getinfo($ch));
                                                    echo 'cURL error: '.curl_errno($ch) . ':' . curl_error($ch); continue;
                                                }
                                                if ($encoding) $result = iconv($encoding,'UTF-8',$result);
                                                file_put_contents($path, $result);
                                            }
                                            $html = file_get_html($path);

                                            if (!$html) continue;
                                            

                                            $html->find('body > table table',5) -> class="base";

                                            foreach ( $html->find('table.base tr') as $key => $row ) {
                                                if ( $parse_param2 && $key<$parse_param2 ) continue;
                                                //printf("%d <BR>",$key);
                                                if ( $x = $row->find('div table',0) ) {
                                                    $tmp = trim( $x->find("td",1)->find('b',0)->plaintext );

                                                    if (mb_strlen($tmp)<1) {
                                                        $text = trim($x->find("td",1)->plaintext);
                                                        $matches = explode(".", $text);
                                                        $tmp = $matches[0];
                                                    }


                                                    if (strlen($tmp)>1 ) $room_name = html_entity_decode($tmp); 
                                                   
                                                }
                                                elseif ( $x = $row->find('td[width="'.$parse_param1.'"]',0)  ) {
                                                    $tariff_desc = trim($x->plaintext);
                                                    $tariff_hash = md5($tariff_desc);
                                                    if (!array_key_exists($tariff_hash, $this->log["tariffs"])) $this->log["tariffs"][$tariff_hash] = $tariff_desc;
                                                    $breakfast = false;
                                                    if( mb_stripos($tariff_desc, "завтрак" )>0 ) $breakfast = true; 
                                                    $price = preg_replace("/(\.0{2})|([^\d])/u", '',  trim($row->find('td[align="right"] b',0)->plaintext) );
                                                    $this->log["data"][$hotelName][$room_name][$person][$tariff_hash][$i->format("d-m-Y")]=$price;


                                                    //$rooms[$room_name][]=[$tariff_desc,$price, $breakfast];

                                                }
                                            }

   
                                          
                                          




                                        
                                        
                                        





                                            
                                        } catch (Exception $e) {

                                        }

                                    }
                
            }
        }

    }

    public function get_log()
    {
        return $this->log;
    }

    public function write_data($folder='')
    {
        $filename = sprintf(
            "polyanaski_com_req%s_start%s_end%s.json",
            date("Y-m-d"),
            $this->dateStart,
            $this->dateEnd
            
        );
        file_put_contents(__DIR__."/{$folder}/{$filename}", json_encode($this->log));
    }

    private function normalize(array &$array,   $size, $offset=0)
    {
        for ($i = $offset; $i < $size; $i++) {
            if (!array_key_exists($i, $array)) {
                $array[$i] = " ";
            }
        }
        ksort($array);
    }

    public function save_to_xls()
    {
        
        include_once __DIR__ . '/lib/xls_writer/xlsxwriter.class.php';
        ini_set('display_errors', 0);
        ini_set('log_errors', 1);
        error_reporting(E_ALL & ~E_NOTICE);
        $filename = "res.xlsx";
        header('Content-disposition: attachment; filename="' . XLSXWriter::sanitize_filename($filename) . '"');
        header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
        header('Content-Transfer-Encoding: binary');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        $writer = new XLSXWriter();
        $writer->setAuthor('polyanaski vs. m.puzko');

        $dateStart = date_create_from_format('Y-m-d', $this->dateStart);
        $dateEnd   = date_create_from_format('Y-m-d', $this->dateEnd);
        $interval  = $this->interval;

        $header = [
            0 => "Отель",
            1 => "Номер",
            2 => "Человек",
            3 => "Тариф"
        ];
        //$dates = [];
        for ($i = clone ($dateStart); $i <= $dateEnd; $i->modify("+1 day")) {
            $header[] = $i->format("d-m-Y");
            //$dates[]  = $i->format("d-m-Y");
        }

        $writer->writeSheetRow('Sheet1', $header); //sheet name here
//   $this->log["data"][$hotelName][$room_name][$person][$tariff_hash][$i->format("d-m-Y")]=$price;


        foreach ($this->log["data"] as $hotel_name => $rooms_list) {
            foreach ($rooms_list as $room_name => $persons) {
                foreach ($persons as $person_qty => $tariffs ) {
                    
                        foreach ($tariffs as $tariff_hash => $dates) {
                            $tariff_name = $this->log["tariffs"][$tariff_hash];
                            $row = [
                                0 => $hotel_name,
                                1 => $room_name,
                                2 => $person_qty,
                                3 => $tariff_name

                            ];
                            foreach ($dates as $date => $price) {

                                $date_index = array_search($date, $header);
                                $row[$date_index] = $price;
                                
                            }
                      
                           
                            
                                $this->normalize($row, count($header),4);
                                $writer->writeSheetRow('Sheet1', $row);
                            
                        }
                    

                }
            }

        }

 
        $writer->writeToStdOut();
        exit(0);
       
    }
    public function save_to_xls_local($folder = '')
    {
        
        include_once __DIR__ . '/lib/xls_writer/xlsxwriter.class.php';
        ini_set('display_errors', 0);
        ini_set('log_errors', 1);
        error_reporting(E_ALL & ~E_NOTICE);
        $filename = "res".date("Y.m.d").".xlsx";
        header('Content-disposition: attachment; filename="' . XLSXWriter::sanitize_filename($filename) . '"');
        header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
        header('Content-Transfer-Encoding: binary');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        $writer = new XLSXWriter();
        $writer->setAuthor('polyanaski vs. m.puzko');

        $dateStart = date_create_from_format('Y-m-d', $this->dateStart);
        $dateEnd   = date_create_from_format('Y-m-d', $this->dateEnd);
        $interval  = $this->interval;

        $header = [
            0 => "Отель",
            1 => "Номер",
            2 => "Человек",
            3 => "Тариф"
        ];
        //$dates = [];
        for ($i = clone ($dateStart); $i <= $dateEnd; $i->modify("+1 day")) {
            $header[] = $i->format("d-m-Y");
            //$dates[]  = $i->format("d-m-Y");
        }

        $writer->writeSheetRow('Sheet1', $header); //sheet name here
//   $this->log["data"][$hotelName][$room_name][$person][$tariff_hash][$i->format("d-m-Y")]=$price;


        foreach ($this->log["data"] as $hotel_name => $rooms_list) {
            foreach ($rooms_list as $room_name => $persons) {
                foreach ($persons as $person_qty => $tariffs ) {
                    
                        foreach ($tariffs as $tariff_hash => $dates) {
                            $tariff_name = $this->log["tariffs"][$tariff_hash];
                            $row = [
                                0 => $hotel_name,
                                1 => $room_name,
                                2 => $person_qty,
                                3 => $tariff_name

                            ];
                            foreach ($dates as $date => $price) {

                                $date_index = array_search($date, $header);
                                $row[$date_index] = $price;
                                
                            }
                      
                           
                            
                                $this->normalize($row, count($header),4);
                                $writer->writeSheetRow('Sheet1', $row);
                            
                        }
                    

                }
            }

        }

 
        $writer->writeToFile(__DIR__."/{$folder}/{$filename}");
       
       
    }

}
