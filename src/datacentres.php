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

	protected function asnMatches(string $name, int $asn) {

		// string matches
		$matches = [
			'GoDaddy', 'IONOS', 'Hetzner', 'DigitalOcean', 'PacketHub', '31173 Services', 'Blix Solutions AS', 'Keminet', 'Private Layer', 'xtom', 'Zenlayer', 'QuadraNet', 'UK-2 Limited', 'Squarespace', 'siteground', 'rackspace', 'namecheap', 'dedipower', 'pulsant', 'MediaTemple', 'valice', 'GANDI.NET', 'PAIR-NETWORKS', 'webzilla', 'softlayer', 'Joyent', 'APPTOCLOUD', 'www.mvps.net', 'ServInt', 'Incapsula', 'Red Hat', 'Vertisoft', 'Secured Network Services', 'Akamai', 'IT Outsourcing LLC', 'fly.io', 'NetPlanet', 'ArcServe', 'Data Techno Park', 'VISANET', 'Virtual Systems', 'Latitude', 'LLC VK', 'Smart Ape', 'RECONN', 'Adman', 'StormWall', 'DDOS-GUARD', 'IQWeb FZ-LLC', 'JSC IOT', 'NForce', 'EuroByte', 'firstcolo', 'dataforest', 'Voxility', 'Atman', 'WorldStream', 'Psychz', 'WebSupport', 'STARK INDUSTRIES SOLUTIONS', 'aurologic', 'Salesforce', 'MEVSPACE', 'QWARTA', 'Selectel', 'Kaspersky', 'Domain names registrar', 'Tucows', 'Beget', 'Fastly', 'Alibaba', 'netcup', 'edgeuno', 'equinix', 'lumen technologies', 'unitas global', 'The Constant Company', 'atlantic.net', 'crocweb', 'small orange', 'hivelocity', 'thehostingsolution', 'NearlyFreeSpeech.NET', 'joink', 'webline services', 'ipower', 'Onehostplanet', 'register.com', 'enom solutions', 'GHOSTnet', 'WebHosts R Us', 'PlanetHoster', 'GLOBALHOSTINGSOLUTIONS', 'ALLHOSTSHOP.COM', 'Xhostserver', 'ROCKHOSTER', 'QuickHostUK', 'EUROHOSTER', 'MKBWebhoster', 'Webhosting24', 'VMhosts', 'QHOSTER', 'webhoster.de', 'BtHoster', 'ASPhostBG', 'Fasthosts', 'SnTHostings', 'MegaHostZone', 'turnkey internet inc', 'GmhostGrupp', 'LightEdge Solutions', 'Digital Edge Korea', 'Robustedge Software And Digital Networks Pvt. Ltd', 'Edge Centres', 'Edge Speed', 'Edgenext', 'data edge', 'Edgecast', 'ADVANCED KNOWLEDGE NETWORKS', 'Block Edge Technologies', 'Edgevana', 'EDGE CLOUD (SG) PTE', 'NEURALEDGE TECHNOLOGIES', 'DIGITAL EDGE VENTURES', 'GreenEdge B.V', 'SBA Edge', 'Redge Technologies', 'EDGEAM', 'EdgeCenter', 'Transparent Edge Services', 'TECHHEDGE LABS ANS', 'EdgeIX', 'Newedge Facilities Management', ' 4EDGE TECNOLOGIA', 'LoadEdge', 'EdgeConneX', 'Defend Edge', 'EDGENAT CLOUD', 'BrightEdge Technologies'
		];
		foreach ($matches AS $item) {
			if (\mb_stripos($name, $item) !== false) {
				return true;
			}
		}

		// see if ASN name matches regex
		$re = '/\bcolo(?!m|rado|n|mbo|r|proctology|ur)|(?<!\bg)host(ing|ed)?\b(?! hotel)|\bhost(?!works-as-ap)(ing|ed|s)?(?! hotel)|Servers(?!orgung)|\bOVH\b|\bVPS|VPS\b|data ?cent(?:er|re)s?|\bCDN(?!bt)|^Network Solutions|^render$|^20i\b|(Lease|Time|Liquid)(?! )Web|\bG-Core|^aeza|^steadfast$\b/i';
		if (\preg_match($re, $name)) {
			return true;
		}

		// ASN matches

		$asnListFR = [13193 => 'Nerim', 15422 => 'AMEN SAS', 197422 => 'TETANEUTRAL.NET', 197922 => 'Techcrea Solutions SAS', 199422 => 'NEXEREN', 201133 => 'PROXGROUP', 201364 => 'Scalair', 203476 => 'ONLYSERVICES', 203646 => 'MyCloud SA (FrenchNodes)', 204818 => 'HOSTEUR SAS', 20766 => 'GIGAHOSTING FR (PlusServer)', 21409 => 'Ikoula Net SAS', 24803 => 'NFRANCE (NFrance Conseil)', 29169 => 'GANDI SAS', 30781 => 'Free Pro SAS', 31216 => 'BSO Network Solutions', 35177 => 'FULLSAVE', 34572 => 'SYSTONIC', 35661 => 'VIRTUA SYSTEMS SAS', 41115 => 'ADISTA', 43940 => 'ALWAYS DATA', 49434 => 'ALTINEA', 49455 => 'IONIS', 50409 => 'SwissCenter / ITS Services', 50474 => 'o2switch', 62000 => 'SERVERD SAS', 62044 => 'PLANETHOSTER', 62119 => 'root SAS', 12876 => 'SCALEWAY S.A.S.'];
		$asnListNL = [8283 => 'Stichting Coloclue', 12859 => 'BIT B.V.', 15480 => 'Cyso / team.blue', 15795 => 'True B.V.', 16265 => 'LeaseWeb Network B.V.', 197731 => 'Tuxis B.V.', 20495 => 'We Dare B.V.', 20847 => 'Previder B.V.', 20857 => 'TransIP B.V.', 21155 => 'ProServe B.V.', 24875 => 'NovoServe B.V.', 29073 => 'Ecatel Network', 31216 => 'BSO Network Solutions', 31477 => 'Duocast B.V.', 34868 => 'Intermax Cloudsourcing B.V.', 43350 => 'NFOrce Entertainment B.V.', 47172 => 'Greenhost B.V.', 47846 => 'Tismi B.V.', 47869 => 'Netrouting', 49544 => 'Interactive 3D B.V.', 49981 => 'WorldStream', 50673 => 'Serverius Holding B.V.', 57043 => 'Hostkey B.V.', 60781 => 'LeaseWeb Netherlands B.V.', 61337 => 'InterRacks B.V.', 61349 => 'PCextreme B.V.', 61480 => 'Mijndomein Hosting B.V.', 203747 => 'Shock Media B.V.', 211252 => 'Serverion B.V.'];
		$asnListDE = [1836 => 'green.ch AG', 8859 => 'ODN OnlineDienst Nordbayern GmbH (netzmarkt)', 8972 => 'PlusServer GmbH', 12306 => 'Plus.line AG', 12693 => 'EDIS GmbH / GHOSTnet', 15657 => 'QualityHosting AG', 20773 => 'Host Europe GmbH', 20886 => 'Bradler & Krantz GmbH & Co. KG', 21232 => 'soprado GmbH (Alfahosting subsidiary?)', 213230 => 'Hetzner Online GmbH', 24940 => 'Hetzner Online GmbH', 24961 => 'MyLoc managed IT AG', 25291 => 'SysEleven GmbH', 25504 => 'Vautron Rechenzentrum AG', 29066 => 'velia.net Internetdienste GmbH', 31103 => 'Keyweb AG', 31400 => 'Accelerated IT Services GmbH', 31543 => 'Globalways GmbH', 33988 => 'Alfahosting GmbH', 34282 => 'manitu GmbH', 34432 => 'Profihost AG', 35540 => 'Jonas Pasche (uberspace.de)', 44066 => 'First Colo GmbH', 45012 => 'dogado GmbH', 48324 => 'WEBGO GmbH', 51167 => 'Contabo GmbH', 56655 => 'TerraHost AS', 58010 => 'INWX GmbH & Co. KG', 197071 => 'Host Unlimited e.K. / DeinProvider', 197540 => 'netcup GmbH', 200924 => 'hosting.de GmbH', 202160 => 'Joodle', 205100 => 'netclusive GmbH'];
		$asnListUS = [3842 => 'RamNode LLC', 8075 => 'Microsoft Corporation (Azure)', 8100 => 'QuadraNet Enterprises LLC', 8560 => 'IONOS SE', 8569 => 'IONOS SE (sub-services)', 8972 => 'PlusServer GmbH', 13649 => 'Flexential Colorado Corp.', 13749 => 'Data Foundry, Inc.', 14061 => 'DigitalOcean, LLC', 14618 => 'Amazon.com, Inc. (AWS)', 14907 => 'Linode, LLC (Akamai Cloud)', 15169 => 'Google LLC (Google Cloud)', 16509 => 'Amazon.com, Inc. (AWS Primary)', 17378 => 'TeraSwitch Networks Inc.', 18450 => 'WebNX, Inc.', 18779 => 'Datacate, Inc.', 18978 => 'ENZU / Atlantic.Net', 19318 => 'Interserver, Inc.', 19551 => 'Imperva (Incapsula CDN/WAF)', 19969 => 'Joe’s Datacenter, LLC', 19994 => 'Rackspace Hosting', 20013 => 'CYRUSONE / LiquidNet Ltd.', 20278 => 'Nexeon Technologies, Inc.', 20446 => 'Highwinds Network Group, Inc. (StackPath)', 20473 => 'The Constant Company, LLC (Vultr)', 20476 => 'Psychz Networks', 21554 => 'LightBound, LLC', 22611 => 'InMotion Hosting, Inc.', 22612 => 'Namecheap, Inc.', 23352 => 'Server Central (SCTG)', 23393 => 'ISPrime, Inc.', 23470 => 'ReliableSite.Net LLC', 25612 => 'Wownet (Wowrack)', 25653 => 'Awknet Communications, LLC', 25847 => 'Choopa, LLC (Vultr)', 25857 => 'SUPERB-1', 26347 => 'New Dream Network, LLC (DreamHost)', 26496 => 'GoDaddy.com, LLC', 27501 => 'Sago Networks', 29802 => 'HIVELOCITY, Inc.', 29838 => 'AMC (Atlantic Metro)', 29854 => 'WestHost Inc.', 30058 => 'FDCSERVERS', 30148 => 'Sucuri / GoDaddy', 30693 => 'ServerHub', 31216 => 'BSO Network Solutions', 32097 => 'WholeSale Internet, Inc. (NOCIX)', 32181 => 'GigeNET', 32244 => 'Liquid Web, L.L.C', 32748 => 'Steadfast Networks, LLC', 33182 => 'HostDime.com, Inc.', 33517 => 'Oracle / Dyn', 34282 => 'manitu GmbH', 34432 => 'Profihost AG', 35540 => 'OVH BE', 36131 => 'NETACTUATE', 36351 => 'SoftLayer Technologies, Inc. (IBM Cloud)', 36352 => 'ColoCrossing', 36444 => 'Newfold Digital / HostGator', 3842 => 'RamNode LLC', 40009 => 'Volico Data Centers', 40065 => 'CNSERVERS LLC', 40244 => 'Turnkey Internet Inc.', 40367 => 'OVH Hosting, Inc. (Canada/US)', 40676 => 'Psychz Networks', 44066 => 'First Colo GmbH', 46562 => 'Total Server Solutions L.L.C.', 46664 => 'VolumeDrive', 46844 => 'Sharktech', 51167 => 'Contabo GmbH', 53667 => 'FranTech Solutions (BuyVM)', 54290 => 'Hostwinds LLC', 54541 => 'KnownHost, Inc.', 55293 => 'A2 Hosting, Inc.', 58010 => 'INWX GmbH & Co. KG', 13335 => 'Cloudflare, Inc.', 54113 => 'Fastly, Inc.', 20940 => 'Akamai Technologies'];



		$asnList = array_replace($asnListFR, $asnListNL, $asnListDE, $asnListUS);

		if (isset($asnList[$asn])) {
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
				for ($i = 0; $i < $count; $i++) {
					$filename = $za->getNameIndex($i);
					if (!\str_ends_with($filename, '/aggregated.json')) {

					} elseif (($content = $za->getFromIndex($i)) === false) {

					} elseif (($json = \json_decode($content)) === false) {

					} elseif (!empty($json->description) && $this->asnMatches($json->description, $json->asn)) {
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
