<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Hook;

use Drupal\aculta_portal\Account\AccountPageTitle;
use Drupal\aculta_portal\Appearance\ColorModePreference;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Entrega o modo de cor resolvido ao tema, com cacheability por usuário.
 */
final class AppearanceHooks {

  public function __construct(
    #[Autowire(service: 'aculta_portal.color_mode_preference')]
    private readonly ColorModePreference $preference,
    #[Autowire(service: 'current_user')]
    private readonly AccountProxyInterface $currentUser,
    #[Autowire(service: 'aculta_portal.account_page_title')]
    private readonly AccountPageTitle $accountTitle,
    #[Autowire(service: 'aculta_portal.domain_purpose')]
    private readonly DomainPurposeManager $domainPurpose,
  ) {}

  /**
   * Contrato neutro: aculta_color_mode = light|dark|auto. O tema aplica o atributo.
   */
  #[Hook('preprocess_html')]
  public function preprocessHtml(array &$variables): void {
    $variables['aculta_color_mode'] = $this->preference->resolve((int) $this->currentUser->id());
    $this->accountTitle->applyHeadTitle($variables);

    // Toda a interface administrativa vive no MAIN. Nos subdomínios, remove o
    // toolbar do Navigation para não exibir links administrativos que dão 404.
    if ($this->domainPurpose->getCurrentPurpose() !== 'main') {
      unset($variables['page_top']['navigation'], $variables['page_top']['top_bar']);
    }
  }

  /**
   * O HTML depende do usuário e do padrão do site.
   *
   * Sem estes metadados, o cache de página serviria um modo antigo.
   */
  #[Hook('page_attachments')]
  public function pageAttachments(array &$attachments): void {
    $attachments['#cache']['contexts'][] = 'user';
    $attachments['#cache']['tags'][] = 'config:aculta_portal.appearance';
    $uid = (int) $this->currentUser->id();
    if ($uid > 0) {
      $attachments['#cache']['tags'][] = 'user:' . $uid;
    }
  }

}
