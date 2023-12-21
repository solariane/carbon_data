<?php


/**
* Build our data from OWID
* Use their carbon, land and population DATA 
*/

error_reporting(E_ERROR);
$shortopts = 's::d::e::a::p::'; // shortopt needs = or no Space
$options = getopt($shortopts);
$source = $options['s']??'owid-co2-data.csv';
$dest = $options['d']??'cumulative.csv';
$area = $options['a']??'land-area-km.csv';
$export = $options['e']??'owid-co2-data-plus-cumulative-and-land.csv';


$rows[0] = ['Country', 'Year', 'iso_code',  'Population','Land', 'owid_co2_luc', 'cumulative', 'cumulative100', 'cumulativeAbsorption','cumulativeAbsorption2','carbonLand'];

$f = new SplFileObject($source); 
$f->setFlags(SplFileObject::READ_CSV);
$head = true;
foreach ($f as $row) { 
	if ($head) {
        $head = false;
        continue;
    }
    // Country isoCode : 2
	// year  : 1
	// co2_including_luc  : 10
	// to be able to compute to data using global area…
// 	if ($row[2])
            $rows[$row[2].'-'.$row[0]][] = [
            	'Country'=> $row[0],
            	'Year'=> $row[1],
            	'iso_code'=> $row[2],
            	'owid_co2_luc'=> (float)($row[10]?:0),
            	'Population'=> (int)($row[4]?:0),
  				//   doesn't seem to gives coherent data
                // 'owid_co2_luc_trade'=> (float)(($row[8]?:0)+($row[42]?:0)+($row[78]?:0)), // CO2 emission + land use + trade
            	]; // could have used year as idx but code easier below that way
}

$ff = new SplFileObject($area); // Entity,Code,Year,Land
$ff->setFlags(SplFileObject::READ_CSV);
foreach ($ff as $landrow) { 
	$idx = $landrow[1].'-'.$landrow[0]; // our key : Entity-Code-Year
	if (isset($rows[$idx] )) {
		foreach ($rows[$idx] as $k=>$d){
			if ($d['Year'] == $landrow[2] && $landrow[2]>1960) {
				$rows[$idx][$k]['Land'] = $landrow[3];
			} elseif (!isset($rows[$idx][$k]['Land']))
				$rows[$idx][$k]['Land'] = 0;
		}
	} 
}
$final = null;
$sequenceSize = 100;
$captureRate = 0.015; // after hundred year ~ 20% still there
foreach($rows as $countryCode=>$d) { 
	if (!$countryCode)
		continue;
	// process all years…
	foreach ($d as $idx => $source) {
		$valOfInterest100 = array_column(array_slice($d, max(0,$idx-$sequenceSize), min ($sequenceSize, $idx+1), true), 'owid_co2_luc'); // take the last 100 years
		$valOfInterest = array_column(array_slice($d, 0, $idx+1, true), 'owid_co2_luc'); // take whole data
		$size100 = count($valOfInterest100);
		$size = count($valOfInterest);
		$rows[$countryCode][$idx]['cumulativeOWID'] =  array_sum($valOfInterest); 
		$rows[$countryCode][$idx]['cumulative100'] =  array_sum($valOfInterest100); 
		$cumulativeWithAbsorption = array_map(function($val, $key)  use ($size, $captureRate){ 
			return $val*((1-$captureRate)**($size-$key));
		}, $valOfInterest, array_keys($valOfInterest));
		$rows[$countryCode][$idx]['cumulativeAbsorption'] =  array_sum($cumulativeWithAbsorption);
		// did previous computation was necessary ? a better approximation of the process at hand ? not sure…
		$rows[$countryCode][$idx]['cumulativeAbsorptionFix'] = $source['owid_co2_luc']+(isset($rows[$countryCode][$idx-1])?(1-$captureRate)*$rows[$countryCode][$idx-1]['cumulativeAbsorptionFix']:0);
		$rows[$countryCode][$idx]['carbonLand'] =  $source['Land']?((10**6) *$rows[$countryCode][$idx]['cumulativeAbsorption'] )/ $source['Land']:0;
	}
	if (!isset($final))
		$final[0] = array_keys($rows[$countryCode][$idx]);
	$final[] = $rows[$countryCode][$idx]; // ['country'=>$countryCode]+
}
// print_r($final);exit();
$o = new SplFileObject($dest, 'w');
foreach ($rows as $k=>$data) { 
	if (!$k)
		$o->fputcsv($data);
	else
		$country = trim(substr($k, strpos($k, '-')+1));
	foreach ($data as $fields) {
		if ($k)
			$o->fputcsv($fields);// (['Country'=>$country]) +
	}
}


$o = new SplFileObject(str_replace('.csv', '.final.csv',$dest), 'w');
foreach ($final as $k=>$data) { 
// 	if (!$k)
// 		$o->fputcsv($data);
// 	else
// 		$country = trim(substr($k, strpos($k, '-')+1));
// 	foreach ($data as $fields) {
// 		if ($k)
			$o->fputcsv($data);// (['Country'=>$country]) +
// 	}
}
unset($final[0]);
$sortColum = array_column($final, 'cumulative');
array_multisort($sortColum, SORT_DESC, $final);
echo "cumulative\n".str_repeat("-", strlen("cumulative"))."\n";
print_r($final);
echo "\n\n -- \n\n";

$sortColum = array_column($final, 'cumulative100');
array_multisort($sortColum, SORT_DESC, $final);
echo "cumulative100\n".str_repeat("-", strlen("cumulative100"))."\n";
print_r($final);



echo "\n\n -- \n\n";
$sortColum = array_column($final, 'cumulativeAbsorption');
array_multisort($sortColum, SORT_DESC, $final);
echo "cumulativeAdvanced\n".str_repeat("-", strlen("cumulativeAdvanced"))."\n";
print_r($final);

echo "\n\n -- \n\n";
$sortColum = array_column($final, 'cumulativeAbsorptionFix');
array_multisort($sortColum, SORT_DESC, $final);
echo "cumulativeAbsorption2\n".str_repeat("-", strlen("cumulativeAbsorption2"))."\n";
print_r($final);

echo "\n\n -- \n\n";
$sortColum = array_column($final, 'carbonLand');
array_multisort($sortColum, SORT_DESC, $final);
echo "carbonLand\n".str_repeat("-", strlen("carbonLand"))."\n";
print_r($final);


?>