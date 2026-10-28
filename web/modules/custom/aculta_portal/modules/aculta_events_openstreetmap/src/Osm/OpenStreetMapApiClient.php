<?php

declare(strict_types=1);

namespace Drupal\aculta_events_openstreetmap\Osm;

use Psr\Http\Message\ResponseInterface;
use Drupal\aculta_events_openstreetmap\OAuth\OpenStreetMapOAuthException;
use Drupal\aculta_events_openstreetmap\Security\OpenStreetMapCredentialProviderInterface;
use Drupal\aculta_events_openstreetmap\Security\OpenStreetMapEnvironment;
use Drupal\aculta_events_openstreetmap\Security\OpenStreetMapTokenStore;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;

/**
 * Reads selected OSM elements and submits reviewed tag changes.
 *
 * Adaptado de OpenCulturas 3.1.4 (GPL-2.0-or-later),
 * `profile/modules/custom/openculturas_openstreetmap/src/OpenStreetMap/ApiClient.php`.
 */
final class OpenStreetMapApiClient {

  private const TYPES = ['node', 'way', 'relation'];

  private const TAGS = [
    'name',
    'addr:street',
    'addr:housenumber',
    'addr:unit',
    'addr:suburb',
    'addr:city',
    'addr:state',
    'addr:postcode',
    'opening_hours',
  ];

  /**
   * Logger without OSM tokens or response bodies. */
  private readonly LoggerInterface $logger;

  /**
   * Creates an OSM API client with fixed environment endpoints.
   */
  public function __construct(
    private readonly ClientInterface $http,
    private readonly OpenStreetMapEnvironment $environment,
    private readonly OpenStreetMapCredentialProviderInterface $credentials,
    private readonly OpenStreetMapTokenStore $tokens,
    LoggerChannelFactoryInterface $loggerFactory,
  ) {
    $this->logger = $loggerFactory->get('aculta_events_openstreetmap');
  }

  /**
   * Reads an OSM element and normalizes its address tags.
   *
   * @return array{type: string, id: string, version: string, lat: float, lon: float,
   *   tags: array<string, string>}
   *   The selected element and its editable tags.
   */
  public function find(string $type, string $id): array {
    $this->validateIdentity($type, $id);
    $base = $this->environment->apiBaseUrl();
    if ($base === NULL) {
      throw new OpenStreetMapOAuthException('OpenStreetMap API is unavailable for this environment.');
    }
    $suffix = in_array($type, ['way', 'relation'], TRUE) ? '/full' : '';
    try {
      $response = $this->http->request('GET', $base . '/' . $type . '/' . $id . $suffix, [
        'headers' => [
          'Accept' => 'application/xml',
          'User-Agent' => 'ACULTA Events OpenStreetMap/0.1.0',
        ],
        'connect_timeout' => 5,
        'timeout' => 15,
        'http_errors' => FALSE,
        'allow_redirects' => FALSE,
      ]);
      if ($response->getStatusCode() !== 200) {
        throw new OpenStreetMapOAuthException('The selected OpenStreetMap element could not be read.');
      }
      return $this->parseElement((string) $response->getBody(), $type, $id);
    }
    catch (OpenStreetMapOAuthException $exception) {
      throw $exception;
    }
    catch (\Throwable $exception) {
      $this->logger->warning('OpenStreetMap read failed: @class.', ['@class' => $exception::class]);
      throw new OpenStreetMapOAuthException('The selected OpenStreetMap element could not be read.');
    }
  }

  /**
   * Submits reviewed tags after rechecking the selected element version.
   *
   * @param array{type: string, id: string, version: string} $element
   *   Element identity and version returned by find().
   * @param array<string, string> $changes
   *   Reviewed tag values. Empty values remove only the corresponding tag.
   * @param string $comment
   *   Required user-authored changeset comment.
   *
   * @return array{changeset: string, version: string}
   *   OSM changeset and new element version.
   */
  public function update(array $element, array $changes, string $comment): array {
    $type = $element['type'] ?? '';
    $id = $element['id'] ?? '';
    $version = $element['version'] ?? '';
    $this->validateIdentity($type, $id);
    if (!preg_match('/^[1-9][0-9]*$/', $version) || trim($comment) === '' || mb_strlen($comment) > 255
      || $this->environment->environment() === NULL || !$this->credentials->clientId((string) $this->environment->environment())
      || !$this->credentials->clientSecret((string) $this->environment->environment())) {
      throw new OpenStreetMapOAuthException('OpenStreetMap editing is unavailable or the changeset comment is invalid.');
    }
    $token = $this->tokens->load();
    if ($token === NULL) {
      throw new OpenStreetMapOAuthException('Connect OpenStreetMap before sending changes.');
    }
    foreach ($changes as $key => $value) {
      if (!in_array($key, self::TAGS, TRUE) || !is_string($value) || mb_strlen($value) > 255 || preg_match('/[\x00-\x1F\x7F]/u', $value)) {
        throw new OpenStreetMapOAuthException('The reviewed OpenStreetMap data is invalid.');
      }
    }

    $current = $this->find($type, $id);
    if ($current['version'] !== $version) {
      throw new OpenStreetMapOAuthException('The OpenStreetMap element changed during review. Find it again before sending.');
    }
    $changeset = $this->createChangeset($token, trim($comment));
    try {
      $newVersion = $this->putElement($token, $current, $changeset, $changes);
      return ['changeset' => $changeset, 'version' => $newVersion];
    }
    finally {
      $this->closeChangeset($token, $changeset);
    }
  }

