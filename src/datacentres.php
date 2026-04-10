<?php
declare(strict_types=1);
namespace hexydec\ipaddresses;

class datacentres extends generate {

	/**
	 * Maps a cloud provider region identifier to an ISO 3166-1 alpha-2 country code
	 *
	 * @param string $region The cloud provider region string (e.g. 'us-east-1', 'westeurope')
	 * @return ?string The two-letter country code, or null if the region is not recognised
	 */
	protected static function regionToCountry(string $region) : ?string {
		$map = regionMapping::get();
		$region = \strtolower(\trim($region));

		// exact match
		if (isset($map[$region])) {
			return $map[$region];

		// strip trailing number for AWS/Oracle style (e.g. eu-north-1 → eu-north)
		} else {
			$stripped = \preg_replace('/-?\d+$/', '', $region);
			if (isset($map[$stripped])) {
				return $map[$stripped];
			}
		}

		return null;
	}

	/**
	 * Fetches and yields Microsoft Azure IP ranges with their region identifiers
	 *
	 * @param string $url The URL of the Azure IP ranges JSON file
	 * @param ?string $cache The directory to cache downloaded data, or null to skip caching
	 * @return \Generator Yields associative arrays with 'range' and 'region' keys
	 */
	protected function getAzure(string $name, string $url, ?string $cache = null) : \Generator {
		if (($file = $this->fetch($url, $cache)) !== false && ($json = \json_decode($file)) !== null) {
			foreach ($json->values AS $item) {
				$region = $item->properties->region ?? null;
				foreach ($item->properties->addressPrefixes ?? [] AS $prefix) {
					yield ['name' => $name, 'range' => $prefix, 'country' => $region ? self::regionToCountry($region) : null];
				}
			}
		}
	}

	/**
	 * Fetches and yields Linode/Akamai IP ranges from the geoip endpoint
	 *
	 * @param ?string $cache The directory to cache downloaded data, or null to skip caching
	 * @return \Generator Yields CIDR range strings
	 */
	protected function getLinode(?string $cache = null) : \Generator {
		if (($result = $this->fetch('https://geoip.linode.com/', $cache)) !== false) {
			foreach (\explode("\n", \trim($result)) AS $item) {
				if (!\str_starts_with($item, '#')) {
					$parts = \explode(',', $item);
					yield [
						'name' => 'Linode',
						'range' => $parts[0],
						'country' => \strtolower($parts[1])
					];
				}
			}
		}
	}

	/**
	 * Fetches and yields Oracle Cloud IP ranges with their region identifiers
	 *
	 * @param ?string $cache The directory to cache downloaded data, or null to skip caching
	 * @return \Generator Yields associative arrays with 'range' and 'region' keys
	 */
	protected function getOracle(?string $cache = null) : \Generator {
		if (($result = $this->fetch('https://docs.oracle.com/en-us/iaas/tools/public_ip_ranges.json', $cache)) !== false && ($json = \json_decode($result)) !== null) {
			foreach ($json->regions ?? [] AS $region) {
				foreach ($region->cidrs AS $item) {
					yield [
						'name' => 'Oracle',
						'range' => $item->cidr,
						'country' => $region->region ? self::regionToCountry($region->region) : null
					];
				}
			}
		}
	}

