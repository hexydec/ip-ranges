<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\BeforeClass;

class CountriesAccuracyTest extends TestCase {

	/**
	 * Packed arrays: parallel arrays of [start, end, country] for fast binary search
	 * More memory-efficient than array-of-arrays
	 */
	protected static array $ourStarts = [];
	protected static array $ourEnds = [];
	protected static array $ourCountries = [];
	protected static array $refStarts = [];
	protected static array $refEnds = [];
	protected static array $refCountries = [];

	protected static function loadCsv(string $file, bool $hasHeader) : array {
		$starts = [];
		$ends = [];
		$countries = [];
		$handle = \fopen($file, 'r');

		if ($hasHeader) {
			\fgetcsv($handle, 0, ',', '"', '\\');
		}

		while (($line = \fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
			if (\count($line) >= 2 && \str_contains($line[1], '/')) {
				$country = \strtolower($line[0]);
				$cidr = $line[1];
				$parts = \explode('/', $cidr, 2);
				$start = \ip2long($parts[0]);
				if ($start === false) {
					continue;
				}
				$prefix = \intval($parts[1]);
				$count = 1 << (32 - $prefix);
				$starts[] = $start;
				$ends[] = $start + $count - 1;
				$countries[] = $country;
			}
		}
		\fclose($handle);

		// sort by start address
		\array_multisort($starts, \SORT_ASC, $ends, \SORT_DESC, $countries);

		return [$starts, $ends, $countries];
	}

	protected static function loadIp2LocationCsv(string $file) : array {
		$starts = [];
		$ends = [];
		$countries = [];
		$handle = \fopen($file, 'r');

		while (($line = \fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
			if (\count($line) >= 3 && \is_numeric($line[0])) {
				$start = \intval($line[0]);
				$end = \intval($line[1]);
				$country = \strtolower($line[2]);
				if ($country === '-' || $country === '') {
					continue;
				}
				$starts[] = $start;
				$ends[] = $end;
				$countries[] = $country;
			}
		}
		\fclose($handle);

		\array_multisort($starts, \SORT_ASC, $ends, \SORT_DESC, $countries);

		return [$starts, $ends, $countries];
	}

	protected static function lookupIp(array $starts, array $ends, array $countries, int $ip) : ?string {
		$lo = 0;
		$hi = \count($starts) - 1;
		$result = null;
		while ($lo <= $hi) {
			$mid = ($lo + $hi) >> 1;
			if ($ip < $starts[$mid]) {
				$hi = $mid - 1;
			} elseif ($ip > $ends[$mid]) {
				$lo = $mid + 1;
			} else {
				// found a match — look for most specific (smallest range)
				$result = $countries[$mid];
				$bestSize = $ends[$mid] - $starts[$mid];

				// check neighbours for tighter matches
				for ($j = $mid - 1; $j >= $lo && $starts[$j] <= $ip; $j--) {
					if ($ip <= $ends[$j]) {
						$size = $ends[$j] - $starts[$j];
						if ($size < $bestSize) {
							$bestSize = $size;
							$result = $countries[$j];
						}
					}
				}
				for ($j = $mid + 1; $j <= $hi && $starts[$j] <= $ip; $j++) {
					if ($ip <= $ends[$j]) {
						$size = $ends[$j] - $starts[$j];
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

		$oursFile = __DIR__ . '/../output/countries-ipv4.csv';
		$refFile = __DIR__ . '/../workspace/IP2LOCATION-LITE-DB1.CSV';

		self::assertFileExists($oursFile, 'Output file countries-ipv4.csv not found — run build.php first');
		self::assertFileExists($refFile, 'Reference file IP2LOCATION-LITE-DB1.CSV not found');

		[self::$ourStarts, self::$ourEnds, self::$ourCountries] = self::loadCsv($oursFile, false);
		[self::$refStarts, self::$refEnds, self::$refCountries] = self::loadIp2LocationCsv($refFile);
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

		// collect unique /24 blocks from our data
		$blocks = [];
		$count = \count(self::$ourStarts);
		for ($i = 0; $i < $count; $i++) {
			$start = self::$ourStarts[$i];
			$end = self::$ourEnds[$i];

			// iterate through /24 blocks covered by this range
			$blockStart = $start & 0xFFFFFF00;
			while ($blockStart <= $end) {
				$blocks[$blockStart] = true;
				$blockStart += 256;

				// limit block collection per range to prevent memory issues on huge ranges
				if (\count($blocks) >= 17000000) {
					break 2;
				}
			}
		}

		// test every /24 block
		$blockKeys = \array_keys($blocks);
		$totalBlocks = \count($blockKeys);
		unset($blocks);

		foreach ($blockKeys AS $blockStart) {
			$ip = $blockStart + \mt_rand(0, 255);
			$ourCountry = self::lookupIp(self::$ourStarts, self::$ourEnds, self::$ourCountries, $ip);
			$refCountry = self::lookupIp(self::$refStarts, self::$refEnds, self::$refCountries, $ip);
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
					$errors[] = \long2ip($ip) . ": ours={$ourCountry} ref={$refCountry}";
				}
			}
		}

		// report
		$comparable = $matches + $mismatches;
		$accuracy = $comparable > 0 ? ($matches / $comparable) * 100 : 0;

		$report = \sprintf(
			"\n--- Accuracy Report ---\n" .
			"Total /24 blocks:    %s\n" .
			"Samples:             %s\n" .
			"Both agree:          %s (%.2f%%)\n" .
			"Disagree:            %s (%.2f%%)\n" .
			"Only in ours:        %s\n" .
			"Only in reference:   %s\n" .
			"Neither has:         %s\n",
			\number_format($totalBlocks),
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

		$this->assertGreaterThan(85.0, $accuracy, "Accuracy {$accuracy}% is below 85% threshold");
	}

	#[Test]
	public function coverageAgainstReference() : void {
		// check how many reference IPs we cover
		$samples = 0;
		$covered = 0;
		$sampleSize = \min(\count(self::$refStarts), 1000000);
		$indices = (array)\array_rand(self::$refStarts, $sampleSize);

		foreach ($indices AS $idx) {
			$start = self::$refStarts[$idx];
			$end = self::$refEnds[$idx];
			$ip = $start === $end ? $start : \mt_rand($start, $end);
			$samples++;
			if (self::lookupIp(self::$ourStarts, self::$ourEnds, self::$ourCountries, $ip) !== null) {
				$covered++;
			}
		}

		$coverage = $samples > 0 ? ($covered / $samples) * 100 : 0;
		echo \sprintf(
			"\n--- Coverage Report ---\n" .
			"Reference IPs sampled: %s\n" .
			"Covered by ours:       %s (%.2f%%)\n",
			\number_format($samples),
			\number_format($covered),
			$coverage
		);

		$this->assertGreaterThan(85.0, $coverage, "Coverage {$coverage}% is below 85% threshold");
	}
}
