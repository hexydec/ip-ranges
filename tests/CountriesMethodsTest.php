<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use hexydec\ipaddresses\countries;
use hexydec\ipaddresses\aggregator;

/**
 * Testable subclass that intercepts fetch() calls with pre-loaded responses
 */
class testableCountries extends countries {

	protected array $responses = [];

	public function setResponse(string $url, string|false $response) : void {
		$this->responses[$url] = $response;
	}

	public function fetch(string $url, ?string $cache = null, bool $contents = true) : string|false {
		if (\array_key_exists($url, $this->responses)) {
			$response = $this->responses[$url];

			// if caller wants a file path, write to a temp file
			if (!$contents && $response !== false) {
				$tmp = \tempnam(\sys_get_temp_dir(), 'ipt');
				\file_put_contents($tmp, $response);
				return $tmp;
			}
			return $response;
		}
		return false;
	}

	// Expose protected methods for testing
	public function callAddIpRange(array &$ips, string $cidr, array $item) : void {
		$this->addIpRange($ips, $cidr, $item);
	}

	public function callGetRirIps(array $sources, array $ips, ?string $cache = null) : array|false {
		return $this->getRirIps($sources, $ips, $cache);
	}

	public function callGetGeofeedIps(string $source, array $ips, ?string $cache = null) : array|false {
		return $this->getGeofeedIps($source, $ips, $cache);
	}

	public function callGetCloudProviderIps(string $source, array $ips) : array|false {
		return $this->getCloudProviderIps($source, $ips);
	}

	public function callGetWhoisIps(array $sources, array $ips, ?string $cache = null) : array|false {
		return $this->getWhoisIps($sources, $ips, $cache);
	}

	public function callIngestBgpData(array $ips, array $asns, string $url, ?string $cache = null) : array|false {
		return $this->ingestBgpData($ips, $asns, $url, $cache);
	}

	public function callGetBgpIps(array $sources, array $ips, ?string $cache = null) : array|false {
		return $this->getBgpIps($sources, $ips, $cache);
	}
}

class CountriesMethodsTest extends TestCase {

	protected function emptyIps() : array {
		return [4 => [], 6 => []];
	}

	// --- addIpRange ---

	#[Test]
	public function addIpRangeInsertsNewEntry() : void {
		$obj = new testableCountries();
		$ips = [];
		$obj->callAddIpRange($ips, '1.0.0.0/24', [
			'start' => \ip2long('1.0.0.0'),
			'count' => 256,
			'country' => 'au'
		]);

		$this->assertArrayHasKey('1.0.0.0/24', $ips);
		$this->assertSame('au', $ips['1.0.0.0/24']->country);
		$this->assertSame(256, $ips['1.0.0.0/24']->count);
	}

	#[Test]
	public function addIpRangeOverwritesCountryOnDuplicate() : void {
		$obj = new testableCountries();
		$ips = [];
		$obj->callAddIpRange($ips, '1.0.0.0/24', [
			'start' => \ip2long('1.0.0.0'),
			'count' => 256,
			'country' => 'au'
		]);
		$obj->callAddIpRange($ips, '1.0.0.0/24', [
			'start' => \ip2long('1.0.0.0'),
			'count' => 256,
			'country' => 'us'
		]);

		$this->assertCount(1, $ips);
		$this->assertSame('us', $ips['1.0.0.0/24']->country);
	}

	#[Test]
	public function addIpRangePreservesOriginalFieldsOnOverwrite() : void {
		$obj = new testableCountries();
		$ips = [];
		$obj->callAddIpRange($ips, '10.0.0.0/8', [
			'start' => \ip2long('10.0.0.0'),
			'count' => 16777216,
			'country' => 'gb'
		]);
		$obj->callAddIpRange($ips, '10.0.0.0/8', [
			'start' => \ip2long('10.0.0.0'),
			'count' => 16777216,
			'country' => 'de'
		]);

		$this->assertSame('de', $ips['10.0.0.0/8']->country);
		$this->assertSame(16777216, $ips['10.0.0.0/8']->count);
	}

	// --- getRirIps ---

