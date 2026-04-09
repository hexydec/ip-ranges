<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\BeforeClass;

class CountriesIpv6AccuracyTest extends TestCase {

	protected static array $ourStarts = [];
	protected static array $ourEnds = [];
	protected static array $ourCountries = [];
	protected static array $refStarts = [];
	protected static array $refEnds = [];
	protected static array $refCountries = [];

	/**
	 * Convert a decimal string (128-bit) to a 16-byte packed binary string
	 */
	protected static function decimalToBin(string $dec) : string {
		$bytes = [];
		for ($i = 0; $i < 16; $i++) {
			$bytes[] = \intval(\bcmod($dec, '256'));
			$dec = \bcdiv($dec, '256', 0);
		}
		return \implode('', \array_map('chr', \array_reverse($bytes)));
	}

	/**
	 * Add a small integer offset to a 16-byte packed binary address
	 */
	protected static function binAddInt(string $bin, int $offset) : string {
		$carry = $offset;
		for ($i = 15; $i >= 0 && $carry > 0; $i--) {
			$sum = \ord($bin[$i]) + ($carry & 0xFF);
			$bin[$i] = \chr($sum & 0xFF);
			$carry = ($carry >> 8) + ($sum >> 8);
		}
		return $bin;
	}

	/**
	 * Load our IPv6 CSV (format: country,cidr)
	 */
	protected static function loadIpv6Csv(string $file) : array {
		$starts = [];
		$ends = [];
		$countries = [];
		$handle = \fopen($file, 'r');

		while (($line = \fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
			if (\count($line) < 2 || !\str_contains($line[1], ':')) {
				continue;
			}
			$country = \strtolower($line[0]);
			$parts = \explode('/', $line[1], 2);
			$start = \inet_pton($parts[0]);
			if ($start === false) {
				continue;
			}
			$prefix = \intval($parts[1]);

			// compute end: start + 2^(128-prefix) - 1
			$blockSize = \str_repeat("\0", 16);
			$bit = 128 - $prefix;
			$byte = 15 - \intdiv($bit, 8);
			$blockSize[$byte] = \chr(1 << ($bit % 8));

			// end = start + blockSize - 1 (add blockSize then subtract 1)
			$end = $start;
			$carry = 0;
			for ($i = 15; $i >= 0; $i--) {
				$sum = \ord($end[$i]) + \ord($blockSize[$i]) + $carry;
				$end[$i] = \chr($sum & 0xFF);
				$carry = $sum >> 8;
			}
			// subtract 1
			for ($i = 15; $i >= 0; $i--) {
				$val = \ord($end[$i]);
				if ($val > 0) {
					$end[$i] = \chr($val - 1);
					break;
				}
				$end[$i] = "\xFF";
			}

			$starts[] = $start;
			$ends[] = $end;
			$countries[] = $country;
		}
		\fclose($handle);

		\array_multisort($starts, \SORT_ASC, $ends, \SORT_DESC, $countries);

		return [$starts, $ends, $countries];
	}

	/**
	 * Load IP2LOCATION IPv6 CSV (format: start_decimal, end_decimal, country_code, country_name)
	 */
	protected static function loadIp2LocationIpv6Csv(string $file) : array {
		$starts = [];
		$ends = [];
		$countries = [];
		$handle = \fopen($file, 'r');

		// ::ffff:0.0.0.0/96 boundaries in packed binary (IPv4-mapped range)
		$v4MappedStart = \str_repeat("\0", 10) . "\xFF\xFF" . "\0\0\0\0";
		$v4MappedEnd = \str_repeat("\0", 10) . "\xFF\xFF\xFF\xFF\xFF\xFF";

		while (($line = \fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
			if (\count($line) < 3 || !\is_numeric($line[0])) {
				continue;
			}
			$country = \strtolower($line[2]);
			if ($country === '-' || $country === '') {
				continue;
			}
			$start = self::decimalToBin($line[0]);
			$end = self::decimalToBin($line[1]);

			// skip IPv4-mapped addresses — these are tested by CountriesAccuracyTest
			if ($start >= $v4MappedStart && $end <= $v4MappedEnd) {
				continue;
			}

			$starts[] = $start;
			$ends[] = $end;
			$countries[] = $country;
		}
		\fclose($handle);

		\array_multisort($starts, \SORT_ASC, $ends, \SORT_DESC, $countries);

		return [$starts, $ends, $countries];
	}

	/**
	 * Binary search for a 16-byte packed IPv6 address
	 */
	protected static function lookupIpv6(array $starts, array $ends, array $countries, string $ip) : ?string {
		$lo = 0;
		$hi = \count($starts) - 1;
		while ($lo <= $hi) {
			$mid = ($lo + $hi) >> 1;
			if ($ip < $starts[$mid]) {
				$hi = $mid - 1;
			} elseif ($ip > $ends[$mid]) {
				$lo = $mid + 1;
			} else {
				$result = $countries[$mid];
				$bestSize = $ends[$mid] ^ $starts[$mid]; // XOR as rough size proxy

				for ($j = $mid - 1; $j >= $lo && $starts[$j] <= $ip; $j--) {
					if ($ip <= $ends[$j]) {
						$size = $ends[$j] ^ $starts[$j];
						if ($size < $bestSize) {
							$bestSize = $size;
							$result = $countries[$j];
						}
					}
				}
				for ($j = $mid + 1; $j <= $hi && $starts[$j] <= $ip; $j++) {
					if ($ip <= $ends[$j]) {
						$size = $ends[$j] ^ $starts[$j];
						if ($size < $bestSize) {
							$bestSize = $size;
							$result = $countries[$j];
						}
					}
				}
				return $result;
			}
		}
		return null;
	}

	#[BeforeClass]
	public static function loadData() : void {
		\ini_set('memory_limit', '4G');

		$oursFile = __DIR__ . '/../output/countries-ipv6.csv';
		$refFile = __DIR__ . '/../workspace/IP2LOCATION-LITE-DB1.IPV6.CSV';

		self::assertFileExists($oursFile, 'Output file countries-ipv6.csv not found — run build.php first');
		self::assertFileExists($refFile, 'Reference file IP2LOCATION-LITE-DB1.IPV6.CSV not found');

		[self::$ourStarts, self::$ourEnds, self::$ourCountries] = self::loadIpv6Csv($oursFile);
		[self::$refStarts, self::$refEnds, self::$refCountries] = self::loadIp2LocationIpv6Csv($refFile);
	}

	#[Test]
	public function accuracyAgainstReference() : void {
		self::assertNotEmpty(self::$ourStarts, 'Our dataset is empty');
		self::assertNotEmpty(self::$refStarts, 'Reference dataset is empty');

		$samples = 0;
		$matches = 0;
		$mismatches = 0;
		$ourOnly = 0;
		$refOnly = 0;
		$bothNull = 0;
		$errors = [];
		$countryMatches = [];
		$countryMismatches = [];

		// sample one IP from each reference range
		$refCount = \count(self::$refStarts);
		for ($i = 0; $i < $refCount; $i++) {
			$ip = self::binAddInt(self::$refStarts[$i], \mt_rand(0, 255));

			// clamp to range end
			if ($ip > self::$refEnds[$i]) {
				$ip = self::$refStarts[$i];
			}

			$ourCountry = self::lookupIpv6(self::$ourStarts, self::$ourEnds, self::$ourCountries, $ip);
			$refCountry = self::$refCountries[$i];
			$samples++;

			if ($ourCountry === null && $refCountry === null) {
				$bothNull++;
			} elseif ($ourCountry !== null && $refCountry === null) {
				$ourOnly++;
			} elseif ($ourCountry === null && $refCountry !== null) {
				$refOnly++;
			} elseif ($ourCountry === $refCountry) {
				$matches++;
				$countryMatches[$refCountry] = ($countryMatches[$refCountry] ?? 0) + 1;
			} else {
				$mismatches++;
				$countryMismatches[$refCountry] = ($countryMismatches[$refCountry] ?? 0) + 1;
				if (\count($errors) < 20) {
					$errors[] = \inet_ntop($ip) . ": ours={$ourCountry} ref={$refCountry}";
				}
			}
		}

		// report
		$comparable = $matches + $mismatches;
		$accuracy = $comparable > 0 ? ($matches / $comparable) * 100 : 0;

		$report = \sprintf(
			"\n--- IPv6 Accuracy Report ---\n" .
			"Reference ranges:    %s\n" .
			"Samples:             %s\n" .
			"Both agree:          %s (%.2f%%)\n" .
			"Disagree:            %s (%.2f%%)\n" .
			"Only in ours:        %s\n" .
			"Only in reference:   %s\n" .
			"Neither has:         %s\n",
			\number_format($refCount),
			\number_format($samples),
			\number_format($matches),
			$accuracy,
			\number_format($mismatches),
			$comparable > 0 ? ($mismatches / $comparable) * 100 : 0,
			\number_format($ourOnly),
			\number_format($refOnly),
			\number_format($bothNull)
		);

		if ($errors) {
			$report .= "Sample mismatches:\n  " . \implode("\n  ", $errors) . "\n";
		}

		// per-country error rates (top 10 by mismatch count)
		$countryErrors = [];
		foreach ($countryMismatches AS $cc => $miss) {
			$total = $miss + ($countryMatches[$cc] ?? 0);
			$countryErrors[$cc] = [
				'mismatches' => $miss,
				'total' => $total,
				'error_rate' => ($miss / $total) * 100
			];
		}
		\uasort($countryErrors, fn($a, $b) => $b['mismatches'] <=> $a['mismatches']);
		$top10 = \array_slice($countryErrors, 0, 10, true);

		$report .= "\nTop 10 countries by mismatches:\n";
		$report .= \sprintf("  %-4s %10s %10s %8s\n", 'CC', 'Mismatch', 'Total', 'Error%');
		foreach ($top10 AS $cc => $data) {
			$report .= \sprintf(
				"  %-4s %10s %10s %7.2f%%\n",
				\strtoupper($cc),
				\number_format($data['mismatches']),
				\number_format($data['total']),
				$data['error_rate']
			);
		}

		echo $report;

		$this->assertGreaterThan(50.0, $accuracy, "IPv6 accuracy {$accuracy}% is below 50% threshold");
	}

	#[Test]
	public function coverageAgainstReference() : void {
		$samples = 0;
		$covered = 0;
		$refCount = \count(self::$refStarts);

		for ($i = 0; $i < $refCount; $i++) {
			$ip = self::binAddInt(self::$refStarts[$i], \mt_rand(0, 255));
			if ($ip > self::$refEnds[$i]) {
				$ip = self::$refStarts[$i];
			}
			$samples++;
			if (self::lookupIpv6(self::$ourStarts, self::$ourEnds, self::$ourCountries, $ip) !== null) {
				$covered++;
			}
		}

		$coverage = $samples > 0 ? ($covered / $samples) * 100 : 0;
		echo \sprintf(
			"\n--- IPv6 Coverage Report ---\n" .
			"Reference IPs sampled: %s\n" .
			"Covered by ours:       %s (%.2f%%)\n",
			\number_format($samples),
			\number_format($covered),
			$coverage
		);

		$this->assertGreaterThan(45.0, $coverage, "IPv6 coverage {$coverage}% is below 45% threshold");
	}
}
