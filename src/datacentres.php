<?php
declare(strict_types=1);
namespace hexydec\ipaddresses;

class datacentres extends generate {

	protected function getAzure(?string $cache = null) : \Generator {
		if (($file = $this->fetch('https://www.microsoft.com/en-my/download/details.aspx?id=56519', $cache)) !== false) {
			if (\preg_match('/<a href="([^"]+\\.json)"/i', $file, $match)) {
				if (($source = \file_get_contents(\htmlspecialchars_decode($match[1]))) !== false && ($json = \json_decode($source)) !== null) {
					foreach ($json->values AS $item) {
						foreach ($item->properties->addressPrefixes ?? [] AS $item) {
							yield [
								'name' => 'Microsoft Azure',
								'range' => $item
							];
						}
					}
				}
			}
		}
	}

	protected function getAsns(?string $cache = null) : \Generator {
		if (($file = $this->fetch('https://github.com/ipverse/asn-ip/archive/refs/heads/master.zip', $cache, false)) !== false) {

			// open zip file and inspect files
			$za = new \ZipArchive();
			if ($za->open($file, \ZipArchive::RDONLY)) {
				for ($i = 0; $i < $za->numFiles; $i++) { 
					$stat = $za->statIndex($i);

					// find matching file
					if ($stat['size'] && \str_starts_with($stat['name'], 'asn-ip-master/as/') && \str_ends_with($stat['name'], '/aggregated.json')) {

						// open and decode file
						if (($source = $za->getFromIndex($i)) !== false && ($json = \json_decode($source)) !== false) {

							// see if ASN name matches regex
							$re = '/\bcolo(?!mbia|rado|n|mbo|r|proctology)|(?<!\bg)host(ing|ed)?\b(?! hotel)|\bhost(ing|ed)?(?! hotel)|Servers(?!orgung)|GoDaddy|IONOS|Hetzner|LiquidWeb|DIGITALOCEAN-ASN|Squarespace|shopify|\bOVH\b|siteground|rackspace|namecheap|linode|dedipower|pulsant|MediaTemple|valice|GANDI.NET|PAIR-NETWORKS|webzilla|softlayer|Joyent|APPTOCLOUD|www\.mvps\.net|\bVPS|VPS\b|datacenter|ServInt|Incapsula|\bCDN(?!bt)|Red Hat|Vertisoft|Secured Network Services|Akamai|^Network Solutions|IT Outsourcing LLC|fly\.io|NetPlanet|ArcServe|^render$/i';
							if (isset($json->description) && !\str_contains(\mb_strtolower($json->description), 'telecom') && \preg_match($re, $json->description, $match)) {
								foreach ($json->subnets->ipv4 ?? [] AS $item) {
									yield [
										'name' => $json->description,
										'range' => $item
									];
								}
								foreach ($json->subnets->ipv6 ?? [] AS $item) {
									yield [
										'name' => $json->description,
										'range' => $item
									];
								}
							}
						}
					}
				}
			}
		}
	}

	public function compile(?string $cache = null) : \Generator {
		$map = [
			'Amazon AWS' => 'https://ip-ranges.amazonaws.com/ip-ranges.json',
			'Google Cloud Platform' => 'https://www.gstatic.com/ipranges/cloud.json'
		];
		foreach ($map AS $key => $item) {
			foreach ($this->getFromJson($item, $cache) AS $value) {
				yield [
					'name' => $key,
					'range' => $value
				];
			}
		}
		foreach ($this->getAzure($cache) AS $item) {
			yield $item;
		}
		$map = [
			'https://www.cloudflare.com/ips-v4/',
			'https://www.cloudflare.com/ips-v6/'
		];
		foreach ($map AS $key => $item) {
			foreach ($this->getFromText($item, $cache) AS $value) {
				yield [
					'name' => 'CloudFlare',
					'range' => $value
				];
			}
		}
		foreach ($this->getAsns($cache) AS $item) {
			yield $item;
		}
	}
}