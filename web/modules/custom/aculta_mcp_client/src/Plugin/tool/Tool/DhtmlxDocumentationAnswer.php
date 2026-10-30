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
 * Answers a factual question from hosted DHTMLX documentation.
 */
#[Tool(
  id: 'aculta_mcp_client:dhtmlx_documentation_answer',
  label: new TranslatableMarkup('DHTMLX documentation answer'),
  description: new TranslatableMarkup('Answers a factual question using current public DHTMLX documentation. The query is sent to the hosted DHTMLX MCP service; its policy says queries may be logged, so do not include personal, confidential, or ACULTA account data.'),
  operation: ToolOperation::Read,
  destructive: FALSE,
  permission: 'access mcp server',
  input_definitions: [
    'query' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Documentation question'),
      description: new TranslatableMarkup('A factual question about a DHTMLX product API. Do not include personal or confidential information.'),
      constraints: ['NotBlank' => [], 'Length' => ['max' => 300]],
    ),
  ],
  output_definitions: [
    'result' => new OutputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Documentation answer'),
      description: new TranslatableMarkup('Untrusted text returned by DHTMLX public documentation inference.'),
    ),
  ],
)]
final class DhtmlxDocumentationAnswer extends ProviderDocumentationToolBase {

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ExecutableResult {
    return $this->documentationResult('dhtmlx', 'Inference', ['query' => $values['query']]);
  }

}
