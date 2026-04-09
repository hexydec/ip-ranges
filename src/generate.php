<?php
declare(strict_types=1);
namespace hexydec\ipaddresses;

class generate {

	protected array $timing = [];

	/**
	 * Counts the total number of lines in a file
	 *
	 * @param string $file The path to the file to count lines in
	 * @return int|false The number of lines, or false if the file does not exist
	 */
	public function getTotalLines(string $file) : int|false {
		if (\file_exists($file)) {
			\set_time_limit(120);
			$obj = new \SplFileObject($file);
			$obj->setFlags($obj::READ_AHEAD);
			return \iterator_count($obj);
		}
		return false;
	}

	/**
	 * Fetches a remote URL, optionally caching the result to a local file
	 *
	 * @param string $url The URL to fetch
	 * @param ?string $cache The directory to cache downloaded files, or null to skip caching
	 * @param bool $contents When true returns the file contents as a string, when false returns the local file path
	 * @return string|false The file contents or local file path, or false on failure
	 */
	public function fetch(string $url, ?string $cache = null, bool $contents = true) : string|false {
		$local = null;

		// generate cache file name
		if ($cache !== null) {
			$local = $cache.\preg_replace('/[^0-9a-z]+/i', '-', $url).(\strrchr(\basename($url), '.') ?: '');
		}

		// fetch from local cache
		if ($local !== null && \file_exists($local) && (!$contents || ($file = \file_get_contents($local)) !== false)) {
			return $contents ? $file : $local;

		// download file
		} else {

			// create cache directory
			if ($local !== null && !\is_dir($cache)) {
				\mkdir($cache, 0755);
			}

			// save timing
			if (($host = \parse_url($url, PHP_URL_HOST)) !== false) {
				$wait = 2;
				if (isset($this->timing[$host]) && \microtime(true) < $this->timing[$host] + $wait) {
					\sleep($wait);
				}
			}
			$context = \stream_context_create([
				'http' => [
					'user_agent' => 'Mozilla/5.0 (compatible; Hexydec IP Ranges Bot/1.0; +https://github.com/hexydec/ip-ranges/)',
					'header' => [
						'Sec-Fetch-Dest: document',
						'Sec-Fetch-Mode: navigate',
						'Sec-Fetch-Site: none',
						'Sec-Fetch-User: ?1',
						'Sec-GPC: 1',
						'Cache-Control: no-cache',
						'Accept-Language: en-GB,en;q=0.5'
					]
				]
			]);
			if ($contents) {
				if (($file = \file_get_contents($url, false, $context)) !== false) {
				
					// save to local file
					if ($local !== null) {
						\file_put_contents($local, $file);
					}
					return $file;
				}
			} else {
				$file = $local ?? \tempnam(\sys_get_temp_dir(), 'ips');
				if (!\copy($url, $file, $context)) {
					$file = false;
				}
			}
			$this->timing[$host] = \microtime(true);
			return $file;
		}
		return false;
	}

	/**
	 * Fetches a JSON source and yields IP range prefixes from common JSON structures
	 *
	 * @param string $file The URL of the JSON file to fetch
	 * @param ?string $cache The directory to cache downloaded data, or null to skip caching
	 * @return \Generator Yields CIDR range strings
	 */
	protected function getFromJson(string $file, ?string $cache = null) : \Generator {
		if (($result = $this->fetch($file, $cache)) !== false && ($json = \json_decode($result)) !== null) {
			foreach ($json->prefixes ?? [] AS $item) {
				yield $item->ipv4Prefix ?? $item->ipv6Prefix ?? $item->ip_prefix;
			}
			foreach ($json->subnets->ipv4 ?? [] AS $item) {
				yield $item;
			}
			foreach ($json->subnets->ipv6 ?? [] AS $item) {
				yield $item;
			}
		}
	}

	/**
	 * Fetches a plain text source and yields each line as an IP range
	 *
	 * @param string $file The URL of the text file to fetch
	 * @param ?string $cache The directory to cache downloaded data, or null to skip caching
	 * @return \Generator Yields trimmed line strings
	 */
	protected function getFromText(string $file, ?string $cache = null) : \Generator {
		if (($result = $this->fetch($file, $cache)) !== false) {
			foreach (\explode("\n", \trim($result)) AS $item) {
				yield \trim($item);
			}
		}
	}