	#[Test]
	public function getRirIpsExtractsIpv4Records() : void {
		$obj = new testableCountries();
		$data = \implode("\n", [
			'2|ripencc|20240101|100000|19830101|20240101|+0100',
			'ripencc|GB|ipv4|1.0.0.0|256|20100101|allocated',
			'ripencc|DE|ipv4|2.0.0.0|1024|20100101|assigned',
		]);
		$obj->setResponse('https://example.com/rir', $data);

		$ips = $this->emptyIps();
		$result = $obj->callGetRirIps(['https://example.com/rir'], $ips);

		$this->assertNotFalse($result);
		$this->assertArrayHasKey('1.0.0.0/24', $result[4]);
		$this->assertSame('gb', $result[4]['1.0.0.0/24']->country);
		$this->assertArrayHasKey('2.0.0.0/22', $result[4]);
		$this->assertSame('de', $result[4]['2.0.0.0/22']->country);
	}

	#[Test]
	public function getRirIpsExtractsIpv6Records() : void {
		$obj = new testableCountries();
		$data = \implode("\n", [
			'2|ripencc|20240101|100000|19830101|20240101|+0100',
			'ripencc|FR|ipv6|2001:db8::|32|20100101|allocated',
		]);
		$obj->setResponse('https://example.com/rir', $data);

		$ips = $this->emptyIps();
		$result = $obj->callGetRirIps(['https://example.com/rir'], $ips);

		$this->assertNotFalse($result);
		$this->assertArrayHasKey('2001:db8::/32', $result[6]);
		$this->assertSame('fr', $result[6]['2001:db8::/32']->country);
	}

	#[Test]
	public function getRirIpsCollectsAsnMappings() : void {
		$obj = new testableCountries();
		$data = \implode("\n", [
			'2|ripencc|20240101|100000|19830101|20240101|+0100',
			'ripencc|US|asn|13335|1|20100101|allocated',
			'ripencc|DE|asn|24940|1|20100101|allocated',
		]);
		$obj->setResponse('https://example.com/rir', $data);

		$ips = $this->emptyIps();
		$result = $obj->callGetRirIps(['https://example.com/rir'], $ips);

		$this->assertNotFalse($result);
		$this->assertArrayHasKey('asns', $result);
		$this->assertSame('us', $result['asns'][13335]);
		$this->assertSame('de', $result['asns'][24940]);
	}

	#[Test]
	public function getRirIpsSkipsNonAllocatedRecords() : void {
		$obj = new testableCountries();
		$data = \implode("\n", [
			'2|ripencc|20240101|100000|19830101|20240101|+0100',
			'ripencc|GB|ipv4|1.0.0.0|256|20100101|reserved',
			'ripencc|DE|ipv4|2.0.0.0|256|20100101|available',
		]);
		$obj->setResponse('https://example.com/rir', $data);

		$ips = $this->emptyIps();
		$result = $obj->callGetRirIps(['https://example.com/rir'], $ips);

		$this->assertNotFalse($result);
		$this->assertEmpty($result[4]);
	}

	#[Test]
	public function getRirIpsReturnsFalseOnFetchFailure() : void {
		$obj = new testableCountries();
		$obj->setResponse('https://example.com/rir', false);

		$ips = $this->emptyIps();
		$result = $obj->callGetRirIps(['https://example.com/rir'], $ips);

		$this->assertFalse($result);
	}

	#[Test]
	public function getRirIpsHandlesMultipleSources() : void {
		$obj = new testableCountries();
		$obj->setResponse('https://example.com/rir1', 'ripencc|GB|ipv4|1.0.0.0|256|20100101|allocated');
		$obj->setResponse('https://example.com/rir2', 'apnic|JP|ipv4|3.0.0.0|256|20100101|allocated');

		$ips = $this->emptyIps();
		$result = $obj->callGetRirIps(['https://example.com/rir1', 'https://example.com/rir2'], $ips);

		$this->assertNotFalse($result);
		$this->assertCount(2, $result[4]);
		$this->assertSame('gb', $result[4]['1.0.0.0/24']->country);
		$this->assertSame('jp', $result[4]['3.0.0.0/24']->country);
	}

