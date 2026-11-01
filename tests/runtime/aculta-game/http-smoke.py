"""Fluxos HTTP reais de Game no Runtime de testes, sem criar contas ou conteúdo."""
import base64
import http.cookiejar
import json
import pathlib
import subprocess
import sys
import urllib.error
import urllib.parse
import urllib.request
from html.parser import HTMLParser

ROOT = pathlib.Path(__file__).resolve().parents[3]
DEPLOY = json.loads((ROOT / 'web/modules/custom/aculta_portal/modules/aculta_deployer/config/deploy.json').read_text())
MAIN, ACCOUNT = [value.rstrip('/') for value in sys.argv[1:3]]
HOSTS = {entry['test_host'] for entry in DEPLOY['domain_transition']['entries']}
assert all(urllib.parse.urlparse(url).hostname in HOSTS for url in [MAIN, ACCOUNT]), 'Somente hosts de teste do Deployer.'
RESULTS = []


def drush(code):
    return subprocess.check_output(['php', 'vendor/drush/drush/drush.php', '--uri=' + ACCOUNT, 'php:eval', code], cwd=ROOT, text=True).strip()


def session(name=None):
    opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    if name:
        url = subprocess.check_output(['php', 'vendor/drush/drush/drush.php', '--uri=' + ACCOUNT, 'uli', '--name=' + name, '--no-browser'], cwd=ROOT, text=True).strip().splitlines()[-1]
        opener.open(url, timeout=30).read()
    return opener


def page(opener, url):
    response = opener.open(url, timeout=30)
    return response.read().decode()


def check(label, ok):
    RESULTS.append({'name': label, 'ok': bool(ok)})
    print(('PASS ' if ok else 'FAIL ') + label, flush=True)
    assert ok, label


class Forms(HTMLParser):
    def __init__(self, html):
        super().__init__(convert_charrefs=True)
        self.forms, self.current = [], None
        self.feed(html)

    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        if tag == 'form':
            self.current = {'action': attrs.get('action', ''), 'values': {}, 'names': set()}
            self.forms.append(self.current)
        if tag == 'input' and self.current is not None and attrs.get('name'):
            name = attrs['name']
            self.current['names'].add(name)
            if attrs.get('type') not in ('checkbox', 'radio') or 'checked' in attrs:
                self.current['values'][name] = attrs.get('value', '1')

    def handle_endtag(self, tag):
        if tag == 'form':
            self.current = None


def submit(opener, url, marker, changes):
    form = next(form for form in Forms(page(opener, url)).forms if marker in form['names'])
    values = form['values']
    for name, value in changes.items():
        if value is None:
            values.pop(name, None)
        else:
            values[name] = value
    request = urllib.request.Request(urllib.parse.urljoin(url, form['action']), data=urllib.parse.urlencode(values).encode())
    return page(opener, request)


