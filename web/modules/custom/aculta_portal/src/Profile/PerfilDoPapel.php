<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Profile;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\profile\Entity\ProfileInterface;

/**
 * Um tipo de perfil por papel: o perfil de uma conta é do tipo do papel mais alto que ela tem.
 *
 * Ordem de prioridade (do mais alto ao mais baixo): administrator, gerente_loja, lms_teacher, associado, email_confirmed,
 * authenticated. A hierarquia de papéis faz o papel superior herdar o inferior, então a prioridade segue a cadeia.
 * Sem tipo fixo no código: quem precisa do perfil chama principal() ou obter(), com o EntityTypeManager injetado.
 */
final class PerfilDoPapel {

  /** Tipos de perfil por papel, em ordem de prioridade. */
  public const TIPOS = ['administrator', 'gerente_loja', 'lms_teacher', 'associado', 'email_confirmed', 'authenticated'];

  /**
   * Tipo do perfil da conta: o primeiro tipo cujo papel ela tem.
   */
  public static function tipoDaConta(AccountInterface $conta): string {
    $papeis = $conta->getRoles();
    foreach (self::TIPOS as $tipo) {
      if (in_array($tipo, $papeis, TRUE)) {
        return $tipo;
      }
    }
    return 'authenticated';
  }

  /**
   * Perfil publicado da conta, procurando pelos tipos em ordem de prioridade. Preserva o perfil do papel anterior
   * enquanto a conta não tem o novo tipo.
   */
  public static function principal(EntityTypeManagerInterface $entityTypeManager, AccountInterface $conta): ?ProfileInterface {
    $storage = $entityTypeManager->getStorage('profile');
    foreach (self::TIPOS as $tipo) {
      $perfil = $storage->loadByUser($conta, $tipo);
      if ($perfil instanceof ProfileInterface) {
        return $perfil;
      }
    }
    return NULL;
  }

  /**
   * Perfil principal da conta, ou um perfil novo (ainda não salvo) do tipo do papel atual.
   */
  public static function obter(EntityTypeManagerInterface $entityTypeManager, AccountInterface $conta): ProfileInterface {
    return self::principal($entityTypeManager, $conta) ?? $entityTypeManager->getStorage('profile')->create([
      'type' => self::tipoDaConta($conta),
      'uid' => (int) $conta->id(),
    ]);
  }

  /**
   * Verdadeiro se o perfil é de um dos tipos de papel.
   */
  public static function ehTipoDePapel(ProfileInterface $perfil): bool {
    return in_array($perfil->bundle(), self::TIPOS, TRUE);
  }

}