  /**
   * Parses only the expected element and safe OSM tags from XML. */
  private function parseElement(string $body, string $type, string $id): array {
    if ($body === '' || strlen($body) > 2 * 1024 * 1024 || str_contains($body, '<!DOCTYPE')) {
      throw new OpenStreetMapOAuthException('The OpenStreetMap response was invalid.');
    }
    $document = new \DOMDocument();
    $document->resolveExternals = FALSE;
    $document->substituteEntities = FALSE;
    if (!@$document->loadXML($body, LIBXML_NONET | LIBXML_NOBLANKS)) {
      throw new OpenStreetMapOAuthException('The OpenStreetMap response was invalid.');
    }
    $nodes = (new \DOMXPath($document))->query('/*[local-name()="osm"]/*[local-name()="' . $type . '"][@id="' . $id . '"]');
    $element = $nodes?->item(0);
    if (!$element instanceof \DOMElement || !$element->hasAttribute('version')) {
      throw new OpenStreetMapOAuthException('The OpenStreetMap element was not found in its response.');
    }
    $tags = [];
    foreach ($element->childNodes as $child) {
      if ($child instanceof \DOMElement && $child->tagName === 'tag' && in_array($child->getAttribute('k'), self::TAGS, TRUE)) {
        $tags[$child->getAttribute('k')] = $child->getAttribute('v');
      }
    }
    $coordinates = $this->coordinates($document, $element, $type);
    return [
      'type' => $type,
      'id' => $id,
      'version' => $element->getAttribute('version'),
      'lat' => $coordinates[0],
      'lon' => $coordinates[1],
      'tags' => $tags,
    ];
  }

