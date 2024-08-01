<?php
declare(strict_types=1);
namespace hexydec\ipaddresses;

require __DIR__.'/src/autoload.php';

// define files
$cache = \in_array('--cache', $argv ?? []) ? __DIR__.'/cache/' : null;

// create object and generate output
$files = [__DIR__.'/output/datacentres.csv', __DIR__.'/output/datacentres.txt', __DIR__.'/output/datacentres.json'];
$obj = new datacentres();
if (($count = $obj->save($files, $cache)) !== false) {
	echo 'Saved '.$count.' Datacentre IP Ranges'."\n";
} else {
	exit('Could not generate file: the output could not be written');
}

// create object and generate output
$files = [__DIR__.'/output/crawlers.csv', __DIR__.'/output/crawlers.txt', __DIR__.'/output/crawlers.json'];
$obj = new crawlers();
if (($count = $obj->save($files, $cache)) !== false) {
	exit('Saved '.$count.' Crawler IP Ranges');
} else {
	exit('Could not generate file: the output could not be written');
}