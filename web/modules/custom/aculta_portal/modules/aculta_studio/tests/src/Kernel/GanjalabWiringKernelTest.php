<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_studio\Kernel;

use Drupal\aculta_studio\Controller\GanjalabController;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Confirma a ligação do controller do Ganjalab no container com o stack do portal.
 */
#[Group('aculta_studio')]
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
final class GanjalabWiringKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'address',
    'comment',
    'crop',
    'ctools',
    'datetime',
    'domain',
    'domain_alias',
    'domain_config',
    'domain_source',
    'entity_usage',
    'file',
    'group',
    'image',
    'image_widget_crop',
    'key',
    'link',
    'media',
    'metatag',
    'node',
    'options',
    'path',
    'profile',
    'taxonomy',
    'token',
    'user',
    'views',
    'flexible_permissions',
    'text',
    'flag',
    'aculta_file_uploader',
    'aculta_image',
    'aculta_mail_confirmer',
    'aculta_mailer',
    'aculta_portal',
    'aculta_portal_cep',
    'aculta_calendar',
    'aculta_studio',
    'aculta_user',
    'commerce',
    'commerce_cart',
    'commerce_checkout',
    'commerce_order',
    'commerce_payment',
    'commerce_product',
    'commerce_store',
    'webform_score',
    'entity_print',
    'physical',
    'ctools_views',
    'media_duplicates',
    'social_auth',
    'login_emailusername',
    'search_api_db',
    'schema_metatag',
    'schema_article',
    'schema_web_site',
    'schema_web_page',
    'schema_organization',
    'schema_product',
    'schema_image_object',
    'schema_event',
    'vvjs',
    'filter',
    'field',
    'commerce_price',
    'path_alias',
    'webform',
    'webform_score',
    'social_api',
    'language',
  ];

  /**
   * O serviço do controller do Ganjalab é instanciado pelo container.
   */
  public function testGanjalabControllerIsWiredInContainer(): void {
    $this->assertInstanceOf(
      GanjalabController::class,
      $this->container->get('aculta_studio.ganjalab_controller'),
    );
  }

}
