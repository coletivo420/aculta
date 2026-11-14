"""CRUD administrativo e concessão manual; remove só os próprios dados de teste."""
import json
import pathlib
import runpy
import urllib.error

env = runpy.run_path(str(pathlib.Path(__file__).with_name('http-smoke.py')))
drush, page, session, submit, check = [env[key] for key in ['drush', 'page', 'session', 'submit', 'check']]
context, MAIN, ACCOUNT = [env[key] for key in ['context', 'MAIN', 'ACCOUNT']]
fixture = 'verificacao_game_temporaria'
assert drush(f"print isset(\\Drupal::service('aculta_user.achievement_definitions')->all()['{fixture}']) ? 'exists' : 'absent';") == 'absent'
new_config = drush(r"print \Drupal::config('aculta_user.achievement_definitions')->isNew() ? 'yes' : 'no';") == 'yes'
admin, member = session(context['admin']), session('teste.comum')
root = MAIN + '/painel-administrativo/game/conquistas'
try:
    catalog = page(admin, root)
    check('Catálogo administrativo permite criar e editar', 'Criar conquista' in catalog and 'Apoiadore' in catalog)
    created = submit(admin, root + '/criar', 'id', {'id': fixture, 'label': 'Conquista temporária de verificação', 'description': 'Critério temporário para verificar os controles administrativos.', 'enabled': '1', 'image': ''})
    check('POST cria definição de conquista', 'Conquista salva.' in created and 'Conquista temporária de verificação' in created)
    edited = submit(admin, root + '/' + fixture + '/editar', 'id', {'label': 'Conquista temporária editada', 'triggers[order_completed]': 'order_completed', 'triggers[donation_completed]': 'donation_completed', 'image': 'fundadore'})
    check('POST edita nome e formas de concessão', 'Conquista salva.' in edited and 'Conquista temporária editada' in edited)
    state = json.loads(drush(f"print json_encode(\\Drupal::service('aculta_user.achievement_definitions')->all()['{fixture}']);"))
    check('Definição inclui pedido, apoio direto e administrador', set(state['triggers']) == {'manual', 'order_completed', 'donation_completed'})
    check('Mudança de critério avança versão', state['criterion_version'] == 2)
    own = page(member, ACCOUNT + '/atividade/conquistas')
    check('Nova definição integra conta e preferências', 'Conquista temporária editada' in own)
    grant_url = root + '/conceder'
    granted = submit(admin, grant_url, 'person', {'person': 'teste.comum (' + str(context['uid']) + ')', 'achievement': fixture, 'operation': 'grant', 'reason': 'Verificação temporária de concessão manual.'})
    check('POST concede com motivo', 'Operação registrada no histórico da conquista.' in granted)
    award = json.loads(drush(f"$s = \\Drupal::entityTypeManager()->getStorage('aculta_badge_award'); $ids = $s->getQuery()->accessCheck(FALSE)->condition('uid', {context['uid']})->condition('badge', '{fixture}')->execute(); $a = $s->load(reset($ids)); print json_encode(['active' => $a->get('revogado_em')->isEmpty(), 'manual' => $a->get('origem')->value === 'manual', 'actor' => (int) $a->get('concedido_por')->target_id, 'reason' => $a->get('motivo_concessao')->value]);"))
    check('Histórico guarda ator, origem e motivo', award['active'] and award['manual'] and award['actor'] > 0 and 'temporária' in award['reason'])
    public = page(session(), MAIN + '/u/' + context['nickname'].lower())
    check('Perfil recebe conquista e imagem configuradas', 'Conquista temporária editada' in public and 'fundadore.png' in public)
    check('Motivo administrativo não aparece em público', award['reason'] not in public)
    revoked = submit(admin, grant_url, 'person', {'person': 'teste.comum (' + str(context['uid']) + ')', 'achievement': fixture, 'operation': 'revoke', 'reason': 'Encerramento da verificação temporária.'})
    check('POST revoga concessão manual', 'Operação registrada no histórico da conquista.' in revoked)
    historical = json.loads(drush(f"$s = \\Drupal::entityTypeManager()->getStorage('aculta_badge_award'); $ids = $s->getQuery()->accessCheck(FALSE)->condition('uid', {context['uid']})->condition('badge', '{fixture}')->execute(); $a = $s->load(reset($ids)); print json_encode(['rows' => count($ids), 'revoked' => !$a->get('revogado_em')->isEmpty(), 'reason' => $a->get('motivo_revogacao')->value]);"))
    check('Revogação preserva registro e motivo', historical['rows'] == 1 and historical['revoked'] and 'temporária' in historical['reason'])
    history = page(admin, MAIN + '/painel-administrativo/game/historico')
    check('Histórico administrativo exibe os motivos', 'Verificação temporária de concessão manual.' in history and 'Encerramento da verificação temporária.' in history)
    check('Revogação invalida perfil público', 'Conquista temporária editada' not in page(session(), MAIN + '/u/' + context['nickname'].lower()))
    for path in ['/criar', '/' + fixture + '/editar', '/conceder']:
        try:
            page(member, root + path)
            check('Membro não acessa operação ' + path, False)
        except urllib.error.HTTPError as error:
            check('Membro não acessa operação ' + path, error.code == 403)
finally:
    # O ID foi confirmado ausente antes da criação. Só dados desta fixture são removidos.
    drush(f"$s = \\Drupal::entityTypeManager()->getStorage('aculta_badge_award'); $ids = $s->getQuery()->accessCheck(FALSE)->condition('uid', {context['uid']})->condition('badge', '{fixture}')->execute(); $s->delete($s->loadMultiple($ids)); $config = \\Drupal::configFactory()->getEditable('aculta_user.achievement_definitions'); $config->clear('definitions.{fixture}'); if ({'TRUE' if new_config else 'FALSE'} && !$config->get('definitions')) {{ $config->delete(); }} else {{ $config->save(); }}")
    (env['ROOT'] / '.suite-out/game/admin-results.json').write_text(json.dumps(env['RESULTS'], ensure_ascii=False, indent=2))
print(f"{len(env['RESULTS'])}/{len(env['RESULTS'])} verificações passaram; fixture removida.")
