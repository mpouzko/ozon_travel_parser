<?php

class RosaParser
{
    static $url       = "https://shop.rosaski.com/hotels/[[hotelId]]/?action=searching&dateFrom=[[dateStart]]&dateTo=[[dateEnd]]&adultCount=[[person]]&childCount=0&foodTypes%5B%5D=[[foodType]]";
    private $foodTypes = [
        1 => [
            "name" => "Без завтрака",
            "value" => 18170,
        ],
        2 => [
            "name" => "Завтрак",
            "value" => 18168,
        ]
    ];
    private $hotelIds = [
         
        0  => [
            "name" => "Radisson Роза Хутор",
            "id"   => 'radisson-rosa-khutor',
        ],
        1  => [
            "name" => "Park Inn Роза Хутор",
            "id"   => 'park-inn-by-radisson-rosa-khutor',
        ],
        2  => [
            "name" => "Mercure Роза Хутор",
            "id"   => 'mercure-rosa-khutor',
        ],
        3 => [
            "name" => "Golden Tulip Роза Хутор",
            "id"   => 'golden-tulip-rosa-khutor',
        ],
    ];
    private $dateStart, $dateEnd, $interval;
    private $log;

    public function __construct($dateStart, $dateEnd = null,   $interval)
    {
        include_once __DIR__ . '/lib/html_parser/simple_html_dom.php';

        $this->set_dates($dateStart, $dateEnd, $interval);
        $this->log = [
            "source"         => "rosaski.com",
            "requestDate"    => date("Y-m-d"),
            "data"           => [],
            "rooms_category" => [],
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
        $foodTypes = $this->foodTypes;
        $interval  = $this->interval;

        for ($i = clone ($dateStart); $i <= $dateEnd; $i->modify("+1 day")) {
            $date2 = clone ($i);
            $date2->modify("+{$interval} day");
            foreach ($this->get_hotels() as $hotel) {
                $hotelId   = $hotel["id"];
                $hotelName = $hotel["name"];
                
               
                    foreach ($foodTypes as $food ) {
                                    $foodType = $food["name"];
                                    for ($person = 1; $person < 3; $person++) {
                                        try {
                                            $url = str_replace(
                                                array(
                                                    "[[hotelId]]",
                                                    "[[dateStart]]",
                                                    "[[dateEnd]]",
                                                    "[[person]]",
                                                    "[[foodType]]"
                                                ),
                                                array(
                                                    $hotelId,
                                                    $i->format('d.m.Y'),
                                                    $date2->format('d.m.Y'),
                                                    $person,
                                                    $food["value"]
                                                ),
                                                self::$url
                                            );
                                            if ($test) {
                                                echo $url .  "<BR>";
                                                continue;
                                            }

                                            $path = __DIR__ . "/cache/" . md5($url) . date("Y-m-d") . ".cache";
                                            if (!is_file($path) || filesize($path) <= 0) {
                                                file_put_contents($path, file_get_contents($url));
                                            }
                                            $html = file_get_html($path);
                                            if (!$html) {

                                                continue;
                                            }

                                            foreach ($html->find('div.offersTable tbody > tr.mainTable ') as $room) {
                                                //$tariffs   = [];
                                                $tmp = trim( $room->find('td.n1 > h3.roomName', 0)->plaintext );
                                                $tariffs = '';
                                                foreach ( $room->find('td.n2 > ul > li span' )  as $tariff) {
                                                    $tariffs .= $tariff->plaintext." ";
                                                }

                                                if (strlen($tmp) > 1) {
                                                    $room_name = $tmp;
                                                }

                                                
                                                $price = trim( $room->find("td.n4 > span",0)->plaintext );

                                                $this->log["data"][$hotelName][$room_name][$person][$foodType][trim($tariffs)][$i->format("d-m-Y")]=$price;
                                                    
                                            }
                                        } catch (Exception $e) {

                                        }

                                    }
                }
            }
        }

    }

    public function get_log()
    {
        return $this->log;
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
        $writer->setAuthor('Ozon.travel vs. m.puzko');

        $dateStart = date_create_from_format('Y-m-d', $this->dateStart);
        $dateEnd   = date_create_from_format('Y-m-d', $this->dateEnd);
        $interval  = $this->interval;

        $header = [
            0 => "Отель",
            1 => "Номер",
            2 => "Человек",
            3 => "Питание",
            4 => "Тариф"
        ];
        //$dates = [];
        for ($i = clone ($dateStart); $i <= $dateEnd; $i->modify("+1 day")) {
            $header[] = $i->format("d-m-Y");
            //$dates[]  = $i->format("d-m-Y");
        }

        $writer->writeSheetRow('Sheet1', $header); //sheet name here
// $this->log["data"][$hotelName][$room_name][$person][$foodType][$i->format("d-m-Y")]=$price;

        foreach ($this->log["data"] as $hotel_name => $rooms_list) {
            foreach ($rooms_list as $room_name => $persons) {
                foreach ($persons as $person_qty => $food_types) {
                    foreach ($food_types as $food_type => $tariffs) {
                        foreach ($tariffs as $tariff_name => $dates) {


                            
                            $row = [
                                0 => $hotel_name,
                                1 => $room_name,
                                2 => $person_qty,
                                3 => $food_type,
                                4 => $tariff_name

                            ];
                            foreach ($dates as $date => $price) {

                                $date_index = array_search($date, $header);
                                $row[$date_index] = $price;
                               /* foreach ($tariffs as $tariff) {
                                    $inserted = 0;
                                    foreach ($rows as &$row) {
                                        if ( !array_key_exists($date_index, $row) && $inserted == 0 ) {
                                            $row[$date_index] = $tariff["price"];
                                            $inserted         = 1;
                                        }
                                    }

                                    if ( $inserted == 0 ) {
                                        $rows[] = [
                                            0           => $hotel_name,
                                            1           => $room_name,
                                            2           => $person_qty,
                                            $date_index => $tariff["price"],
                                        ];
                                    }
         
                                }*/
                            }
                      
                           
                            
                                $this->normalize($row, count($header),5);
                                $writer->writeSheetRow('Sheet1', $row);
                            
                        }
                    }

                }
            }

        }

 
        $writer->writeToStdOut();
        exit(0);
       
    }
 public function write_data($folder = '')
    {
        $filename = sprintf(
            "rosaski_com_req%s_start%s_end%s.json",
            date("Y-m-d"),
            $this->dateStart,
            $this->dateEnd
      
        );
        file_put_contents(__DIR__."/{$folder}/{$filename}", json_encode($this->log));
    }

    

    public function save_to_xls_local($folder = '')
    {
        
        include_once __DIR__ . '/lib/xls_writer/xlsxwriter.class.php';
        ini_set('display_errors', 0);
        ini_set('log_errors', 1);
        error_reporting(E_ALL & ~E_NOTICE);
        $filename = "res".date("Y.m.d").".xlsx";
        header('Content-disposition: attachment; filename="'.XLSXWriter::sanitize_filename($filename).'"');
        header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
        header('Content-Transfer-Encoding: binary');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        $writer = new XLSXWriter();
        $writer->setAuthor('rosaski.com vs. m.puzko');

        $dateStart = date_create_from_format('Y-m-d', $this->dateStart);
        $dateEnd   = date_create_from_format('Y-m-d', $this->dateEnd);
        $interval  = $this->interval;

        $header = [
            0 => "Отель",
            1 => "Номер",
            2 => "Человек",
            3 => "Питание",
            4 => "Тариф"
        ];
        //$dates = [];
        for ($i = clone ($dateStart); $i <= $dateEnd; $i->modify("+1 day")) {
            $header[] = $i->format("d-m-Y");
            //$dates[]  = $i->format("d-m-Y");
        }

        $writer->writeSheetRow('Sheet1', $header); //sheet name here
// $this->log["data"][$hotelName][$room_name][$person][$foodType][$i->format("d-m-Y")]=$price;

        foreach ($this->log["data"] as $hotel_name => $rooms_list) {
            foreach ($rooms_list as $room_name => $persons) {
                foreach ($persons as $person_qty => $food_types) {
                    foreach ($food_types as $food_type => $tariffs) {
                        foreach ($tariffs as $tariff_name => $dates) {


                            
                            $row = [
                                0 => $hotel_name,
                                1 => $room_name,
                                2 => $person_qty,
                                3 => $food_type,
                                4 => $tariff_name

                            ];
                            foreach ($dates as $date => $price) {

                                $date_index = array_search($date, $header);
                                $row[$date_index] = $price;
                                
                            }
                      
                           
                            
                                $this->normalize($row, count($header),5);
                                $writer->writeSheetRow('Sheet1', $row);
                            
                        }
                    }

                }
            }

        }

 
        $writer->writeToFile(__DIR__."/{$folder}/{$filename}");
     
       
    }

}
