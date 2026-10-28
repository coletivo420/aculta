<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit\EventSubscriber;

use Drupal\aculta_portal\Account\EmailConfirmationPolicy;
use Drupal\aculta_portal\Email\EmailConfirmationRequester;
use Drupal\aculta_portal\EventSubscriber\SimplifiedAccountCheckoutSubscriber;
use Drupal\aculta_portal\Form\SimplifiedAccountSettingsForm;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Event\OrderEvent;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserDataInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Painel do cadastro simplificado: desligado, a conclusão do checkout não pede confirmação.
 */
#[CoversClass(SimplifiedAccountCheckoutSubscriber::class)]
#[Group('aculta_portal')]
final class SimplifiedAccountCheckoutSubscriberTest extends UnitTestCase {

  /**
   * Com o cadastro simplificado desligado, nenhum e-mail de confirmação é pedido.
   */
  public function testDisabledSettingSkipsConfirmation(): void {
    $requester = $this->createMock(EmailConfirmationRequester::class);
    $requester->expects($this->never())->method('request');
    $config = $this->createMock(Config::class);
    $config->method('get')->willReturnMap([['enabled', FALSE]]);
    $configs = $this->createMock(ConfigFactoryInterface::class);
    $configs->method('get')->with(SimplifiedAccountSettingsForm::CONFIG)->willReturn($config);
    $policy = new EmailConfirmationPolicy(
      $this->createMock(UserDataInterface::class),
      $this->createMock(\Drupal\Core\Entity\EntityTypeManagerInterface::class),
      $this->createMock(\Drupal\Core\Extension\ModuleHandlerInterface::class),
    );
    $subscriber = new SimplifiedAccountCheckoutSubscriber($requester, $policy, $this->createMock(UserDataInterface::class), $configs);

    $order = $this->createMock(OrderInterface::class);
    $order->expects($this->never())->method('getCustomer');
    $subscriber->onCompletion(new OrderEvent($order));
  }

}
