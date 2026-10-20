<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Policy;

use Drupal\aculta_mailer\Plugin\AcultaMailAdjuster\AcultaMailAdjusterManager;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Resolve a política de um tipo: ajustes globais e, depois, os do tipo (que prevalecem).
 *
 * Configuração em `aculta_mailer.policy` (exportável, sem credenciais): `global` e `types.<tipo>`, com as chaves dos
 * plugins de ajuste (`transport`, `reply_to`, `priority`, `skip`). Chave sem plugin é ignorada.
 */
final class MailPolicyResolver {

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly AcultaMailAdjusterManager $adjusters,
  ) {}

  /**
   * Política efetiva do tipo.
   */
  public function resolve(string $typeId): MailPolicy {
    $config = $this->configFactory->get('aculta_mailer.policy');
    // Camada global primeiro, depois a do tipo: cada valor é validado no ajuste, sem apagar o da camada anterior.
    $policy = $this->apply(new MailPolicy(), (array) $config->get('global'));
    return $this->apply($policy, (array) ($config->get('types.' . $typeId) ?? []));
  }

  /**
   * Aplica uma camada de ajustes sobre a política.
   *
   * @param \Drupal\aculta_mailer\Policy\MailPolicy $policy
   *   Política de partida.
   * @param array<string, mixed> $settings
   *   Valores por ID de ajuste.
   */
  private function apply(MailPolicy $policy, array $settings): MailPolicy {
    foreach ($settings as $id => $value) {
      if ($this->adjusters->hasDefinition($id)) {
        $policy = $this->adjusters->createInstance($id)->adjust($policy, $value);
      }
    }
    return $policy;
  }

}