	// --- getGeofeedIps ---

	#[Test]
	public function getGeofeedIpsExtractsIpv4() : void {
		$obj = new testableCountries();
		$data = \implode("\n", [
			'# comment line',
			'192.168.1.0/24,US,US-CA,San Francisco,',
			'10.0.0.0/8,GB,GB-LND,London,',
		]);
		$obj->setResponse('https://example.com/geofeed.csv', $data);

		$ips = $this->emptyIps();
		$result = $obj->callGetGeofeedIps('https://example.com/geofeed.csv', $ips);

		$this->assertNotFalse($result);
		$this->assertArrayHasKey('192.168.1.0/24', $result[4]);
		$this->assertSame('us', $result[4]['192.168.1.0/24']->country);
		$this->assertArrayHasKey('10.0.0.0/8', $result[4]);
		$this->assertSame('gb', $result[4]['10.0.0.0/8']->country);
	}

	#[Test]
	public function getGeofeedIpsExtractsIpv6() : void {
		$obj = new testableCountries();
		$data = '2001:db8::/32,DE,DE-HE,Frankfurt,';
		$obj->setResponse('https://example.com/geofeed.csv', $data);

		$ips = $this->emptyIps();
		$result = $obj->callGetGeofeedIps('https://example.com/geofeed.csv', $ips);

		$this->assertNotFalse($result);
		$this->assertArrayHasKey('2001:db8::/32', $result[6]);
		$this->assertSame('de', $result[6]['2001:db8::/32']->country);
	}

	#[Test]
	public function getGeofeedIpsSkipsComments() : void {
		$obj = new testableCountries();
		$data = \implode("\n", [
			'# this is a comment',
			'# another comment',
			'192.168.1.0/24,US,US-CA,,',
		]);
		$obj->setResponse('https://example.com/geofeed.csv', $data);

		$ips = $this->emptyIps();
		$result = $obj->callGetGeofeedIps('https://example.com/geofeed.csv', $ips);

		$this->assertNotFalse($result);
		$this->assertCount(1, $result[4]);
	}

	#[Test]
	public function getGeofeedIpsFallsBackToRegionForCountry() : void {
		$obj = new testableCountries();
		// Empty country field, should fall back to first 2 chars of region
		$data = '192.168.1.0/24,,US-CA,,';
		$obj->setResponse('https://example.com/geofeed.csv', $data);

		$ips = $this->emptyIps();
		$result = $obj->callGetGeofeedIps('https://example.com/geofeed.csv', $ips);

		$this->assertNotFalse($result);
		$this->assertSame('us', $result[4]['192.168.1.0/24']->country);
	}

	#[Test]
	public function getGeofeedIpsReturnsFalseOnFetchFailure() : void {
		$obj = new testableCountries();
		$obj->setResponse('https://example.com/geofeed.csv', false);

		$ips = $this->emptyIps();
		$result = $obj->callGetGeofeedIps('https://example.com/geofeed.csv', $ips);

		$this->assertFalse($result);
	}

	// --- getCloudProviderIps ---

	#[Test]
	public function getCloudProviderIpsExtractsIpv4() : void {
		$tmp = \tempnam(\sys_get_temp_dir(), 'cloud');
		$handle = \fopen($tmp, 'w');
		\fputcsv($handle, ['Amazon AWS', '10.0.0.0/16', 'us'], ',', '"', '\\');
		\fputcsv($handle, ['Google Cloud', '172.16.0.0/12', 'gb'], ',', '"', '\\');
		\fclose($handle);

		$obj = new testableCountries();
		$ips = $this->emptyIps();
		$result = $obj->callGetCloudProviderIps($tmp, $ips);

		\unlink($tmp);

		$this->assertNotFalse($result);
		$this->assertArrayHasKey('10.0.0.0/16', $result[4]);
		$this->assertSame('us', $result[4]['10.0.0.0/16']->country);
		$this->assertArrayHasKey('172.16.0.0/12', $result[4]);
		$this->assertSame('gb', $result[4]['172.16.0.0/12']->country);
	}

