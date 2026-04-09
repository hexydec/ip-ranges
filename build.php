<?php
declare(strict_types=1);
namespace hexydec\ipaddresses;

require __DIR__.'/src/autoload.php';

// parse options
$cache = \in_array('--cache', $argv ?? []) || isset($_GET['cache']) ? \str_replace('\\', '/', __DIR__).'/cache/' : null;
$types = ['datacentres', 'crawlers', 'countries'];
$requested = \array_values(\array_intersect($argv ?? [], $types));
$build = $requested ?: $types;

// datacentres
if (\in_array('datacentres', $build, true)) {
	$files = [
		__DIR__.'/output/datacentres.csv',
		__DIR__.'/output/datacentres.txt',
		__DIR__.'/output/datacentres.json',
		__DIR__.'/output/datacentres-ipv4.csv',
		__DIR__.'/output/datacentres-ipv4.txt',
		__DIR__.'/output/datacentres-ipv4.json',
		__DIR__.'/output/datacentres-ipv6.csv',
		__DIR__.'/output/datacentres-ipv6.txt',
		__DIR__.'/output/datacentres-ipv6.json'
	];
	$obj = new datacentres();
	if (($count = $obj->save($files, $cache)) !== false) {
		echo 'Saved '.$count.' Datacentre IP Ranges'."\n\n";
	} else {
		exit('Could not generate file: the output could not be written');
	}
}

// crawlers
if (\in_array('crawlers', $build, true)) {
	$files = [
		__DIR__.'/output/crawlers.csv',
		__DIR__.'/output/crawlers.txt',
		__DIR__.'/output/crawlers.json',
		__DIR__.'/output/crawlers-ipv4.csv',
		__DIR__.'/output/crawlers-ipv4.txt',
		__DIR__.'/output/crawlers-ipv4.json',
		__DIR__.'/output/crawlers-ipv6.csv',
		__DIR__.'/output/crawlers-ipv6.txt',
		__DIR__.'/output/crawlers-ipv6.json'
	];
	$obj = new crawlers();
	if (($count = $obj->save($files, $cache)) !== false) {
		echo 'Saved '.$count.' Crawler IP Ranges'."\n\n";
	} else {
		exit('Could not generate file: the output could not be written');
	}
}

// countries
if (\in_array('countries', $build, true)) {
	$time = \microtime(true);
	$files = [
		__DIR__.'/output/countries.csv',
		__DIR__.'/output/countries.json',
		__DIR__.'/output/countries-ipv4.csv',
		__DIR__.'/output/countries-ipv4.json',
		__DIR__.'/output/countries-ipv6.csv',
		__DIR__.'/output/countries-ipv6.json'
	];
	$obj = new countries();
	if (($count = $obj->save($files, $cache)) !== false) {
		echo 'Saved '.$count.' country IP Ranges in '.\number_format(\microtime(true) - $time, 1).'s using '.\number_format(\memory_get_peak_usage()/1024/1024, 2).'MB of memory';
	} else {
		exit('Could not generate file: the output could not be written');
	}
}