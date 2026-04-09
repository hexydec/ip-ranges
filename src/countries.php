<?php
declare(strict_types=1);
namespace hexydec\ipaddresses;

class countries extends generate {

	/**
	 * Adds an IP range to the dataset
	 * 
	 * @param array &$ips An array of IP ranges to add the new IP range to
	 * @param string $cidr The IP range to add
	 * @param array $item The IP data to add
	 * @return void
	 */
	protected function addIpRange(array &$ips, string $cidr, array $item) : void {
		if (isset($ips[$cidr])) {
			$ips[$cidr]->country = $item['country'];
		} else {
			$ips[$cidr] = (object) $item;
		}
	}

	/**
	 * Gets data from the RIR's and extracts IP ranges
	 * 
	 * @param array $sources An array of source URLs
	 * @param array $ips An array of currently collected IP's, grouped into 4 and 6
	 * @param ?string $cache The directory to cache data or null not to cache
	 * @return array|false $ips with any new IP ranges added to it
	 */
	protected function getRirIps(array $sources, array $ips, ?string $cache = null) : array|false {
		progress::status('Collecting RIR IP data');
		$statuses = ['allocated', 'assigned'];
		$ips['asns'] = [];
		foreach ($sources AS $item) {
			if (($result = $this->fetch($item, $cache)) !== false) {
				$lines = \explode("\n", \trim($result));
				unset($result);
				$total = \count($lines);
				$time = \time();
				foreach ($lines AS $i => $value) {

					// progress
					$current = \time();
					if ($current !== $time) {
						\set_time_limit(30);
						progress::render($total, $i, ['Ingesting RIR data']);
						$time = $current;
					}

					// filter by eligible records
					$parts = \explode('|', \trim($value));
					$type = $parts[2] ?? '';
					$status = $parts[6] ?? '';
					$country = \strtolower($parts[1] ?? '');

					// collect ASN-to-country mappings
					if ($type === 'asn' && $country !== '' && $country !== '*') {
						$ips['asns'][\intval($parts[3])] = $country;

					// process IP records
					} elseif (\in_array($status, $statuses, true) && $country !== '') {

						// process IPv4 records
						if ($type === 'ipv4') {
							if (($long = \ip2long($parts[3])) !== false) {
								$prefix = 32 - \intval(\round(\log(\intval($parts[4]), 2)));
								$count = \intval(\pow(2, 32 - $prefix));
								$this->addIpRange($ips[4], $parts[3] . '/' . $prefix, [
									'start' => $long,
									'count' => $count,
									'country' => $country
								]);
							}

						// process IPv6 records
						} elseif ($type === 'ipv6') {
							if (($bin = \inet_pton($parts[3])) !== false) {
								$prefix = \intval($parts[4]);
								$end = aggregator::binAdd($bin, aggregator::prefixToBlockSize($prefix));
								$this->addIpRange($ips[6], $parts[3] . '/' . $parts[4], [
									'start' => $bin,
									'end' => $end,
									'prefix' => $prefix,
									'country' => $country
								]);
							}
						}
					}
				}
			} else {
				return false;
			}
		}
		return $ips;
	}

	/**
	 * Extracts IPv6 ranges and country codes from WHOIS inet6num database dumps
	 *
	 * @param array $sources An array of gzipped WHOIS database URLs to process
	 * @param array $ips An array of currently collected IP ranges, grouped into 4 and 6
	 * @param ?string $cache The directory to cache downloaded data, or null to skip caching
	 * @return array|false $ips with any new IPv6 ranges added, or false on failure
	 */
	protected function getWhoisIps(array $sources, array $ips, ?string $cache = null) : array|false {
		progress::status('Collecting WHOIS IP data');
		foreach ($sources AS $url) {

			// open gzipped file as stream
			if (($file = $this->fetch($url, $cache, false)) !== false && ($handle = \gzopen($file, 'r')) !== false) {
				$total = \intval(\filesize($file) * 0.54); // rough estimate
				$i = 0;
				$time = \time();
				$cidr = null;
				$country = null;
				while (($line = \gzgets($handle)) !== false) {

					// progress
					$current = \time();
					if ($current !== $time) {
						\set_time_limit(30);
						progress::render($total, $i, ['Ingesting WHOIS inet6num data']);
						$time = $current;
					}
					$i++;
					$line = \rtrim($line, "\r\n");

					// blank line = end of object
					if ($line === '') {
						if ($cidr !== null && $country !== null) {
							$parts = \explode('/', $cidr, 2);
							if (\count($parts) === 2 && ($bin = \inet_pton($parts[0])) !== false) {
								$prefix = \intval($parts[1]);
								$end = aggregator::binAdd($bin, aggregator::prefixToBlockSize($prefix));
								$this->addIpRange($ips[6], $cidr, [
									'start' => $bin,
									'end' => $end,
									'prefix' => $prefix,
									'country' => $country
								]);
							}
						}
						$cidr = null;
						$country = null;

					// parse key: value
					} elseif (\str_starts_with($line, 'inet6num:')) {
						$cidr = \trim(\substr($line, 9));
					} elseif (\str_starts_with($line, 'country:')) {
						$country = \strtolower(\trim(\substr($line, 8)));
					}
				}

				// flush last object
				if ($cidr !== null && $country !== null) {
					$parts = \explode('/', $cidr, 2);
					if (\count($parts) === 2 && ($bin = \inet_pton($parts[0])) !== false) {
						$prefix = \intval($parts[1]);
						$end = aggregator::binAdd($bin, aggregator::prefixToBlockSize($prefix));
						$this->addIpRange($ips[6], $cidr, [
							'start' => $bin,
							'end' => $end,
							'prefix' => $prefix,
							'country' => $country
						]);
					}
				}
				\gzclose($handle);
			} else {
				return false;
			}
		}
		return $ips;
	}