	#[Test]
	public function getCloudProviderIpsExtractsIpv6() : void {
		$tmp = \tempnam(\sys_get_temp_dir(), 'cloud');
		$handle = \fopen($tmp, 'w');
		\fputcsv($handle, ['Azure', '2001:db8::/32', 'de'], ',', '"', '\\');
		\fclose($handle);

		$obj = new testableCountries();
		$ips = $this->emptyIps();
		$result = $obj->callGetCloudProviderIps($tmp, $ips);

		\unlink($tmp);

		$this->assertNotFalse($result);
		$this->assertArrayHasKey('2001:db8::/32', $result[6]);
		$this->assertSame('de', $result[6]['2001:db8::/32']->country);
	}

	#[Test]
	public function getCloudProviderIpsSkipsRowsWithEmptyCountry() : void {
		$tmp = \tempnam(\sys_get_temp_dir(), 'cloud');
		$handle = \fopen($tmp, 'w');
		\fputcsv($handle, ['Amazon AWS', '10.0.0.0/16', ''], ',', '"', '\\');
		\fputcsv($handle, ['Google Cloud', '172.16.0.0/12', 'us'], ',', '"', '\\');
		\fclose($handle);

		$obj = new testableCountries();
		$ips = $this->emptyIps();
		$result = $obj->callGetCloudProviderIps($tmp, $ips);

		\unlink($tmp);

		$this->assertNotFalse($result);
		$this->assertCount(1, $result[4]);
	}

	#[Test]
	public function getCloudProviderIpsReturnsFalseForMissingFile() : void {
		$obj = new testableCountries();
		$ips = $this->emptyIps();
		$result = $obj->callGetCloudProviderIps('/nonexistent/file.csv', $ips);

		$this->assertFalse($result);
	}

	// --- getWhoisIps ---

	#[Test]
	public function getWhoisIpsExtractsIpv6Ranges() : void {
		$whois = \implode("\n", [
			'inet6num:        2001:db8::/32',
			'netname:         TEST-NET',
			'country:         DE',
			'',
			'inet6num:        2001:db9::/32',
			'netname:         TEST-NET-2',
			'country:         FR',
			'',
		]);

		// Write to a gzipped temp file
		$tmp = \tempnam(\sys_get_temp_dir(), 'whois') . '.gz';
		$gz = \gzopen($tmp, 'w');
		\gzwrite($gz, $whois);
		\gzclose($gz);

		$obj = new testableCountries();
		// Override fetch to return the gzipped file path
		$obj->setResponse('https://example.com/whois.gz', \file_get_contents($tmp));

		$ips = $this->emptyIps();

		// We need to use the actual gz file, so call via reflection with the real file
		// Instead, create a subclass approach: write the gz and use it directly
		$rc = new \ReflectionClass($obj);
		$method = $rc->getMethod('getWhoisIps');

		// Override fetch to return the file path (not contents)
		$testObj = new class extends testableCountries {
			public string $gzFile = '';
			public function fetch(string $url, ?string $cache = null, bool $contents = true) : string|false {
				if (!$contents) {
					return $this->gzFile;
				}
				return parent::fetch($url, $cache, $contents);
			}
		};
		$testObj->gzFile = $tmp;

		$result = $method->invoke($testObj, ['https://example.com/whois.gz'], $ips);

		\unlink($tmp);

		$this->assertNotFalse($result);
		$this->assertArrayHasKey('2001:db8::/32', $result[6]);
		$this->assertSame('de', $result[6]['2001:db8::/32']->country);
		$this->assertArrayHasKey('2001:db9::/32', $result[6]);
		$this->assertSame('fr', $result[6]['2001:db9::/32']->country);
	}

