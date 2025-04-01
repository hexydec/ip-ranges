<?php
declare(strict_types=1);
namespace hexydec\ipaddresses;

class generate {

	protected array $timing = [];

	public function fetch(string $url, ?string $cache = null, bool $contents = true) : string|false {
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
				$file = $local ?? \tempnam(\sys_get_temp_dir(), 'datacentres');
				if (!\copy($url, $file, $context)) {
					$file = false;
				}
			}
			$this->timing[$host] = \microtime(true);
			return $file;
		}
		return false;
	}

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

	protected function getFromText(string $file, ?string $cache = null) : \Generator {
		if (($result = $this->fetch($file, $cache)) !== false) {
			foreach (\explode("\n", \trim($result)) AS $item) {
				yield \trim($item);
			}
		}
	}

	protected function getFromHtml(string $file, ?string $cache = null) : \Generator {
		if (($result = $this->fetch($file, $cache)) !== false && \preg_match_all('/(?:[0-9]++(?:\.[0-9]++){3}|(?:[0-9a-f]{1,4}::?){2,7}(?:[0-9a-f]{1,4})?)(?:\/[0-9]{1,3})?/i', $result, $match)) {
			foreach ($match[0] AS $item) {
				yield \trim($item);
			}
		}
	}

	protected function getFromCsv(string $file, ?string $cache = null) : \Generator {
		if (($result = $this->fetch($file, $cache)) !== false) {
			foreach (\explode("\n", \trim($result)) AS $item) {
				if (!\str_starts_with($item, '#') && ($data = \str_getcsv($item, ',', '"', '\\')) !== false) {
					yield $data[0];
				}
			}
		}
	}

	public function compile(?string $cache = null) : \Generator {
		yield [];
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
		$i = 0;
		$ranges = [];
		foreach ($this->compile($cache) AS $item) {
			if (!\in_array($item['range'], $ranges, true)) {
				$ranges[] = $item['range'];
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
				$i++;
			}
		}

		// close file handles
		foreach ($handles AS $key => $item) {
			if ($key === '.json' && \fwrite($handle, "\n]") === false) {
				return false;
			}
			\fclose($item);
		}
		return $i;
	}
}