  /**
   * Gets a node coordinate or an arithmetic center for a way/relation. */
  private function coordinates(\DOMDocument $document, \DOMElement $element, string $type): array {
    if ($type === 'node') {
      $lat = $element->getAttribute('lat');
      $lon = $element->getAttribute('lon');
      if (!is_numeric($lat) || !is_numeric($lon)) {
        throw new OpenStreetMapOAuthException('The OpenStreetMap element has no valid coordinates.');
      }
      $latitude = (float) $lat;
      $longitude = (float) $lon;
      if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
        throw new OpenStreetMapOAuthException('The OpenStreetMap element has no valid coordinates.');
      }
      return [$latitude, $longitude];
    }
    $nodes = (new \DOMXPath($document))->query('/*[local-name()="osm"]/*[local-name()="node"][@lat and @lon]');
    $latitudes = $longitudes = [];
    foreach ($nodes ?: [] as $node) {
      if ($node instanceof \DOMElement && is_numeric($node->getAttribute('lat')) && is_numeric($node->getAttribute('lon'))) {
        $latitude = (float) $node->getAttribute('lat');
        $longitude = (float) $node->getAttribute('lon');
        if ($latitude >= -90 && $latitude <= 90 && $longitude >= -180 && $longitude <= 180) {
          $latitudes[] = $latitude;
          $longitudes[] = $longitude;
        }
      }
    }
    if ($latitudes === []) {
      throw new OpenStreetMapOAuthException('The OpenStreetMap element has no valid coordinates.');
    }
    return [array_sum($latitudes) / count($latitudes), array_sum($longitudes) / count($longitudes)];
  }

  /**
   * Creates an OSM changeset and returns its validated ID. */
  private function createChangeset(string $token, string $comment): string {
    $document = new \DOMDocument('1.0', 'UTF-8');
    $osm = $document->createElement('osm');
    $osm->setAttribute('version', '0.6');
    $changeset = $document->createElement('changeset');
    foreach (['created_by' => 'ACULTA Events OpenStreetMap/0.1.0', 'comment' => $comment] as $key => $value) {
      $tag = $document->createElement('tag');
      $tag->setAttribute('k', $key);
      $tag->setAttribute('v', $value);
      $changeset->appendChild($tag);
    }
    $osm->appendChild($changeset);
    $document->appendChild($osm);
    $response = $this->write($token, 'PUT', '/changeset/create', $document->saveXML());
    $id = trim((string) $response->getBody());
    if ($response->getStatusCode() !== 200 || !preg_match('/^[1-9][0-9]*$/', $id)) {
      throw new OpenStreetMapOAuthException('OpenStreetMap did not create the changeset.');
    }
    return $id;
  }

  /**
   * Updates tags while preserving element geometry and unrelated tags. */
  private function putElement(string $token, array $current, string $changesetId, array $changes): string {
    $type = $current['type'];
    $id = $current['id'];
    $base = $this->environment->apiBaseUrl();
    if ($base === NULL) {
      throw new OpenStreetMapOAuthException('OpenStreetMap API is unavailable for this environment.');
    }
    $response = $this->http->request('GET', $base . '/' . $type . '/' . $id . (in_array($type, ['way', 'relation'], TRUE) ? '/full' : ''), [
      'headers' => ['Accept' => 'application/xml', 'User-Agent' => 'ACULTA Events OpenStreetMap/0.1.0'],
      'connect_timeout' => 5,
      'timeout' => 15,
      'http_errors' => FALSE,
      'allow_redirects' => FALSE,
    ]);
    if ($response->getStatusCode() !== 200) {
      throw new OpenStreetMapOAuthException('The OpenStreetMap element could not be reloaded.');
    }
    $document = new \DOMDocument();
    $document->resolveExternals = FALSE;
    $document->substituteEntities = FALSE;
    $xml = (string) $response->getBody();
    if (str_contains($xml, '<!DOCTYPE') || !@$document->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS)) {
      throw new OpenStreetMapOAuthException('The OpenStreetMap response was invalid.');
    }
    $elements = (new \DOMXPath($document))->query('/*[local-name()="osm"]/*[local-name()="' . $type . '"][@id="' . $id . '"]');
    $element = $elements?->item(0);
    if (!$element instanceof \DOMElement || $element->getAttribute('version') !== $current['version']) {
      throw new OpenStreetMapOAuthException('The OpenStreetMap element changed during review. Find it again before sending.');
    }
    $element->setAttribute('changeset', $changesetId);
    $tags = [];
    foreach ($element->childNodes as $child) {
      if ($child instanceof \DOMElement && $child->tagName === 'tag') {
        $tags[$child->getAttribute('k')] = $child;
      }
    }
    foreach ($changes as $key => $value) {
      if ($value === '') {
        if (isset($tags[$key])) {
          $element->removeChild($tags[$key]);
        }
        continue;
      }
      if (isset($tags[$key])) {
        $tags[$key]->setAttribute('v', $value);
      }
      else {
        $tag = $document->createElement('tag');
        $tag->setAttribute('k', $key);
        $tag->setAttribute('v', $value);
        $element->appendChild($tag);
      }
    }
    $payload = new \DOMDocument('1.0', 'UTF-8');
    $root = $payload->createElement('osm');
    $root->setAttribute('version', '0.6');
    $root->appendChild($payload->importNode($element, TRUE));
    $payload->appendChild($root);
    $updated = $this->write($token, 'PUT', '/' . $type . '/' . $id, $payload->saveXML());
    $newVersion = trim((string) $updated->getBody());
    if ($updated->getStatusCode() !== 200 || !preg_match('/^[1-9][0-9]*$/', $newVersion)) {
      throw new OpenStreetMapOAuthException('OpenStreetMap did not accept the reviewed changes.');
    }
    return $newVersion;
  }

  /**
   * Closes a created changeset even when element upload fails. */
  private function closeChangeset(string $token, string $id): void {
    try {
      $response = $this->write($token, 'PUT', '/changeset/' . $id . '/close', '');
      if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
        $this->logger->error('OpenStreetMap changeset could not be closed (HTTP @status).', ['@status' => $response->getStatusCode()]);
      }
    }
    catch (\Throwable $exception) {
      $this->logger->error('OpenStreetMap changeset close failed: @class.', ['@class' => $exception::class]);
    }
  }

  /**
   * Sends one authenticated edit request to the fixed active API. */
  private function write(string $token, string $method, string $path, string $body): ResponseInterface {
    $base = $this->environment->apiBaseUrl();
    if ($base === NULL || !str_starts_with($path, '/')) {
      throw new OpenStreetMapOAuthException('OpenStreetMap API is unavailable for this environment.');
    }
    try {
      return $this->http->request($method, $base . $path, [
        'headers' => [
          'Accept' => 'application/xml, text/plain',
          'Authorization' => 'Bearer ' . $token,
          'Content-Type' => 'text/xml; charset=utf-8',
          'User-Agent' => 'ACULTA Events OpenStreetMap/0.1.0',
        ],
        'body' => $body,
        'connect_timeout' => 5,
        'timeout' => 20,
        'http_errors' => FALSE,
        'allow_redirects' => FALSE,
      ]);
    }
    catch (\Throwable $exception) {
      $this->logger->warning('OpenStreetMap edit request failed: @class.', ['@class' => $exception::class]);
      throw new OpenStreetMapOAuthException('OpenStreetMap could not receive the reviewed changes.');
    }
  }

  /**
   * Validates user-controlled element type and ID. */
  private function validateIdentity(string $type, string $id): void {
    if (!in_array($type, self::TYPES, TRUE) || !preg_match('/^[1-9][0-9]{0,18}$/', $id)) {
      throw new OpenStreetMapOAuthException('Choose a valid OpenStreetMap element.');
    }
  }

}
