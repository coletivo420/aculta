<?php

declare(strict_types=1);

namespace Drupal\aculta_mcp\Plugin\tool\Tool;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\ExecutableResult;
use Drupal\tool\Tool\ToolOperation;
use Drupal\tool\TypedData\OutputDefinition;

/**
 * Lists endpoint paths from public Melhor Envio API documentation.
 */
#[Tool(
  id: 'aculta_mcp:melhorenvio_endpoint_list',
  label: new TranslatableMarkup('Melhor Envio endpoint list'),
  description: new TranslatableMarkup('Lists endpoint paths from public Melhor Envio documentation. It does not execute provider API requests.'),
  operation: ToolOperation::Read,
  destructive: FALSE,
  permission: 'access mcp server',
  output_definitions: [
    'result' => new OutputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Documented endpoints'),
      description: new TranslatableMarkup('Untrusted text listing paths from Melhor Envio public API documentation.'),
    ),
  ],
)]
final class MelhorEnvioEndpointList extends ProviderDocumentationToolBase {

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ExecutableResult {
    return $this->documentationResult('melhorenvio', 'list-endpoints', []);
  }

}
