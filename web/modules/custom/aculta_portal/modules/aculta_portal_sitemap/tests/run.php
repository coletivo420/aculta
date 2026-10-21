<?php

declare(strict_types=1);

// Verificações estáticas da fase 0.1.0-E. Não usam Drupal.
$root = dirname(__DIR__);
$failures = 0;
$assert = static function (bool $ok, string $name) use (&$failures): void {
  echo ($ok ? 'PASS ' : 'FAIL ') . $name . PHP_EOL;
  if (!$ok) {
    $failures++;
  }
};
$plugin = (string) file_get_contents($root . '/src/Plugin/simple_sitemap/UrlGenerator/PurposeNodeUrlGenerator.php');
$assert(!str_contains($plugin, '\\Drupal::'), 'plugin usa injeção de dependências, sem \\Drupal:: estático');
$assert(str_contains($plugin, "const INDEXABLE_PURPOSES = ['main', 'support', 'magazine', 'wiki', 'courses'];"), 'SHOP fora dos purposes indexáveis (sem conteúdo público)');
$assert(str_contains($plugin, "const ROOT_PURPOSES = ['main', 'support', 'magazine', 'wiki', 'courses'];"), 'raiz de cada purpose indexável é URL própria (rota, não nó)');
$assert(str_contains($plugin, 'isPurposeFrontNode'), 'página inicial de domínio excluída do conjunto de nós (sem redirect)');
$assert(str_contains($plugin, "const PUBLIC_PATHS = ['main' => ['/contato']];"), 'contato (webform) incluído no MAIN, como no sitemap institucional anterior');
$assert(str_contains($plugin, 'runInPurpose'), 'acesso avaliado no contexto do purpose de origem (verbetes da WIKI)');
$assert(str_contains($plugin, 'DomainConfigCollectionUtils'), 'página inicial lida pela coleção de configuração do domínio');
$assert(str_contains($plugin, "'page', 'activity', 'document', 'wiki_entry', 'article'"), 'bundles elegíveis conforme a matriz da ADR-009');
$assert(str_contains($plugin, "field_domain_source"), 'origem do conteúdo vem de field_domain_source (R1)');
$assert(str_contains($plugin, 'id = "aculta_purpose_node"'), 'id do plugin declarado na anotação');
$assert(str_contains($plugin, 'canonicalRouteUrl') || str_contains($plugin, 'canonicalPathUrl'), 'URLs canônicas vêm do DomainPurposeManager (R7)');
$assert(str_contains($plugin, 'AnonymousUserSession'), 'elegibilidade verificada como anônimo (R4)');
echo $failures === 0 ? "tests: PASS\n" : "tests: FAIL ($failures)\n";
exit($failures === 0 ? 0 : 1);
