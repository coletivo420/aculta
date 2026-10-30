"""Teste do comportamento esperado de Minha conta > Meus dados > Endereço, no Firefox (X11), via geckodriver.

Uso: gerar o link único de login com `drush -l conta.aculta.toca.net.br uli --uid=<uid de teste>` e passar o arquivo
com o link: `DISPLAY=:0 python3 -I scripts/test-account-address-browser.py <arquivo_do_link>`. O link é de uso único.

Não imprime segredos. Entra pelo link único do Drush (procedimento documentado) e confere o formulário e o CEP.
Saída: uma linha por verificação, PASS ou FAIL, e o código de saída 1 se alguma falhar.
"""
import json
import os
import subprocess
import sys
import time
import urllib.error
import urllib.request

PORT = int(os.environ.get('WD_PORT', '4444'))  # use WD_PORT se a porta padrão estiver ocupada
BASE = f'http://127.0.0.1:{PORT}'
ULI = open(sys.argv[1], encoding='utf8').read().strip()
ACCOUNT = 'https://conta.aculta.toca.net.br/dados/endereco'
F = 'field_address[0][address]'
results = []


def call(method, path, body=None):
    if method == 'POST' and body is None:
        body = {}
    data = json.dumps(body).encode() if body is not None else None
    req = urllib.request.Request(BASE + path, data=data, method=method,
                                 headers={'Content-Type': 'application/json'})
    with urllib.request.urlopen(req, timeout=60) as resp:
        return json.loads(resp.read() or b'{}').get('value')


def check(name, ok, detail=''):
    results.append(ok)
    print(('PASS ' if ok else 'FAIL ') + name + (f' ({detail})' if detail else ''), flush=True)


def find(css):
    try:
        el = call('POST', '/session/%s/element' % SID, {'using': 'css selector', 'value': css})
    except urllib.error.HTTPError:
        return None
    return list(el.values())[0] if el else None


def find_all(css):
    els = call('POST', '/session/%s/elements' % SID, {'using': 'css selector', 'value': css}) or []
    return [list(e.values())[0] for e in els]


def js(script, *args):
    return call('POST', '/session/%s/execute/sync' % SID, {'script': script, 'args': list(args)})


def field(name):
    return find(f'[name="{F}[{name}]"]')


def value_of(name):
    return js('var e=document.querySelector(arguments[0]); return e ? e.value : null;', f'[name="{F}[{name}]"]')


def wait_for(fn, timeout=20):
    end = time.time() + timeout
    while time.time() < end:
        v = fn()
        if v:
            return v
        time.sleep(0.4)
    return fn()


def type_into(name, text):
    def _attempt():
        el = field(name)
        if not el:
            return False
        try:
            call('POST', f'/session/{SID}/element/{el}/clear')
            call('POST', f'/session/{SID}/element/{el}/value', {'text': text})
            js('var e=document.querySelector(arguments[0]); if(e){ e.dispatchEvent(new Event("input", {bubbles:true})); e.dispatchEvent(new Event("change", {bubbles:true})); }', f'[name="{F}[{name}]"]')
            return True
        except urllib.error.HTTPError:
            time.sleep(0.4)
            return False
    wait_for(_attempt, timeout=15)


SID = None
gecko = subprocess.Popen(['geckodriver', '--port', str(PORT)], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL,
                         env={**os.environ, 'DISPLAY': ':0'})
