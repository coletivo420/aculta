<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_document_viewer\Kernel;

/**
 * Pre-enables the real module closure without installing Portal config.
 *
 * `aculta_document_viewer` depends on `aculta_portal`, whose services also
 * reference services from `aculta_user` (a circular custom dependency that
 * cannot be declared in .info.yml). Kernel tests boot only the modules listed
 * in `$modules`; enabling the full closure here avoids dangling service
 * references and lets the module under test be installed at runtime without
 * installing unrelated Portal configuration.
 *
 * The module under test is intentionally excluded so `module_installer` can
 * install it and its config during the test.
 */
trait DocumentViewerKernelClosureTrait {

  /**
   * Transitive dependencies of aculta_document_viewer plus aculta_user.
   */
  protected const DOCUMENT_VIEWER_KERNEL_CLOSURE = [
    'aculta_portal',
    'ctools',
    'ctools_views',
    'block',
    'views',
    'filter',
    'user',
    'system',
    'aculta_image',
    'file',
    'field',
    'image',
    'media',
    'entity_usage',
    'media_duplicates',
    'aculta_mailer',
    'profile',
    'entity',
    'vvjs',
    'vvj_core',
    'domain',
    'options',
    'text',
    'domain_alias',
    'domain_config',
    'language',
    'domain_source',
    'node',
    'path_alias',
    'address',
    'aculta_portal_cep',
    'login_emailusername',
    'aculta_mail_confirmer',
    'token',
    'aculta_mail_confirmer_user',
    'social_auth',
    'social_api',
    'group',
    'flexible_permissions',
    'lms',
    'commerce',
    'inline_entity_form',
    'datetime',
    'commerce_cart',
    'commerce_order',
    'commerce_price',
    'commerce_store',
    'path',
    'commerce_number_pattern',
    'entity_reference_revisions',
    'state_machine',
    'commerce_checkout',
    'commerce_payment',
    'physical',
    'key',
    'taxonomy',
    'metatag',
    'schema_metatag',
    'schema_article',
    'schema_event',
    'schema_image_object',
    'schema_organization',
    'schema_product',
    'schema_web_site',
    'schema_web_page',
    'search_api_db',
    'search_api',
    'csp',
    'reporting',
    'aculta_user',
    'comment',
    'link',
  ];

}
