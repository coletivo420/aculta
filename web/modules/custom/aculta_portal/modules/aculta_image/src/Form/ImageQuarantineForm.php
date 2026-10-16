<?php

declare(strict_types=1);

namespace Drupal\aculta_image\Form;

use Drupal\aculta_image\Audit\ImageAudit;
use Drupal\aculta_image\Quarantine\ImageQuarantine;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Quarentena de imagens candidatas: enviar, restaurar e excluir vencidas. Cada ação é separada e confirmada pelo botão.
 */
final class ImageQuarantineForm extends FormBase {

  public function __construct(
    private readonly ImageAudit $audit,
    private readonly ImageQuarantine $quarantine,
    private readonly EntityTypeManagerInterface $entityTypeManagerService,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('aculta_image.audit'),
      $container->get('aculta_image.quarantine'),
      $container->get('entity_type.manager'),
    );
  }

  public function getFormId(): string {
    return 'aculta_image_quarantine_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $candidatas = [];
    foreach ($this->audit->candidatas(50) as $c) {
      $candidatas[$c['fid']] = $c['uri'] . ' (' . round($c['bytes'] / 1024, 1) . ' KB)';
    }
    $form['candidatas'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Candidatas sem referência'),
      '#options' => $candidatas,
      '#description' => $this->t('Enviar move o arquivo para a área privada. Nada é apagado.'),
    ];
    $form['enviar'] = ['#type' => 'submit', '#value' => $this->t('Enviar selecionadas à quarentena'), '#submit' => ['::enviar']];

    $quarentena = [];
    foreach ($this->quarantine->listar() as $linha) {
      $quarentena[(int) $linha['fid']] = $linha['uri_original'] . ' — ' . ($linha['vencida'] ? $this->t('vencida') : $this->t('vence em @d', ['@d' => date('d/m/Y', (int) $linha['vence_em'])]));
    }
    $form['quarentena'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Na quarentena'),
      '#options' => $quarentena,
    ];
    $form['restaurar'] = ['#type' => 'submit', '#value' => $this->t('Restaurar selecionadas'), '#submit' => ['::restaurar']];
    $form['excluir'] = [
      '#type' => 'submit',
      '#value' => $this->t('Excluir vencidas (sem referência)'),
      '#submit' => ['::excluir'],
      '#attributes' => ['onclick' => 'return confirm(' . json_encode((string) $this->t('Excluir as imagens vencidas que ainda não têm referência? Esta ação não pode ser desfeita.')) . ');'],
    ];
    return $form;
  }

  public function enviar(array &$form, FormStateInterface $form_state): void {
    $enviadas = 0;
    foreach (array_filter($form_state->getValue('candidatas') ?? []) as $fid) {
      $arquivo = $this->entityTypeManagerService->getStorage('file')->load((int) $fid);
      if ($arquivo && $this->quarantine->enviar($arquivo, 'candidata revisada no relatório')) {
        $enviadas++;
      }
    }
    $this->messenger()->addStatus($this->t('@n arquivo(s) enviado(s) à quarentena.', ['@n' => $enviadas]));
  }

  public function restaurar(array &$form, FormStateInterface $form_state): void {
    $restauradas = 0;
    foreach (array_filter($form_state->getValue('quarentena') ?? []) as $fid) {
      if ($this->quarantine->restaurar((int) $fid)) {
        $restauradas++;
      }
    }
    $this->messenger()->addStatus($this->t('@n arquivo(s) restaurado(s).', ['@n' => $restauradas]));
  }

  public function excluir(array &$form, FormStateInterface $form_state): void {
    $apagadas = $this->quarantine->excluirVencidas();
    $this->messenger()->addStatus($this->t('@n arquivo(s) vencido(s) excluído(s).', ['@n' => $apagadas]));
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {}

}
