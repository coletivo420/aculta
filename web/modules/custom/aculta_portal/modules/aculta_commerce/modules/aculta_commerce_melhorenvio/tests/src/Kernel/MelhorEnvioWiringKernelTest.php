<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_commerce_melhorenvio\Kernel;

use Drupal\aculta_commerce_melhorenvio\Http\MelhorEnvioClient;
use Drupal\aculta_commerce_melhorenvio\Http\MelhorEnvioException;
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
    'system',
    'aculta_commerce_melhorenvio',
    'aculta_image',
    'aculta_mail_confirmer',
    'aculta_mail_confirmer_user',
    'aculta_mailer',
    'aculta_pagamentos_mercadopago',
    'aculta_portal',
    'aculta_user',
    'config',
    'aculta_portal_cep',
    'aculta_portal_sitemap',
    'address',
    'block',
    'commerce',
    'commerce_cart',
    'commerce_number_pattern',
    'commerce_order',
    'commerce_payment',
    'commerce_price',
    'commerce_shipping',
    'commerce_store',
    'ctools',
    'ctools_views',
    'datetime',
    'domain',
    'domain_alias',
    'domain_config',
    'domain_source',
    'entity',
    'entity_reference_revisions',
    'entity_usage',
    'field',
    'file',
    'filter',
    'flexible_permissions',
    'group',
    'image',
    'inline_entity_form',
    'key',
    'language',
    'lms',
    'login_emailusername',
    'media',
    'media_duplicates',
    'metatag',
    'node',
    'options',
    'path',
    'path_alias',
    'physical',
    'profile',
    'schema_article',
    'schema_event',
    'schema_image_object',
    'schema_metatag',
    'schema_organization',
    'schema_product',
    'schema_web_page',
    'schema_web_site',
    'simple_sitemap',
    'social_api',
    'social_auth',
    'state_machine',
    'taxonomy',
    'token',
    'user',
    'views',
    'vvj_core',
    'vvjs',
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
    foreach (['aculta_commerce_melhorenvio.oauth_authorize', 'aculta_commerce_melhorenvio.oauth_callback'] as $name) {
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
