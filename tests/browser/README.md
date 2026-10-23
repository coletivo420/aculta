# Testes visuais de navegador

Esta pasta guarda os scripts de teste visual do projeto — navegador, captura de
tela e medição de renderização — para que outras sessões reaproveitem o que já
existe em vez de reescrever a descoberta de seletores e o fluxo.

## Regra

Todo script de teste visual que um agente criar deve ser salvo **no
repositório**, em `tests/browser/<frente>/`, com um `README.md` na própria
pasta. Script em `/tmp` ou em diretório pessoal não conta: some quando a
sessão termina e o trabalho se perde.

- **Uma pasta por frente**, nomeada pelo assunto (`aculta-cpf-checkout`), não
  pelo agente nem pela data.
- **O `README.md` local** diz o que o script cobre, o ambiente em que roda
  (servidor de testes ou local), as variáveis de ambiente, como executar e
  quais são as limitações conhecidas.
- **Artefatos ficam fora do repositório**: PNG, HTML e logs vão para
  `$SUITE_OUT` (padrão em `/tmp/...`). O repositório guarda o script, não a
  evidência — evidência com dado de conta não deve ser versionada.
- **Sem segredos no script**: credenciais vêm do ambiente (`secrets/*.env`);
  nada de senha, token ou link de uso único hardcoded ou impresso.
- **Reaproveite antes de criar**: procure aqui a frente mais próxima e reuse
  `wdlib.py` (WebDriver W3C do projeto) em vez de reescrever a camada de
  driver.
- **Ambiente declarado**: o script deve dizer se fala com o servidor de testes
  (`*.aculta.toca.net.br`) ou com o ambiente local (`127.0.0.1` com os hosts
  resolvidos no perfil do Firefox). Não confunda os dois.
- **Mudança global exige restauração**: se o teste troca tema, configuração ou
  qualquer estado compartilhado, o comando de execução deve restaurar o estado
  com `trap`, no mesmo comando, e o README deve mostrar como.

Regra vigente de navegador: Firefox ESR visível em `DISPLAY=:0`, via
geckodriver; nunca Chromium nem headless (`docs/operations/NAVEGADOR-TESTES.md`
e `AGENTS.md`).

## Scripts salvos hoje

### `aculta-cpf-checkout/`

Frente CPF ID + checkout compartilhado Loja/LMS. Detalhes no `README.md` da
pasta.

| Script | Cobre | Ambiente |
|---|---|---|
| `wdlib.py` | biblioteca WebDriver W3C: sessão, `get`, `js`, `find`, `click`, `type`, abas, screenshot | qualquer |
| `login-probe.py` | descoberta de seletores do login, `/dados` e página de produto da Loja | servidor de testes |
| `login-captcha.py` | login com espera do token do Cloudflare Turnstile | servidor de testes |
| `multi-tab-checkout.py` | carrinho e checkout em múltiplas abas, cookies sem valor, LMS (`/group/1`) | servidor de testes |
| `olivero-smoke.py` | tema Olivero no Portal, Fórum e Revista; assets do tema, erros visíveis, screenshot | ambiente local |

### Fora da convenção de pasta

Scripts de teste visual anteriores à regra, mantidos onde a frente dona os
deixou. Servem de referência de fluxo; ao evoluí-los, considere movê-los para
`tests/browser/<frente>/`.

| Script | Cobre | Situação |
|---|---|---|
| `scripts/check-browser-hosts.sh` | carregamento de hosts de teste, tabela URL/código/bytes/título e HTML por URL | documentado em `docs/operations/NAVEGADOR-TESTES.md` |
| `scripts/test-account-address-browser.py` | formulário de endereço e CEP em Minha conta, via link único de login | da frente `aculta_portal_cep` |

## Evidência das últimas rodadas

- CPF/checkout, servidor de testes: `docs/portal/CPF-CONSOLIDACAO-E-CHECKOUT.md`, seção 9.6.
- Olivero, ambiente local: mesma seção, subseção "Tema Olivero no ambiente local".
