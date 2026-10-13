<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\profile\Entity\ProfileInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Minha conta > Configurações > Perfil e privacidade.
 *
 * Define o apelido (caminho público /u/<apelido>), a visibilidade do perfil e a escolha de exibir cada item.
 * Cidade, UF, WhatsApp, nome completo e endereço não aparecem aqui: nunca são públicos.
 */
final class ProfileVisibilityForm extends FormBase {

  /**
   * Palavras que não podem ser apelido, para não colidir com rotas do site.
   */
  public const RESERVED = [
    'admin', 'u', 'perfil', 'entrar', 'sair', 'cadastro', 'minha-conta', 'atividade', 'configuracoes',
    'dados', 'pedidos', 'seguranca', 'conexoes', 'cursos', 'curso', 'busca', 'carrinho', 'pagamento',
    'user', 'node', 'lms', 'api', 'autores',
  ];

  public function __construct(
    private EntityTypeManagerInterface $entityTypeManagerService,
    private AccountProxyInterface $currentUserService,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('entity_type.manager'), $container->get('current_user'));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_user_profile_visibility_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $profile = $this->loadOrCreateProfile();

    $form['apelido'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Apelido'),
      '#description' => $this->t('Caminho público do seu perfil: /u/apelido. Use de 3 a 40 letras minúsculas sem acento, números ou hífen.'),
      '#default_value' => (string) $profile->get('field_nickname')->value,
      '#maxlength' => 40,
      '#required' => TRUE,
    ];

    $form['publico'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Perfil público'),
      '#description' => $this->t('Quando desligado, o perfil não aparece para ninguém.'),
      '#default_value' => $this->flag($profile, 'field_perfil_publico', TRUE),
    ];

    $form['exibir_foto'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Mostrar minha foto'),
      '#default_value' => $this->flag($profile, 'field_exibir_foto', FALSE),
    ];

    $form['exibir_bio'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Mostrar minha bio'),
      '#default_value' => $this->flag($profile, 'field_exibir_bio', FALSE),
    ];
    $form['bio'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Bio'),
      '#description' => $this->t('Texto curto, até 500 caracteres.'),
      '#default_value' => $profile->hasField('field_bio') ? (string) $profile->get('field_bio')->value : '',
      '#maxlength' => 500,
    ];

    $form['exibir_email'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Mostrar meu e-mail'),
      '#default_value' => $this->flag($profile, 'field_exibir_email', FALSE),
    ];

    $form['exibir_redes'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Mostrar minhas redes sociais'),
      '#default_value' => $this->flag($profile, 'field_exibir_redes', FALSE),
    ];
    $form['redes'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Redes sociais'),
      '#description' => $this->t('Um endereço https por linha, até 5 linhas.'),
      '#default_value' => $this->redesAsText($profile),
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Salvar')];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $apelido = trim((string) $form_state->getValue('apelido'));
    if (!preg_match('/^[a-z0-9][a-z0-9-]{2,39}$/', $apelido)) {
      $form_state->setErrorByName('apelido', $this->t('Apelido inválido: use de 3 a 40 letras minúsculas sem acento, números ou hífen.'));
    }
    elseif (in_array($apelido, self::RESERVED, TRUE)) {
      $form_state->setErrorByName('apelido', $this->t('Este apelido não está disponível.'));
    }
    elseif ($this->nicknameTakenByOther($apelido)) {
      $form_state->setErrorByName('apelido', $this->t('Este apelido já está em uso.'));
    }

    $lines = $this->redesLines((string) $form_state->getValue('redes'));
    if (count($lines) > 5) {
      $form_state->setErrorByName('redes', $this->t('Use no máximo 5 endereços.'));
    }
    foreach ($lines as $line) {
      if (!filter_var($line, FILTER_VALIDATE_URL) || !str_starts_with($line, 'https://')) {
        $form_state->setErrorByName('redes', $this->t('Endereço inválido: @url. Use https://.', ['@url' => $line]));
        break;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $profile = $this->loadOrCreateProfile();
    $profile->set('field_nickname', trim((string) $form_state->getValue('apelido')));
    $profile->set('field_perfil_publico', (bool) $form_state->getValue('publico'));
    $profile->set('field_exibir_foto', (bool) $form_state->getValue('exibir_foto'));
    $profile->set('field_exibir_bio', (bool) $form_state->getValue('exibir_bio'));
    $profile->set('field_bio', (string) $form_state->getValue('bio'));
    $profile->set('field_exibir_email', (bool) $form_state->getValue('exibir_email'));
    $profile->set('field_exibir_redes', (bool) $form_state->getValue('exibir_redes'));
    $links = [];
    foreach ($this->redesLines((string) $form_state->getValue('redes')) as $line) {
      $links[] = ['uri' => $line, 'title' => ''];
    }
    $profile->set('field_redes_sociais', $links);
    $profile->save();

    $this->messenger()->addStatus($this->t('Configurações de perfil salvas.'));
    $form_state->setRedirect('aculta_user.configuracoes_perfil');
  }

  /**
   * Carrega o perfil participante da conta atual, criando-o se ainda não existe.
   */
  private function loadOrCreateProfile(): ProfileInterface {
    $storage = $this->entityTypeManagerService->getStorage('profile');
    $uid = (int) $this->currentUserService->id();
    $profile = $storage->loadByUser($this->currentUserService->getAccount(), 'participante');
    if ($profile === NULL) {
      $profile = $storage->create(['type' => 'participante', 'uid' => $uid]);
    }
    return $profile;
  }

  /**
   * Indica se outra conta já usa o apelido.
   */
  private function nicknameTakenByOther(string $apelido): bool {
    $ids = $this->entityTypeManagerService->getStorage('profile')->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'participante')
      ->condition('field_nickname', $apelido)
      ->execute();
    $own = (int) $this->loadOrCreateProfile()->id();
    foreach ($ids as $id) {
      if ((int) $id !== $own) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Lê um campo booleano com valor padrão quando o campo ainda não tem valor.
   */
  private function flag(ProfileInterface $profile, string $field, bool $default): bool {
    if (!$profile->hasField($field) || $profile->get($field)->isEmpty()) {
      return $default;
    }
    return (bool) $profile->get($field)->value;
  }

  /**
   * Transforma os links do perfil em texto, uma linha por endereço.
   */
  private function redesAsText(ProfileInterface $profile): string {
    if (!$profile->hasField('field_redes_sociais')) {
      return '';
    }
    $uris = [];
    foreach ($profile->get('field_redes_sociais') as $item) {
      $uris[] = (string) $item->uri;
    }
    return implode("\n", $uris);
  }

  /**
   * Quebra o texto de redes em linhas vazias descartadas.
   *
   * @return string[]
   */
  private function redesLines(string $text): array {
    return array_values(array_filter(array_map('trim', preg_split('/\R/', $text) ?: [])));
  }

}
