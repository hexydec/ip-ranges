<?php
declare(strict_types=1);

\spl_autoload_register(function (string $class) : void {
	$classes = [
		'hexydec\\ipaddresses\\generate' => __DIR__.'/generate.php',
		'hexydec\\ipaddresses\\datacentres' => __DIR__.'/datacentres.php',
		'hexydec\\ipaddresses\\crawlers' => __DIR__.'/crawlers.php'
	];
	if (isset($classes[$class])) {
		require $classes[$class];
	}
});