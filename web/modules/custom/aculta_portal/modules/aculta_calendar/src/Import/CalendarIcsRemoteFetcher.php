<?php

declare(strict_types=1);

namespace Drupal\aculta_calendar\Import;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Utils;

/** Fetches a bounded remote ICS document from an explicitly allowlisted host. */
final class CalendarIcsRemoteFetcher {

  public const MAX_FILE_BYTES = IcsCalendarFileReader::MAX_FILE_BYTES;
  public const TIMEOUT_SECONDS = 15;
  public const CONNECT_TIMEOUT_SECONDS = 5;

  public function __construct(
    private readonly ClientInterface $httpClient,
    private readonly CalendarHostResolverInterface $hostResolver,
  ) {}

  /**
   * Downloads one ICS file, with DNS pinning and SSRF protection.
   *
   * @param string[] $allowedHosts
   *   Exact DNS names. Wildcards and IP literals are rejected.
   */
  public function fetch(string $url, array $allowedHosts): string {
    if (!extension_loaded('curl')) {
      throw new \RuntimeException('A importação remota requer o transporte cURL para fixar o endereço IP validado.');
    }
    $curlVersion = curl_version();
    if (!isset($curlVersion['version_number']) || $curlVersion['version_number'] < 0x071503) {
      throw new \RuntimeException('A importação remota requer cURL com suporte a pinagem de DNS.');
    }

    $parts = parse_url($url);
    if (!is_array($parts)
      || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
      || isset($parts['user'])
      || isset($parts['pass'])
      || isset($parts['fragment'])
      || (isset($parts['port']) && $parts['port'] !== 443)
      || !isset($parts['host'])
    ) {
      throw new \InvalidArgumentException('A fonte remota deve ser uma URL HTTPS válida na porta 443, sem credenciais nem fragmento.');
    }

    $host = strtolower((string) $parts['host']);
    if (!$this->isDnsName($host) || filter_var($host, FILTER_VALIDATE_IP) !== FALSE) {
      throw new \InvalidArgumentException('O host da fonte iCalendar deve ser um nome DNS válido, sem curingas nem IP literal.');
    }
    $allowlist = [];
    foreach ($allowedHosts as $allowed) {
      if (!is_string($allowed)) {
        throw new \InvalidArgumentException('A allowlist da fonte contém um host inválido.');
      }
      $allowed = strtolower(trim($allowed));
      if (!$this->isDnsName($allowed) || filter_var($allowed, FILTER_VALIDATE_IP) !== FALSE) {
        throw new \InvalidArgumentException('A allowlist aceita somente nomes DNS exatos, sem curingas ou IPs literais.');
      }
      $allowlist[] = $allowed;
    }
    if (!in_array($host, $allowlist, TRUE)) {
      throw new \InvalidArgumentException('O host da fonte iCalendar não está na allowlist.');
    }

    $addresses = $this->hostResolver->resolve($host);
    if ($addresses === []) {
      throw new \RuntimeException('O host da fonte iCalendar não tem endereços DNS.');
    }
    foreach ($addresses as $address) {
      if (!$this->isPublicUnicastAddress($address)) {
        throw new \InvalidArgumentException('A fonte iCalendar resolve para endereço IP privado, reservado ou não global.');
      }
    }

    // Pin the already validated address into libcurl while preserving the URL
    // hostname for Host, SNI, and certificate verification.
    $pinnedAddress = $addresses[0];
    if (str_contains($pinnedAddress, ':')) {
      $pinnedAddress = '[' . $pinnedAddress . ']';
    }
    $sink = new CalendarImportResponseStream(
      Utils::streamFor(fopen('php://temp', 'w+')),
      self::MAX_FILE_BYTES,
    );
    try {
      $response = $this->httpClient->request('GET', $url, [
      'allow_redirects' => FALSE,
      'connect_timeout' => self::CONNECT_TIMEOUT_SECONDS,
      'timeout' => self::TIMEOUT_SECONDS,
      'verify' => TRUE,
      'http_errors' => FALSE,
      'stream' => TRUE,
      'decode_content' => FALSE,
      'sink' => $sink,
      'headers' => [
        'Accept' => 'text/calendar, text/plain;q=0.9',
        'Accept-Encoding' => 'identity',
      ],
      'curl' => [
        CURLOPT_RESOLVE => [$host . ':443:' . $pinnedAddress],
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_PROXY => '',
        CURLOPT_NOPROXY => '*',
      ],
      ]);
    }
    catch (\Throwable $exception) {
      if ($sink->limitExceeded()) {
        throw new CalendarFileLimitException('Arquivo ICS remoto excede o limite de 2 MB.', 0, $exception);
      }
      throw $exception;
    }
    if ($sink->limitExceeded()) {
      throw new CalendarFileLimitException('Arquivo ICS remoto excede o limite de 2 MB.');
    }

    if ($response->getStatusCode() !== 200) {
      throw new \RuntimeException('A fonte iCalendar respondeu com status HTTP inesperado.');
    }
    $contentLength = trim($response->getHeaderLine('Content-Length'));
    if ($contentLength !== '' && ctype_digit($contentLength) && (int) $contentLength > self::MAX_FILE_BYTES) {
      throw new CalendarFileLimitException('Arquivo ICS remoto excede o limite de 2 MB.');
    }

    $body = $response->getBody();
    if ($body->isSeekable()) {
      $body->rewind();
    }
    $contents = '';
    while (!$body->eof()) {
      $chunk = $body->read(8192);
      if ($chunk === '') {
        break;
      }
      $contents .= $chunk;
      if (strlen($contents) > self::MAX_FILE_BYTES) {
        throw new CalendarFileLimitException('Arquivo ICS remoto excede o limite de 2 MB.');
      }
    }
    return $contents;
  }

