<?php

declare(strict_types=1);

namespace Drupal\aculta_domain_config\EventSubscriber;

use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\domain\DomainInterface;
use Drupal\domain_config_switcher\DomainConfigSwitcherInterface;
use Drupal\domain_config_ui\DomainConfigEditContextInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Applies the selected config domain before block entities are loaded.
 *
 * O MAIN concentra a administração. O domain_config_ui resolve o domínio
 * negociado, que no MAIN é o domínio base, mas os overrides de bloco ficam na
 * coleção de outro domínio. Este assinante lê `config_domain` antes do
 * ParamConverter carregar o bloco e registra o contexto de edição para as
 * configurações de bloco, sem tocar na negociação real do request.
 */
final class DomainConfigEditingSubscriber implements EventSubscriberInterface {

  /**
   * Rota que representa a raiz da administração de blocos.
   */
  private const BLOCK_ADMIN_ROUTE = 'block.admin_display';

  public function __construct(
    private readonly DomainConfigSwitcherInterface $switcher,
    private readonly DomainConfigEditContextInterface $editContext,
    private readonly StorageInterface $configStorage,
    private readonly RouteProviderInterface $routeProvider,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Acima de RouterListener (32): o ParamConverter do bloco precisa do
    // contexto já definido para carregar o override do domínio escolhido.
    return [KernelEvents::REQUEST => ['onRequest', 100]];
  }

  /**
   * Retargets block config edits to the selected domain, when applicable.
   */
  public function onRequest(RequestEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }
    $request = $event->getRequest();
    $domainId = $this->selectedDomainId($request);
    if ($domainId === NULL || $domainId === '') {
      return;
    }
    $domain = $this->entityTypeManager->getStorage('domain')->load($domainId);
    // O domínio padrão representa o valor global; não criar override nele.
    if (!$domain instanceof DomainInterface || !$domain->status() || $domain->isDefault()) {
      return;
    }
    if (!$this->isBlockAdminPath($request->getPathInfo())) {
      return;
    }
    $configNames = $this->configStorage->listAll('block.block.');
    if ($configNames === []) {
      return;
    }
    $this->editContext->setEditingDomain($domainId, $configNames);
  }

  /**
   * Lê a seleção da URL atual ou do destination de uma operação de bloco.
   *
   * Os links Desativar/Ativar da lista de blocos preservam `config_domain`
   * apenas no `destination`; sem esta leitura a operação atingiria o global.
   */
  private function selectedDomainId(Request $request): ?string {
    $domainId = $this->switcher->getSelectedDomainId();
    if ($domainId !== NULL) {
      return $domainId;
    }
    $destination = $request->query->get('destination');
    if (!is_string($destination) || $destination === '') {
      return NULL;
    }
    $query = parse_url($destination, PHP_URL_QUERY);
    if (!is_string($query) || $query === '') {
      return NULL;
    }
    parse_str($query, $params);
    $candidate = $params[DomainConfigSwitcherInterface::QUERY_ARG] ?? NULL;
    if (!is_string($candidate) || $candidate === '') {
      return NULL;
    }
    $selectable = $this->switcher->getSelectableDomains();
    return isset($selectable[$candidate]) ? $candidate : NULL;
  }

  /**
   * Checks whether the path belongs to the block layout administration.
   */
  private function isBlockAdminPath(string $path): bool {
    try {
      $route = $this->routeProvider->getRouteByName(self::BLOCK_ADMIN_ROUTE);
    }
    catch (\Exception) {
      return FALSE;
    }
    $base = '/' . trim($route->getPath(), '/');
    // Aceita prefixo de idioma ou de caminho antes da rota administrativa.
    return $path === $base || str_contains($path, $base . '/');
  }

}