	#[Test]
	public function getWhoisIpsFlushesLastObject() : void {
		// No trailing blank line — should still capture the last record
		$whois = \implode("\n", [
			'inet6num:        2001:db8::/48',
			'country:         JP',
		]);

		$tmp = \tempnam(\sys_get_temp_dir(), 'whois') . '.gz';
		$gz = \gzopen($tmp, 'w');
		\gzwrite($gz, $whois);
		\gzclose($gz);

		$testObj = new class extends testableCountries {
			public string $gzFile = '';
			public function fetch(string $url, ?string $cache = null, bool $contents = true) : string|false {
				if (!$contents) {
					return $this->gzFile;
				}
				return false;
			}
		};
		$testObj->gzFile = $tmp;

		$rc = new \ReflectionClass($testObj);
		$method = $rc->getMethod('getWhoisIps');
		$ips = $this->emptyIps();
		$result = $method->invoke($testObj, ['https://example.com/whois.gz'], $ips);

		\unlink($tmp);

		$this->assertNotFalse($result);
		$this->assertArrayHasKey('2001:db8::/48', $result[6]);
		$this->assertSame('jp', $result[6]['2001:db8::/48']->country);
	}

	#[Test]
	public function getWhoisIpsSkipsEntriesWithoutCountry() : void {
		$whois = \implode("\n", [
			'inet6num:        2001:db8::/32',
			'netname:         TEST-NET',
			'',
		]);

		$tmp = \tempnam(\sys_get_temp_dir(), 'whois') . '.gz';
		$gz = \gzopen($tmp, 'w');
		\gzwrite($gz, $whois);
		\gzclose($gz);

		$testObj = new class extends testableCountries {
			public string $gzFile = '';
			public function fetch(string $url, ?string $cache = null, bool $contents = true) : string|false {
				if (!$contents) {
					return $this->gzFile;
				}
				return false;
			}
		};
		$testObj->gzFile = $tmp;

		$rc = new \ReflectionClass($testObj);
		$method = $rc->getMethod('getWhoisIps');
		$ips = $this->emptyIps();
		$result = $method->invoke($testObj, ['https://example.com/whois.gz'], $ips);

		\unlink($tmp);

		$this->assertNotFalse($result);
		$this->assertEmpty($result[6]);
	}

	// --- ingestBgpData (via getBgpSource) ---

	#[Test]
	public function ingestBgpDataExtractsIpv4FromDump() : void {
		$bgpLines = \implode("\n", [
			'TABLE_DUMP2|1234567890|B|198.51.100.1|65000|192.0.2.0/24|65000 13335|IGP',
			'TABLE_DUMP2|1234567890|B|198.51.100.1|65000|203.0.113.0/24|65000 24940|IGP',
			'TABLE_DUMP2|1234567890|B|198.51.100.1|65000|10.0.0.0/8|65000 99999|IGP',
		]);

		// Write to a cache file that getBgpSource will find
		$cacheDir = \sys_get_temp_dir() . '/ipt_bgp_test_' . \mt_rand() . '/';
		\mkdir($cacheDir, 0755, true);
		$cachefile = $cacheDir . 'bgpdata.txt';
		\file_put_contents($cachefile, $bgpLines);

		$obj = new testableCountries();
		$ips = $this->emptyIps();
		$asns = [13335 => 'us', 24940 => 'de'];

		// Use reflection to call ingestBgpData, but we need getBgpSource to return our file
		// Override getBgpSource via a subclass
		$testObj = new class($cachefile) extends testableCountries {
			private string $bgpFile;
			public function __construct(string $bgpFile) {
				$this->bgpFile = $bgpFile;
			}
			protected function getBgpSource(string $url, string $cachefile, ?string $cache = null) {
				$handle = \fopen($this->bgpFile, 'r');
				if ($handle === false) {
					return false;
				}
				// count lines
				$obj = new \SplFileObject($this->bgpFile);
				$obj->setFlags($obj::READ_AHEAD);
				$total = \iterator_count($obj);
				return [$handle, $total];
			}
		};

		$rc = new \ReflectionClass($testObj);
		$method = $rc->getMethod('ingestBgpData');
		$result = $method->invoke($testObj, $ips, $asns, 'http://example.com/bgp.bz2');

		\unlink($cachefile);
		\rmdir($cacheDir);

		$this->assertNotFalse($result);
		$this->assertArrayHasKey('192.0.2.0/24', $result[4]);
		$this->assertSame('us', $result[4]['192.0.2.0/24']->country);
		$this->assertArrayHasKey('203.0.113.0/24', $result[4]);
		$this->assertSame('de', $result[4]['203.0.113.0/24']->country);
		// ASN 99999 not in our mapping, should be skipped
		$this->assertArrayNotHasKey('10.0.0.0/8', $result[4]);
	}

