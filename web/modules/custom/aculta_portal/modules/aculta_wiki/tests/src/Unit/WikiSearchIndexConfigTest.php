<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_wiki\Unit;

use Drupal\Component\Serialization\Yaml;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * O verbete entra na busca do site: o índice aculta_conteudo cobre wiki_entry.
 *
 * Guarda de configuração exportada: a busca pública usa o índice
 * `aculta_conteudo`, e sem o bundle na lista os verbetes nunca são indexados.
 * Só conteúdo publicado é devolvido, porque a tela de busca confere o acesso de
 * visualização antes de mostrar o resultado.
 */
#[Group('aculta_wiki')]
final class WikiSearchIndexConfigTest extends TestCase {

  /**
   * O índice do site inclui o bundle wiki_entry.
   */
  public function testContentIndexIncludesWikiEntries(): void {
    $file = $this->repositoryRoot() . '/config/sync/search_api.index.aculta_conteudo.yml';
    $this->assertFileExists($file);

    $index = Yaml::decode((string) file_get_contents($file));
    $this->assertIsArray($index);

    $selected = $index['datasource_settings']['entity:node']['bundles']['selected'] ?? [];
    $this->assertContains('wiki_entry', $selected);

    // Os bundles que já estavam no índice continuam lá.
    foreach (['activity', 'revista', 'document', 'page'] as $bundle) {
      $this->assertContains($bundle, $selected);
    }
  }

  /**
   * O índice é indexado na hora e acompanha mudanças de referência.
   */
  public function testIndexStreamsUpdates(): void {
    $file = $this->repositoryRoot() . '/config/sync/search_api.index.aculta_conteudo.yml';
    $index = Yaml::decode((string) file_get_contents($file));

    $this->assertTrue($index['options']['index_directly']);
    $this->assertTrue($index['options']['track_changes_in_references']);
  }

  /**
   * Raiz do repositório, para ler a configuração exportada.
   */
  private function repositoryRoot(): string {
    return dirname(__DIR__, 9);
  }

}
