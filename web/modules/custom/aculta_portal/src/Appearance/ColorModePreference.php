<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Appearance;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\user\UserDataInterface;

/**
 * Modo de cor do ACULTA420: preferência por usuário (user.data, banco) com padrão global.
 *
 * O Portal resolve o modo e entrega um valor neutro ao tema (light, dark ou auto). O tema só
 * apresenta; ele não lê usuário nem configuração.
 */
final class ColorModePreference {

  public const MODES = ['light', 'dark', 'auto'];

  public const DEFAULT_MODE = 'light';

  public const MODULE = 'aculta_portal';

  public const KEY = 'color_mode';

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly UserDataInterface $userData,
  ) {}

  /** Padrão do site, definido pelo administrador. */
  public function siteDefault(): string {
    $mode = (string) $this->configFactory->get('aculta_portal.appearance')->get('default_mode');
    return in_array($mode, self::MODES, TRUE) ? $mode : self::DEFAULT_MODE;
  }

  /** Escolha salva pela pessoa usuária, ou NULL se nunca escolheu. Uid 0 nunca tem escolha. */
  public function forUser(int $uid): ?string {
    if ($uid === 0) {
      return NULL;
    }
    $mode = $this->userData->get(self::MODULE, $uid, self::KEY);
    return is_string($mode) && in_array($mode, self::MODES, TRUE) ? $mode : NULL;
  }

  /** Modo efetivo: escolha da pessoa usuária, senão o padrão do site. */
  public function resolve(int $uid): string {
    return $this->forUser($uid) ?? $this->siteDefault();
  }

  public function save(int $uid, string $mode): void {
    if (!in_array($mode, self::MODES, TRUE)) {
      throw new \InvalidArgumentException('Modo de cor inválido.');
    }
    $this->userData->set(self::MODULE, $uid, self::KEY, $mode);
  }

}
