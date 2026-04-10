<?php
declare(strict_types=1);
namespace hexydec\ipaddresses;

class aggregator {

	/**
	 * Adds two 16-byte packed binary IPv6 addresses
	 *
	 * @param string $a The first 16-byte packed binary address
	 * @param string $b The second 16-byte packed binary address
	 * @return string The resulting 16-byte packed binary sum
	 */
	public static function binAdd(string $a, string $b) : string {
		$carry = 0;
		for ($i = 15; $i >= 0; $i--) {
			$sum = \ord($a[$i]) + \ord($b[$i]) + $carry;
			$a[$i] = \chr($sum & 0xFF);
			$carry = $sum >> 8;
		}
		return $a;
	}

	/**
	 * Converts a prefix length to a 16-byte packed binary block size (2^(128-prefix))
	 *
	 * @param int $prefix The CIDR prefix length (0-128)
	 * @return string The 16-byte packed binary block size
	 */
	public static function prefixToBlockSize(int $prefix) : string {
		$size = \str_repeat("\0", 16);
		$bit = 128 - $prefix;
		$byte = 15 - \intdiv($bit, 8);
		$size[$byte] = \chr(1 << ($bit % 8));
		return $size;
	}

	/**
	 * Subtracts two 16-byte packed binary values: $a - $b
	 *
	 * @param string $a The minuend (16-byte packed binary)
	 * @param string $b The subtrahend (16-byte packed binary)
	 * @return string The 16-byte packed binary difference
	 */
	protected static function binSub(string $a, string $b) : string {
		$borrow = 0;
		for ($i = 15; $i >= 0; $i--) {
			$diff = \ord($a[$i]) - \ord($b[$i]) - $borrow;
			if ($diff < 0) {
				$diff += 256;
				$borrow = 1;
			} else {
				$borrow = 0;
			}
			$a[$i] = \chr($diff);
		}
		return $a;
	}

	/**
	 * Counts trailing zero bits in a 16-byte packed binary value
	 *
	 * @param string $bin The 16-byte packed binary value
	 * @return int The number of trailing zero bits
	 */
	protected static function countTrailingZeroBits(string $bin) : int {
		$zeros = 0;
		for ($i = 15; $i >= 0; $i--) {
			$byte = \ord($bin[$i]);
			if ($byte === 0) {
				$zeros += 8;
			} else {
				for ($b = 0; $b < 8; $b++) {
					if ($byte & (1 << $b)) {
						break;
					}
					$zeros++;
				}
				break;
			}
		}
		return $zeros;
	}

	/**
	 * Finds the position of the highest set bit in a 16-byte packed binary value
	 *
	 * @param string $bin The 16-byte packed binary value
	 * @return int The zero-based bit position of the highest set bit
	 */
	protected static function highestBitPosition(string $bin) : int {
		for ($i = 0; $i < 16; $i++) {
			$byte = \ord($bin[$i]);
			if ($byte !== 0) {
				$bitPos = 7;
				while ($bitPos > 0 && !($byte & (1 << $bitPos))) {
					$bitPos--;
				}
				return (15 - $i) * 8 + $bitPos;
			}
		}
		return 0;
	}

	/**
	 * Converts a start/end binary range to an array of CIDR block strings
	 *
	 * @param string $start The 16-byte packed binary start address
	 * @param string $end The 16-byte packed binary end address (exclusive)
	 * @return array An array of IPv6 CIDR notation strings
	 */
	protected static function ipv6RangeToCidr(string $start, string $end) : array {
		$cidrs = [];
		while ($start < $end) {

			// compute alignment from trailing zero bits of start
			$align = self::countTrailingZeroBits($start);

			// compute max block size from remaining range (end - start)
			$remaining = self::binSub($end, $start);
			$maxBits = self::highestBitPosition($remaining);

			// block size is the smaller of alignment and remaining
			$blockBits = \min($align, $maxBits);
			$prefix = 128 - $blockBits;

			$cidrs[] = \inet_ntop($start) . '/' . $prefix;
			$start = self::binAdd($start, self::prefixToBlockSize($prefix));
		}
		return $cidrs;
	}

