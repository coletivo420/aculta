<?php

declare(strict_types=1);

namespace Drupal\aculta_mcp_client\Plugin\tool\Tool;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\ExecutableResult;
use Drupal\tool\Tool\ToolOperation;
use Drupal\tool\TypedData\InputDefinition;
use Drupal\tool\TypedData\OutputDefinition;

/**
 * Reads one endpoint definition from public Melhor Envio documentation.
 */
#[Tool(
  id: 'aculta_mcp_client:melhorenvio_endpoint_details',
  label: new TranslatableMarkup('Melhor Envio endpoint details'),
  description: new TranslatableMarkup('Reads the documentation for one Melhor Envio endpoint. The method and path are used only to select documentation; no provider API request is made.'),
  operation: ToolOperation::Read,
  destructive: FALSE,
  permission: 'access mcp server',
  input_definitions: [
    'method' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('HTTP method'),
      description: new TranslatableMarkup('Documented HTTP method.'),
      constraints: ['Choice' => ['choices' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE']]],
    ),
    'path' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('API path'),
      description: new TranslatableMarkup('Relative Melhor Envio API path, such as /api/v2/me/orders/{orderID}.'),
      constraints: [
        'NotBlank' => [],
        'Length' => ['max' => 240],
        'Regex' => ['pattern' => '/^\/[A-Za-z0-9_{}\/.\-]+$/'],
      ],
    ),
  ],
  output_definitions: [
    'result' => new OutputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Endpoint documentation'),
      description: new TranslatableMarkup('Untrusted documentation text for the selected endpoint.'),
    ),
  ],
)]
final class MelhorEnvioEndpointDetails extends ProviderDocumentationToolBase {

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ExecutableResult {
    return $this->documentationResult('melhorenvio', 'get-endpoint', [
      'method' => $values['method'],
      'path' => $values['path'],
    ]);
  }

}
