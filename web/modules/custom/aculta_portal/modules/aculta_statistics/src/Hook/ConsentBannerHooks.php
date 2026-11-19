<?php

declare(strict_types=1);

namespace Drupal\aculta_statistics\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;

/**
 * Anexa o consentimento de visitante anônimo quando o banner está habilitado.
 *
 * O Portal prepara o comportamento e os dados neutros; o tema renderiza o banner
 * (componente `consent-banner`, oculto até o JavaScript decidir exibi-lo). Com a
 * opção desabilitada, nenhuma biblioteca é anexada e a página não muda.
 *
 * O consentimento opcional de contas segue o ledger aculta_user_agreement.
 */
final class ConsentBannerHooks {

  /**
   * Nome-base do cookie de decisão. A versão é acrescentada ao nome.
   */
  public const COOKIE_NAME = 'aculta_consent';

  /**
   * Versão do texto e da decisão. Mudar a versão pede nova decisão aos visitantes.
   */
  public const COOKIE_VERSION = 1;

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly AccountProxyInterface $currentUser,
  ) {}

  /**
   * Anexa a biblioteca e as configurações do banner, se habilitado.
   *
   * @param array<string, mixed> $attachments
   *   Anexos da página, alterados por referência.
   */
  #[Hook('page_attachments')]
  public function pageAttachments(array &$attachments): void {
    // Ligar ou desligar o banner invalida as páginas em cache.
    $attachments['#cache']['tags'][] = 'config:aculta_statistics.settings';
    if (!$this->isEnabledForVisitor()) {
      return;
    }

    $attachments['#attached']['library'][] = 'aculta_statistics/consent';
    $attachments['#attached']['drupalSettings']['acultaStatistics']['consent'] = [
      'cookieName' => self::COOKIE_NAME,
      'version' => self::COOKIE_VERSION,
      // Caminho relativo: o host vem do ambiente, nunca fixo no código (AGENTS, endereços dependentes de ambiente).
      'privacyUrl' => Url::fromUserInput('/sobre/politica-de-privacidade', ['absolute' => FALSE])->toString(),
    ];
  }

  /**
   * Adds the visual banner to the footer only for anonymous visitors.
   *
   * @param array<string, mixed> $page_bottom
   *   Page bottom render region, altered by reference.
   */
  #[Hook('page_bottom')]
  public function pageBottom(array &$page_bottom): void {
    if (!$this->isEnabledForVisitor()) {
      return;
    }

    $page_bottom['aculta_statistics_consent_banner'] = [
      '#type' => 'component',
      '#component' => 'aculta420_v2:consent-banner',
      '#props' => [
        'privacy_url' => Url::fromUserInput('/sobre/politica-de-privacidade', ['absolute' => FALSE])->toString(),
      ],
      '#cache' => [
        'contexts' => ['user'],
        'tags' => ['config:aculta_statistics.settings'],
        'max-age' => 0,
      ],
    ];
  }

  /**
   * Whether the configured consent banner should be shown to this visitor.
   */
  private function isEnabledForVisitor(): bool {
    return $this->currentUser->isAnonymous()
      && (bool) $this->configFactory->get('aculta_statistics.settings')->get('consent_banner.enabled');
  }

}
