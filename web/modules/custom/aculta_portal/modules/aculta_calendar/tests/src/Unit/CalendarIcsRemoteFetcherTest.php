<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_calendar\Unit;

use Drupal\aculta_calendar\Import\CalendarFileLimitException;
use Drupal\aculta_calendar\Import\CalendarHostResolverInterface;
use Drupal\aculta_calendar\Import\CalendarIcsRemoteFetcher;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/** Tests remote ICS fetching without network requests. */
#[Group('aculta_calendar')]
final class CalendarIcsRemoteFetcherTest extends TestCase {

  public function testTransportSinkStopsAtItsByteLimit(): void {
    $sink = new \Drupal\aculta_calendar\Import\CalendarImportResponseStream(Utils::streamFor(''), 4);

    self::assertSame(0, $sink->write('12345'));
    self::assertTrue($sink->limitExceeded());
    self::assertSame(4, $sink->getSize());
  }

  public function testFetchPinsValidatedAddressAndEnforcesTransportOptions(): void {
    $history = [];
    $handler = HandlerStack::create(new MockHandler([new Response(200, [], "BEGIN:VCALENDAR\r\nEND:VCALENDAR\r\n")]));
    $handler->push(Middleware::history($history));
    $resolver = new class implements CalendarHostResolverInterface {
      public function resolve(string $hostname): array {
        return ['8.8.8.8'];
      }
    };

    $contents = (new CalendarIcsRemoteFetcher(new Client(['handler' => $handler]), $resolver))
      ->fetch('https://calendar.example.invalid/events.ics', ['calendar.example.invalid']);

    self::assertSame("BEGIN:VCALENDAR\r\nEND:VCALENDAR\r\n", $contents);
    self::assertCount(1, $history);
    $options = $history[0]['options'];
    self::assertFalse($options['allow_redirects']);
    self::assertTrue($options['verify']);
    self::assertSame(5, $options['connect_timeout']);
    self::assertSame(15, $options['timeout']);
    self::assertSame(['calendar.example.invalid:443:8.8.8.8'], $options['curl'][CURLOPT_RESOLVE]);
    self::assertSame(CURLPROTO_HTTPS, $options['curl'][CURLOPT_PROTOCOLS]);
    self::assertSame('', $options['curl'][CURLOPT_PROXY]);
    self::assertSame('*', $options['curl'][CURLOPT_NOPROXY]);
  }

  public function testPinsValidatedIpv6Address(): void {
    $history = [];
    $handler = HandlerStack::create(new MockHandler([new Response(200, [], 'ics')]));
    $handler->push(Middleware::history($history));
    $resolver = new class implements CalendarHostResolverInterface {
      public function resolve(string $hostname): array {
        return ['2606:4700:4700::1111'];
      }
    };

    (new CalendarIcsRemoteFetcher(new Client(['handler' => $handler]), $resolver))
      ->fetch('https://calendar.example.invalid/events.ics', ['calendar.example.invalid']);

    self::assertSame(
      ['calendar.example.invalid:443:[2606:4700:4700::1111]'],
      $history[0]['options']['curl'][CURLOPT_RESOLVE],
    );
  }

  public function testRejectsHostOutsideAllowlistBeforeDnsOrHttp(): void {
    $resolver = new class implements CalendarHostResolverInterface {
      public function resolve(string $hostname): array {
        self::fail('DNS should not be called for a host outside the allowlist.');
      }
    };
    $client = $this->createMock(\GuzzleHttp\ClientInterface::class);
    $client->expects(self::never())->method('request');

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('não está na allowlist');
    (new CalendarIcsRemoteFetcher($client, $resolver))
      ->fetch('https://other.example.invalid/events.ics', ['calendar.example.invalid']);
  }

  public function testRejectsWildcardAllowlistEntry(): void {
    $resolver = new class implements CalendarHostResolverInterface {
      public function resolve(string $hostname): array {
        self::fail('DNS should not be called for a wildcard allowlist.');
      }
    };
    $client = $this->createMock(\GuzzleHttp\ClientInterface::class);
    $client->expects(self::never())->method('request');

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('nomes DNS exatos');
    (new CalendarIcsRemoteFetcher($client, $resolver))
      ->fetch('https://calendar.example.invalid/events.ics', ['*.example.invalid']);
  }

