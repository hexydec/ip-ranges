<?php
declare(strict_types=1);
namespace hexydec\ipaddresses;

class datacentres extends generate {

	protected function getAzure(string $url, ?string $cache = null) : \Generator {
		if (($file = $this->fetch($url, $cache)) !== false && ($json = \json_decode($file)) !== null) {
			foreach ($json->values AS $item) {
				foreach ($item->properties->addressPrefixes ?? [] AS $item) {
					yield $item;
				}
			}
		}
	}

	protected function getLinode(?string $cache = null) : \Generator {
		if (($result = $this->fetch('https://geoip.linode.com/', $cache)) !== false) {
			foreach (\explode("\n", \trim($result)) AS $item) {
				yield \explode(',', $item, 2)[0];
			}
		}
	}

	protected function getAsnIds(string $file, ?string $cache = null) {
		if (($data = $this->fetch($file, $cache)) !== false) {
			$asns = [];
			foreach (\explode("\n", \trim($data)) AS $item) {
				$parts = \explode(' ', $item, 2);
				if (isset($parts[1])) {
					$name = \explode(',', $parts[1], 2);
					$asns[$parts[0]] = [
						'name' => \trim($name[0]),
						'country' => isset($name[1]) ? \trim($name[1]) : null
					];
				}
			}

			// see if ASN name matches regex
			$re = '/\bcolo(?!mbia|rado|n|mbo|r|proctology)|(?<!\bg)host(ing|ed)?\b(?! hotel)|\bhost(ing|ed)?(?! hotel)|Servers(?!orgung)|GoDaddy|IONOS|Hetzner|LiquidWeb|DIGITALOCEAN-ASN|PacketHub|M247|31173 Services|Blix Solutions AS|Keminet|Private Layer|xtom|Zenlayer|QuadraNet|UK-2 Limited|Squarespace|\bOVH\b|siteground|rackspace|namecheap|dedipower|pulsant|MediaTemple|valice|GANDI.NET|PAIR-NETWORKS|webzilla|softlayer|Joyent|APPTOCLOUD|www\.mvps\.net|\bVPS|VPS\b|datacenter|ServInt|Incapsula|\bCDN(?!bt)|Red Hat|Vertisoft|Secured Network Services|Akamai|^Network Solutions|IT Outsourcing LLC|fly\.io|NetPlanet|ArcServe|^render$|^20i\b|Data Techno Park|VISANET|Aeza|Virtual Systems|Latitude|Equinix|Baxet|Yandex\.Cloud|LLC VK|Smart Ape|RECONN|Adman|StormWall|DDOS-GUARD|IQWeb FZ-LLC|JSC IOT|NForce|EuroByte|firstcolo|dataforest|Voxility|Atman|WorldStream|Psychz|WebSupport|STARK INDUSTRIES SOLUTIONS|TimeWeb|LeaseWeb|Liquid Web|aurologic|G-Core|Salesforce|MEVSPACE|QWARTA|GoDaddy|Selectel|Kaspersky|Domain names registrar|Tucows|20i Limited|Beget|Fastly|Alibaba|netcup/i';
			$found = [];
			foreach ($asns AS $key => $item) {
				if (\preg_match($re, $item['name'])) {
					$found[$key] = $item;
				}
			}
			return \array_keys($found);
		}
	}

	protected function getAsns(array $asns, ?string $cache = null) : \Generator {
		if (($file = $this->fetch('https://github.com/ipverse/asn-ip/archive/refs/heads/master.zip', $cache, false)) !== false) {

			// open zip file and inspect files
			$za = new \ZipArchive();
			if ($za->open($file, \ZipArchive::RDONLY)) {
				foreach ($asns AS $asn) {
					if (($content = $za->getFromName('asn-ip-master/as/'.$asn.'/aggregated.json')) === false) {

					} elseif (($json = \json_decode($content)) === false) {
						
					} else {
						foreach ($json->subnets->ipv4 ?? [] AS $item) {
							yield [
								'name' => $json->description ?? null,
								'range' => $item
							];
						}
						foreach ($json->subnets->ipv6 ?? [] AS $item) {
							yield [
								'name' => $json->description ?? null,
								'range' => $item
							];
						}
					}
				}
			}
		}
	}

	public function compile(?string $cache = null) : \Generator {

		// AWS and GCP
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

		// Azure
		$map = [
			'Microsoft Azure Public' => 'https://azureipranges.azurewebsites.net/Data/Public.json',
			'Microsoft Azure Government' => 'https://azureipranges.azurewebsites.net/Data/AzureGovernment.json',
			'Microsoft Azure Germany' => 'https://azureipranges.azurewebsites.net/Data/AzureGermany.json',
			'Micorosft Azure China' => 'https://azureipranges.azurewebsites.net/Data/China.json'
		];
		foreach ($map AS $key => $item) {
			foreach ($this->getAzure($item, $cache) AS $value) {
				yield [
					'name' => $key,
					'range' => $value
				];
			}
		}

		// cloudflare
		$map = [
			'https://www.cloudflare.com/ips-v4/',
			'https://www.cloudflare.com/ips-v6/'
		];
		foreach ($map AS $item) {
			foreach ($this->getFromText($item, $cache) AS $value) {
				yield [
					'name' => 'CloudFlare',
					'range' => $value
				];
			}
		}

		// linode
		foreach ($this->getLinode() AS $item) {
			yield [
				'name' => 'Linode',
				'range' => $value
			];
		}

		// Filter ASN ID's
		if (($asns = $this->getAsnIds('https://ftp.ripe.net/ripe/asnames/asn.txt', $cache)) !== false) {
			foreach ($this->getAsns($asns, $cache) AS $item) {
				yield $item;
			}
		}
	}
}