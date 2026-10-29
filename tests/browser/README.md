# Testes visuais de navegador

Esta pasta guarda os scripts de teste visual do projeto — navegador, captura de
tela e medição de renderização — para que outras sessões reaproveitem o que já
existe em vez de reescrever a descoberta de seletores e o fluxo.

## Ambiente de execução

- Os testes visuais X11 rodam com `DISPLAY=:0` contra o servidor de desenvolvimento `aculta.toca.net.br`, cujo docroot
  `/home/aculta/public_html` é o checkout `/home/piradopirata/projetos/aculta`.
- O código testado é o do commit que está nesse checkout. Confira antes com `git rev-parse --short HEAD` no checkout.
- Trocar o commit desse checkout exige autorização do responsável, backup de alterações não commitadas e `drush cr`.

## Regra

Todo script de teste visual que um agente criar deve ser salvo **no
repositório**, em `tests/browser/<frente>/`, com um `README.md` na própria
pasta. Script em `/tmp` ou em diretório pessoal não conta: some quando a
sessão termina e o trabalho se perde.

- **Uma pasta por frente**, nomeada pelo assunto (`aculta-cpf-checkout`), não
  pelo agente nem pela data.
- **O `README.md` local** diz o que o script cobre, o ambiente em que roda
  (servidor de desenvolvimento ou local), as variáveis de ambiente, como executar e
  quais são as limitações conhecidas.
- **Artefatos ficam fora do repositório**: PNG, HTML e logs vão para
  `$SUITE_OUT` (padrão em `/tmp/...`). O repositório guarda o script, não a
  evidência — evidência com dado de conta não deve ser versionada.
- **Sem segredos no script**: credenciais vêm do ambiente (`secrets/*.env`);
  nada de senha, token ou link de uso único hardcoded ou impresso.
- **Reaproveite antes de criar**: procure aqui a frente mais próxima e reuse
  `wdlib.py` (WebDriver W3C do projeto) em vez de reescrever a camada de
  driver.
- **Ambiente declarado**: o script deve dizer se fala com o servidor de desenvolvimento
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
| `login-probe.py` | descoberta de seletores do login, `/dados` e página de produto da Loja | servidor de desenvolvimento |
| `login-captcha.py` | login com espera do token do Cloudflare Turnstile | servidor de desenvolvimento |
| `multi-tab-checkout.py` | carrinho e checkout em múltiplas abas, cookies sem valor, LMS (`/group/1`) | servidor de desenvolvimento |
| `olivero-smoke.py` | tema Olivero no Portal, Fórum e Revista; assets do tema, erros visíveis, screenshot | ambiente local |

### `aculta-wiki/`

| Script | Cobre | Ambiente |
|---|---|---|
| `visual.py`, `keyboard.py` | páginas públicas, reflow e teclado da busca | servidor de desenvolvimento |
| `we-sativa.py` | workspace autenticado, reflow 1280/390/320 px, ação nativa e retorno ao Studio | servidor de desenvolvimento; login Drush da conta de teste |

### `aculta-conta-raiz/`

Raiz da Conta (Domain ACCOUNT) sem sessão: `/` e `/conta-legada` levam ao login, `/minha-conta` e `/studio` dão 404, e o login não tem rolagem horizontal em 390 px e 1280 px. Servidor de desenvolvimento, Firefox ESR em `DISPLAY=:0`. Não cobre o estado logado. Ver `aculta-conta-raiz/README.md`.

### `aculta-qrcode/`

`visual.py` valida painel, prévia SVG, downloads SVG/PNG/WebP/PDF, redirecionamento
público e reflow 1280/390/320 px usando uma entidade temporária `[TESTE]` no
Runtime de desenvolvimento. Requer uma sessão administrativa efêmera. Ver o
README local para criação e limpeza da fixture.

### `aculta-portal-cep/`

