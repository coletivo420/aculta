<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Form;

use Drupal\aculta_mailer\Monitor\MailMonitor;
use Drupal\aculta_mailer\Transport\SecretProviderInterface;
use Drupal\aculta_mailer\Transport\SecretWriterInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Dados do monitoramento IMAP (servidor, usuário, pasta e intervalos). A senha não entra aqui: fica em IMAP_PASSWORD.
 */
final class MailImapSettingsForm extends ConfigFormBase {

  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typedConfigManager,
    private readonly MailMonitor $monitor,
    private readonly ?SecretProviderInterface $secrets,
    private readonly ?SecretWriterInterface $writer,
  ) {
    parent::__construct($config_factory, $typedConfigManager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get(MailMonitor::class),
      $container->has(SecretProviderInterface::class) ? $container->get(SecretProviderInterface::class) : NULL,
      $container->has(SecretWriterInterface::class) ? $container->get(SecretWriterInterface::class) : NULL,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_mailer_imap_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [MailMonitor::CONFIG];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config(MailMonitor::CONFIG);
    $state = $this->monitor->enabled() ? $this->t('ligado') : $this->t('desligado');
    $form['switch'] = [
      '#markup' => '<p>' . $this->t('Ligar ou desligar o monitoramento: página Entrega, alertas e resumo. Estado atual: @state.', ['@state' => $state]) . '</p>',
    ];
    $form['host'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Servidor IMAP'),
      '#default_value' => (string) $config->get('host'),
      '#maxlength' => 255,
      '#description' => $this->t('Conexão IMAP segura (TLS implícito). Ex.: o servidor de correio do domínio.'),
    ];
    $form['port'] = [
      '#type' => 'number',
      '#title' => $this->t('Porta'),
      '#default_value' => (int) ($config->get('port') ?: 993),
      '#min' => 1,
      '#max' => 65535,
    ];
    $form['user'] = [
      '#type' => 'textfield',
      '#title' => $this->t('E-mail (usuário IMAP)'),
      '#default_value' => (string) $config->get('user'),
      '#maxlength' => 255,
      '#description' => $this->t('Caixa que recebe os relatórios DMARC e as notificações de falha de entrega.'),
    ];
    $canWrite = $this->writer !== NULL && $this->currentUser()->hasPermission('administer aculta secrets');
    $form['password'] = [
      '#type' => 'password',
      '#title' => $this->t('Senha IMAP'),
      '#description' => $this->passwordDescription($canWrite),
      '#disabled' => !$canWrite,
      '#attributes' => ['autocomplete' => 'new-password'],
    ];
    $form['mailbox'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Pasta'),
      '#default_value' => (string) ($config->get('mailbox') ?: 'INBOX'),
      '#maxlength' => 255,
    ];
    $form['intervals'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Intervalos'),
      'bounce_interval' => [
        '#type' => 'number',
        '#title' => $this->t('Bounces (segundos)'),
        '#default_value' => (int) ($config->get('bounce_interval') ?: MailMonitor::BOUNCE_INTERVAL),
        '#min' => MailMonitor::MIN_INTERVAL,
        '#description' => $this->t('Padrão: 1800 (30 minutos). Mínimo: @min.', ['@min' => MailMonitor::MIN_INTERVAL]),
      ],
      'dmarc_interval' => [
        '#type' => 'number',
        '#title' => $this->t('DMARC (segundos)'),
        '#default_value' => (int) ($config->get('dmarc_interval') ?: MailMonitor::DMARC_INTERVAL),
        '#min' => MailMonitor::MIN_INTERVAL,
        '#description' => $this->t('Padrão: 43200 (12 horas). Mínimo: @min.', ['@min' => MailMonitor::MIN_INTERVAL]),
      ],
    ];
    $form['status'] = [
      '#type' => 'details',
      '#title' => $this->t('Estado'),
      '#open' => TRUE,
      'password' => [
        '#markup' => '<p>' . $this->t('Senha (IMAP_PASSWORD): @state.', [
          '@state' => $this->monitor->passwordConfigured() ? $this->t('configurada') : $this->t('não configurada'),
        ]) . $this->changeLink() . '</p>',
      ],
      'runs' => ['#markup' => '<p>' . $this->runsText() . '</p>'],
      'error' => ['#markup' => $this->errorText()],
      'reports' => $this->reportsTable(),
    ];
    $form['actions']['test'] = [
      '#type' => 'submit',
      '#value' => $this->t('Testar conexão'),
      '#submit' => ['::testConnection'],
      '#limit_validation_errors' => [],
      '#description' => $this->t('Testa os dados digitados, sem gravar nada e sem alterar as leituras.'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $password = (string) $form_state->getValue('password');
    $form_state->setValue('password', '');
    if ($password !== '') {
      // Vai ao arquivo de segredos, nunca à configuração. Erro de gravação aparece sem o valor.
      $result = $this->writer?->save(MailMonitor::PASSWORD, $password) ?? [
        'ok' => FALSE,
        'message' => (string) $this->t('Gravação de credenciais indisponível.'),
      ];
      $result['ok']
        ? $this->messenger()->addStatus($this->t('Senha IMAP gravada no arquivo de segredos.'))
        : $this->messenger()->addError($this->t('Senha IMAP não gravada: @message', ['@message' => $result['message']]));
    }
    $this->config(MailMonitor::CONFIG)
      ->set('host', trim((string) $form_state->getValue('host')))
      ->set('port', (int) $form_state->getValue('port'))
      ->set('user', trim((string) $form_state->getValue('user')))
      ->set('mailbox', trim((string) $form_state->getValue('mailbox')) ?: 'INBOX')
      ->set('bounce_interval', max(MailMonitor::MIN_INTERVAL, (int) $form_state->getValue('bounce_interval')))
      ->set('dmarc_interval', max(MailMonitor::MIN_INTERVAL, (int) $form_state->getValue('dmarc_interval')))
      ->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * Testa a conexão com os dados do formulário: quantas mensagens a caixa tem. Não lê corpo nem altera cursor.
   */
  public function testConnection(array &$form, FormStateInterface $form_state): void {
    try {
      $typed = (string) $form_state->getValue('password');
      $client = $this->monitor->clientFor(
        trim((string) $form_state->getValue('host')),
        (int) ($form_state->getValue('port') ?: 993),
        trim((string) $form_state->getValue('user')),
        trim((string) $form_state->getValue('mailbox')) ?: 'INBOX',
        $typed !== '' ? $typed : $this->monitor->storedPassword(),
      );
      if ($client === NULL) {
        $this->messenger()->addWarning($this->t('Informe servidor, usuário e senha (ou grave a senha antes) para testar.'));
        return;
      }
      $this->messenger()->addStatus($this->t('Conexão feita. Mensagens na pasta: @n.', ['@n' => count($client->uidsAbove(0))]));
    }
    catch (\Throwable $error) {
      $this->messenger()->addError($this->t('Não foi possível ler a caixa: @message', ['@message' => $error->getMessage()]));
    }
  }

  /**
   * Texto do campo de senha: o que acontece ao gravar e por que não fica no banco.
   */
  private function passwordDescription(bool $canWrite): string {
    if (!$canWrite) {
      return (string) $this->t('Gravar a senha exige a permissão de administrar credenciais (@perm).', ['@perm' => 'administer aculta secrets']);
    }
    $state = $this->monitor->passwordConfigured() ? $this->t('Já há uma senha gravada; campo vazio mantém a atual.') : $this->t('Nenhuma senha gravada ainda.');
    return (string) $this->t('@state Fica no arquivo de segredos do servidor, nunca no banco nem na configuração.', ['@state' => $state]);
  }

  /**
   * Link para alterar a senha no painel de credenciais, quando o usuário tem a permissão.
   */
  private function changeLink(): string {
    if ($this->secrets === NULL || !$this->currentUser()->hasPermission('administer aculta secrets')) {
      return '';
    }
    $url = Url::fromRoute('aculta_portal.secrets_edit', ['name' => MailMonitor::PASSWORD])->toString();
    return ' <a href="' . $url . '">' . $this->t('Alterar no painel de credenciais') . '</a>';
  }

  /**
   * Última execução de cada leitura.
   */
  private function runsText(): string {
    $last = $this->monitor->lastRuns();
    $format = fn(int $when): string => $when > 0 ? date('Y-m-d H:i', $when) : (string) $this->t('nunca');
    return (string) $this->t('Última leitura de bounces: @bounce. Última leitura DMARC: @dmarc.', [
      '@bounce' => $format($last['bounce']),
      '@dmarc' => $format($last['dmarc']),
    ]);
  }

  /**
   * Último erro de leitura, sem credenciais.
   */
  private function errorText(): string {
    $error = $this->monitor->lastError();
    if ($error === NULL) {
      return '';
    }
    return '<p>' . $this->t('Última falha (@kind, @when): @message', [
      '@kind' => $error['kind'],
      '@when' => date('Y-m-d H:i', $error['when']),
      '@message' => $error['message'],
    ]) . '</p>';
  }

  /**
   * Resumos dos relatórios DMARC recebidos. Sem IPs de origem.
   */
  private function reportsTable(): array {
    $rows = [];
    foreach ($this->monitor->reports() as $report) {
      $rows[] = [
        $report['org'],
        $report['domain'],
        date('Y-m-d', $report['begin']) . ' a ' . date('Y-m-d', $report['end']),
        $report['total'],
        $report['pass'],
        $report['fail'],
        date('Y-m-d H:i', $report['received']),
      ];
    }
    return [
      '#type' => 'table',
      '#header' => [
        $this->t('Remetente do relatório'),
        $this->t('Domínio'),
        $this->t('Período'),
        $this->t('Total'),
        $this->t('Aprovadas'),
        $this->t('Reprovadas'),
        $this->t('Recebido em'),
      ],
      '#rows' => $rows,
      '#empty' => $this->t('Nenhum relatório DMARC recebido ainda.'),
    ];
  }

}
