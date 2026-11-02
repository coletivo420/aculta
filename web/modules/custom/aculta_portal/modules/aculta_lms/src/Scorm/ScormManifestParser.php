<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Scorm;

/**
 * Lê o `imsmanifest.xml` de um pacote SCORM 1.2 ou 2004.
 *
 * Usa DOMDocument com LIBXML_NONET e recusa DOCTYPE, para não carregar entidades
 * externas nem expandir entidades internas. Sem dependência do Drupal.
 *
 * O resultado é um array com:
 * - version: '1.2' ou '2004'.
 * - default_organization: identificador da organização padrão, ou NULL.
 * - organizations: árvore de itens por organização.
 * - resources: recursos por identificador, com tipo, href e arquivos.
 * - launch_items: itens que apontam para um recurso com href, na ordem do manifesto.
 */
final class ScormManifestParser {

  /**
   * Tamanho máximo aceito para o manifesto (1 MiB).
   */
  public const MAX_MANIFEST_BYTES = 1048576;

  /**
   * Lê e valida o manifesto.
   *
   * @return array<string, mixed>
   *   Estrutura descrita na classe.
   *
   * @throws \UnexpectedValueException
   *   Quando o XML é inválido, usa DOCTYPE, tem versão desconhecida, aponta para
   *   recurso inexistente ou não tem nenhum item lançável.
   */
  public function parse(string $xml): array {
    if (strlen($xml) > self::MAX_MANIFEST_BYTES) {
      throw new \UnexpectedValueException('O manifesto SCORM é grande demais.');
    }
    if (preg_match('/<!DOCTYPE/i', $xml) === 1) {
      throw new \UnexpectedValueException('O manifesto SCORM não pode declarar DOCTYPE.');
    }

    $dom = new \DOMDocument();
    $previous = libxml_use_internal_errors(TRUE);
    $loaded = $dom->loadXML($xml, LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    if ($loaded !== TRUE || $dom->documentElement === NULL || $dom->documentElement->localName !== 'manifest') {
      throw new \UnexpectedValueException('O manifesto SCORM não é um XML válido.');
    }

    $xpath = new \DOMXPath($dom);
    $version = $this->version($xpath);
    $resources = $this->resources($xpath);
    $organizations = $this->organizations($xpath, $resources);
    $launchItems = [];
    foreach ($organizations['list'] as $organization) {
      $this->collectLaunchItems($organization['items'], $organization['identifier'], $resources, $launchItems);
    }
    if ($launchItems === []) {
      throw new \UnexpectedValueException('O manifesto SCORM não tem item lançável.');
    }

    return [
      'version' => $version,
      'default_organization' => $organizations['default'],
      'organizations' => $organizations['list'],
      'resources' => $resources,
      'launch_items' => $launchItems,
    ];
  }

  /**
   * Versão pelo elemento `schemaversion`: '1.2' ou '2004'.
   */
  private function version(\DOMXPath $xpath): string {
    $node = $xpath->query('//*[local-name()="metadata"]/*[local-name()="schemaversion"]')->item(0);
    $value = $node === NULL ? '' : trim($node->textContent);
    if ($value === '1.2') {
      return '1.2';
    }
    if (preg_match('/^(CAM\s+)?1\.3$|2004/i', $value) === 1) {
      return '2004';
    }
    throw new \UnexpectedValueException('Versão SCORM desconhecida no manifesto.');
  }

  /**
   * Recursos por identificador.
   *
   * @return array<string, array{type: string, href: string, files: string[]}>
   */
  private function resources(\DOMXPath $xpath): array {
    $resources = [];
    foreach ($xpath->query('//*[local-name()="resources"]/*[local-name()="resource"]') as $node) {
      /** @var \DOMElement $node */
      $identifier = $node->getAttribute('identifier');
      if ($identifier === '') {
        throw new \UnexpectedValueException('Recurso sem identificador no manifesto.');
      }
      $files = [];
      foreach ($node->getElementsByTagNameNS('*', 'file') as $file) {
        $href = $file->getAttribute('href');
        if ($href !== '') {
          $files[] = $href;
        }
      }
      $resources[$identifier] = [
        'type' => strtolower($this->scormType($node)),
        'href' => $node->getAttribute('href'),
        'files' => $files,
      ];
    }
    return $resources;
  }

  /**
   * Tipo SCORM pelo atributo com qualquer prefixo (adlcp:scormType ou scormType).
   */
  private function scormType(\DOMElement $node): string {
    foreach ($node->attributes as $attribute) {
      if (strtolower($attribute->localName) === 'scormtype') {
        return $attribute->value;
      }
    }
    return '';
  }

  /**
   * Organizações, com a padrão identificada no atributo `default` de `organizations`.
   *
   * @param array<string, array> $resources
   *
   * @return array{default: string|null, list: array<int, array{identifier: string, title: string, items: array}>}
   */
  private function organizations(\DOMXPath $xpath, array $resources): array {
    $containers = $xpath->query('//*[local-name()="organizations"]');
    $container = $containers->item(0);
    $default = $container instanceof \DOMElement && $container->getAttribute('default') !== ''
      ? $container->getAttribute('default')
      : NULL;

    $list = [];
    foreach ($xpath->query('//*[local-name()="organizations"]/*[local-name()="organization"]') as $node) {
      /** @var \DOMElement $node */
      $identifier = $node->getAttribute('identifier');
      if ($identifier === '') {
        throw new \UnexpectedValueException('Organização sem identificador no manifesto.');
      }
      $list[] = [
        'identifier' => $identifier,
        'title' => $this->childText($node, 'title'),
        'items' => $this->items($node, $resources),
      ];
    }
    if ($default !== NULL && !in_array($default, array_column($list, 'identifier'), TRUE)) {
      throw new \UnexpectedValueException('A organização padrão do manifesto não existe.');
    }
    return ['default' => $default ?? ($list[0]['identifier'] ?? NULL), 'list' => $list];
  }

  /**
   * Itens de uma organização, em árvore. Falha se um item aponta para recurso inexistente.
   *
   * @param array<string, array> $resources
   *
   * @return array<int, array{identifier: string, title: string, identifierref: string|null, children: array}>
   */
  private function items(\DOMElement $parent, array $resources): array {
    $items = [];
    foreach ($parent->childNodes as $child) {
      if (!$child instanceof \DOMElement || $child->localName !== 'item') {
        continue;
      }
      $ref = $child->getAttribute('identifierref');
      if ($ref !== '' && !isset($resources[$ref])) {
        throw new \UnexpectedValueException('Um item do manifesto aponta para recurso inexistente.');
      }
      $items[] = [
        'identifier' => $child->getAttribute('identifier'),
        'title' => $this->childText($child, 'title'),
        'identifierref' => $ref === '' ? NULL : $ref,
        'children' => $this->items($child, $resources),
      ];
    }
    return $items;
  }

  /**
   * Coleta os itens com recurso de lançamento, em ordem de árvore.
   *
   * @param array<int, array> $items
   * @param array<string, array> $resources
   * @param array<int, array> $launchItems
   */
  private function collectLaunchItems(array $items, string $organization, array $resources, array &$launchItems): void {
    foreach ($items as $item) {
      $ref = $item['identifierref'];
      if ($ref !== NULL && $resources[$ref]['href'] !== '') {
        $launchItems[] = [
          'identifier' => $item['identifier'],
          'title' => $item['title'],
          'organization' => $organization,
          'resource' => $ref,
          'href' => $resources[$ref]['href'],
          'type' => $resources[$ref]['type'],
        ];
      }
      $this->collectLaunchItems($item['children'], $organization, $resources, $launchItems);
    }
  }

  /**
   * Texto do primeiro filho com o nome local informado, ou vazio.
   */
  private function childText(\DOMElement $node, string $localName): string {
    foreach ($node->childNodes as $child) {
      if ($child instanceof \DOMElement && $child->localName === $localName) {
        return trim($child->textContent);
      }
    }
    return '';
  }

}
