<?php
declare(strict_types=1);
namespace hexydec\ipaddresses;

require __DIR__.'/datacentres.php';

// define files
$files = [__DIR__.'/output/datacentres.csv', __DIR__.'/output/datacentres.txt'];
$cache = \in_array('--cache', $argv ?? []) ? __DIR__.'/cache/' : null;

// create object and generate output
$obj = new datacentres();
if (($count = $obj->generate($files, $cache)) !== false) {
	exit('Saved '.$count.' Datacentre IP Ranges');
} else {
	exit('Could not generate file: the output could not be written');
}