	/**
	 * Extracts IP ranges and country codes from a geofeed CSV source
	 *
	 * @param string $source The URL of the geofeed CSV file
	 * @param array $ips An array of currently collected IP ranges, grouped into 4 and 6
	 * @param ?string $cache The directory to cache downloaded data, or null to skip caching
	 * @return array|false $ips with any new IP ranges added, or false on failure
	 */
	protected function getGeofeedIps(string $source, array $ips, ?string $cache = null) : array|false {
		progress::status('Collecting Geofeed IP data');
		if (($result = $this->fetch($source, $cache)) !== false) {
			$lines = \explode("\n", \trim($result));
			unset($result);
			$total = \count($lines);
			$time = \time();
			foreach ($lines AS $i => $line) {

				// progress
				$current = \time();
				if ($current !== $time) {
					\set_time_limit(30);
					progress::render($total, $i, ['Ingesting Geofeed data']);
					$time = $current;
				}

				// detect comments
				if (\str_starts_with($line, '#')) {
					
				// process CSV
				} elseif (($value = \str_getcsv($line, ',', '"', '\\')) === false) {
				
				// ensure the country is set
				} elseif (\count($value) < 3 || ($country = \strtolower($value[1] ?: \substr($value[2], 0, 2))) === '*') {

				// process the data
				} else {
					$cidr = $value[0];
					$parts = \explode('/', $cidr, 2);

					// IPv6
					if (\str_contains($cidr, ':')) {
						if (($bin = \inet_pton($parts[0])) !== false) {
							$prefix = \intval($parts[1]);
							$end = aggregator::binAdd($bin, aggregator::prefixToBlockSize($prefix));
							$this->addIpRange($ips[6], $cidr, [
								'start' => $bin,
								'end' => $end,
								'prefix' => $prefix,
								'country' => $country
							]);
						}

					// IPv4
					} elseif (($long = \ip2long($parts[0])) !== false) {
						$count = \intval(\pow(2, 32 - \intval($parts[1])));
						$this->addIpRange($ips[4], $cidr, [
							'start' => $long,
							'count' => $count,
							'country' => $country
						]);
					}
				}
			}
			unset($lines);
			return $ips;
		}
		return false;
	}

