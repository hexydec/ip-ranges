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

	protected function getOracle(?string $cache = null) : \Generator {
		if (($result = $this->fetch('https://docs.oracle.com/en-us/iaas/tools/public_ip_ranges.json', $cache)) !== false && ($json = \json_decode($result)) !== null) {
			foreach ($json->regions ?? [] AS $region) {
				foreach ($region->cidrs AS $item) {
					yield $item->cidr;
				}
			}
		}
	}

	protected function asnMatches(string $name) : bool {

		// string matches
		$matches = [
			'GoDaddy', 'IONOS', 'Hetzner', 'DigitalOcean', 'PacketHub', '31173 Services', 'Blix Solutions AS', 'Keminet', 'Private Layer', 'xtom', 'Zenlayer', 'QuadraNet', 'UK-2 Limited', 'Squarespace', 'siteground', 'rackspace', 'namecheap', 'dedipower', 'pulsant', 'MediaTemple', 'valice', 'GANDI', 'PAIR-NETWORKS', 'webzilla', 'softlayer', 'Joyent', 'APPTOCLOUD', 'www.mvps.net', 'ServInt', 'Incapsula', 'Red Hat', 'Vertisoft', 'Secured Network Services', 'Akamai', 'IT Outsourcing LLC', 'fly.io', 'NetPlanet', 'ArcServe', 'Data Techno Park', 'VISANET', 'Virtual Systems', 'Latitude', 'LLC VK', 'Smart Ape', 'RECONN', 'Adman', 'StormWall', 'DDOS-GUARD', 'IQWeb FZ-LLC', 'JSC IOT', 'NForce', 'EuroByte', 'firstcolo', 'dataforest', 'Voxility', 'Atman', 'WorldStream', 'Psychz', 'WebSupport', 'STARK INDUSTRIES SOLUTIONS', 'aurologic', 'Salesforce', 'MEVSPACE', 'QWARTA', 'Selectel', 'Kaspersky', 'Domain names registrar', 'Tucows', 'Beget', 'Fastly', 'Alibaba', 'netcup', 'edgeuno', 'equinix', 'lumen technologies', 'unitas global', 'The Constant Company', 'atlantic.net', 'crocweb', 'small orange', 'hivelocity', 'thehostingsolution', 'NearlyFreeSpeech.NET', 'joink', 'webline services', 'ipower', 'Onehostplanet', 'register.com', 'enom solutions', 'GHOSTnet', 'WebHosts R Us', 'PlanetHoster', 'GLOBALHOSTINGSOLUTIONS', 'ALLHOSTSHOP.COM', 'Xhostserver', 'ROCKHOSTER', 'QuickHostUK', 'EUROHOSTER', 'MKBWebhoster', 'Webhosting24', 'VMhosts', 'QHOSTER', 'webhoster.de', 'BtHoster', 'ASPhostBG', 'Fasthosts', 'SnTHostings', 'MegaHostZone', 'turnkey internet inc', 'GmhostGrupp', 'LightEdge Solutions', 'Digital Edge Korea', 'Robustedge Software And Digital Networks Pvt. Ltd', 'Edge Centres', 'Edge Speed', 'Edgenext', 'data edge', 'Edgecast', 'ADVANCED KNOWLEDGE NETWORKS', 'Block Edge Technologies', 'Edgevana', 'EDGE CLOUD (SG) PTE', 'NEURALEDGE TECHNOLOGIES', 'DIGITAL EDGE VENTURES', 'GreenEdge B.V', 'SBA Edge', 'Redge Technologies', 'EDGEAM', 'EdgeCenter', 'Transparent Edge Services', 'TECHHEDGE LABS ANS', 'EdgeIX', 'Newedge Facilities Management', ' 4EDGE TECNOLOGIA', 'LoadEdge', 'EdgeConneX', 'Defend Edge', 'EDGENAT CLOUD', 'BrightEdge Technologies', 'RamNode', 'LeaseWeb', 'zscaler', 'Atlantic Metro', 'BIT BV', 'BSO Network Solutions', 'Contabo', 'DEFT.COM', 'Duocast', 'Eonix', 'ALTINEA', 'Flexential', 'GigeNET', 'Ikoula', 'Interserver', 'Keyweb', 'Nexeon', 'NovoServe', 'o2switch', 'odn', 'plus.line', 'ReliableSite.Net', 'SCALEWAY', 'Serverius', 'Sharktech', 'SysEleven', 'UKDedicated', 'VIRTUA SYSTEMS', 'Vautron Rechenzentrum', 'We Dare', 'WebNX', 'WholeSale Internet', 'dogado', 'i3D.net', 'root SAS', 'velia.net', 'webgo', 'Performive', 'TierPoint', 'wiit'
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

	protected function getAsns(?string $cache = null) : \Generator {
		if (($file = $this->fetch('https://github.com/ipverse/asn-ip/archive/refs/heads/master.zip', $cache, false)) !== false) {

			// open zip file and inspect files
			$za = new \ZipArchive();
			if ($za->open($file, \ZipArchive::RDONLY)) {
				$count = $za->numFiles;
				$asns = [];
				for ($i = 0; $i < $count; $i++) {
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
					} elseif (isset($this->asns[$json->asn])) {
						$asns[$json->asn] = $json->description;
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
			'Microsoft Azure China' => 'https://azureipranges.azurewebsites.net/Data/China.json'
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
		foreach ($this->getLinode($cache) AS $item) {
			yield [
				'name' => 'Linode',
				'range' => $item
			];
		}

		// oracle
		foreach ($this->getOracle($cache) AS $item) {
			yield [
				'name' => 'Oracle',
				'range' => $item
			];
		}

		// IBM
		foreach ($this->getFromHtml('https://cloud.ibm.com/docs/security-groups?topic=security-groups-ibm-cloud-ip-ranges', $cache) AS $item) {
			yield [
				'name' => 'IBM',
				'range' => $item
			];
		}

		// get ranges from matching ASN's
		foreach ($this->getAsns($cache) AS $item) {
			yield $item;
		}
	}
}