time.sleep(2)
try:
    session = urllib.request.urlopen(urllib.request.Request(
        BASE + '/session', method='POST', headers={'Content-Type': 'application/json'},
        data=json.dumps({'capabilities': {'alwaysMatch': {'browserName': 'firefox',
                                                          'moz:firefoxOptions': {'binary': '/usr/bin/firefox'}}}}).encode()),
        timeout=90).read()
    SID = json.loads(session)['value']['sessionId']
    call('POST', f'/session/{SID}/url', {'url': ULI})
    wait_for(lambda: 'reset' not in (call('GET', f'/session/{SID}/url') or ''), timeout=15)
    time.sleep(2)
    call('POST', f'/session/{SID}/url', {'url': ACCOUNT})
    if not wait_for(lambda: find(f'[name="{F}[postal_code]"]')):
        print('URL atual:', call('GET', f'/session/{SID}/url'), flush=True)
        print('Título:', call('GET', f'/session/{SID}/title'), flush=True)
        src = call('GET', f'/session/{SID}/source') or ''
        print('Fonte:', src[:500], flush=True)
        raise SystemExit('campo de CEP não apareceu na página')

    # Estrutura do formulário
    names = js('return Array.from(document.querySelectorAll("[name]")).map(e=>e.name).filter(n=>n.indexOf("%s")===0);' % F)
    check('formulário tem Número (street_number) e S/N', any('street_number]' in n for n in names) and any('street_number_sn' in n for n in names))
    check('sem seletor de país visível', not any('country_code' in n and js('return document.querySelector(\'[name="%s"]\').offsetParent!==null;' % n) for n in names), 'país fixo BR, oculto')

    # Ordem visual/DOM no formulário: o CEP deve ser o primeiro campo visível
    visible_names = js('''
        return Array.from(document.querySelectorAll("input, select, textarea"))
            .filter(e => e.name && e.name.indexOf(arguments[0]) === 0 && e.offsetParent !== null)
            .map(e => e.name);
    ''', F)
    check('CEP é o primeiro campo visível no formulário', len(visible_names) > 0 and visible_names[0] == f'{F}[postal_code]', visible_names[0] if visible_names else '')

    # Cadastro novo: rua e bairro começam vazios (o primeiro CEP preenche o que está vazio)
    # Se S/N estiver marcado de execução anterior, desmarca para reabilitar o número
    if js('return document.querySelector(arguments[0]) ? document.querySelector(arguments[0]).checked : false;', f'[name="{F}[street_number_sn]"]'):
        js('var el=document.querySelector(arguments[0]); if(el){ el.click(); }', f'[name="{F}[street_number_sn]"]')
        time.sleep(0.5)
    type_into('address_line1', '')
    type_into('dependent_locality', '')
    # Primeiro CEP
    type_into('postal_code', '01001000')
    ok = wait_for(lambda: value_of('locality') == 'São Paulo')
    check('1º CEP preenche cidade São Paulo', bool(ok), value_of('locality') or '')
    check('1º CEP preenche estado SP', wait_for(lambda: value_of('administrative_area') == 'SP') is not None and value_of('administrative_area') == 'SP', value_of('administrative_area') or '')
    wait_for(lambda: 'Praça da Sé' in (value_of('address_line1') or ''))
    check('1º CEP preenche rua', 'Praça da Sé' in (value_of('address_line1') or ''), value_of('address_line1') or '')

    # Número digitado pela pessoa deve sobreviver às trocas de CEP
    type_into('street_number', '100')
    time.sleep(1)

    # Trocar CEP depois do primeiro preenchimento: o endereço tem que acompanhar o novo CEP
    type_into('postal_code', '20040020')
    ok = wait_for(lambda: value_of('locality') == 'Rio de Janeiro')
    check('2º CEP atualiza cidade para Rio de Janeiro', bool(ok), value_of('locality') or '')
    wait_for(lambda: value_of('administrative_area') == 'RJ')
    check('2º CEP atualiza estado para RJ', value_of('administrative_area') == 'RJ', value_of('administrative_area') or '')
    check('2º CEP atualiza rua', 'Pio X' in (value_of('address_line1') or ''), value_of('address_line1') or '')
    check('2º CEP atualiza bairro', value_of('dependent_locality') == 'Centro', value_of('dependent_locality') or '')
    check('número digitado é mantido após troca de CEP', value_of('street_number') == '100', repr(value_of('street_number')))

    # Terceira troca, mesmo estado: o endereço continua acompanhando o CEP
    type_into('postal_code', '01310100')
    ok = wait_for(lambda: 'Paulista' in (value_of('address_line1') or ''))
    check('3º CEP (mesmo estado) atualiza rua para Avenida Paulista', bool(ok), value_of('address_line1') or '')
    check('3º CEP atualiza bairro para Bela Vista', wait_for(lambda: value_of('dependent_locality') == 'Bela Vista') is not None and value_of('dependent_locality') == 'Bela Vista', value_of('dependent_locality') or '')
    check('3º CEP mantém cidade São Paulo', wait_for(lambda: value_of('locality') == 'São Paulo') is not None and value_of('locality') == 'São Paulo', value_of('locality') or '')
    check('número digitado continua após a 3ª troca', value_of('street_number') == '100', repr(value_of('street_number')))

    # S/N: desabilita o número e limpa
    js('var el=document.querySelector(arguments[0]); if(el){ el.click(); }', f'[name="{F}[street_number_sn]"]')
    time.sleep(1)
    check('S/N desabilita o número', js('return document.querySelector(arguments[0]).disabled;', f'[name="{F}[street_number]"]') is True, f"sn_checked={js('return document.querySelector(arguments[0]).checked;', f'[name=\"{F}[street_number_sn]\"]')}")
    js('var el=document.querySelector(arguments[0]); if(el){ el.click(); }', f'[name="{F}[street_number_sn]"]')
    time.sleep(1)
    check('desmarcar S/N reabilita o número', js('return document.querySelector(arguments[0]).disabled;', f'[name="{F}[street_number]"]') is False, f"sn_checked={js('return document.querySelector(arguments[0]).checked;', f'[name=\"{F}[street_number_sn]\"]')}")
    # Número volta a ser digitado (ligar S/N limpa o número; sem S/N ele é obrigatório)
    type_into('street_number', '100')
    type_into('address_line2', 'Apto 42')
    check('complemento aceita digitação', value_of('address_line2') == 'Apto 42')
    # Salvar: o endereço gravado tem que ser o do último CEP, com o número digitado
    btn = find('[name="op"][value="Salvar endereço"]')
    call('POST', f'/session/{SID}/element/{btn}/click')
    time.sleep(4)
    saved_ok = js('return document.querySelector("[role=alert]") === null;') is True and value_of('street_number') == '100'
    check('salvar endereço responde sem erro', saved_ok)
    check('complemento salvo é mantido', value_of('address_line2') == 'Apto 42')

    # Página recarregada com endereço salvo: um CEP diferente troca o endereço inteiro (sem salvar)
    call('POST', f'/session/{SID}/url', {'url': ACCOUNT})
    wait_for(lambda: find(f'[name="{F}[postal_code]"]'))
    type_into('postal_code', '74711020')
    ok = wait_for(lambda: value_of('locality') == 'Goiânia')
    check('recarregada: CEP de outro estado troca a cidade', bool(ok), value_of('locality') or '')
    check('recarregada: CEP troca a rua', wait_for(lambda: value_of('address_line1') == 'Rua 11') is not None and value_of('address_line1') == 'Rua 11', value_of('address_line1') or '')
    check('recarregada: CEP troca o bairro', wait_for(lambda: value_of('dependent_locality') == 'Vila Bandeirantes') is not None and value_of('dependent_locality') == 'Vila Bandeirantes', value_of('dependent_locality') or '')
    # Editabilidade: campos continuam editáveis mesmo após preenchimento do CEP
    ro = lambda n: js('return document.querySelector(arguments[0]).readOnly;', f'[name="{F}[{n}]"]')
    check('campos editáveis: rua continua editável', ro('address_line1') is False)
    check('campos editáveis: bairro continua editável', ro('dependent_locality') is False)
    check('campos editáveis: número continua editável', ro('street_number') is False)
    check('campos editáveis: estilo do tema com cursor normal', js('return getComputedStyle(document.querySelector(arguments[0])).cursor;', f'[name="{F}[address_line1]"]') != 'not-allowed')
    dis = lambda n: js('return document.querySelector(arguments[0]).disabled;', f'[name="{F}[{n}]"]')
    check('campos editáveis: select de cidade habilitado', dis('locality') is False)
    check('campos editáveis: select de UF habilitado', dis('administrative_area') is False)
    type_into('postal_code', '06550000')
    wait_for(lambda: value_of('locality') == 'Pirapora do Bom Jesus')
    time.sleep(3)
    check('CEP geral: preenche cidade Pirapora do Bom Jesus', value_of('locality') == 'Pirapora do Bom Jesus', value_of('locality') or '')
    check('CEP geral: bairro continua editável', ro('dependent_locality') is False)
    type_into('dependent_locality', 'Centro')
    check('CEP geral: bairro aceita digitação', value_of('dependent_locality') == 'Centro', value_of('dependent_locality') or '')
    type_into('address_line1', 'Rua Principal')
    check('CEP geral: rua aceita digitação', value_of('address_line1') == 'Rua Principal', value_of('address_line1') or '')
finally:
    try:
        if SID:
            call('DELETE', '/session/%s' % SID)
    except Exception:
        pass
    gecko.terminate()

total = len(results)
print(f'RESULTADO: {sum(results)}/{total} verificações OK')
sys.exit(0 if all(results) and total else 1)
