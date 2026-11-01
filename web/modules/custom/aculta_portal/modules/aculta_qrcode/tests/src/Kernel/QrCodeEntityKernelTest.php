<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_qrcode\Kernel;

use Drupal\aculta_qrcode\Controller\QrCodeRedirectController;
use Drupal\aculta_qrcode\Entity\QrCode;
use Drupal\aculta_qrcode\Exception\InvalidQrCodeException;
use Drupal\aculta_qrcode\Service\QrCodeManager;
use Drupal\aculta_qrcode\Value\QrCodeFormat;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Testa entidade, validade, revisões e redirecionamento do QR dinâmico.
 */
#[Group('aculta_qrcode')]
#[RunTestsInSeparateProcesses]
final class QrCodeEntityKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'datetime',
    'aculta_qrcode',
  ];

  /**
   * Gerenciador sob teste.
   */
  private QrCodeManager $manager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system', 'user']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('aculta_qr_code');
    $administrator = User::create(['name' => 'administrador', 'status' => 1]);
    $administrator->save();
    $this->container->get('current_user')->setAccount($administrator);
    $this->manager = $this->container->get('aculta_qrcode.manager');
  }

  /**
   * Cria um código publicado com código público de 12 caracteres.
   */
  public function testCreatesPublishedCodeWithOpaqueIdentifier(): void {
    $entity = $this->manager->create('Cartaz da oficina', 'https://example.invalid/apoie');

    self::assertInstanceOf(QrCode::class, $entity);
    self::assertTrue($entity->isPublished());
    self::assertMatchesRegularExpression('/^[a-z0-9]{12}$/', $entity->getCode());
    self::assertSame('https://example.invalid/apoie', $entity->getTargetUrl());
    self::assertSame('aculta_padrao', $entity->getPresetId());
  }

  /**
   * Rejeita destinos executáveis ou com credenciais antes de gravar.
   */
  public function testRejectsUnsafeTargetWithoutSaving(): void {
    $this->expectException(InvalidQrCodeException::class);
    try {
      $this->manager->create('Teste', 'javascript:alert(1)');
    }
    finally {
      self::assertSame(0, $this->countCodes());
    }
  }

  /**
   * Rejeita janela de validade em que o fim não é posterior ao início.
   */
  public function testRejectsInvertedValidityWindow(): void {
    $this->expectException(InvalidQrCodeException::class);
    $this->manager->create('Teste', 'https://example.invalid/', NULL, 2000, 1000);
  }

  /**
   * Respeita publicação e janela de validade na busca pública.
   */
  public function testFindActiveRespectsStatusAndWindow(): void {
    $now = 1_700_000_000;
    $entity = $this->manager->create('Janela', 'https://example.invalid/', NULL, $now + 100, $now + 200);

    self::assertTrue($this->manager->findActive($entity->getCode(), $now) === NULL, 'Antes do início.');
    self::assertSame($entity->id(), $this->manager->findActive($entity->getCode(), $now + 150)?->id());
    self::assertTrue($this->manager->findActive($entity->getCode(), $now + 200) === NULL, 'No fim, já expirou.');

    $this->manager->setPublished($entity, FALSE);
    self::assertTrue($this->manager->findActive($entity->getCode(), $now + 150) === NULL, 'Desativado.');
  }

  /**
   * Não aceita formatos de código fora do padrão.
   */
  public function testFindActiveRejectsMalformedCode(): void {
    self::assertNull($this->manager->findActive('CODIGO-ERRADO', 1_700_000_000));
  }

  /**
   * Altera o destino mantendo o código e registra uma nova revisão.
   */
  public function testUpdateTargetKeepsCodeAndCreatesRevision(): void {
    $entity = $this->manager->create('Revisado', 'https://example.invalid/antigo', NULL, NULL, NULL, 'criação');
    $code = $entity->getCode();

    $this->manager->updateTarget($entity, 'https://example.invalid/novo', 'troca de destino');

    $reloaded = $this->storage()->load($entity->id());
    self::assertSame($code, $reloaded->getCode());
    self::assertSame('https://example.invalid/novo', $reloaded->getTargetUrl());
    self::assertCount(2, $this->storage()->getQuery()->accessCheck(FALSE)->allRevisions()->condition('id', $entity->id())->execute());
  }

  /**
   * Redireciona com 302 ao destino quando o código está ativo.
   */
  public function testRedirectsActiveCodeWith302(): void {
    $entity = $this->manager->create('Redirect', 'https://example.invalid/apoie');
    $response = $this->controller()->redirectByCode($entity->getCode());

    self::assertInstanceOf(TrustedRedirectResponse::class, $response);
    self::assertSame(302, $response->getStatusCode());
    self::assertSame('https://example.invalid/apoie', $response->getTargetUrl());
  }

  /**
   * Responde 404 quando o código está desativado ou inexistente.
   */
  public function testInactiveOrUnknownCodeReturnsNotFound(): void {
    $entity = $this->manager->create('Inativo', 'https://example.invalid/');
    $this->manager->setPublished($entity, FALSE);

    $this->expectException(NotFoundHttpException::class);
    $this->controller()->redirectByCode($entity->getCode());
  }

  /**
   * Registra as rotas do painel e o download por formato.
   */
  public function testAdministrativeRoutesAreRegistered(): void {
    $provider = $this->container->get('router.route_provider');
    $expected = [
      'entity.aculta_qr_code.collection' => '/painel-administrativo/aculta/codigos-qr',
      'entity.aculta_qr_code.add_form' => '/painel-administrativo/aculta/codigos-qr/adicionar',
      'entity.aculta_qr_code.canonical' => '/painel-administrativo/aculta/codigos-qr/{aculta_qr_code}',
      'entity.aculta_qr_code.edit_form' => '/painel-administrativo/aculta/codigos-qr/{aculta_qr_code}/editar',
      'aculta_qrcode.download' => '/painel-administrativo/aculta/codigos-qr/{aculta_qr_code}/baixar/{formato}',
    ];
    foreach ($expected as $name => $path) {
      self::assertSame($path, $provider->getRouteByName($name)->getPath(), $name);
    }
  }

  /**
   * Recusa exportação de arquivo sem o aculta_image (falha fechada).
   */
  public function testRasterExportRefusedWithoutAcultaImage(): void {
    $entity = $this->manager->create('Exporta', 'https://example.invalid/');
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('aculta_image indisponível');
    $this->container->get('aculta_qrcode.exporter')->raster($entity, QrCodeFormat::Png);
  }

  /**
   * Sem URL pública (aculta_portal ausente), a exportação não produz arquivo.
   */
  public function testSvgExportNeedsPublicUrl(): void {
    $entity = $this->manager->create('Sem URL', 'https://example.invalid/');
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('URL pública do código QR indisponível');
    $this->container->get('aculta_qrcode.exporter')->svg($entity);
  }

  /**
   * Retorna o controlador do redirecionamento pelo container.
   */
  private function controller(): QrCodeRedirectController {
    return $this->container->get('aculta_qrcode.redirect_controller');
  }

  /**
   * Armazenamento da entidade.
   */
  private function storage(): EntityStorageInterface {
    return $this->container->get('entity_type.manager')->getStorage('aculta_qr_code');
  }

  /**
   * Conta códigos gravados na tabela base.
   */
  private function countCodes(): int {
    return (int) $this->storage()->getQuery()->accessCheck(FALSE)->count()->execute();
  }

}
