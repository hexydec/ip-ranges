<?php
declare(strict_types=1);
namespace hexydec\ipaddresses;

require __DIR__.'/datacentres.php';

$file = __DIR__.'/output/datacentres.csv';
// $file = __DIR__.'/output/datacentres.txt';
$cache = __DIR__.'/cache/';
$obj = new datacentres();
if (($count = $obj->save($file, $cache)) !== false) {
	exit('Saved '.$count.' Datacentre IP Ranges');
} else {
	exit('Could not generate file: the output file could not be written');
}