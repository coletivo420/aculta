<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\RestrictedForum;

use Drupal\node\NodeInterface;

/**
 * Resolve a relação entre tópicos do Fórum e Groups restritos a associados.
 *
 * É um adapter do Fórum: não presume que o Group esteja configurado, não
 * reutiliza os tipos `lms_course`/`lms_class` e devolve vazio quando não há
 * tipo de Group configurado. Sem tipo configurado, nenhum tópico é restrito.
 */
interface RestrictedForumGroupResolverInterface {

  /**
   * Tipo de Group configurado para fóruns restritos, ou NULL.
   *
   * NULL significa "recurso desligado": sem tipo válido, nenhum tópico é
   * tratado como restrito e nenhuma membership é sincronizada.
   */
  public function configuredGroupType(): ?string;

  /**
   * IDs dos Groups restritos que contêm o tópico ou a sua categoria.
   *
   * @return int[]
   *   IDs dos Groups do tipo configurado, em ordem estável e sem duplicados.
   */
  public function groupIdsForTopic(NodeInterface $topic): array;

  /**
   * Groups do tipo configurado.
   *
   * @return \Drupal\group\Entity\GroupInterface[]
   *   Groups existentes, indexados pelo ID.
   */
  public function groupsOfType(): array;

}
