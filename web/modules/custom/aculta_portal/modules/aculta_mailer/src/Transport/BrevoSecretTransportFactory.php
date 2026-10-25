<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Transport;

use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoApiTransport;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Fábrica do transporte Brevo (API), com a chave lida do arquivo de credenciais do servidor.
 *
 * A DSN fica sem segredo (`aculta-brevo://default`), então a configuração do transporte pode ser exportada. A chave
 * vem de `BREVO_API_KEY` (ver SecretProviderInterface). Sem chave, o transporte não é criado.
 */
final class BrevoSecretTransportFactory extends AbstractTransportFactory {

  /**
   * Esquema da DSN.
   */
  public const SCHEME = 'aculta-brevo';

  /**
   * Nome da credencial no contrato.
   */
  public const SECRET = 'BREVO_API_KEY';

  public function __construct(
    private readonly ?SecretProviderInterface $secrets,
    ?EventDispatcherInterface $dispatcher = NULL,
    ?HttpClientInterface $client = NULL,
    ?LoggerInterface $logger = NULL,
  ) {
    parent::__construct($dispatcher, $client, $logger);
  }

  /**
   * {@inheritdoc}
   */
  public function create(Dsn $dsn): TransportInterface {
    if ($dsn->getScheme() !== self::SCHEME) {
      throw new UnsupportedSchemeException($dsn, 'aculta-brevo', [self::SCHEME]);
    }
    // Sem o provedor de credenciais (aculta_portal desabilitado), o transporte não é criado.
    $key = $this->secrets?->value(self::SECRET);
    if ($key === NULL) {
      throw new \RuntimeException('Credencial do Brevo não configurada: transporte indisponível.');
    }
    return new BrevoApiTransport($key, $this->client, $this->dispatcher, $this->logger);
  }

  /**
   * {@inheritdoc}
   */
  protected function getSupportedSchemes(): array {
    return [self::SCHEME];
  }

}
