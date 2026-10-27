<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_qrcode\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Regressão: a listagem administrativa de códigos QR renderiza com códigos existentes (antes, retornava 500).
 */
#[Group('aculta_qrcode')]
#[RunTestsInSeparateProcesses]
final class QrCodeListBuilderKernelTest extends KernelTestBase {

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
  }

  /**
   * A lista renderiza com um código existente, com o título como link e os downloads por formato.
   */
  public function testListRendersExistingCode(): void {
    $this->container->get('aculta_qrcode.manager')->create('Cartaz da oficina', 'https://example.invalid/oficina');
    $list = $this->container->get('entity_type.manager')->getListBuilder('aculta_qr_code')->render();
    $html = (string) $this->container->get('renderer')->renderInIsolation($list);
    self::assertStringContainsString('Cartaz da oficina', $html);
    self::assertStringContainsString('codigos-qr/1/baixar/svg', $html);
  }

}
