<?php
declare(strict_types=1);
namespace hexydec\ipaddresses;

class datacentres {

	public static function fetch(string $url, ?string $cache = null, bool $contents = true) : string|false {
		$local = null;

		// generate cache file name
		if ($cache !== null) {
			$local = $cache.\preg_replace('/[^0-9a-z]+/i', '-', $url).'.cache';
		}

		// fetch from local cache
		if ($local !== null && \file_exists($local) && (!$contents || ($file = \file_get_contents($local)) !== false)) {
			return $contents ? $file : $local;

		// download file
		} else {
			$context = \stream_context_create([
				'http' => [
					'user_agent' => 'Mozilla/5.0 (compatible; Hexydec Datacentre IP Ranges Bot/1.0; +https://github.com/hexydec/datacentre-ip-ranges/)'
				]
			]);
			if ($local !== null && !\is_dir($cache)) {
				\mkdir($cache, 0755);
			}
			if ($contents) {
				if (($file = \file_get_contents($url, false, $context)) !== false) {
				
					// save to local file
					if ($local !== null) {
						\file_put_contents($local, $file);
					}
					return $file;
				}
			} else {
				$file = $local ?? \tempnam(\sys_get_temp_dir(), 'datacentres');
				if (\copy($url, $file, $context)) {
					return $file;
				}
			}
		}
		return false;
	}

	protected function getAws(?string $cache = null) : \Generator {
		if (($file = $this->fetch('https://ip-ranges.amazonaws.com/ip-ranges.json', $cache)) !== false) {
			if (($json = \json_decode($file)) !== false) {
				foreach ($json->prefixes AS $item) {
					yield [
						'name' => 'Amazon AWS',
						'range' => $item->ip_prefix
					];
				}
			}
		}
	}

	protected function getGcp(?string $cache = null) : \Generator {
		if (($file = $this->fetch('https://www.gstatic.com/ipranges/cloud.json', $cache)) !== false) {
			if (($json = \json_decode($file)) !== false) {
				foreach ($json->prefixes AS $item) {
					yield [
						'name' => 'Google Cloud Platform',
						'range' => $item->ipv4Prefix ?? $item->ipv6Prefix
					];
				}
			}
		}
	}

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

	protected function getCloudflare(?string $cache = null) : \Generator {
		$urls = [
			'https://www.cloudflare.com/ips-v4/',
			'https://www.cloudflare.com/ips-v6/'
		];
		foreach ($urls AS $url) {
			if (($file = $this->fetch($url, $cache)) !== false) {
				foreach (\explode("\n", \trim($file)) AS $item) {
					yield [
						'name' => 'Cloudflare',
						'range' => $item
					];
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
		foreach ($this->getAws($cache) AS $item) {
			yield $item;
		}
		foreach ($this->getGcp($cache) AS $item) {
			yield $item;
		}
		foreach ($this->getAzure($cache) AS $item) {
			yield $item;
		}
		foreach ($this->getCloudflare($cache) AS $item) {
			yield $item;
		}
		foreach ($this->getAsns($cache) AS $item) {
			yield $item;
		}
	}

	public function save(array $files, ?string $cache = null) : int|false {
		$handles = [];

		// prepare output folders
		foreach ($files AS $file) {
			$dir = \dirname($file);
			if (!is_dir($dir)) {
				\mkdir($dir, 0755, true);
			}
			if (($handle = \fopen($file, 'w')) !== false) {
				$ext = \mb_strrchr($file, '.');
				$handles[$ext] = $handle;
			} else {
				return false;
			}
		}

		// compile IP ranges
		$i = 0;
		foreach ($this->compile($cache) AS $item) {
			foreach ($handles AS $ext => $handle) {
				if ($ext === '.csv' && \fputcsv($handle, $item) === false) {
					return false;
				} elseif ($ext === '.txt' && \fwrite($handle, $item['range']."\n") === false) {
					return false;
				}
			}
			$i++;
		}

		// close file handles
		foreach ($handles AS $item) {
			\fclose($item);
		}
		return $i;
	}
}