	/**
	 * Reads cloud provider IP ranges from a local CSV file and adds country data to the IP collection
	 *
	 * @param string $source The path to the cloud provider CSV file
	 * @param array $ips An array of currently collected IP ranges, grouped into 4 and 6
	 * @return array|false $ips with any new IP ranges added, or false on failure
	 */
	protected function getCloudProviderIps(string $source, array $ips) : array|false {

		// set progress and calculate total
		progress::status('Collecting Cloud Provider IP data');

		// process file
		if (($total = $this->getTotalLines($source)) !== false && ($handle = \fopen($source, 'r')) !== false) {
			$i = 0;
			$time = \time();
			while (($line = \fgetcsv($handle, 0, ',', '"', '\\')) !== false) {

				// update progress
				$current = \time();
				if ($current !== $time) {
					\set_time_limit(30);
					progress::render($total, $i, ['Ingesting Cloud Provider data']);
					$time = $current;
				}
				$i++;

				// process data
				if (\count($line) >= 3 && ($country = \strtolower($line[2])) !== '') {
					$cidr = $line[1];
					$parts = \explode('/', $cidr, 2);

					// process IPv6
					if (\str_contains($cidr, ':')) {
						if (($bin = \inet_pton($parts[0])) !== false) {
							$prefix = \intval($parts[1]);
							$end = aggregator::binAdd($bin, aggregator::prefixToBlockSize($prefix));
							$this->addIpRange($ips[6], $cidr, [
								'start' => $bin,
								'end' => $end,
								'prefix' => $prefix,
								'country' => $country
							]);
						}

					// process IPv4
					} elseif (($long = \ip2long($parts[0])) !== false) {
						$count = \intval(\pow(2, 32 - \intval($parts[1])));
						$this->addIpRange($ips[4], $cidr, [
							'start' => $long,
							'count' => $count,
							'country' => $country
						]);
					}
				}
			}
			\fclose($handle);
			return $ips;
		}
		return false;
	}

	/**
	 * Fetches and converts an MRT BGP dump to text using bgpdump, returning a readable stream
	 *
	 * @param string $url The URL of the MRT dump file
	 * @param string $cachefile The local path to cache the converted text output
	 * @param ?string $cache The directory to cache downloaded data, or null to skip caching
	 * @return array{resource, int}|false A readable stream of bgpdump text output and estimated row count, or false on failure
	 */
	protected function getBgpSource(string $url, string $cachefile, ?string $cache = null) {

		// get from cache
		if ($cache !== null && \file_exists($cachefile)) {
			return [\fopen($cachefile, 'r'), $this->getTotalLines($cachefile)];

		// extract from MRT file
		} elseif (($file = $this->fetch($url, $cache, false)) !== false) {
			\set_time_limit(300);
			if (PHP_OS === 'WINNT') {
				$wslcache = \trim(\shell_exec('wsl wslpath '.\escapeshellarg($cachefile)));
				$wslfile = \trim(\shell_exec('wsl wslpath '.\escapeshellarg($file)));
				$cmd = 'wsl bash -c '.\escapeshellarg('bgpdump -m -O '.\escapeshellarg($wslcache).' '.\escapeshellarg($wslfile).' 2>/dev/null');
			} else {
				$cmd = 'bgpdump -m -O '.\escapeshellarg($cachefile).' '.\escapeshellarg($file).' 2>/dev/null';
			}
			if (\exec($cmd)) {
				return [\fopen($cachefile, 'r'), $this->getTotalLines($cachefile)];
			}
		}
		return false;
	}

	/**
	 * Ingests a single BGP MRT dump, mapping announced prefixes to countries via ASN lookup
	 *
	 * @param array $ips An array of currently collected IP ranges, grouped into 4 and 6
	 * @param array $asns An associative array mapping ASN numbers to country codes
	 * @param string $url The URL of the MRT/BGP dump file to process
	 * @param ?string $cache The directory to cache downloaded data, or null to skip caching
	 * @return array|false $ips with any new IP ranges added, or false on failure
	 */
	protected function ingestBgpData(array $ips, array $asns, string $url, ?string $cache = null) : array|false {

		// fetch source data
		$ipv6 = \str_contains($url, 'route-views6');
		$cachefile = \dirname(__DIR__) . '/cache/bgpdata'.($ipv6 ? '6' : '').'.txt';
		if (($source = $this->getBgpSource($url, $cachefile, $cache)) !== false) {
			[$handle, $total] = $source;
			$i = 0;
			$time = \time();
			while (($line = \fgets($handle)) !== false) {

				// progress
				$current = \time();
				if ($current !== $time) {
					\set_time_limit(30);
					progress::render($total, $i, ['Ingesting BGP IPv'.($ipv6 ? '6' : '4').' data']);
					$time = $current;
				}
				$i++;

				// process line
				$parts = \explode('|', $line);
				if ($parts[0] === 'TABLE_DUMP2' && isset($parts[6])) {
					$path = \explode(' ', \trim($parts[6]));
					$asn = \end($path);
					if (isset($asns[$asn])) {
						$cidr = \trim($parts[5]);
						$parts = \explode('/', $cidr, 2);

						// ipv6
						if (\str_contains($cidr, ':')) {
							if (($bin = \inet_pton($parts[0])) !== false) {
								$prefix = \intval($parts[1]);
								$end = aggregator::binAdd($bin, aggregator::prefixToBlockSize($prefix));
								$this->addIpRange($ips[6], $cidr, [
									'start' => $bin,
									'end' => $end,
									'prefix' => $prefix,
									'country' => $asns[$asn],
								]);
							}

						// ipv4
						} elseif (($long = \ip2long($parts[0])) !== false) {
							$count = \intval(\pow(2, 32 - \intval($parts[1])));
							$this->addIpRange($ips[4], $cidr, [
								'start' => $long,
								'count' => $count,
								'country' => $asns[$asn],
							]);
						}
					}
				}
			}
			\fclose($handle);
			return $ips;
		}
		return false;
	}