	#[Test]
	public function ingestBgpDataExtractsIpv6FromDump() : void {
		$bgpLines = 'TABLE_DUMP2|1234567890|B|2001:db8::1|65000|2001:db8::/32|65000 13335|IGP';

		$cachefile = \tempnam(\sys_get_temp_dir(), 'bgp6');
		\file_put_contents($cachefile, $bgpLines);

		$testObj = new class($cachefile) extends testableCountries {
			private string $bgpFile;
			public function __construct(string $bgpFile) {
				$this->bgpFile = $bgpFile;
			}
			protected function getBgpSource(string $url, string $cachefile, ?string $cache = null) {
				$handle = \fopen($this->bgpFile, 'r');
				return $handle !== false ? [$handle, 1] : false;
			}
		};

		$rc = new \ReflectionClass($testObj);
		$method = $rc->getMethod('ingestBgpData');
		$ips = $this->emptyIps();
		$result = $method->invoke($testObj, $ips, [13335 => 'us'], 'http://example.com/route-views6/bgp.bz2');

		\unlink($cachefile);

		$this->assertNotFalse($result);
		$this->assertArrayHasKey('2001:db8::/32', $result[6]);
		$this->assertSame('us', $result[6]['2001:db8::/32']->country);
	}

	#[Test]
	public function ingestBgpDataSkipsNonTableDump2Lines() : void {
		$bgpLines = \implode("\n", [
			'SOME_OTHER_FORMAT|1234567890|B|198.51.100.1|65000|192.0.2.0/24|65000 13335|IGP',
			'# comment line',
			'',
		]);

		$cachefile = \tempnam(\sys_get_temp_dir(), 'bgpskip');
		\file_put_contents($cachefile, $bgpLines);

		$testObj = new class($cachefile) extends testableCountries {
			private string $bgpFile;
			public function __construct(string $bgpFile) {
				$this->bgpFile = $bgpFile;
			}
			protected function getBgpSource(string $url, string $cachefile, ?string $cache = null) {
				$handle = \fopen($this->bgpFile, 'r');
				return $handle !== false ? [$handle, 3] : false;
			}
		};

		$rc = new \ReflectionClass($testObj);
		$method = $rc->getMethod('ingestBgpData');
		$ips = $this->emptyIps();
		$result = $method->invoke($testObj, $ips, [13335 => 'us'], 'http://example.com/bgp.bz2');

		\unlink($cachefile);

		$this->assertNotFalse($result);
		$this->assertEmpty($result[4]);
	}

	#[Test]
	public function ingestBgpDataUsesLastAsnInPath() : void {
		// AS path: 65000 65001 13335 — should use 13335 (the origin ASN)
		$bgpLines = 'TABLE_DUMP2|1234567890|B|198.51.100.1|65000|192.0.2.0/24|65000 65001 13335|IGP';

		$cachefile = \tempnam(\sys_get_temp_dir(), 'bgppath');
		\file_put_contents($cachefile, $bgpLines);

		$testObj = new class($cachefile) extends testableCountries {
			private string $bgpFile;
			public function __construct(string $bgpFile) {
				$this->bgpFile = $bgpFile;
			}
			protected function getBgpSource(string $url, string $cachefile, ?string $cache = null) {
				$handle = \fopen($this->bgpFile, 'r');
				return $handle !== false ? [$handle, 1] : false;
			}
		};

		$rc = new \ReflectionClass($testObj);
		$method = $rc->getMethod('ingestBgpData');
		$ips = $this->emptyIps();
		$result = $method->invoke($testObj, $ips, [13335 => 'us', 65001 => 'de'], 'http://example.com/bgp.bz2');

		\unlink($cachefile);

		$this->assertNotFalse($result);
		$this->assertSame('us', $result[4]['192.0.2.0/24']->country);
	}

