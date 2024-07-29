<?php
declare(strict_types=1);
namespace hexydec\ipaddresses;

require __DIR__.'/datacentres.php';

$files = [__DIR__.'/output/datacentres.csv', __DIR__.'/output/datacentres.txt'];
$cache = __DIR__.'/cache/';
$obj = new datacentres();
if (($count = $obj->save($files, $cache)) !== false) {
	exit('Saved '.$count.' Datacentre IP Ranges');
} else {
	exit('Could not generate file: the output could not be written');
}