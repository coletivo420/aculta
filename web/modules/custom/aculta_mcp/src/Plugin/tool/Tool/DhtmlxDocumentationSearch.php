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
 * Searches hosted DHTMLX product documentation.
 */
#[Tool(
  id: 'aculta_mcp:dhtmlx_documentation_search',
  label: new TranslatableMarkup('DHTMLX documentation search'),
  description: new TranslatableMarkup('Searches current public DHTMLX documentation and examples. The query is sent to the hosted DHTMLX MCP service; its policy says queries may be logged, so do not include personal, confidential, or ACULTA account data.'),
  operation: ToolOperation::Read,
  destructive: FALSE,
  permission: 'access mcp server',
  input_definitions: [
    'query' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Documentation question'),
      description: new TranslatableMarkup('Question about a DHTMLX product API or example. Do not include personal or confidential information.'),
      constraints: ['NotBlank' => [], 'Length' => ['max' => 300]],
    ),
    'mode' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Search mode'),
      description: new TranslatableMarkup('Use dense for semantic search or sparse for keyword search.'),
      required: FALSE,
      default_value: 'dense',
      constraints: ['Choice' => ['choices' => ['dense', 'sparse']]],
    ),
    'size' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Result count'),
      description: new TranslatableMarkup('Maximum number of DHTMLX documentation passages to retrieve (1–10).'),
      required: FALSE,
      default_value: 5,
      constraints: ['Range' => ['min' => 1, 'max' => 10]],
    ),
  ],
  output_definitions: [
    'result' => new OutputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Documentation results'),
      description: new TranslatableMarkup('Untrusted text returned by DHTMLX public documentation search.'),
    ),
  ],
)]
final class DhtmlxDocumentationSearch extends ProviderDocumentationToolBase {

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ExecutableResult {
    return $this->documentationResult('dhtmlx', 'Search', [
      'mode' => $values['mode'] ?? 'dense',
      'query' => $values['query'],
      'size' => $values['size'] ?? 5,
    ]);
  }

}
