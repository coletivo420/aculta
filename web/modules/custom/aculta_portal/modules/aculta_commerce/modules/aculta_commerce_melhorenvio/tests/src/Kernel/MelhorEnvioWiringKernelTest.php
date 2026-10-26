<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_commerce_melhorenvio\Kernel;

use Drupal\aculta_commerce_melhorenvio\Http\MelhorEnvioClient;
use Drupal\aculta_commerce_melhorenvio\Http\MelhorEnvioEnvironment;
use Drupal\aculta_commerce_melhorenvio\Http\MelhorEnvioException;
use Drupal\aculta_commerce_melhorenvio\Quote\QuotePackage;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\key\Entity\Key;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Yaml\Yaml;

/**
 * Cabeamento do cliente OAuth2 no container real: credenciais do Drupal Key, configuração e tokens em State.
 *
 * Sem rede. Prova que a URL de autorização usa o client_id do Key no sandbox, que o secret não vaza para a URL, e que
 * sem token a requisição falha como unauthenticated antes de qualquer envio.
 */
#[Group('aculta_commerce_melhorenvio')]
#[RunTestsInSeparateProcesses]
final class MelhorEnvioWiringKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'aculta_commerce',
    'aculta_commerce_melhorenvio',
    'aculta_document_viewer',
    'aculta_forum',
    'aculta_gallery',
    'aculta_image',
    'aculta_mail_confirmer',
    'aculta_mail_confirmer_user',
    'aculta_mailer',
    'aculta_messenger',
    'aculta_pagamentos_mercadopago',
    'aculta_pdf_viewer',
    'aculta_portal',
    'aculta_portal_cep',
    'aculta_studio',
    'aculta_user',
    'address',
    'block',
    'ckeditor5',
    'comment',
    'commerce',
    'commerce_cart',
    'commerce_checkout',
    'commerce_number_pattern',
    'commerce_order',
    'commerce_payment',
    'commerce_price',
    'commerce_product',
    'commerce_store',
    'content_moderation',
    'csp',
    'ctools',
    'ctools_views',
    'datetime',
    'datetime_range',
    'diff',
    'domain',
    'domain_alias',
    'domain_config',
    'domain_source',
    'editor',
    'embed',
    'entity',
    'entity_embed',
    'entity_reference_revisions',
    'entity_usage',
    'field',
    'file',
    'filter',
    'flag',
    'flexible_permissions',
    'group',
    'history',
    'image',
    'inline_entity_form',
    'key',
    'language',
    'link',
    'lms',
    'login_emailusername',
    'media',
    'media_duplicates',
    'media_gallery',
    'media_library',
    'menu_link_content',
    'menu_ui',
    'message',
    'message_notify',
    'message_subscribe',
    'metatag',
    'migrate',
    'node',
    'options',
    'path',
    'path_alias',
    'pathauto',
    'photoswipe',
    'physical',
    'private_message',
    'profile',
    'reporting',
    'schema_article',
    'schema_event',
    'schema_image_object',
    'schema_metatag',
    'schema_organization',
    'schema_product',
    'schema_web_page',
    'schema_web_site',
    'search_api',
    'search_api_db',
    'site_alert',
    'social_api',
    'social_auth',
    'state_machine',
    'system',
    'taxonomy',
    'text',
    'token',
    'user',
    'views',
    'views_bulk_operations',
    'vvj_core',
    'vvjs',
    'workflows',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['key', 'aculta_commerce_melhorenvio']);
    Key::create([
      'id' => 'me_sb_id',
      'label' => 'Melhor Envio sandbox client_id',
      'key_type' => 'authentication',
      'key_provider' => 'config',
      'key_provider_settings' => ['key_value' => 'cid-sandbox-fixture'],
    ])->save();
    Key::create([
      'id' => 'me_sb_secret',
      'label' => 'Melhor Envio sandbox client_secret',
      'key_type' => 'authentication',
      'key_provider' => 'config',
      'key_provider_settings' => ['key_value' => 'SECRET-SANDBOX-FIXTURE'],
    ])->save();
    $this->config('aculta_commerce_melhorenvio.settings')
      ->set('environment', 'sandbox')
      ->set('redirect_uri', 'https://aculta.example.test/melhorenvio/callback')
      ->set('contact_email', 'suporte@example.test')
      ->set('keys.sandbox.client_id', 'me_sb_id')
      ->set('keys.sandbox.client_secret', 'me_sb_secret')
      ->save();
  }

  /**
   * A URL de autorização usa o client_id do Key, no sandbox.
   */
  public function testAuthorizationUrlUsesKeyCredentialsOnSandbox(): void {
    $client = $this->container->get('aculta_commerce_melhorenvio.client');
    self::assertInstanceOf(MelhorEnvioClient::class, $client);
    $url = $client->authorizationUrl('estado-teste');
    self::assertStringStartsWith('https://sandbox.melhorenvio.com.br/oauth/authorize?', $url);
    self::assertStringContainsString('client_id=cid-sandbox-fixture', $url);
    self::assertStringContainsString('state=estado-teste', $url);
  }

  /**
   * O client_secret nunca aparece na URL de autorização.
   */
  public function testSecretNeverLeaksIntoAuthorizationUrl(): void {
    $url = $this->container->get('aculta_commerce_melhorenvio.client')->authorizationUrl('s');
    self::assertStringNotContainsString('SECRET-SANDBOX-FIXTURE', $url);
  }

  /**
   * Autorização e retorno ficam fora do cache de página e do cache dinâmico.
   */
  public function testOauthRoutesAreNeverCached(): void {
    $path = $this->container->get('extension.list.module')->getPath('aculta_commerce_melhorenvio');
    $routes = Yaml::parseFile(DRUPAL_ROOT . '/' . $path . '/aculta_commerce_melhorenvio.routing.yml');
    $oauthRoutes = [
      'aculta_commerce_melhorenvio.status',
      'aculta_commerce_melhorenvio.oauth_authorize',
      'aculta_commerce_melhorenvio.oauth_callback',
    ];
    foreach ($oauthRoutes as $name) {
      self::assertTrue((bool) ($routes[$name]['options']['no_cache'] ?? FALSE), $name);
    }
  }

  /**
   * Cada acesso a autorizar gera um state novo, e o redirecionamento não pode ser cacheado.
   *
   * Regressão: o cache dinâmico guardava o primeiro redirecionamento e servia o state já consumido.
   */
  public function testEachAuthorizeIssuesFreshUncacheableRedirect(): void {
    $controller = $this->container->get('aculta_commerce_melhorenvio.oauth_controller');
    $states = [];
    foreach ([1, 2] as $attempt) {
      $request = Request::create('/integracoes/melhorenvio/autorizar');
      $request->setSession(new Session(new MockArraySessionStorage()));
      $response = $controller->authorize($request);
      self::assertInstanceOf(TrustedRedirectResponse::class, $response, 'Tentativa ' . $attempt);
      self::assertSame(0, $response->getCacheableMetadata()->getCacheMaxAge(), 'Tentativa ' . $attempt);
      self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
      parse_str((string) parse_url($response->getTargetUrl(), PHP_URL_QUERY), $query);
      self::assertSame($request->getSession()->get('aculta_commerce_melhorenvio.oauth_state'), $query['state']);
      $states[] = $query['state'];
    }
    self::assertNotSame($states[0], $states[1]);
  }

  /**
   * Retorno com state que não está pendente: mensagem de erro do Drupal e página padrão de popup, sem troca de código.
   */
  public function testCallbackWithUnknownStateUsesPopupReturnAndMessage(): void {
    $this->installEntitySchema('path_alias');
    $this->container->get('router.builder')->rebuild();
    $request = Request::create(
      'https://aculta.example.test/integracoes/melhorenvio/retorno',
      'GET',
      ['state' => 'antigo', 'code' => 'x'],
    );
    $request->setSession(new Session(new MockArraySessionStorage()));
    $response = $this->container->get('aculta_commerce_melhorenvio.oauth_controller')->callback($request);
    self::assertSame(200, $response->getStatusCode());
    self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    $html = (string) $response->getContent();
    self::assertStringContainsString('window.opener.location.href = redirect', $html);
    self::assertStringContainsString('/admin/commerce/config/melhorenvio', $html);
    $errors = $this->container->get('messenger')->messagesByType('error');
    self::assertCount(1, $errors);
    self::assertNull($this->container->get('aculta_commerce_melhorenvio.token_store')->load(MelhorEnvioEnvironment::Sandbox));
  }

  /**
   * A página de estado mostra o ambiente, a conexão e o botão que abre a autorização em popup.
   */
  public function testStatusPageOffersPopupConnect(): void {
    $this->installEntitySchema('path_alias');
    $this->container->get('router.builder')->rebuild();
    $build = $this->container->get('aculta_commerce_melhorenvio.status_controller')->page();
    $html = (string) $this->container->get('renderer')->renderInIsolation($build);
    self::assertStringContainsString('Sandbox (testes)', $html);
    self::assertStringContainsString('Não conectada', $html);
    self::assertStringContainsString('data-aculta-popup="melhorenvio_autorizacao"', $html);
    self::assertStringContainsString('data-portal-ui="button-primary"', $html);
    self::assertStringContainsString('/integracoes/melhorenvio/autorizar', $html);
    self::assertSame(0, $build['#cache']['max-age']);
  }

  /**
   * O método de envio do Melhor Envio é descoberto pelo motor ACULTA, com os serviços confirmados.
   */
  public function testShippingMethodPluginIsDiscovered(): void {
    $definition = $this->container->get('plugin.manager.aculta_shipping_method')->getDefinition('aculta_melhorenvio');
    self::assertSame(['1', '2', '3', '4', '17'], array_map('strval', array_keys($definition['services'])));
  }

  /**
   * Cotação de ponta a ponta com HTTP simulado pela resposta real do sandbox; endereço inválido não chama a API.
   */
  public function testQuoterEndToEndWithSandboxFixture(): void {
    $history = [];
    $mock = new MockHandler([
      new GuzzleResponse(200, ['Content-Type' => 'application/json'], (string) file_get_contents(dirname(__DIR__, 2) . '/fixtures/quote-sandbox.json')),
    ]);
    $stack = HandlerStack::create($mock);
    $stack->push(Middleware::history($history));
    $this->container->set('http_client', new GuzzleClient(['handler' => $stack]));
    $this->container->get('aculta_commerce_melhorenvio.token_store')->save(MelhorEnvioEnvironment::Sandbox, [
      'access_token' => 'token-fixture',
      'refresh_token' => 'refresh-fixture',
      'expires_at' => time() + 3600,
    ]);
    $this->config('aculta_commerce_melhorenvio.settings')
      ->set('origin.country_code', 'BR')
      ->set('origin.postal_code', '74003-010')
      ->save();

    $quoter = $this->container->get('aculta_commerce_melhorenvio.quoter');
    $package = new QuotePackage(16, 12, 24, 0.8, 120);

    self::assertSame([], $quoter->quote(['country_code' => 'BR', 'postal_code' => '123'], $package));
    self::assertCount(0, $history);

    $options = $quoter->quote(['country_code' => 'BR', 'postal_code' => '01310-100'], $package);
    self::assertSame(['1', '2'], array_map(static fn($o) => $o->serviceId, $options));
    self::assertCount(1, $history);
    $request = $history[0]['request'];
    self::assertSame('POST', $request->getMethod());
    self::assertSame('/api/v2/me/shipment/calculate', $request->getUri()->getPath());
    self::assertSame('Bearer token-fixture', $request->getHeaderLine('Authorization'));
    $body = json_decode((string) $request->getBody(), TRUE);
    self::assertSame('74003010', $body['from']['postal_code']);
    self::assertSame('01310100', $body['to']['postal_code']);
    self::assertSame(120.0, (float) $body['products'][0]['insurance_value']);
  }

  /**
   * Sem token armazenado, a requisição autenticada falha como unauthenticated, sem chegar à rede.
   */
  public function testRequestWithoutTokenIsUnauthenticated(): void {
    $client = $this->container->get('aculta_commerce_melhorenvio.client');
    try {
      $client->request('GET', '/api/v2/me/teste', NULL, TRUE);
      self::fail('Esperada exceção de autenticação.');
    }
    catch (MelhorEnvioException $e) {
      self::assertSame('unauthenticated', $e->category());
    }
  }

}
