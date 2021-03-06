<?php
header('Access-Control-Allow-Origin: *'); 

function objectToArray($d) {
    if (is_object($d))
        $d = get_object_vars($d); // Gets the properties of the given object with get_object_vars function
    if (is_array($d))
        return array_map(__FUNCTION__, $d); // Return array converted to object using __FUNCTION__ (Magic constant) for recursive call
    else
        return $d; // Return array
}

$type 	= isset($_REQUEST["type"]) ? $_REQUEST["type"] : null;
$id		= isset($_REQUEST["id"]) ? $_REQUEST["id"] : null;

// http://blkfeed.com:8080/php-helpers/jsonData.php?type=exchange&id=
if($type=="exchange"){
	$data = array();
	$proceed = true;
	$helperFile = "helperFile_$id.txt";
	if(file_exists($helperFile)){
		$data = objectToArray(json_decode(file_get_contents($helperFile, true)));
		$tDiff = time()-$data["ts"];
		$data["cts"] = time();
		$data["tdiff"] = $tDiff;
		if($tDiff < 60){
			$proceed = false;
		}
	}
	if($proceed){
		$data["ts"] = time();
		if(isset($id) && strtolower($id) == "excoin"){
			$stats = json_decode(file_get_contents("https://exco.in/api/v1/exchange/BTC/BLK/summary"), true);
			$data["24hlow"] = $stats["low"] * 100000000;
			$data["24hhigh"] = $stats["high"] * 100000000;
			$data["24hvol"] = round($stats["volume"], 2);
			$data["last_price"] = $stats["last_price"] * 100000000;
		} else if (isset($id) && strtolower($id) == "cryptsy") {
			$cryptsy_stats = json_decode(file_get_contents("http://pubapi.cryptsy.com/api.php?method=singlemarketdata&marketid=179"), true);
			
			$data["24hlow"] = PHP_INT_MAX;
			$data["24hhigh"] = ~PHP_INT_MAX;
			
			// some hack to get max. and min. order from cryptsy:
			foreach($cryptsy_stats["return"]["markets"]["BC"]["recenttrades"] as $trade){
				if($trade["price"] > $data["24hhigh"]){
					$data["24hhigh"] = $trade["price"];
				}
				if($trade["price"] < $data["24hlow"]){
					$data["24hlow"] = $trade["price"]; 
				}
			}
			$data["24hlow"] = $data["24hlow"] * 100000000;
			$data["24hhigh"] = $data["24hhigh"] * 100000000;
			$data["24hvol"] = round(($cryptsy_stats["return"]["markets"]["BC"]["volume"] * $cryptsy_stats["return"]["markets"]["BC"]["lasttradeprice"]), 2);
			$data["24hvol_bc"] = round($cryptsy_stats["return"]["markets"]["BC"]["volume"], 2);
			$data["last_price"] = $cryptsy_stats["return"]["markets"]["BC"]["lasttradeprice"] * 100000000;
		} else if (isset($id) && strtolower($id) == "bittrex") {
			$stats = json_decode(file_get_contents("https://bittrex.com/api/v1.1/public/getmarketsummary?market=BTC-BC"), true);
			$data["24hlow"] = $stats["result"][0]["Low"] * 100000000;
			$data["24hhigh"] = $stats["result"][0]["High"] * 100000000;
			$data["24hvol"] = round($stats["result"][0]["BaseVolume"], 2);
			$data["last_price"] = $stats["result"][0]["Last"] * 100000000;
		} else if (isset($id) && strtolower($id) == "bter") {
			$stats = json_decode(file_get_contents("http://data.bter.com/api/1/ticker/bc_btc"), true);
			$data["24hlow"] = $stats["low"] * 100000000;
			$data["24hhigh"] = $stats["high"] * 100000000;
			$data["24hvol"] = round($stats["vol_btc"], 2);
			$data["last_price"] = $stats["last"] * 100000000;
		} else if (isset($id) && strtolower($id) == "btc38") {
			$header = array(
				"User-Agent:Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/31.0.1650.63 Safari/537.36"
			);
			$curl = curl_init('http://api.btc38.com/v1/ticker.php?c=bc&mk_type=cny');
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
			$res = curl_exec($curl);
			curl_close($curl);
			$stats = objectToArray(json_decode($res));
			
			$header = array(
				"User-Agent:Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/31.0.1650.63 Safari/537.36"
			);
			$curl = curl_init('http://api.btc38.com/v1/ticker.php?c=btc&mk_type=cny');
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
			$res = curl_exec($curl);
			curl_close($curl);
			$stats_btc = objectToArray(json_decode($res));
			
			$btc_price = $stats_btc["ticker"]["last"];
					
			$data["24hlow"] = round(($stats["ticker"]["low"]/$btc_price) * 100000000, 0);
			$data["24hhigh"] = round(($stats["ticker"]["high"]/$btc_price) * 100000000, 0);
			$data["24hvol"] = round((round($stats["ticker"]["vol"], 2) * $stats["ticker"]["last"]) / $btc_price, 2);
			$data["24hvol_bc"] = round($stats["ticker"]["vol"], 2);
			$data["last_price"] = round(($stats["ticker"]["last"]/$btc_price) * 100000000, 0);
		}
		$file = file_put_contents ( $helperFile  , json_encode($data) );
		// http://api.btc38.com/v1/ticker.php?c=bc&mk_type=cny	
		// http://api.btc38.com/v1/ticker.php?c=bc&mk_type=btc	
		// http://data.bter.com/api/1/ticker/BC_BTC	
		// http://data.bter.com/api/1/ticker/BC_CNY
	} 
	echo json_encode($data);
} else if ($type == "buypressure"){
	$data = array();
	$proceed = true;
	$helperFile = "helperFile_$type"."_$id.txt";
	if(file_exists($helperFile)){
		$data = objectToArray(json_decode(file_get_contents($helperFile, true)));
		$tDiff = time()-$data["ts"];
		$data["cts"] = time();
		$data["tdiff"] = $tDiff;
		if($tDiff < 3600){
			$proceed = false;
		}
	}
	if($proceed){
		$data = array();
		if (isset($id) && strtolower($id) == strtolower("blackcoinpool")) {
			$hashrates_url = "http://blackcoinpool.com/api/stats/";
			$hashrates = objectToArray(json_decode(file_get_contents($hashrates_url)));

			$profitabilities_url = "http://blackcoinpool.com/api/profitability/";
			$profitabilities = objectToArray(json_decode(file_get_contents($profitabilities_url)));

			$scryptHash = $hashrates["results"][0]["hashrate"] / 1000000;
			$sha256Hash = $hashrates["results"][1]["hashrate"] / 1000000;
			$x11Hash = $hashrates["results"][2]["hashrate"] / 1000000;
			$scryptnHash = $hashrates["results"][3]["hashrate"] / 1000000;
			$scrypt = $scryptHash * 0.00015;// $profitabilities["results"][0]["profitability"];
			$sha256 = $sha256Hash * $profitabilities["results"][0]["profitability"] / 1000 / 85; // per 85ghs
			$x11 = $x11Hash * $profitabilities["results"][1]["profitability"] / 4; // per 4 mhs
			$scryptn = $scryptnHash * $profitabilities["results"][2]["profitability"] * 2; // per 0.5 mhs
			
			$data["btc"] = $scrypt + $sha256 + $x11 + $scryptn;
						
			$stats = json_decode(file_get_contents("http://data.bter.com/api/1/ticker/bc_btc"), true);
			$data["24hlow"] = $stats["low"];
			$data["24hhigh"] = $stats["high"];

			$data["average"] = ($data["24hlow"] + $data["24hhigh"]) / 2;
			
			$data["bc_buy"] = round($data["btc"] / $data["average"], 2);
			$data["ts"] = time();
		} else if (isset($id) && strtolower($id) == "excoin"){
			$stats = json_decode(file_get_contents("https://api.exco.in/v1/summary"), true);
			$data["ts"] = time();
			
			foreach($stats as $stat){
				if($stat["currency"] == "BTC"){
					$data["btc"] += $stat["volume"];
					$data["btc_fees"] += $stat["volume"] * 0.0015 * 2; // buyer and seller
				} else if ($stat["currency"] == "BLK"){
					$data["blk"] += $stat["volume"];
					$data["blk_fees"] += $stat["volume"] * 0.0015 * 2; // buyer and seller
				}
				if($stat["currency"] == "BTC" && $stat["commodity"] == "BLK"){
					$data["avg"] = $stat["last_price"];
				}
			}
			
			$data["bc_buy"] = round($data["blk_fees"] + ($data["btc_fees"] / $data["avg"]), 2);
		}
		$file = file_put_contents ( $helperFile  , json_encode($data) );
	}
	echo json_encode($data);
}	

//http://www.altmining.farm/api.php?method=paidcoins&display=human
