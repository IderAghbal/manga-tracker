<?php

class MangaFox_test extends SiteTestCase {
	public function test_success() {
		$testSeries = [
			'tsugumomo'              => 'Tsugumomo',
			'inari_konkon_koi_iroha' => 'Inari, Konkon, Koi Iroha',
			'futari_no_renai_shoka'  => 'Futari no Renai Shoka',
			'boku_girl'              => 'Boku Girl',
			'sakuranbo_syndrome'     => 'Sakuranbo Syndrome'
		];
		$this->_testSiteSuccessRandom($testSeries);
	}
	public function test_failure() {
		$this->_testSiteFailure('Bad Status Code (302)');
	}
}