context = json.loads(drush(r"""
if (\Drupal::database()->getConnectionOptions()['database'] !== 'aculta_runtime') { throw new \RuntimeException('Somente Runtime de testes.'); }
$users = \Drupal::entityTypeManager()->getStorage('user')->loadByProperties(['name' => 'teste.comum']); $u = reset($users);
if (!$u) { throw new \RuntimeException('Conta de teste necessária.'); }
$uid = (int) $u->id(); $p = \Drupal::entityTypeManager()->getStorage('profile')->loadByUser($u, 'participante');
$awards = \Drupal::service('aculta_user.badge_service')->activeFor($uid); $award = reset($awards);
print json_encode(['uid' => $uid, 'nickname' => $p->get('field_nickname')->value, 'preferences' => \Drupal::service('user.data')->get('aculta_user', $uid, 'game_achievement_display'), 'badge' => $award->get('badge')->value, 'label' => \Drupal\aculta_user\Badge\BadgeCatalog::all()[$award->get('badge')->value]['label'], 'admin' => \Drupal::entityTypeManager()->getStorage('user')->load(1)->getAccountName(), 'mission_enabled' => \Drupal::config('aculta_game.missions')->get('missions.comunidade.enabled')]);
"""))
try:
    member, anonymous, admin = session('teste.comum'), session(), session(context['admin'])
    own_url = ACCOUNT + '/atividade/conquistas'
    profile_url = MAIN + '/u/' + context['nickname'].lower()
    check('Visão geral oferece entrada para Game', 'Ver missões e escolher' in page(member, ACCOUNT + '/'))
    own = page(member, own_url)
    check('Três missões com progresso aparecem', all(label in own for label in ['Primeira contribuição no Growroom420', 'Contribuição ao conhecimento', 'Primeira formação concluída', 'marco registrado']))
    check('Preferências de conquista aparecem', 'Salvar exibição das conquistas' in own)
    hidden = submit(member, own_url, 'hide_all', {'hide_all': '1'})
    check('POST salva ocultação global', 'Exibição das conquistas salva.' in hidden)
    check('Visitante não vê conquista oculta', context['label'] not in page(anonymous, profile_url))
    shown = submit(member, own_url, 'hide_all', {'hide_all': None, 'items[' + context['badge'] + '][show]': '1'})
    check('POST reexibe conquista', 'Exibição das conquistas salva.' in shown)
    check('Cache público acompanha reexibição', context['label'] in page(anonymous, profile_url))
    submit(member, own_url, 'hide_all', {'items[' + context['badge'] + '][show]': None})
    check('Ocultação individual é respeitada', context['label'] not in page(anonymous, profile_url))
    submit(member, own_url, 'hide_all', {'items[' + context['badge'] + '][show]': '1', 'items[' + context['badge'] + '][highlight]': '1', 'items[' + context['badge'] + '][weight]': '-10'})
    check('Destaque aparece no perfil público', 'em destaque' in page(anonymous, profile_url))
    check('Privacidade contém o acesso às escolhas', 'Escolher conquistas públicas' in page(member, ACCOUNT + '/configuracoes/privacidade'))
    admin_url = MAIN + '/painel-administrativo/game'
    controls = page(admin, admin_url)
    check('Administração tem três controles de missão', sum(name.startswith('missions[') for form in Forms(controls).forms for name in form['names']) == 3)
    saved = submit(admin, admin_url, 'missions[comunidade]', {'missions[comunidade]': None})
    check('POST administrativo desativa missão', drush(r"print json_encode(\Drupal::config('aculta_game.missions')->get('missions.comunidade.enabled'));") == 'false')
    check('Missão desativada some da conta', 'Primeira contribuição no Growroom420' not in page(member, own_url))
    submit(admin, admin_url, 'missions[comunidade]', {'missions[comunidade]': '1'})
    check('Missão reativada retorna à conta', 'Primeira contribuição no Growroom420' in page(member, own_url))
    try:
        forbidden = page(member, admin_url)
        check('Membro comum não acessa controles', 'missions[comunidade]' not in forbidden)
    except urllib.error.HTTPError as error:
        check('Membro comum não acessa controles', error.code == 403)
    redirected = admin.open(ACCOUNT + '/painel-administrativo/game', timeout=30)
    check('GET administrativo no host Conta canonicaliza para MAIN', redirected.url == admin_url)
    try:
        page(admin, urllib.request.Request(ACCOUNT + '/painel-administrativo/game', data=b'op=Salvar'))
        check('POST administrativo no host errado é negado', False)
    except urllib.error.HTTPError as error:
        check('POST administrativo no host errado é negado', error.code in (403, 404))
finally:
    encoded = base64.b64encode(json.dumps(context['preferences']).encode()).decode()
    enabled = 'TRUE' if context['mission_enabled'] else 'FALSE'
    drush(f"$before = json_decode(base64_decode('{encoded}'), TRUE); $data = \\Drupal::service('user.data'); if ($before === NULL) {{ $data->delete('aculta_user', {context['uid']}, 'game_achievement_display'); }} else {{ $data->set('aculta_user', {context['uid']}, 'game_achievement_display', $before); }} \\Drupal::service('cache_tags.invalidator')->invalidateTags(['aculta_game:display:{context['uid']}']); \\Drupal::configFactory()->getEditable('aculta_game.missions')->set('missions.comunidade.enabled', {enabled})->save();")
    out = ROOT / '.suite-out/game'
    out.mkdir(parents=True, exist_ok=True)
    (out / 'http-results.json').write_text(json.dumps(RESULTS, ensure_ascii=False, indent=2))
print(f'{len(RESULTS)}/{len(RESULTS)} verificações HTTP passaram; preferências e configuração restauradas.')