  /** Validates a plain DNS name without a trailing dot, wildcard, or IP form. */
  private function isDnsName(string $host): bool {
    return str_contains($host, '.')
      && preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)(?:\.(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?))*$/D', $host) === 1;
  }

  /** Rejects non-global, multicast, and translation/transition addresses. */
  private function isPublicUnicastAddress(string $address): bool {
    if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE) === FALSE) {
      return FALSE;
    }
    $packed = inet_pton($address);
    if ($packed === FALSE) {
      return FALSE;
    }
    if (strlen($packed) === 4) {
      return !$this->addressInRange($address, '224.0.0.0/4')
        && !$this->addressInRange($address, '192.88.99.0/24');
    }

    return $this->addressInRange($address, '2000::/3')
      && !$this->addressInRange($address, '2001::/23')
      && !$this->addressInRange($address, '2002::/16')
      && !$this->addressInRange($address, '64:ff9b::/96')
      && !$this->addressInRange($address, '64:ff9b:1::/48')
      && !$this->addressInRange($address, '3fff::/20');
  }

  /** Checks whether an IP address is inside a CIDR range. */
  private function addressInRange(string $address, string $cidr): bool {
    [$network, $prefixText] = explode('/', $cidr, 2);
    $addressBytes = inet_pton($address);
    $networkBytes = inet_pton($network);
    if ($addressBytes === FALSE || $networkBytes === FALSE || strlen($addressBytes) !== strlen($networkBytes)) {
      return FALSE;
    }
    $prefix = (int) $prefixText;
    $wholeBytes = intdiv($prefix, 8);
    $remainingBits = $prefix % 8;
    if (substr($addressBytes, 0, $wholeBytes) !== substr($networkBytes, 0, $wholeBytes)) {
      return FALSE;
    }
    if ($remainingBits === 0) {
      return TRUE;
    }
    $mask = (0xFF << (8 - $remainingBits)) & 0xFF;
    return (ord($addressBytes[$wholeBytes]) & $mask) === (ord($networkBytes[$wholeBytes]) & $mask);
  }

}
