<?php

declare(strict_types=1);

namespace Drupal\aculta_mcp\Plugin\tool\Tool;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\ExecutableResult;
use Drupal\tool\Tool\ToolOperation;
use Drupal\tool\TypedData\InputDefinition;
use Drupal\tool\TypedData\OutputDefinition;

/**
 * Searches public Melhor Envio API documentation.
 */
#[Tool(
  id: 'aculta_mcp:melhorenvio_documentation_search',
  label: new TranslatableMarkup('Melhor Envio documentation search'),
  description: new TranslatableMarkup('Searches public Melhor Envio API documentation. It does not execute Melhor Envio API requests or access the ACULTA account.'),
  operation: ToolOperation::Read,
  destructive: FALSE,
  permission: 'access mcp server',
  input_definitions: [
    'pattern' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Search pattern'),
      description: new TranslatableMarkup('API resource, endpoint, parameter, or behavior to find.'),
      constraints: ['NotBlank' => [], 'Length' => ['max' => 200]],
    ),
  ],
  output_definitions: [
    'result' => new OutputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Documentation results'),
      description: new TranslatableMarkup('Untrusted text returned by Melhor Envio public API documentation search.'),
    ),
  ],
)]
final class MelhorEnvioDocumentationSearch extends ProviderDocumentationToolBase {

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ExecutableResult {
    return $this->documentationResult('melhorenvio', 'search-endpoints', ['pattern' => $values['pattern']]);
  }

}
