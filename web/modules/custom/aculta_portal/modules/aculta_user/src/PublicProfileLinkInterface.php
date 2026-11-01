<?php

declare(strict_types=1);

namespace Drupal\aculta_user;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Url;
use Drupal\user\UserInterface;

/** API para obter o link canônico do perfil público de uma conta. */
interface PublicProfileLinkInterface {

  /**
   * Retorna o link permitido e adiciona suas dependências à cache metadata.
   */
  public function build(UserInterface $account, CacheableMetadata $cache): ?Url;

}
