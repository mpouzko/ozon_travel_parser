<?php

class OzonTravelParser
{
    static $url       = "https://www.ozon.travel/hotel_by_accommodation/[[hotelId]]/in[[dateIn]]out[[dateOut]]/?Dlts=[[adults]]";
    private $hotelIds = [
        1  => [
            "name" => "Риксос",
            "id"   => '1691244267000/1615511486000',
        ],
        2  => [
            "name" => "Марриотт",
            "id"   => '1691244267000/1615510418000',
        ],
        3  => [
            "name" => "Radisson Роза Хутор",
            "id"   => '1691244267000/1065987635000',
        ],
        4  => [
            "name" => "Гранд отель поляна",
            "id"   => '1691211287000/36182200000',
        ],
        5  => [
            "name" => "Поляна 1389",
            "id"   => '1691211287000/1680716168000',
        ],
        6  => [
            "name" => "Горки Панорама",
            "id"   => '1691244267000/2134421396000',
        ],
        7  => [
            "name" => "Park Inn Роза Хутор",
            "id"   => '1691244267000/464346972000',
        ],
        8  => [
            "name" => "Пик отель",
            "id"   => '1691244267000/216164971000',
        ],
        9  => [
            "name" => "Mercure Роза Хутор",
            "id"   => '1691244267000/1065987834000',
        ],
        10 => [
            "name" => "Golden Tulip Роза Хутор",
            "id"   => '1691211287000/1628187573000',
        ],
    ];
    private $dateStart, $dateEnd, $interval;
    private $log;

    public function __construct($dateStart, $dateEnd = null,   $interval)
    {
        include_once __DIR__ . '/lib/html_parser/simple_html_dom.php';

        $this->set_dates($dateStart, $dateEnd, $interval);
        $this->log = [
            "source"         => "ozon.travel",
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

    public function set_dates($dateStart, $dateEnd = null,   $interval)
    {
        //date format is 'Y-m-d'
        //TODO - if dateEnd is null, it must be dateStart + 1 day
        //TODO - all date checks
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
                if (!array_key_exists($hotelName, $this->log["rooms_category"])) {
                    $this->log["rooms_category"][$hotelName] = [];
                }

                if (!array_key_exists($hotelName, $this->log["data"])) {
                    $this->log["data"][$hotelName] = [];
                }

                for ($person = 1; $person < 3; $person++) {

                    try {
                        $url = str_replace(
                            array(
                                "[[hotelId]]",
                                "[[dateIn]]",
                                "[[dateOut]]",
                                "[[adults]]",
                            ),
                            array(
                                $hotelId,
                                $i->format('Y-m-d'),
                                $date2->format('Y-m-d'),
                                $person,
                            ),
                            self::$url
                        );
                        if ($test) {
                            echo $url . "<BR>";
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

                        foreach ($html->find('#booking #rooms ul.rooms > li.room') as $room) {
                            $tariffs   = [];
                            $room_name = trim($room->find('div.room-name', 0)->plaintext);
                            if (strlen($room_name) < 1) {
                                $room_name = "None";
                            }

                            if (!array_key_exists($room_name, $this->log["data"][$hotelName])) {
                                $this->log["data"][$hotelName][$room_name] = [];
                            }

                            if (!array_key_exists($person, $this->log["data"][$hotelName][$room_name])) {
                                $this->log["data"][$hotelName][$room_name][$person] = [];
                            }

                            if (!in_array($room_name, $this->log["rooms_category"][$hotelName])) {
                                array_push($this->log["rooms_category"][$hotelName], $room_name);
                            }

                            $room_id = array_search($room_name, $this->log["rooms_category"][$hotelName]);

                            foreach ($room->find('ul.tariffs  li.tariff') as $tariff) {
                                $conditions = [];
                                foreach ($tariff->find("div.tariff-services li") as $t) {
                                    $conditions[] = $t->plaintext;
                                }
                                $conditions[] = $room->find("div.tariff-cancel-conditions span.inner", 0)->plaintext;
                                $tariffs[]    = array(

                                    "conditions" => trim(implode(",", $conditions)),
                                    "price"      => trim($tariff->find("span.tariff-price-current-value", 0)->plaintext),

                                );
                            }
                            $this->log["data"][$hotelName][$room_name][$person][$i->format("d-m-Y")] = $tariffs;

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

    public function write_data()
    {
        $filename = sprintf(
            "ozon.travel_start%s_end%s_req%s.json",

            $this->dateStart,
            $this->dateEnd,
            date("Y-m-d-His")
        );
        file_put_contents($filename, json_encode($this->log));
    }

    private function normalize(array &$array,   $size)
    {
        for ($i = 3; $i < $size; $i++) {
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
        ];
        //$dates = [];
        for ($i = clone ($dateStart); $i <= $dateEnd; $i->modify("+1 day")) {
            $header[] = $i->format("d-m-Y");
            //$dates[]  = $i->format("d-m-Y");
        }

        $writer->writeSheetRow('Sheet1', $header); //sheet name here

        foreach ($this->log["data"] as $hotel_name => $rooms_list) {
            foreach ($rooms_list as $room_name => $persons) {
                foreach ($persons as $person_qty => $dates) {

                    $rows   = [];
                    $rows[] = [
                        0 => $hotel_name,
                        1 => $room_name,
                        2 => $person_qty,
                    ];
                    foreach ($dates as $date => $tariffs) {
                        $date_index = array_search($date, $header);
                        foreach ($tariffs as $tariff) {
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
 
                        }
                    }
                   
                    foreach ($rows as &$row) {
                        $this->normalize($row, count($header));
                        $writer->writeSheetRow('Sheet1', $row);
                    }

                }
            }

        }

 
        $writer->writeToStdOut();
        exit(0);
       
    }

}
