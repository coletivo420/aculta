<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Kernel;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_portal\Hook\CartProductLinkHooks;
use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Url;
use Drupal\domain\Entity\Domain;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Covers product links rendered from Commerce cart line items.
 */
#[Group('aculta_portal')]
#[RunTestsInSeparateProcesses]
final class CartProductLinkHooksKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'domain'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('domain');
  }

  /**
   * Rewrites the cart product link to the configured SHOP domain.
   */
  public function testCartProductLinkUsesShopDomain(): void {
    Domain::create([
      'id' => 'loja_aculta_org',
      'hostname' => 'loja.example.invalid',
      'name' => 'Loja',
      'scheme' => 'https',
      'weight' => 0,
      'status' => TRUE,
    ])->save();
    $manager = new DomainPurposeManager(
      $this->container->get('entity_type.manager'),
      $this->container->get(DomainNegotiatorInterface::class),
      $this->container->get('config.factory'),
      $this->container->get('request_stack'),
    );

    $product = $this->createMock(ProductInterface::class);
    $product->method('id')->willReturn('42');
    $product->method('label')->willReturn('Produto de teste');
    $variation = $this->createMock(ProductVariationInterface::class);
    $variation->method('getProduct')->willReturn($product);
    $display = $this->createMock(EntityViewDisplayInterface::class);
    $display->method('getMode')->willReturn('cart');
    $build = [
      'product_id' => [
        0 => [
          '#type' => 'link',
          '#title' => 'Produto de teste',
          '#url' => Url::fromRoute('<front>'),
          '#options' => [],
        ],
      ],
      '#cache' => ['contexts' => []],
    ];

    (new CartProductLinkHooks($manager))->entityViewAlter($build, $variation, $display);

    $url = $build['product_id'][0]['#url'];
    $this->assertSame('entity.commerce_product.canonical', $url->getRouteName());
    $this->assertSame(['commerce_product' => '42'], $url->getRouteParameters());
    $this->assertSame('loja_aculta_org', $url->getOptions()['domain']->id());
    $this->assertContains('domain', $build['#cache']['contexts']);
  }

}