	/**
	 * Collects IP-to-country mappings from multiple BGP route dump sources
	 *
	 * @param array $sources An array of BGP dump URLs to process
	 * @param array $ips An array of currently collected IP ranges, grouped into 4 and 6
	 * @param ?string $cache The directory to cache downloaded data, or null to skip caching
	 * @return array|false $ips with any new IP ranges added, or false on failure
	 */
	protected function getBgpIps(array $sources, array $ips, ?string $cache = null) : array|false {
		progress::status('Collecting BGP IP data');
		$asns = $ips['asns'];

		foreach ($sources AS $source) {
			$result = $this->ingestBgpData($ips, $asns, $source, $cache);
			if ($result !== false) {
				$ips = $result;
			}
		}
		return $ips;
	}

	/**
	 * Compiles country-level IP range data from RIR, BGP, WHOIS, geofeed, and cloud provider sources,
	 * resolving overlaps so more specific/higher-priority sources win
	 *
	 * @param ?string $cache The directory to cache downloaded data, or null to skip caching
	 * @return \Generator Yields associative arrays with 'country' and 'range' keys
	 */
	public function compile(?string $cache = null) : \Generator {
		\ini_set('memory_limit', '4G');
		$ips = [4 => [], 6 => []];

		// build sources
		
		$date = \date('Y.m');
		$day = \date('Ymd');
		$sources = [
			'rir' => [
				'https://ftp.arin.net/pub/stats/arin/delegated-arin-extended-latest',
				'https://ftp.ripe.net/pub/stats/ripencc/delegated-ripencc-extended-latest',
				'https://ftp.apnic.net/pub/stats/apnic/delegated-apnic-extended-latest',
				'https://ftp.lacnic.net/pub/stats/lacnic/delegated-lacnic-extended-latest',
				'https://ftp.afrinic.net/pub/stats/afrinic/delegated-afrinic-extended-latest'
			],
			'bgp' => [
				'http://archive.routeviews.org/bgpdata/'.$date.'/RIBS/rib.'.$day.'.0000.bz2',
				'https://archive.routeviews.org/route-views6/bgpdata/'.$date.'/RIBS/rib.'.$day.'.0000.bz2'
			],
			'whois' => [
				'https://ftp.ripe.net/ripe/dbase/split/ripe.db.inet6num.gz',
				'https://ftp.apnic.net/apnic/whois/apnic.db.inet6num.gz',
				'https://ftp.afrinic.net/pub/dbase/afrinic.db.gz',
				'https://ftp.lacnic.net/lacnic/dbase/lacnic.db.gz',
			],
			'geofeed' => 'https://geolocatemuch.com/geofeeds/validated-all.csv',
			'cloud' => \dirname(__DIR__).'/output/datacentres.csv'
		];

		// collect from all sources — later sources overwrite same key = higher priority
		if (($ips = $this->getRirIps($sources['rir'], $ips, $cache)) === false) {
			\trigger_error('Unable to process RIR data', E_USER_ERROR);
		} elseif (($ips = $this->getBgpIps($sources['bgp'], $ips, $cache)) === false) {
			\trigger_error('Unable to process BGP data', E_USER_ERROR);
		} elseif (($ips = $this->getWhoisIps($sources['whois'], $ips, $cache)) === false) {
			\trigger_error('Unable to process WHOIS data', E_USER_ERROR);
		} elseif (($ips = $this->getGeofeedIps($sources['geofeed'], $ips, $cache)) === false) {
			\trigger_error('Unable to process Geofeed data', E_USER_ERROR);
		} elseif (($ips = $this->getCloudProviderIps($sources['cloud'], $ips)) === false) {
			\trigger_error('Unable to process cloud providers data', E_USER_ERROR);
		} else {

			// resolve overlaps and yield
			yield from aggregator::resolveIpv4($ips[4]);
			yield from aggregator::resolveIpv6($ips[6]);
		}
	}
}
