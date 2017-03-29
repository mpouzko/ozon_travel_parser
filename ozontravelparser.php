<?php
 
class OzonTravelParser
{
    static $url      = "https://www.ozon.travel/hotel_by_accommodation/[[hotelId]]/in[[dateIn]]out[[dateOut]]/?Dlts=[[adults]]";
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
    private  $dateStart, $dateEnd, $interval;
    private $log;

    public function __construct($dateStart, $dateEnd = null, int $interval)
    {
        include_once __DIR__.'/lib/html_parser/simple_html_dom.php';
        
        $this->set_dates($dateStart, $dateEnd, $interval);
        $this->log = [
            "source"      => "ozon.travel",
            "requestDate" => date("Y-m-d"),
            "data"        => [],
            "rooms_category" => [],
        ];
    }

    public function __get($var)
    {
        return false;
    }

    public function load($filename)
    {
        $this->log          = json_decode(file_get_contents($filename));
        $this->dateStart    = $this->log["requestDate"];
        $this->dateEnd      = $this->log["requestDate"];
        $this->interval     = 1;
    }

    /*
    returns list of hotels to set proper Id to fetch data
     */

    public function get_hotels()
    {
         return $this->hotelIds;
    }

    
    public function get_hotel_name(int $id)
    {
        if (array_key_exists($id, $this->$hotelIds)) {
            return $this->hotelIds[$id]["name"];
        } else {
            throw new Exception("ERROR: hotel not found. Use get_hotels() to see proper IDs");
        }
    }

    public function set_dates($dateStart, $dateEnd = null, int $interval)
    {
        //date format is 'Y-m-d'
        //TODO - if dateEnd is null, it must be dateStart + 1 day
        //TODO - all date checks
        $this->dateStart = $dateStart;
        $this->dateEnd   = $dateEnd;
        $this->interval  = $interval;
    }

    public function extract_all_data( $test = false)
    {
        $dateStart = date_create_from_format('Y-m-d', $this->dateStart);
        $dateEnd   = date_create_from_format('Y-m-d', $this->dateEnd);
        $interval  = $this->interval;

        for ($i = clone ($dateStart); $i <= $dateEnd; $i->modify("+1 day")) {
        	if (!array_key_exists($i->format('Y-m-d'), $this->log["data"])) $this->log["data"][$i->format('Y-m-d')] = [];
            $date2 = clone ($i);
            $date2->modify("+{$interval} day");
            foreach ( $this->get_hotels() as $hotel ) {
                if ( !array_key_exists($hotel["name"], $this->log["rooms_category"]) ) $this->log["rooms_category"][$hotel["name"]] = [];
        	    $hotelId = $hotel["id"];
                $hotelName = $hotel["name"];
	            for ($person=1;$person<3;$person++) {
		            try {
		                $url = str_replace(
		                    array(
		                        "[[hotelId]]",
		                        "[[dateIn]]",
		                        "[[dateOut]]",
		                        "[[adults]]"
		                    ),
		                    array(
		                        $hotelId,
		                        $i->format('Y-m-d'),
		                        $date2->format('Y-m-d'),
		                        $person
		                    ),
		                    self::$url
		                );
                        if ($test) {
                            echo $url."<BR>";
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
		                    $room_name = trim($room->find('span.room-name-inner', 0)->plaintext);
                            if (strlen($room_name) <1) $room_name = "None";
                            if ( !in_array($room_name, $this->log["rooms_category"][$hotelName] ) )
                                array_push($this->log["rooms_category"][$hotelName], $room_name );
                            $room_id = array_search($room_name,$this->log["rooms_category"][$hotelName] );

		                    foreach ($room->find('ul.tariffs  li.tariff') as $tariff) {
		                        $conditions = [];
		                        foreach ($tariff->find("div.tariff-services li") as $t) {
		                            $conditions[] = $t->plaintext;
		                        }
		                        $conditions[] = $room->find("div.tariff-cancel-conditions span.inner", 0)->plaintext;
		                        $tariffs[]    = array(

		                            "conditions" => implode(",", $conditions),
		                            "price"      => trim($tariff->find("span.tariff-price-current-value", 0)->plaintext),
		                        );
		                    }
		                    $this->log["data"][$i->format('Y-m-d')][] = [
		                    	"hotel"	   => $hotelName,
		                        "room"     => $room_name,
                                "room_category_index" => $room_id,
		                        
		                        "capacity" => $person, 
		                        "tariffs"  => $tariffs,
		                    ];
                           
		                }
		            } catch (Exception $e) {

		            }

		        }
		    }
        }
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

    public function save_to_xls()
    {
        return null;
    }

}