	/**
	 * Converts an IPv4 start address and host count to an array of CIDR blocks with country data
	 *
	 * @param int $long The starting IPv4 address as an integer
	 * @param int $count The number of addresses in the range
	 * @param string $country The two-letter country code to associate with each block
	 * @return array An array of associative arrays with 'country' and 'range' keys
	 */
	protected static function ipv4CountToCidr(int $long, int $count, string $country) : array {
		$end = $long + $count;
		$data = [];
		while ($long < $end) {

			// trailing zeros of $long give alignment (max block size)
			$align = $long === 0 ? 32 : \intval(\log($long & -$long, 2));

			// highest bit of remaining gives max block from size
			$remaining = $end - $long;
			$maxBits = \intval(\log($remaining, 2));

			$blockBits = $align < $maxBits ? $align : $maxBits;
			$prefix = 32 - $blockBits;

			$data[] = ['country' => $country, 'range' => \long2ip($long) . '/' . $prefix];
			$long += 1 << $blockBits;
		}
		return $data;
	}

	/**
	 * Resolves overlapping IPv4 blocks using an event sweep where the most specific (smallest) block wins
	 *
	 * @param array $ipv4 An array of objects with start, count, and country properties
	 * @return \Generator Yields associative arrays with 'country' and 'range' keys
	 */
	public static function resolveIpv4(array $ipv4) : \Generator {
		$ipv4 = \array_values($ipv4);
		$total = \count($ipv4);

		// extract sort keys for C-level sorting via array_multisort
		$starts = [];
		$counts = [];
		foreach ($ipv4 AS $item) {
			$starts[] = $item->start;
			$counts[] = $item->count;
		}

		progress::render($total, 0, ['Sorting IPv4 Ranges']);

		// sort by start ASC, count DESC (enclosing blocks before their children)
		\array_multisort($starts, \SORT_ASC, $counts, \SORT_DESC, $ipv4);
		unset($starts, $counts);

		$stack = []; // [end, country] — outermost at bottom, innermost on top
		$stackLen = 0;
		$cursor = 0;
		$bufStart = 0;
		$bufCount = 0;
		$bufCountry = '';
		$time = \time();
		foreach ($ipv4 AS $i => $item) {
			$current = \time();
			if ($current !== $time) {
				\set_time_limit(30);
				progress::render($total, $i, ['Aggregating IPv4 Ranges']);
				$time = $current;
			}

			// skip blocks already fully covered
			$end = $item->start + $item->count;
			if ($end > $cursor) {

				// flush expired blocks from stack, yielding their remaining ranges
				while ($stackLen && $stack[$stackLen - 1][0] <= $item->start) {
					$top = $stack[--$stackLen];
					if ($cursor < $top[0]) {
						$count = $top[0] - $cursor;
						if ($top[1] === $bufCountry && $cursor === $bufStart + $bufCount) {
							$bufCount += $count;
						} else {
							if ($bufCount) {
								yield from self::ipv4CountToCidr($bufStart, $bufCount, $bufCountry);
							}
							$bufStart = $cursor;
							$bufCount = $count;
							$bufCountry = $top[1];
						}
						$cursor = $top[0];
					}
				}

				// fill gap between cursor and this block with innermost active country
				if ($cursor < $item->start) {
					if ($stackLen) {
						$country = $stack[$stackLen - 1][1];
						$count = $item->start - $cursor;
						if ($country === $bufCountry && $cursor === $bufStart + $bufCount) {
							$bufCount += $count;
						} else {
							if ($bufCount) {
								yield from self::ipv4CountToCidr($bufStart, $bufCount, $bufCountry);
							}
							$bufStart = $cursor;
							$bufCount = $count;
							$bufCountry = $country;
						}
					} else if ($bufCount) {
						yield from self::ipv4CountToCidr($bufStart, $bufCount, $bufCountry);
						$bufCount = 0;
					}
					$cursor = $item->start;
				}

				$country = $item->country;

				// push this block as the new innermost active range
				$stack[$stackLen++] = [$end, $country];
			}
		}

		// flush remaining active blocks
		while ($stackLen) {
			$top = $stack[--$stackLen];
			if ($cursor < $top[0]) {
				$count = $top[0] - $cursor;
				if ($top[1] === $bufCountry && $cursor === $bufStart + $bufCount) {
					$bufCount += $count;
				} else {
					if ($bufCount) {
						yield from self::ipv4CountToCidr($bufStart, $bufCount, $bufCountry);
					}
					$bufStart = $cursor;
					$bufCount = $count;
					$bufCountry = $top[1];
				}
				$cursor = $top[0];
			}
		}

		// flush remaining buffer
		if ($bufCount) {
			yield from self::ipv4CountToCidr($bufStart, $bufCount, $bufCountry);
		}
	}

