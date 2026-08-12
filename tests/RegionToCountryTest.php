<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use hexydec\ipaddresses\datacentres;

class RegionToCountryTest extends TestCase {

	protected static function callRegionToCountry(string $region) : ?string {
		$rc = new \ReflectionClass(datacentres::class);
		$method = $rc->getMethod('regionToCountry');
		return $method->invoke(null, $region);
	}

	public static function awsRegionProvider() : array {
		return [
			['us-east-1', 'us'],
			['us-west-2', 'us'],
			['eu-west-1', 'ie'],
			['eu-west-2', 'gb'],
			['eu-west-3', 'fr'],
			['eu-central-1', 'de'],
			['eu-central-2', 'ch'],
			['eu-north-1', 'se'],
			['eu-south-1', 'it'],
			['eu-south-2', 'es'],
			['ap-southeast-1', 'sg'],
			['ap-southeast-2', 'au'],
			['ap-northeast-1', 'jp'],
			['ap-northeast-2', 'kr'],
			['ap-northeast-3', 'jp'],
			['ap-south-1', 'in'],
			['ap-east-1', 'hk'],
			['sa-east-1', 'br'],
			['af-south-1', 'za'],
			['me-south-1', 'bh'],
			['me-central-1', 'ae'],
			['il-central-1', 'il'],
			['ca-central-1', 'ca'],
			['cn-north-1', 'cn'],
		];
	}

	#[Test]
	#[DataProvider('awsRegionProvider')]
	public function awsRegions(string $region, string $expected) : void {
		$this->assertSame($expected, self::callRegionToCountry($region), "AWS region {$region}");
	}

	public static function gcpRegionProvider() : array {
		return [
			['us-central1', 'us'],
			['us-east1', 'us'],
			['europe-west1', 'be'],
			['europe-west2', 'gb'],
			['europe-west3', 'de'],
			['europe-west4', 'nl'],
			['europe-west9', 'fr'],
			['europe-north1', 'fi'],
			['asia-east1', 'tw'],
			['asia-east2', 'hk'],
			['asia-northeast1', 'jp'],
			['asia-southeast1', 'sg'],
			['australia-southeast1', 'au'],
			['southamerica-east1', 'br'],
			['northamerica-northeast1', 'ca'],
			['africa-south1', 'za'],
			['me-west1', 'il'],
		];
	}

	#[Test]
	#[DataProvider('gcpRegionProvider')]
	public function gcpRegions(string $region, string $expected) : void {
		$this->assertSame($expected, self::callRegionToCountry($region), "GCP region {$region}");
	}

	public static function azureRegionProvider() : array {
		return [
			['eastus', 'us'],
			['westeurope', 'nl'],
			['northeurope', 'ie'],
			['uksouth', 'gb'],
			['centralfrance', 'fr'],
			['germanywc', 'de'],
			['japaneast', 'jp'],
			['koreacentral', 'kr'],
			['australiaeast', 'au'],
			['southeastasia', 'sg'],
			['centralindia', 'in'],
			['brazilsouth', 'br'],
			['canadacentral', 'ca'],
			['swedencentral', 'se'],
			['southafricanorth', 'za'],
		];
	}

	#[Test]
	#[DataProvider('azureRegionProvider')]
	public function azureRegions(string $region, string $expected) : void {
		$this->assertSame($expected, self::callRegionToCountry($region), "Azure region {$region}");
	}

	public static function oracleRegionProvider() : array {
		return [
			['us-ashburn-1', 'us'],
			['us-phoenix-1', 'us'],
			['eu-frankfurt-1', 'de'],
			['eu-amsterdam-1', 'nl'],
			['uk-london-1', 'gb'],
			['ap-tokyo-1', 'jp'],
			['ap-mumbai-1', 'in'],
			['ap-sydney-1', 'au'],
			['ap-seoul-1', 'kr'],
			['sa-saopaulo-1', 'br'],
			['mx-monterrey-1', 'mx'],
			['af-johannesburg-1', 'za'],
			['me-jeddah-1', 'sa'],
			['il-jerusalem-1', 'il'],
			['ca-montreal-1', 'ca'],
		];
	}

	#[Test]
	#[DataProvider('oracleRegionProvider')]
	public function oracleRegions(string $region, string $expected) : void {
		$this->assertSame($expected, self::callRegionToCountry($region), "Oracle region {$region}");
	}

	#[Test]
	public function globalReturnsNull() : void {
		$this->assertNull(self::callRegionToCountry('GLOBAL'));
		$this->assertNull(self::callRegionToCountry(''));
	}

	#[Test]
	public function unmappedRegionsInLiveData() : void {
		datacentres::$unmapped = [];
		$dc = new datacentres();
		foreach ($dc->compile(\dirname(__DIR__) . '/cache/') AS $item) {
		}

		// anything recorded here is a region string none of the mappings in regionMapping::get() matched
		$unmapped = \array_diff(datacentres::$unmapped, ['', 'global']);

		if ($unmapped) {
			echo "\nUnmapped regions: " . \implode(', ', $unmapped) . "\n";
		}
		$this->assertEmpty($unmapped, \count($unmapped) . ' unmapped region(s) found: ' . \implode(', ', $unmapped));
	}
}
