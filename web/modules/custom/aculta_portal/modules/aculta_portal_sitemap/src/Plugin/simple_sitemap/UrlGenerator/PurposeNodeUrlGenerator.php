<?php

declare(strict_types=1);

namespace Drupal\aculta_portal_sitemap\Plugin\simple_sitemap\UrlGenerator;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\node\NodeInterface;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\simple_sitemap\Logger;
use Drupal\simple_sitemap\Plugin\simple_sitemap\SimpleSitemapPluginBase;
use Drupal\simple_sitemap\Plugin\simple_sitemap\UrlGenerator\UrlGeneratorBase;
use Drupal\domain_config\Config\DomainConfigCollectionUtils;
use Drupal\simple_sitemap\Settings;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Gera URLs canônicas de nós cuja origem é o purpose da variante.
 *
 * Regras (ADR-009, política de indexação 0.1.0-D):
 * - R1/R2: o nó entra somente no sitemap do purpose de seu field_domain_source;
 * - R3: somente nós publicados;
 * - R4: somente o que um usuário anônimo pode visualizar (verificado com a conta anônima);
 * - R5a: exclui nós com robots noindex no metatag;
 * - R7: URL sempre pelo host canônico de produção, via canonicalRouteUrl().
 *
 * @UrlGenerator(
 *   id = "aculta_purpose_node",
 *   label = @Translation("ACULTA: nós por purpose"),
 *   description = @Translation("Nós publicados cuja origem é o purpose da variante, com URL canônica de produção."),
 * )
 */
final class PurposeNodeUrlGenerator extends UrlGeneratorBase {

  /** Chave de configuração de terceiros da variante que guarda o purpose. */
  public const PURPOSE_KEY = 'purpose';

  /**
   * Purposes com política de indexação ativa (ADR-009, 0.1.0-F). Controle
   * explícito: existir como domínio não basta para ser indexável. SHOP fica fora
   * até ter catálogo público; FORUM e ACCOUNT nunca entram.
   */
  public const INDEXABLE_PURPOSES = ['main', 'support', 'magazine', 'wiki', 'courses', 'links'];

  /**
   * Purposes cuja página pública inicial é uma rota (não um nó). A raiz do host
   * entra no sitemap do purpose como URL própria. SUPPORT: página de apoio na raiz
   * do subdomínio (DT-P23). MAGAZINE, WIKI e COURSES: home por front de rota.
   */
  public const ROOT_PURPOSES = ['main', 'support', 'magazine', 'wiki', 'courses', 'links'];

  /**
   * Páginas públicas que não são nós (rotas ou webforms) por purpose. Mantém a
   * paridade com o sitemap institucional anterior: /contato é o webform de contato.
   */
  public const PUBLIC_PATHS = ['main' => ['/contato']];