	/**
	 * Resolves overlapping IPv6 blocks using an event sweep where the most specific (highest prefix) block wins
	 *
	 * @param array $ipv6 An array of objects with start, end, prefix, and country properties
	 * @return \Generator Yields associative arrays with 'country' and 'range' keys
	 */
	public static function resolveIpv6(array $ipv6) : \Generator {
		$ipv6 = \array_values($ipv6);
		$zero = \str_repeat("\0", 16);
		$total = \count($ipv6);

		progress::render($total, 0, ['Sorting IPv6 Ranges']);

		// extract sort keys for C-level sorting via array_multisort
		$starts = [];
		$prefixes = [];
		foreach ($ipv6 AS $item) {
			$starts[] = $item->start;
			$prefixes[] = $item->prefix;
		}

		// sort by start ASC, prefix ASC (lower prefix = larger block first)
		\array_multisort($starts, \SORT_ASC, $prefixes, \SORT_ASC, $ipv6);
		unset($starts, $prefixes);

		$stack = []; // [end_bin, country] — outermost at bottom, innermost on top
		$stackLen = 0;
		$cursor = $zero;
		$bufStart = $zero;
		$bufEnd = $zero;
		$bufCountry = '';
		$time = \time();
		foreach ($ipv6 AS $i => $item) {
			$current = \time();
			if ($current !== $time) {
				\set_time_limit(30);
				progress::render($total, $i, ['Aggregating IPv6 Ranges']);
				$time = $current;
			}

			// skip blocks already fully covered
			if ($item->end <= $cursor) {
				continue;
			}

			// flush expired blocks from stack, yielding their remaining ranges
			while ($stackLen && $stack[$stackLen - 1][0] <= $item->start) {
				$top = $stack[--$stackLen];
				if ($cursor < $top[0]) {
					if ($top[1] === $bufCountry && $cursor === $bufEnd) {
						$bufEnd = $top[0];
					} else {
						if ($bufStart !== $bufEnd) {
							foreach (self::ipv6RangeToCidr($bufStart, $bufEnd) AS $cidr) {
								yield ['country' => $bufCountry, 'range' => $cidr];
							}
						}
						$bufStart = $cursor;
						$bufEnd = $top[0];
						$bufCountry = $top[1];
					}
					$cursor = $top[0];
				}
			}

			// fill gap between cursor and this block with innermost active country
			if ($cursor < $item->start) {
				if ($stackLen) {
					$country = $stack[$stackLen - 1][1];
					if ($country === $bufCountry && $cursor === $bufEnd) {
						$bufEnd = $item->start;
					} else {
						if ($bufStart !== $bufEnd) {
							foreach (self::ipv6RangeToCidr($bufStart, $bufEnd) AS $cidr) {
								yield ['country' => $bufCountry, 'range' => $cidr];
							}
						}
						$bufStart = $cursor;
						$bufEnd = $item->start;
						$bufCountry = $country;
					}
				} else if ($bufStart !== $bufEnd) {
					foreach (self::ipv6RangeToCidr($bufStart, $bufEnd) AS $cidr) {
						yield ['country' => $bufCountry, 'range' => $cidr];
					}
					$bufEnd = $bufStart;
				}
				$cursor = $item->start;
			}

			// push this block as the new innermost active range
			$country = $item->country;
			$stack[$stackLen++] = [$item->end, $country];
		}

		// flush remaining active blocks
		while ($stackLen) {
			$top = $stack[--$stackLen];
			if ($cursor < $top[0]) {
				if ($top[1] === $bufCountry && $cursor === $bufEnd) {
					$bufEnd = $top[0];
				} else {
					if ($bufStart !== $bufEnd) {
						foreach (self::ipv6RangeToCidr($bufStart, $bufEnd) AS $cidr) {
							yield ['country' => $bufCountry, 'range' => $cidr];
						}
					}
					$bufStart = $cursor;
					$bufEnd = $top[0];
					$bufCountry = $top[1];
				}
				$cursor = $top[0];
			}
		}

		// flush remaining buffer
		if ($bufStart !== $bufEnd) {
			foreach (self::ipv6RangeToCidr($bufStart, $bufEnd) AS $cidr) {
				yield ['country' => $bufCountry, 'range' => $cidr];
			}
		}
	}
}
