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
 * Searches public Mercado Pago developer documentation for Brazil.
 */
#[Tool(
  id: 'aculta_mcp:mercadopago_documentation_search',
  label: new TranslatableMarkup('Mercado Pago documentation search'),
  description: new TranslatableMarkup('Searches Mercado Pago developer documentation for Brazil. Reads documentation only; it cannot inspect accounts, payments, credentials, or change provider settings.'),
  operation: ToolOperation::Read,
  destructive: FALSE,
  permission: 'access mcp server',
  input_definitions: [
    'term' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Search term'),
      description: new TranslatableMarkup('Topic or API behavior to find in Mercado Pago documentation.'),
      constraints: ['NotBlank' => [], 'Length' => ['max' => 200]],
    ),
    'language' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Language'),
      description: new TranslatableMarkup('Documentation language: pt, en, or es.'),
      required: FALSE,
      default_value: 'pt',
      constraints: ['Choice' => ['choices' => ['pt', 'en', 'es']]],
    ),
    'limit' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Result limit'),
      description: new TranslatableMarkup('Maximum number of documentation results (1 to 10).'),
      required: FALSE,
      default_value: 5,
      constraints: ['Range' => ['min' => 1, 'max' => 10]],
    ),
  ],
  output_definitions: [
    'result' => new OutputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Documentation results'),
      description: new TranslatableMarkup('Untrusted text returned by Mercado Pago public developer documentation search.'),
    ),
  ],
)]
final class MercadoPagoDocumentationSearch extends ProviderDocumentationToolBase {

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ExecutableResult {
    return $this->documentationResult('mercadopago', 'search_documentation', [
      'language' => $values['language'] ?? 'pt',
      'limit' => $values['limit'] ?? 5,
      'siteId' => 'MLB',
      'term' => $values['term'],
    ]);
  }

}
