<?php

declare(strict_types=1);

namespace Drupal\aculta_qrcode\ListBuilder;

use Drupal\aculta_qrcode\Entity\QrCode;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Lista administrativa de códigos QR dinâmicos com downloads por formato.
 */
final class QrCodeListBuilder extends EntityListBuilder {

  /**
   * Formatos oferecidos para download.
   */
  private const FORMATS = ['svg', 'png', 'webp', 'pdf'];

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    return [
      'title' => $this->t('Título'),
      'code' => $this->t('Código'),
      'target' => $this->t('Destino'),
      'state' => $this->t('Estado'),
      'downloads' => $this->t('Baixar'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    assert($entity instanceof QrCode);
    $downloads = [];
    foreach (self::FORMATS as $format) {
      $downloads[] = Link::fromTextAndUrl(
        strtoupper($format),
        Url::fromRoute('aculta_qrcode.download', ['aculta_qr_code' => $entity->id(), 'formato' => $format]),
      )->toRenderable();
    }
    return [
      'title' => $entity->toLink($entity->label())->toRenderable(),
      'code' => $entity->getCode(),
      'target' => $entity->getTargetUrl(),
      'state' => $entity->isPublished() ? $this->t('Ativo') : $this->t('Desativado'),
      'downloads' => ['data' => ['#theme' => 'item_list', '#items' => $downloads]],
    ] + parent::buildRow($entity);
  }

}
