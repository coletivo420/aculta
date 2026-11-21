<?php

declare(strict_types=1);

namespace Drupal\aculta_cgu\Scope;

use Drupal\aculta_cgu\Entity\CguReport;
use Drupal\aculta_cgu\ModerationScope;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Decide se uma conta pode moderar uma denúncia específica.
 *
 * Ordem: permissão de purpose do CGU (global ou do purpose) e, só depois, adaptadores
 * de alvo. Sem adaptador registrado para o purpose, o resultado é negativo (fail closed).
 */
final class ReportTargetScope {

  /**
   * @param iterable<ReportTargetScopeProviderInterface> $providers
   */
  public function __construct(
    private readonly iterable $providers,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Informa se algum adaptador de alvo trata o purpose.
   */
  public function supports(string $purpose): bool {
    foreach ($this->providers as $provider) {
      if ($provider->supports($purpose)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Informa se a conta pode decidir a denúncia.
   */
  public function allowsReport(CguReport $report, AccountInterface $account): bool {
    return $this->decide($this->purposeOf($report), $this->loadTarget($report), $account);
  }

  /**
   * Decisão pura: purpose, alvo carregado e conta. Sem leitura de entidade da denúncia.
   */
  public function decide(string $purpose, ?EntityInterface $target, AccountInterface $account): bool {
    if ($purpose === '') {
      return FALSE;
    }
    if (ModerationScope::allows($account, $purpose)) {
      return TRUE;
    }
    if (!$target instanceof EntityInterface) {
      return FALSE;
    }
    foreach ($this->providers as $provider) {
      if ($provider->supports($purpose) && $provider->allowsTarget($account, $target)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Retorna o purpose da denúncia como string, ou vazio.
   */
  private function purposeOf(CguReport $report): string {
    $value = $report->get('purpose')->value;
    return is_string($value) ? $value : '';
  }

  /**
   * Carrega o alvo da denúncia, se ainda existir.
   */
  private function loadTarget(CguReport $report): ?EntityInterface {
    $type = $report->get('target_type')->value;
    $id = $report->get('target_id')->value;
    if (!is_string($type) || $type === '' || !is_numeric($id) || !$this->entityTypeManager->hasDefinition($type)) {
      return NULL;
    }
    $target = $this->entityTypeManager->getStorage($type)->load((int) $id);
    return $target instanceof EntityInterface ? $target : NULL;
  }

}
