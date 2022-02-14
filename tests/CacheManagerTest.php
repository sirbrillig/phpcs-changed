<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/index.php';

use PHPUnit\Framework\TestCase;
use PhpcsChanged\CacheManager;
use PhpcsChangedTests\TestCache;

final class CacheManagerTest extends TestCase {

	public function providePhpcsStandardCacheKeyGenerationData() {
		return [
			'default error and warning severity produces unchanged key' => [
				'standard',
				'5',
				'5',
				'standard'
			],
			'non-default error and warning severity produces changed key' => [
				'standard',
				'1',
				'1',
				'standard:w1e1'
			],
			'empty warning severity key gets replaced by default value of 5' => [
				'standard',
				'',
				'1',
				'standard:w5e1',
			],
			'empty error severity key gets replaced by default value of 5' => [
				'standard',
				'1',
				'',
				'standard:w1e5',
			],
			'empty error and warning severity key returns unchanged standard value' => [
				'standard',
				'',
				'',
				'standard'
			]
		];
	}

	/**
	 * @dataProvider providePhpcsStandardCacheKeyGenerationData
	 */
	public function testPhpcsStandardCacheKeyGeneration( $phpcsStandard, $warningSeverity, $errorSeverity, $expected ) {
		$cache = new CacheManager( new TestCache() );

		$phpcsStandardCacheKey = $cache->getPhpcsStandardCacheKey( $phpcsStandard, $warningSeverity, $errorSeverity );

		$this->assertEquals( $expected, $phpcsStandardCacheKey );
	}
}