  public function testRejectsNonHttpsAndCredentials(): void {
    $resolver = new class implements CalendarHostResolverInterface {
      public function resolve(string $hostname): array {
        self::fail('DNS should not be called for an invalid URL.');
      }
    };
    $client = $this->createMock(\GuzzleHttp\ClientInterface::class);

    foreach (['http://calendar.example.invalid/events.ics', 'https://user@calendar.example.invalid/events.ics'] as $url) {
      try {
        (new CalendarIcsRemoteFetcher($client, $resolver))->fetch($url, ['calendar.example.invalid']);
        self::fail('Invalid URL was accepted.');
      }
      catch (\InvalidArgumentException $exception) {
        self::assertNotSame('', $exception->getMessage());
      }
    }
  }

  public function testRejectsPrivateResolvedAddress(): void {
    $resolver = new class implements CalendarHostResolverInterface {
      public function resolve(string $hostname): array {
        return ['8.8.8.8', '10.20.30.40'];
      }
    };
    $client = $this->createMock(\GuzzleHttp\ClientInterface::class);
    $client->expects(self::never())->method('request');

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('privado, reservado ou não global');
    (new CalendarIcsRemoteFetcher($client, $resolver))
      ->fetch('https://calendar.example.invalid/events.ics', ['calendar.example.invalid']);
  }

  public function testRejectsMulticastAndIpv6TranslationAddresses(): void {
    foreach (['224.0.0.1', 'ff02::1', '64:ff9b::808:808'] as $unsafeAddress) {
      $resolver = new class($unsafeAddress) implements CalendarHostResolverInterface {
        public function __construct(private readonly string $address) {}

        public function resolve(string $hostname): array {
          return [$this->address];
        }
      };
      $client = $this->createMock(\GuzzleHttp\ClientInterface::class);
      $client->expects(self::never())->method('request');
      try {
        (new CalendarIcsRemoteFetcher($client, $resolver))
          ->fetch('https://calendar.example.invalid/events.ics', ['calendar.example.invalid']);
        self::fail('A non-unicast address was accepted.');
      }
      catch (\InvalidArgumentException $exception) {
        self::assertStringContainsString('não global', $exception->getMessage());
      }
    }
  }

  public function testRejectsRedirectInsteadOfFollowingIt(): void {
    $handler = HandlerStack::create(new MockHandler([new Response(302, ['Location' => 'https://other.example.invalid/evil.ics'])]));
    $resolver = $this->publicResolver();
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('status HTTP inesperado');
    (new CalendarIcsRemoteFetcher(new Client(['handler' => $handler]), $resolver))
      ->fetch('https://calendar.example.invalid/events.ics', ['calendar.example.invalid']);
  }

  public function testRejectsResponseOverTwoMiB(): void {
    $handler = HandlerStack::create(new MockHandler([
      new Response(200, ['Content-Length' => (string) (CalendarIcsRemoteFetcher::MAX_FILE_BYTES + 1)], 'x'),
    ]));
    $this->expectException(CalendarFileLimitException::class);
    $this->expectExceptionMessage('Arquivo ICS remoto excede o limite de 2 MB.');
    (new CalendarIcsRemoteFetcher(new Client(['handler' => $handler]), $this->publicResolver()))
      ->fetch('https://calendar.example.invalid/events.ics', ['calendar.example.invalid']);
  }

  public function testEnforcesTwoMiBWhenContentLengthIsMissing(): void {
    $handler = HandlerStack::create(new MockHandler([
      new Response(200, [], str_repeat('x', CalendarIcsRemoteFetcher::MAX_FILE_BYTES + 1)),
    ]));
    $this->expectException(CalendarFileLimitException::class);
    $this->expectExceptionMessage('Arquivo ICS remoto excede o limite de 2 MB.');
    (new CalendarIcsRemoteFetcher(new Client(['handler' => $handler]), $this->publicResolver()))
      ->fetch('https://calendar.example.invalid/events.ics', ['calendar.example.invalid']);
  }

  private function publicResolver(): CalendarHostResolverInterface {
    return new class implements CalendarHostResolverInterface {
      public function resolve(string $hostname): array {
        return ['8.8.8.8'];
      }
    };
  }

}
