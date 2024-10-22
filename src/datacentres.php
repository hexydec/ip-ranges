<?php
declare(strict_types=1);
namespace hexydec\ipaddresses;

class datacentres extends generate {

	// protected function getAzure(?string $cache = null) : \Generator {
	// 	$url = 'https://www.microsoft.com/en-my/download/details.aspx?id=56519';
	// 	if (($file = $this->fetch($url, $cache)) !== false) {
	// 		if (\preg_match('/<a href="([^"]+\\.json)"/i', $file, $match)) {
	// 			\sleep(3);
	// 			if (($source = $this->fetch(\htmlspecialchars_decode($match[1]), $cache, true, ['Referer: '.$url])) !== false && ($json = \json_decode($source)) !== null) {
	// 				foreach ($json->values AS $item) {
	// 					foreach ($item->properties->addressPrefixes ?? [] AS $item) {
	// 						yield [
	// 							'name' => 'Microsoft Azure',
	// 							'range' => $item
	// 						];
	// 					}
	// 				}
	// 			}
	// 		}
	// 	}
	// }

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
		if (($file = $this->fetch($file, $cache, false)) !== false) {

		}
	}

	protected function getAsns(array $sources, ?string $cache = null) : \Generator {
		if (($file = $this->fetch('https://github.com/ipverse/asn-ip/archive/refs/heads/master.zip', $cache, false)) !== false) {

			// open zip file and inspect files
			$za = new \ZipArchive();
			if ($za->open($file, \ZipArchive::RDONLY)) {
				foreach ($sources AS $source) {
					foreach ($this->getFromText($source, $cache) AS $item) {
						if (($content = $za->getFromName('asn-ip-master/as/'.$item.'/aggregated.json')) === false) {

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
			foreach ($this->getAzure($item, $cache) AS $item) {
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

		// ASNs
		$map = [
			'https://github.com/Umkus/ip-index/raw/refs/heads/master/data/asns_dcs.csv',
			'https://github.com/Umkus/ip-index/raw/refs/heads/master/data/asns_dcs_unconfirmed.csv'
		];
		foreach ($this->getAsns($map, $cache) AS $item) {
			yield $item;
		}
	}
}