	/**
	 * Fetches an HTML page and extracts IPv4/IPv6 addresses and CIDR ranges from its content
	 *
	 * @param string $file The URL of the HTML page to fetch
	 * @param ?string $cache The directory to cache downloaded data, or null to skip caching
	 * @return \Generator Yields CIDR range or IP address strings
	 */
	protected function getFromHtml(string $file, ?string $cache = null) : \Generator {
		if (($result = $this->fetch($file, $cache)) !== false && \preg_match_all('/(?<=[>\\n\\r\\t ])(?:[0-9]++(?:\.[0-9]++){3}|(?:[0-9a-f]{1,4}::?){2,7}(?:[0-9a-f]{1,4})?)(?:\/[0-9]{1,3})?(?=[<\\n\\r\\t ])/i', $result, $match)) {
			foreach ($match[0] AS $item) {
				if (!\preg_match('/^\d{2}:\d{2}:\d{2}$/', $item)) {
					yield \trim($item);
				}
			}
		}
	}

	/**
	 * Fetches a CSV source and yields each parsed row, skipping comment lines
	 *
	 * @param string $file The URL of the CSV file to fetch
	 * @param ?string $cache The directory to cache downloaded data, or null to skip caching
	 * @return \Generator Yields arrays of CSV field values
	 */
	protected function getFromCsv(string $file, ?string $cache = null) : \Generator {
		if (($result = $this->fetch($file, $cache)) !== false) {
			foreach (\explode("\n", \trim($result)) AS $item) {
				if (!\str_starts_with($item, '#') && ($data = \str_getcsv($item, ',', '"', '\\')) !== false) {
					yield $data;
				}
			}
		}
	}

	/**
	 * Compiles IP range data from configured sources (base implementation yields nothing)
	 *
	 * @param ?string $cache The directory to cache downloaded data, or null to skip caching
	 * @return \Generator Yields IP range data arrays
	 */
	public function compile(?string $cache = null) : \Generator {
		yield [];
	}

	/**
	 * Compiles IP ranges and writes them to one or more output files in CSV, TXT, or JSON format
	 *
	 * @param array $files An array of output file paths; filenames containing '-ipv4' or '-ipv6' filter by protocol
	 * @param ?string $cache The directory to cache downloaded data, or null to skip caching
	 * @return int|false The number of ranges written, or false on failure
	 */
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
				$handles[$file] = $handle;
				if ($ext === '.json' && \fwrite($handle, "[\n") === false) {
					return false;
				}
			} else {
				return false;
			}
		}

		// compile IP ranges
		$include = function (string $file, string $range) : bool {
			if (\str_contains($file, '-ipv4')) {
				return !\str_contains($range, ':');
			} elseif (\str_contains($file, '-ipv6')) {
				return \str_contains($range, ':');
			}
			return true;
		};
		$time = \time();
		foreach ($this->compile($cache) AS $i => $item) {

			// set progress
			$current = \time();
			if ($current !== $time) {
				\set_time_limit(30);
				progress::status('Writing output: '.\number_format($i).' ranges');
				$time = $current;
			}

			// write files
			foreach ($handles AS $file => $handle) {
				if ($include($file, $item['range'])) {
					if (\str_ends_with($file, '.csv') && \fputcsv($handle, $item, ',', '"', '\\') === false) {
						return false;
					} elseif (\str_ends_with($file, '.txt') && \fwrite($handle, $item['range']."\n") === false) {
						return false;
					} elseif (\str_ends_with($file, '.json') && \fwrite($handle, ($i > 0 ? ",\n" : '').\json_encode($item)) === false) {
						return false;
					}
				}
			}
		}
		progress::status('');

		// close file handles
		foreach ($handles AS $file => $handle) {
			$ext = \mb_strrchr($file, '.');
			if ($ext === '.json' && \fwrite($handle, "\n]") === false) {
				return false;
			}
			\fclose($handle);
		}
		return $i;
	}
}