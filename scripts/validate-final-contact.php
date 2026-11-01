<?php
/** Local transaction test; outgoing mail is captured by Core, never delivered. */
use Drupal\webform\Entity\WebformSubmission;
use Drupal\webform\WebformSubmissionForm;
$host = \Drupal::request()->getHost();
if (!in_array($host, ['localhost', '127.0.0.1'], TRUE) && !str_ends_with($host, 'aculta.toca.net.br')) throw new RuntimeException('Local or test host only.');
// CAPTCHA: no ambiente de teste o Turnstile usa a chave de teste (TurnstileKeyOverride, TURNSTILE_TEST_KEYS_JSON).
// Decisão do responsável (2026-10-20). O token fictício é o da documentação do Cloudflare para a chave de teste e
// vai na requisição, não nos dados. O envio roda em transação revertida e com o coletor de e-mail (sem envio externo).
\Drupal::request()->request->set('cf-turnstile-response', 'XXXX.DUMMY.TOKEN.XXXX');
$transaction = \Drupal::database()->startTransaction();
$originalMailInterface = \Drupal::config('system.mail')->get('interface');
$initial = count(\Drupal::state()->get('system.test_mail_collector', []));
try {
  \Drupal::configFactory()->getEditable('system.mail')->set('interface', ['default' => 'test_mail_collector', 'webform' => 'test_mail_collector'])->save();
  if (\Drupal::service('plugin.manager.mail')->getInstance(['module' => 'webform', 'key' => 'email'])::class !== \Drupal\Core\Mail\Plugin\Mail\TestMailCollector::class) throw new RuntimeException('Safe mail collector was not selected; do not submit.');
  $submission = WebformSubmission::create(['webform_id' => 'aculta_contact', 'uid' => 0, 'langcode' => 'pt-br', 'data' => ['name' => 'Verificação local', 'email' => '4e20coletivo@gmail.com', 'subject' => 'Teste local sem envio externo', 'message' => 'Validação técnica temporária do formulário.']]);
  $result = WebformSubmissionForm::submitWebformSubmission($submission);
  if (!$result instanceof WebformSubmission || !$result->id()) throw new RuntimeException('Contact validation/save failed.');
  $messages = \Drupal::state()->get('system.test_mail_collector', []);
  if (count($messages) !== $initial + 1 || end($messages)['to'] !== '4e20coletivo@gmail.com') throw new RuntimeException('Expected one institutional notification.');
  echo "Four-field submission validated and saved; notification captured locally, no external mail: OK\n";
} finally {
  $transaction->rollBack();
  // Restaura a configuração original: reset() só limpa o cache e não desfaz o save feito acima.
  \Drupal::configFactory()->getEditable('system.mail')->set('interface', $originalMailInterface)->save();
  \Drupal::entityTypeManager()->getStorage('webform_submission')->resetCache();
  \Drupal\Core\Cache\Cache::invalidateTags(['config:system.mail', 'webform_submission_list']);
}
echo "Temporary submission, mail collector and mail configuration rolled back.\n";
