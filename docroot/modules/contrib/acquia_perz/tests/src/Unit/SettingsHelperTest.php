<?php

namespace Drupal\Tests\acquia_perz\Unit;

use Drupal\acquia_perz\Service\Helper\SettingsHelper;
use Drupal\Tests\UnitTestCase;

/**
 * SettingsHelper Test.
 *
 * @coversDefaultClass Drupal\acquia_perz\Service\Helper\SettingsHelper
 * @group acquia_perz
 */
class SettingsHelperTest extends UnitTestCase {

  /**
   * Tests the isValidCredentialAccountId() method.
   *
   * @param string|null $account_id
   *   Account ID.
   * @param bool $expected
   *   Expected Result.
   *
   * @covers ::isValidCredentialAccountId
   * @dataProvider providerTestIsValidCredentialAccountId
   */
  public function testIsValidCredentialAccountId(?string $account_id, bool $expected) {
    $result = SettingsHelper::isValidCredentialAccountId($account_id);
    $this->assertEquals($expected, $result);
  }

  /**
   * Data provider for testIsValidCredentialAccountId().
   */
  public function providerTestIsValidCredentialAccountId() {
    $data = [];

    $data['invalid null'] = [NULL, FALSE];
    $data['invalid empty'] = ['', FALSE];
    $data['invalid start with number'] = ['1a', FALSE];
    $data['invalid has "~" sign'] = ['a~', FALSE];
    $data['valid has "_" sign'] = ['a_', TRUE];
    $data['valid start with alphabetic then alphanumeric'] = ['a123', TRUE];

    return $data;
  }

  /**
   * Tests the isValidCredentialSiteId() method.
   *
   * @param string|null $site_id
   *   Site ID.
   * @param bool $expected
   *   Expected Result.
   *
   * @covers ::isValidCredentialSiteId
   * @dataProvider providerTestIsValidCredentialSiteId
   */
  public function testIsValidCredentialSiteId(?string $site_id, bool $expected) {
    $result = SettingsHelper::isValidCredentialSiteId($site_id);
    $this->assertEquals($expected, $result);
  }

  /**
   * Data provider for testIsValidCredentialSiteId().
   */
  public function providerTestIsValidCredentialSiteId() {
    $data = [];

    $data['invalid null'] = [NULL, FALSE];
    $data['invalid empty'] = ['', FALSE];
    $data['invalid has space'] = ['a bc', FALSE];
    $data['invalid has special characters'] = ['abc-#efg', FALSE];
    $data['valid alphanumeric 1'] = ['a123', TRUE];
    $data['valid alphanumeric 2'] = ['3ab', TRUE];
    $data['valid alphanumeric with _'] = ['abb_def', TRUE];
    $data['valid has capital letters'] = ['Abc', TRUE];
    $data['valid has dashes'] = ['Abc-123', TRUE];
    $data['valid has letters, numbers, underscores and dashes'] = ['Ab_c1-23', TRUE];

    return $data;
  }

  /**
   * Tests the isValidCredentialAssetsUrl() method.
   *
   * @param string|null $assets_url
   *   Assets Url.
   * @param bool $expected
   *   Expected Result.
   *
   * @covers ::isValidCredentialAssetsUrl
   * @dataProvider providerTestIsValidCredentialAssetsUrl
   */
  public function testIsValidCredentialAssetsUrl(?string $assets_url, bool $expected) {
    $result = SettingsHelper::isValidCredentialAssetsUrl($assets_url);
    $this->assertEquals($expected, $result);
  }

  /**
   * Data provider for testIsValidCredentialAssetsUrl().
   */
  public function providerTestIsValidCredentialAssetsUrl() {
    $data = [];

    $data['invalid null'] = [NULL, FALSE];
    $data['invalid empty'] = ['', FALSE];
    $data['invalid has non-ascii characters'] = ['不合法', FALSE];
    $data['valid url 1'] = ['acquia', TRUE];
    $data['valid url 2'] = ['acquia.com', TRUE];

    return $data;
  }

  /**
   * Tests the isValidCredentialDecisionApiUrl() method.
   *
   * @param string|null $endpoint
   *   Decision API Endpoint.
   * @param bool $expected
   *   Expected Result.
   *
   * @dataProvider providerTestIsValidCredentialDecisionApiUrl
   */
  public function testIsValidCredentialDecisionApiUrl(?string $endpoint, bool $expected) {
    $result = SettingsHelper::isValidCredentialDecisionApiUrl($endpoint);
    $this->assertEquals($expected, $result);
  }

  /**
   * Data provider for testIsValidCredentialDecisionApiUrl().
   */
  public function providerTestIsValidCredentialDecisionApiUrl() {
    $data = [];

    $data['invalid has non-ascii characters'] = ['不合法', FALSE];
    $data['invalid null'] = [NULL, FALSE];
    $data['invalid empty'] = ['', FALSE];
    $data['valid url 1'] = ['acquia', TRUE];
    $data['valid url 2'] = ['acquia.com', TRUE];

    return $data;
  }

  /**
   * Tests the isValidCredential() method.
   *
   * @param array|null $credential_settings
   *   Credential Settings.
   * @param string|null $site_id
   *   Site ID.
   * @param string|null $assets_url
   *   Assets URL.
   * @param bool $expected
   *   Expected Results.
   *
   * @covers ::isValidCredential
   * @dataProvider providerTestIsValidCredential
   */
  public function testIsValidCredential(?array $credential_settings, ?string $site_id, ?string $assets_url, bool $expected) {
    $result = SettingsHelper::isValidCredential($credential_settings, $site_id, $assets_url);
    $this->assertEquals($expected, $result);
  }

  /**
   * Data provider for testIsInvalidCredential().
   */
  public function providerTestIsValidCredential() {
    $data = [];
    // Testing Valid credential settings.
    $valid_credential_settings = [
      'account_id' => 'AccountId1',
      'endpoint' => 'decision_api_url_1',
    ];

    $data['valid credential settings'] = [$valid_credential_settings, 'test_site', 'AssetsUrl1', TRUE];

    // Testing Invalid credential settings.
    $data['invalid site id'] = [$valid_credential_settings, 'test site', 'AssetsUrl1', FALSE];
    $data['invalid assets url'] = [$valid_credential_settings, 'test_site', 'Assets - Url1', FALSE];

    $invalid_credential_settings = [
      'account_id' => '',
      'endpoint' => '',
    ];
    $data['empty account_id'] = [$invalid_credential_settings, 'test_site', 'AssetsUrl1', FALSE];
    $data['empty enpoint'] = [$invalid_credential_settings, 'test_site', 'AssetsUrl1', FALSE];

    $invalid_credential_settings = [
      'account_id' => '1account id#',
      'endpoint' => '#decision_api_url_1/',
    ];
    $data['invalid account_id'] = [$invalid_credential_settings, 'test_site', 'AssetsUrl1', FALSE];
    $data['invalid endpoint'] = [$invalid_credential_settings, 'test_site', 'AssetsUrl1', FALSE];
    $invalid_credential_settings = [];
    $data['missing account_id'] = [$invalid_credential_settings, 'test_site', 'AssetsUrl1', FALSE];
    $data['missing endpoint'] = [$invalid_credential_settings, 'test_site', 'AssetsUrl1', FALSE];
    return $data;
  }

}