  /** Bundles elegíveis (matriz da política de indexação). */
  public const BUNDLES = ['page', 'activity', 'document', 'wiki_entry', 'revista'];

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    Logger $logger,
    Settings $settings,
    private readonly DomainPurposeManager $purposes,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AccountSwitcherInterface $accountSwitcher,
    private readonly StorageInterface $configStorage,
    private readonly AliasManagerInterface $aliasManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $logger, $settings);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): SimpleSitemapPluginBase {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('simple_sitemap.logger'),
      $container->get('simple_sitemap.settings'),
      $container->get('aculta_portal.domain_purpose'),
      $container->get('entity_type.manager'),
      $container->get('account_switcher'),
      $container->get('config.storage'),
      $container->get('path_alias.manager'),
    );
  }

  /** {@inheritdoc} */
  public function getDataSets(): array {
    $purpose = $this->sitemapPurpose();
    if ($purpose === NULL || !in_array($purpose, self::INDEXABLE_PURPOSES, TRUE)) {
      return [];
    }
    $domain = $this->purposes->getDomain($purpose);
    if ($domain === NULL) {
      return [];
    }
    $sets = [];
    if (in_array($purpose, self::ROOT_PURPOSES, TRUE)) {
      $sets[] = ['purpose' => $purpose, 'root' => TRUE];
    }
    foreach (self::PUBLIC_PATHS[$purpose] ?? [] as $path) {
      $sets[] = ['purpose' => $purpose, 'path' => $path];
    }
    $ids = $this->entityTypeManager->getStorage('node')->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', 1)
      ->condition('type', self::BUNDLES, 'IN')
      ->condition('field_domain_source', $domain->id())
      ->sort('nid')
      ->execute();
    $size = (int) $this->settings->get('entities_per_queue_item', 50);
    foreach (array_chunk(array_values($ids), max(1, $size)) as $chunk) {
      $sets[] = ['purpose' => $purpose, 'ids' => $chunk];
    }
    return $sets;
  }

  /** {@inheritdoc} */
  protected function processDataSet($data_set): array {
    return $this->collect($data_set);
  }

  /**
   * Lista plana de dados de caminho (uma entrada por URL), como o gerador de
   * entidades do Simple Sitemap retorna.
   *
   * {@inheritdoc}
   */
  public function generate($data_set): array {
    return $this->collect($data_set);
  }

  /** @return array<int, array<string, mixed>> */
  private function collect(array $data_set): array {
    $purpose = (string) ($data_set['purpose'] ?? '');
    if (!empty($data_set['root'])) {
      $root = $this->purposes->canonicalPathUrl($purpose, '/');
      return $root === NULL ? [] : [$this->constructPathData($root)];
    }
    if (isset($data_set['path'])) {
      $url = $this->purposes->canonicalPathUrl($purpose, (string) $data_set['path']);
      return $url === NULL ? [] : [$this->constructPathData($url)];
    }
    $results = [];
    // R4: a elegibilidade de visualização é verificada como usuário anônimo.
    $this->accountSwitcher->switchTo(new AnonymousUserSession());
    try {
      foreach ($this->entityTypeManager->getStorage('node')->loadMultiple($data_set['ids'] ?? []) as $node) {
        if (!$node instanceof NodeInterface || !$this->isEligible($node, $purpose)) {
          continue;
        }
        $url = $this->purposes->canonicalRouteUrl($purpose, 'entity.node.canonical', ['node' => $node->id()]);
        if ($url === NULL) {
          continue;
        }
        $results[] = $this->constructPathData($url, [
          'lastmod' => date('c', (int) $node->getChangedTime()),
        ]);
      }
    }
    finally {
      $this->accountSwitcher->switchBack();
    }
    return $results;
  }

  private function isEligible(NodeInterface $node, string $purpose): bool {
    if (!$node->isPublished()) {
      return FALSE;
    }
    // O acesso é avaliado no contexto do purpose de origem: o hook de entidade do
    // Portal só libera verbetes da WIKI quando o host ativo é o da WIKI.
    $viewable = $this->purposes->runInPurpose($purpose, static fn(): bool => $node->access('view'));
    if ($viewable !== TRUE) {
      return FALSE;
    }
    $source = $node->hasField('field_domain_source') && !$node->get('field_domain_source')->isEmpty()
      ? $this->purposes->getPurposeForDomainId((string) $node->get('field_domain_source')->target_id)
      : NULL;
    if ($source !== $purpose) {
      return FALSE;
    }
    // A página inicial do purpose é representada pela raiz do host (ROOT_PURPOSES).
    // O nó que ocupa essa posição redireciona para a raiz; listá-lo duplicaria a URL.
    if ($this->isPurposeFrontNode($node, $purpose)) {
      return FALSE;
    }
    // R5a: noindex declarado no metatag do nó.
    if ($node->hasField('field_meta_tags') && !$node->get('field_meta_tags')->isEmpty()
      && stripos((string) $node->get('field_meta_tags')->value, 'noindex') !== FALSE) {
      return FALSE;
    }
    return TRUE;
  }

  private function isPurposeFrontNode(NodeInterface $node, string $purpose): bool {
    $domain = $this->purposes->getDomain($purpose);
    if ($domain === NULL) {
      return FALSE;
    }
    // Sem sobrescrita do domínio, a página inicial é a global (system.site).
    $front = $this->configStorage
      ->createCollection(DomainConfigCollectionUtils::createDomainConfigCollectionName($domain->id()))
      ->read('system.site')['page']['front'] ?? $this->configStorage->read('system.site')['page']['front'] ?? NULL;
    if (!is_string($front) || $front === '') {
      return FALSE;
    }
    $alias = $this->aliasManager->getAliasByPath('/node/' . $node->id());
    return $front === $alias || $front === '/node/' . $node->id();
  }

  private function sitemapPurpose(): ?string {
    $purpose = $this->sitemap?->getThirdPartySetting('aculta_portal_sitemap', self::PURPOSE_KEY);
    return is_string($purpose) && $purpose !== '' ? $purpose : NULL;
  }

}