`olivero.py` verifica o formulário autenticado de endereço, o preenchimento
assíncrono do CEP, campos bloqueados e reflow 1280/390/320 px com o tema Olivero.
Usa conta descartável e link ULI; não submete o formulário. A ativação temporária
do tema deve ser restaurada pelo `trap` documentado no README local. Capturas e
link ULI ficam em `$SUITE_OUT`.

### `aculta-image-admin/`

`visual.py` abre as opções de processamento de imagens com Firefox ESR, verifica
compressão, WebP, formatos de entrada/saída, detecção de bibliotecas e reflow
em 1280/390/320 px. Cria e remove conta/papel temporários com permissão restrita;
não salva configuração nem altera arquivos. Servidor de desenvolvimento; ver o
README local para execução e limitações.

### `aculta-forum-lms/`

`h5p-discussions.py` prepara um curso LMS, uma conta Educadora temporária e um
tópico ligado a um conteúdo H5P existente, e verifica o link de discussão em
1280/390/320 px no Firefox ESR. A fixture é removida no `finally`. A execução
atual no Runtime de desenvolvimento foi bloqueada antes do navegador porque
`private://h5p` não permite escrita ao Drush; ver o README local. Não altere
permissões para contornar esse bloqueio sem autorização.

### `aculta-game/`

`smoke.mjs` verifica a área de conta, perfil público e alternativas de texto das
imagens de conquistas. Reutiliza a sessão WebDriver do Messenger, usa a conta
descartável `teste.comum` (UID 5) do Runtime de desenvolvimento e não submete
formulários. O trecho administrativo só roda com `GAME_ADMIN_ACCOUNT` apontando
para uma conta ativa com `administer aculta game`. O layout responsivo pode ser
omitido com `GAME_SKIP_RESPONSIVE=1` quando o CSS do candidato não está servido;
nesse modo não há evidência móvel. Ver `aculta-game/README.md`.

### Fora da convenção de pasta

Scripts de teste visual anteriores à regra, mantidos onde a frente dona os
deixou. Servem de referência de fluxo; ao evoluí-los, considere movê-los para
`tests/browser/<frente>/`.

| Script | Cobre | Situação |
|---|---|---|
| `scripts/check-browser-hosts.sh` | carregamento de hosts de teste, tabela URL/código/bytes/título e HTML por URL | documentado em `docs/operations/NAVEGADOR-TESTES.md` |
| `scripts/test-account-address-browser.py` | formulário de endereço e CEP em Minha conta, via link único de login | da frente `aculta_portal_cep` |

## Evidência das últimas rodadas

- CPF/checkout, servidor de desenvolvimento: `docs/portal/CPF-CONSOLIDACAO-E-CHECKOUT.md`, seção 9.6.
- Olivero, ambiente local: mesma seção, subseção "Tema Olivero no ambiente local".

### `aculta-mercadopago/`

`buyer-login.py` abre Mercado Pago em Firefox ESR visível e tenta autenticar
somente o comprador sandbox autorizado. Lê as credenciais ignoradas pelo Git em
memória; não salva HTML/screenshot, não cria pedido e não inicia pagamento. Em
2026-10-27 o login não foi confirmado e os domínios Mercado Pago responderam
HTTP 403 na sessão de teste. Ver o README da pasta para execução e limitações.

`checkout-smoke.py` abre um produto em `loja.aculta.toca.net.br`, adiciona ao
carrinho e segue o checkout central em `aculta.toca.net.br`. Em 2026-10-27
chegou ao login/cadastro Drupal antes da preferência Mercado Pago. Usa produto
de teste e pode deixar carrinho anônimo no Runtime; não submete cadastro nem
pagamento. Ver `aculta-mercadopago/README.md`.

### `aculta-theme-mobile-first/`

`author-card.py` valida o breakpoint do cartão de autoria no consumer público do
Fórum (`/discussoes/novas`), além de overflow horizontal em 320–1440 px. Roda no
servidor de desenvolvimento, Firefox ESR visível, sem escrita. `account-shell.py`
confere a navegação autenticada da Conta por GET com link Drush efêmero e compara
o CSS servido com a folha candidata opcional. Consulte o README local para
variáveis e limitações.