	/**
	 * Determines whether an ASN description matches known datacentre/hosting provider patterns
	 *
	 * @param string $name The ASN description to test
	 * @return bool True if the name matches a known hosting/datacentre provider
	 */
	protected function asnMatches(string $name) : bool {

		// string matches
		$matches = [
			'GoDaddy', 'IONOS', 'Hetzner', 'DigitalOcean', 'PacketHub', '31173 Services', 'Blix Solutions AS', 'Keminet', 'Private Layer', 'xtom', 'Zenlayer', 'QuadraNet', 'UK-2 Limited', 'Squarespace', 'siteground', 'rackspace', 'namecheap', 'dedipower', 'pulsant', 'MediaTemple', 'valice', 'GANDI', 'PAIR-NETWORKS', 'webzilla', 'softlayer', 'Joyent', 'APPTOCLOUD', 'www.mvps.net', 'ServInt', 'Incapsula', 'Red Hat', 'Vertisoft', 'Secured Network Services', 'Akamai', 'IT Outsourcing LLC', 'fly.io', 'NetPlanet', 'ArcServe', 'Data Techno Park', 'VISANET', 'Virtual Systems', 'Latitude', 'LLC VK', 'Smart Ape', 'RECONN', 'Adman', 'StormWall', 'DDOS-GUARD', 'IQWeb FZ-LLC', 'JSC IOT', 'NForce', 'EuroByte', 'firstcolo', 'dataforest', 'Voxility', 'Atman', 'WorldStream', 'Psychz', 'WebSupport', 'STARK INDUSTRIES SOLUTIONS', 'aurologic', 'Salesforce', 'MEVSPACE', 'QWARTA', 'Selectel', 'Kaspersky', 'Domain names registrar', 'Tucows', 'Beget', 'Fastly', 'Alibaba', 'netcup', 'edgeuno', 'equinix', 'lumen technologies', 'unitas global', 'The Constant Company', 'atlantic.net', 'crocweb', 'small orange', 'hivelocity', 'thehostingsolution', 'NearlyFreeSpeech.NET', 'joink', 'webline services', 'ipower', 'Onehostplanet', 'register.com', 'enom solutions', 'GHOSTnet', 'WebHosts R Us', 'PlanetHoster', 'GLOBALHOSTINGSOLUTIONS', 'ALLHOSTSHOP.COM', 'Xhostserver', 'ROCKHOSTER', 'QuickHostUK', 'EUROHOSTER', 'MKBWebhoster', 'Webhosting24', 'VMhosts', 'QHOSTER', 'webhoster.de', 'BtHoster', 'ASPhostBG', 'Fasthosts', 'SnTHostings', 'MegaHostZone', 'turnkey internet inc', 'GmhostGrupp', 'LightEdge Solutions', 'Digital Edge Korea', 'Robustedge Software And Digital Networks Pvt. Ltd', 'Edge Centres', 'Edge Speed', 'Edgenext', 'data edge', 'Edgecast', 'ADVANCED KNOWLEDGE NETWORKS', 'Block Edge Technologies', 'Edgevana', 'EDGE CLOUD (SG) PTE', 'NEURALEDGE TECHNOLOGIES', 'DIGITAL EDGE VENTURES', 'GreenEdge B.V', 'SBA Edge', 'Redge Technologies', 'EDGEAM', 'EdgeCenter', 'Transparent Edge Services', 'TECHHEDGE LABS ANS', 'EdgeIX', 'Newedge Facilities Management', ' 4EDGE TECNOLOGIA', 'LoadEdge', 'EdgeConneX', 'Defend Edge', 'EDGENAT CLOUD', 'BrightEdge Technologies', 'RamNode', 'LeaseWeb', 'zscaler', 'Atlantic Metro', 'BIT BV', 'BSO Network Solutions', 'Contabo', 'DEFT.COM', 'Duocast', 'Eonix', 'ALTINEA', 'Flexential', 'GigeNET', 'Ikoula', 'Interserver', 'Keyweb', 'Nexeon', 'NovoServe', 'o2switch', 'odn', 'plus.line', 'ReliableSite.Net', 'SCALEWAY', 'Serverius', 'Sharktech', 'SysEleven', 'UKDedicated', 'VIRTUA SYSTEMS', 'Vautron Rechenzentrum', 'We Dare', 'WebNX', 'WholeSale Internet', 'dogado', 'i3D.net', 'root SAS', 'velia.net', 'webgo', 'Performive', 'TierPoint', 'wiit', 'Enzu', 'DeinProvider', 'The Internet Engineering Group', 'France IX Services', 'LLC Digital Network', 'Wisconsin CyberLynk Network', 'sucuri', 'Liquid Web', '2342 Verwaltungs', 'SEDO GmbH'
		];
		foreach ($matches AS $item) {
			if (\mb_stripos($name, $item) !== false) {
				return true;
			}
		}

		// see if ASN name matches regex
		$re = '/\bcolo(?!m|rado|n|mbo|r|proctology|ur)|(?<!\b[g-])host(ing|ed)?\b(?! hotel| call centre)|\bhost(?!works-as-ap)(ing|ed|s)?(?! hotel)|Servers(?!orgung)|\bOVH\b|\bVPS|VPS\b|data ?cent(?:er|re)s?|\bCDN(?!bt)|CDN\b|^Network Solutions|^render$|^20i\b|(Lease|Time|Liquid)(?! )Web|\bG-Core|^aeza|^steadfast$/i';
		if (\preg_match($re, $name)) {
			return true;
		}
		return false;
	}

