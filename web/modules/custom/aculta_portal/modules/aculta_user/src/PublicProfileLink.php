<?php

declare(strict_types=1);

namespace Drupal\aculta_user;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_portal\Profile\PerfilDoPapel;
use Drupal\aculta_user\Account\AccountNames;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\user\UserInterface;

/**
 * URL do perfil público de uma conta, gerada a cada render a partir do apelido atual.
 *
 * O segmento `/u/<apelido>` nunca é guardado: sai do perfil publicado no momento do render, no purpose `main`. Quando o
 * apelido muda, o link muda junto, porque o perfil entra nas tags de cache devolvidas em $cache. Sem perfil público
 * (apelido ausente, inválido ou perfil privado) o método devolve NULL, e quem renderiza mostra só o nome.
 */
final class PublicProfileLink implements PublicProfileLinkInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly DomainPurposeManager $purposeManager,
  ) {}

  /**
   * Devolve a URL pública da conta, ou NULL quando não há perfil público. Acumula as dependências de cache em $cache.
   */
  public function build(UserInterface $account, CacheableMetadata $cache): ?Url {
    $cache->addCacheableDependency($account);
    $profile = PerfilDoPapel::principal($this->entityTypeManager->getStorage('profile'), $account);
    if ($profile !== NULL) {
      $cache->addCacheableDependency($profile);
    }

    $nickname = $profile && $profile->hasField('field_nickname') ? trim((string) $profile->get('field_nickname')->value) : '';
    $publico = $profile ? (bool) $profile->get('field_perfil_publico')->value : TRUE;
    $publico = $publico || PublicProfilePolicy::isObrigatorioPublico($account->getRoles());
    $slug = AccountNames::publicRouteSlug($nickname);
    if (!$publico || $slug === NULL) {
      return NULL;
    }

    return $this->purposeManager->routeUrl('main', 'aculta_user.perfil_publico', ['apelido' => $slug]);
  }

}
