<?php
declare(strict_types=1);

\spl_autoload_register(function (string $class) : void {
	$classes = [
		'hexydec\\ipaddresses\\generate' => __DIR__.'/generate.php',
		'hexydec\\ipaddresses\\datacentres' => __DIR__.'/datacentres.php',
		'hexydec\\ipaddresses\\crawlers' => __DIR__.'/crawlers.php',
		'hexydec\\ipaddresses\\countries' => __DIR__.'/countries.php',
		'hexydec\\ipaddresses\\regionMapping' => __DIR__.'/helpers/region-mapping.php',
		'hexydec\\ipaddresses\\aggregator' => __DIR__.'/helpers/aggregator.php',
		'hexydec\\ipaddresses\\progress' => __DIR__.'/helpers/progress.php'
	];
	if (isset($classes[$class])) {
		require $classes[$class];
	}
});