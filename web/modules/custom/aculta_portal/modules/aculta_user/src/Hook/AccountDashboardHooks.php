<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Hook;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_portal\Render\PortalLinkElement;
use Drupal\aculta_user\Account\AccountNames;
use Drupal\aculta_user\Bio\AccountBioCard;
use Drupal\aculta_user\Social\SocialLinksCard;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Drupal\aculta_portal\Profile\PerfilDoPapel;

/**
 * Caixa de bio na visão geral da Minha conta. Substitui o resumo de Cursos (aculta_portal).
 */
final class AccountDashboardHooks {

  public function __construct(
    #[Autowire(service: 'aculta_user.account_bio_card')]
    private readonly AccountBioCard $bioCard,
    #[Autowire(service: 'aculta_user.social_links_card')]
    private readonly SocialLinksCard $socialCard,
    #[Autowire(service: 'entity_type.manager')]
    private readonly EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'aculta_portal.domain_purpose')]
    private readonly DomainPurposeManager $purposeManager,
  ) {}

  /**
   * Adiciona a caixa Bio à visão geral. O Portal dispara aculta_portal_dashboard.
   */
  #[Hook('aculta_portal_dashboard_alter')]
  public function dashboardAlter(array &$build, AccountInterface $account): void {
    $build['bio'] = $this->bioCard->build($account);
    $build['redes'] = $this->socialCard->build($account);
    $build['acoes'] = $this->overviewActions($account);
  }

  /**
   * Ações do topo da visão geral: "Ver Perfil" leva ao perfil público (purpose main). Sem apelido, nada aparece.
   */
  private function overviewActions(AccountInterface $account): array {
    $profile = PerfilDoPapel::principal($this->entityTypeManager->getStorage('profile'), $account);
    $nickname = $profile && $profile->hasField('field_nickname') ? trim((string) $profile->get('field_nickname')->value) : '';
    // Preserve display casing, but generate the route from its lowercase slug.
    // Invalid legacy values omit this action rather than breaking the overview.
    $publicSlug = AccountNames::publicRouteSlug($nickname);
    if ($publicSlug === NULL) {
      return [];
    }
    $url = $this->purposeManager->routeUrl('main', 'aculta_user.perfil_publico', ['apelido' => $publicSlug]);
    if ($url === NULL) {
      return [];
    }
    $cache = new CacheableMetadata();
    $cache->addCacheTags($profile->getCacheTags());
    return [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'account-overview-actions'],
      '#weight' => -10,
      '#cache' => ['tags' => $cache->getCacheTags()],
      'profile' => PortalLinkElement::build(
        (string) t('Ver Perfil'),
        $url,
        ['data-portal-ui' => 'account-view-profile', 'title' => (string) t('Abrir o perfil público')],
      ),
    ];
  }

}
