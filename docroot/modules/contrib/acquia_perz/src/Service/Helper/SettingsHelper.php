<?php

namespace Drupal\acquia_perz\Service\Helper;

use Drupal\Component\Utility\UrlHelper;
use GuzzleHttp\Exception\RequestException;

/**
 * Defines the Settings Helper class.
 */
class SettingsHelper {
  /**
   * Default identity type's default value.
   */
  const DEFAULT_IDENTITY_TYPE_DEFAULT = 'email';

  /**
   * Cdf version's default value.
   */
  const CDF_VERSION_DEFAULT = 1;

  /**
   * Is a valid credential.
   *
   * @param array $credential_settings
   *   Credential settings array.
   * @param string $site_id
   *   Site Id.
   * @param string $assets_url
   *   Assets url.
   *
   * @return bool
   *   True if is a valid credential.
   */
  public static function isValidCredential(array $credential_settings, string $site_id, string $assets_url) {
    $account_id = $credential_settings['account_id'] ?? '';
    $endpoint = $credential_settings['endpoint'] ?? '';
    return self::isValidCredentialAccountId($account_id) &&
      self::isValidCredentialSiteId($site_id) &&
      self::isValidCredentialAssetsUrl($assets_url) &&
      self::isValidCredentialDecisionAPIUrl($endpoint);
  }

  /**
   * Check valid account id.
   *
   * Is a valid credential Account ID. Invalid if:
   *   1) Missing, or
   *   2) Not start with a letter and contain only alphanumerical characters.
   *
   * @param string|null $account_id
   *   Credential Account ID.
   *
   * @return bool
   *   True if is a valid credential Account ID.
   */
  public static function isValidCredentialAccountId(?string $account_id) {
    return !empty($account_id) && preg_match('/^[a-zA-Z_][a-zA-Z\\d_]*$/', $account_id);
  }

  /**
   * Check valid site id.
   *
   * Is a valid Site ID. Invalid if:
   *   1) Missing, or
   *   2) contain characters other than letters, numbers,
   *   underscores and dashes.
   *
   * @param string|null $site_id
   *   Credential Site ID.
   *
   * @return bool
   *   True if is a valid credential Site ID.
   */
  public static function isValidCredentialSiteId(?string $site_id) {
    return !empty($site_id) && !preg_match('@[^A-Za-z0-9_-]+@', $site_id);
  }

  /**
   * Check valid assets url.
   *
   * Is a valid credential Assets URL. Invalid if:
   *   1) Missing, or
   *   2) Not a valid URL.
   *
   * @param string|null $assets_url
   *   Credential Assets URL.
   *
   * @return bool
   *   True if is a valid credential Assets URL.
   */
  public static function isValidCredentialAssetsUrl(?string $assets_url) {
    return !empty($assets_url) && UrlHelper::isValid($assets_url);
  }

  /**
   * Check valid decision api url.
   *
   * Is a valid credential Decision API URL. Invalid if:
   *   1) missing, and
   *   2) Not a valid URL.
   *
   * @param string|null $decision_api_url
   *   Credential Decision API URL.
   *
   * @return bool
   *   True if is a valid credential Decision API URL.
   */
  // phpcs:ignore
  public static function isValidCredentialDecisionAPIUrl(?string $decision_api_url) {
    return !empty($decision_api_url) && UrlHelper::isValid($decision_api_url);
  }

  /**
   * Is a valid bootstrap mode.
   *
   * @param string $test_mode
   *   Mode to compare.
   *
   * @return bool
   *   True if valid, false otherwise.
   */
  public static function isValidBootstrapMode(string $test_mode) {
    $valid_modes = ['auto', 'manual'];
    return in_array($test_mode, $valid_modes);
  }

  /**
   * Is a valid content replacement mode.
   *
   * @param string $test_mode
   *   Mode to compare.
   *
   * @return bool
   *   True if valid, false otherwise.
   */
  public static function isValidContentReplacementMode(string $test_mode) {
    $valid_modes = ['trusted', 'customized'];
    return in_array($test_mode, $valid_modes);
  }

  /**
   * Is a valid cdf version.
   *
   * @param string $version
   *   Version to compare.
   *
   * @return bool
   *   True if valid, false otherwise.
   */
  public static function isValidCdfVersion(string $version) {
    $valid_versions = [1, 2];
    return in_array($version, $valid_versions);
  }

  /**
   * Returns the list of UDFs that can be mapped to.
   *
   * @param string $type
   *   The type of UDF field. Can be person, touch or event.
   *
   * @return int
   *   An array of possible UDF metatag values for the given type.
   *
   * @throws \Exception
   *   An exception if the type given is not supported.
   */
  public static function getUdfLimitsForType(string $type = "person") {
    if ($type !== 'person' && $type !== 'touch' && $type !== 'event') {
      throw new \Exception('This UDF Field type is not supported.');
    }
    $counts = [
      'person' => 50,
      'touch' => 20,
      'event' => 50,
    ];
    return $counts[$type];
  }

  /**
   * Ping URI.
   *
   * @param string $base_uri
   *   Base URI.
   * @param string $path
   *   Path to "ping" end point.
   *
   * @return array
   *   Returns 'statusCode' and 'reasonPhrase' of the response.
   */
  public static function pingUri($base_uri, $path) {
    /** @var \Drupal\Core\Http\ClientFactory $clientFactory */
    $clientFactory = \Drupal::service('http_client_factory');
    $client = $clientFactory->fromOptions(['base_uri' => $base_uri]);

    try {
      $response = $client->get($path, ['http_errors' => FALSE]);
    }
    catch (RequestException $e) {
      return [];
    }

    return [
      'statusCode' => $response->getStatusCode(),
      'reasonPhrase' => $response->getReasonPhrase(),
    ];
  }

}