	// --- Priority / overwrite behaviour ---

	#[Test]
	public function laterSourceOverwritesCountryForSameCidr() : void {
		$obj = new testableCountries();

		// First source assigns country 'au'
		$obj->setResponse('https://example.com/rir1', 'ripencc|AU|ipv4|1.0.0.0|256|20100101|allocated');
		// Second source assigns country 'nz' to the same range
		$obj->setResponse('https://example.com/rir2', 'apnic|NZ|ipv4|1.0.0.0|256|20100101|allocated');

		$ips = $this->emptyIps();
		$result = $obj->callGetRirIps(['https://example.com/rir1', 'https://example.com/rir2'], $ips);

		$this->assertNotFalse($result);
		// The second source should overwrite the first
		$this->assertSame('nz', $result[4]['1.0.0.0/24']->country);
	}

	#[Test]
	public function geofeedOverwritesRirForSameCidr() : void {
		$obj = new testableCountries();

		// RIR says 'au'
		$obj->setResponse('https://example.com/rir', 'ripencc|AU|ipv4|1.0.0.0|256|20100101|allocated');

		$ips = $this->emptyIps();
		$ips = $obj->callGetRirIps(['https://example.com/rir'], $ips);
		$this->assertSame('au', $ips[4]['1.0.0.0/24']->country);

		// Geofeed says 'nz'
		$obj->setResponse('https://example.com/geofeed', '1.0.0.0/24,NZ,NZ-AUK,,');
		$ips = $obj->callGetGeofeedIps('https://example.com/geofeed', $ips);

		$this->assertSame('nz', $ips[4]['1.0.0.0/24']->country);
	}

	// --- Edge cases ---

	#[Test]
	public function getRirIpsHandlesEmptyInput() : void {
		$obj = new testableCountries();
		$obj->setResponse('https://example.com/rir', '');

		$ips = $this->emptyIps();
		$result = $obj->callGetRirIps(['https://example.com/rir'], $ips);

		$this->assertNotFalse($result);
		$this->assertEmpty($result[4]);
		$this->assertEmpty($result[6]);
	}

	#[Test]
	public function getGeofeedIpsHandlesWildcardCountry() : void {
		$obj = new testableCountries();
		$data = '192.168.1.0/24,*,*,,';
		$obj->setResponse('https://example.com/geofeed.csv', $data);

		$ips = $this->emptyIps();
		$result = $obj->callGetGeofeedIps('https://example.com/geofeed.csv', $ips);

		$this->assertNotFalse($result);
		$this->assertEmpty($result[4]);
	}

	#[Test]
	public function getRirIpsSkipsWildcardCountryForAsn() : void {
		$obj = new testableCountries();
		$data = 'ripencc|*|asn|13335|1|20100101|allocated';
		$obj->setResponse('https://example.com/rir', $data);

		$ips = $this->emptyIps();
		$result = $obj->callGetRirIps(['https://example.com/rir'], $ips);

		$this->assertNotFalse($result);
		$this->assertEmpty($result['asns']);
	}

	#[Test]
	public function getCloudProviderIpsMixedProtocols() : void {
		$tmp = \tempnam(\sys_get_temp_dir(), 'cloud');
		$handle = \fopen($tmp, 'w');
		\fputcsv($handle, ['AWS', '10.0.0.0/24', 'us'], ',', '"', '\\');
		\fputcsv($handle, ['AWS', '2001:db8::/32', 'us'], ',', '"', '\\');
		\fputcsv($handle, ['GCP', '172.16.0.0/16', 'de'], ',', '"', '\\');
		\fclose($handle);

		$obj = new testableCountries();
		$ips = $this->emptyIps();
		$result = $obj->callGetCloudProviderIps($tmp, $ips);

		\unlink($tmp);

		$this->assertNotFalse($result);
		$this->assertCount(2, $result[4]);
		$this->assertCount(1, $result[6]);
	}
}