	/**
	 * Fetches ASN subnet data from ipverse and yields ranges belonging to hosting/datacentre providers
	 *
	 * @param ?string $cache The directory to cache downloaded data, or null to skip caching
	 * @return \Generator Yields associative arrays with 'name' and 'range' keys
	 */
	protected function getAsns(?string $cache = null) : \Generator {
		progress::status('Fetching ASN ranges');
		$src = 'https://github.com/ipverse/asn-ip/archive/refs/heads/master.zip';
		if (($file = $this->fetch($src, $cache, false)) !== false) {

			// open zip file and inspect files
			$za = new \ZipArchive();
			if ($za->open($file, \ZipArchive::RDONLY)) {
				$count = $za->numFiles;
				$time = \time();
				for ($i = 0; $i < $count; $i++) {
					$current = \time();
					if ($current !== $time) {
						\set_time_limit(30);
						progress::render($count, $i, ['Processing ASN data']);
						$time = $current;
					}
					$filename = $za->getNameIndex($i);
					if (!\str_ends_with($filename, '/aggregated.json')) {

					} elseif (($content = $za->getFromIndex($i)) === false) {

					} elseif (($json = \json_decode($content)) === false) {

					} elseif (!empty($json->description) && $this->asnMatches($json->description)) {
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

	/**
	 * Compiles datacentre IP ranges from AWS, GCP, Azure, CloudFlare, Linode, Oracle, IBM, and ASN sources
	 *
	 * @param ?string $cache The directory to cache downloaded data, or null to skip caching
	 * @return \Generator Yields associative arrays with 'name', 'range', and 'country' keys
	 */
	public function compile(?string $cache = null) : \Generator {

		// AWS and GCP
		$map = [
			'Amazon AWS' => 'https://ip-ranges.amazonaws.com/ip-ranges.json',
			'Google Cloud Platform' => 'https://www.gstatic.com/ipranges/cloud.json'
		];
		foreach ($map AS $key => $url) {
			progress::status('Fetching '.$key.' ranges');
			if (($result = $this->fetch($url, $cache)) !== false && ($json = \json_decode($result)) !== null) {
				foreach ($json->prefixes ?? [] AS $item) {
					$range = $item->ipv4Prefix ?? $item->ipv6Prefix ?? $item->ip_prefix ?? null;
					$region = $item->region ?? $item->scope ?? '';
					if ($range !== null) {
						yield [
							'name' => $key,
							'range' => $range,
							'country' => self::regionToCountry($region)
						];
					}
				}
				foreach (\array_merge($json->ipv6_prefixes ?? [], $json->ipv6Prefixes ?? []) AS $item) {
					$range = $item->ipv6_prefix ?? $item->ipv6Prefix ?? null;
					$region = $item->region ?? $item->scope ?? '';
					if ($range !== null) {
						yield [
							'name' => $key,
							'range' => $range,
							'country' => self::regionToCountry($region)
						];
					}
				}
			}
		}

		// Azure
		$map = [
			'Microsoft Azure Public' => 'https://azureipranges.azurewebsites.net/Data/Public.json',
			'Microsoft Azure Government' => 'https://azureipranges.azurewebsites.net/Data/AzureGovernment.json',
			'Microsoft Azure Germany' => 'https://azureipranges.azurewebsites.net/Data/AzureGermany.json',
			'Microsoft Azure China' => 'https://azureipranges.azurewebsites.net/Data/China.json'
		];
		foreach ($map AS $key => $url) {
			progress::status('Fetching '.$key.' ranges');
			yield from $this->getAzure($key, $url, $cache);
		}

		// cloudflare
		$map = [
			'https://www.cloudflare.com/ips-v4/',
			'https://www.cloudflare.com/ips-v6/'
		];
		progress::status('Fetching CloudFlare ranges');
		foreach ($map AS $item) {
			foreach ($this->getFromText($item, $cache) AS $value) {
				yield [
					'name' => 'CloudFlare',
					'range' => $value,
					'country' => null
				];
			}
		}

		// linode
		progress::status('Fetching Linode ranges');
		yield from $this->getLinode($cache);

		// oracle
		progress::status('Fetching Oracle ranges');
		yield from $this->getOracle($cache);

		// IBM
		progress::status('Fetching IBM ranges');
		foreach ($this->getFromHtml('https://cloud.ibm.com/docs/security-groups?topic=security-groups-ibm-cloud-ip-ranges', $cache) AS $item) {
			yield [
				'name' => 'IBM',
				'range' => $item,
				'country' => null
			];
		}

		// get ranges from matching ASN's
		foreach ($this->getAsns($cache) AS $item) {
			$item['country'] = null;
			yield $item;
		}
	}
}