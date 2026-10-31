# Revisão visual e de navegação do ACULTA Studio

Suíte no Firefox ESR visível na sessão X11 local (`DISPLAY=:0`), pelo geckodriver (WebDriver W3C e BiDi). Não usar headless para validação. Confere a navegação da Suite Marley, as abas e o painel, a navegação AJAX
(troca de aba sem recarregar a página), `aria-current`, largura de 320 a 1440 px emulada pelo BiDi, contraste claro/escuro, navegação por teclado, alvos mínimos 44×44 CSS px para abas
do Studio, ausência de rolagem horizontal e erros de JavaScript.
Também confere a composição vertical/horizontal em 320/360/390/599/600/767/768/1024/1280/1440px.
Os cinco estados editoriais da Revista são verificados na seção Publicações; a
raiz `/marley` não é obrigada a exibir esse conjunto de outra seção. Quando a
aba Revista não estiver autorizada para a conta, esses checks retornam `SKIP`.
No contexto de Conta, mede cada link contextual em 320/390/768/1280px e exige
alvo de 44×44 CSS px.
`STUDIO_CSS_OVERRIDE=1` injeta somente o bloco de tokens de fidelidade escopado
ao Studio e `studio.css` do checkout indicado por `STUDIO_CSS_ROOT`, incluindo
as fontes WOFF2 em data URIs, antes das medições. Também confirma que o gutter
global do shell não muda. Esse modo verifica os estilos candidatos sobre o markup servido e
**não** comprova que a versão candidata está implantada no Runtime nem constitui
por si só prova de paridade com o OpenCulturas.
Nas capturas de WE SATIVA, o teste também mede o `row-gap`, as margens das seções
e a distância efetiva entre listas; o gap deve corresponder ao token responsivo
de 3rem, 4rem ou 5rem, sem margens duplicadas.

Somente no servidor de desenvolvimento, com a conta `teste.comum` (login único por Drush, sem senha no repositório). O
Studio precisa estar habilitado no Runtime e a conta precisar de uma aba visível (permissão `create revista content`).
O wrapper consulta a rota `aculta_studio.home` antes de abrir o Firefox e interrompe com diagnóstico se o Runtime ainda não expõe `/marley`.
A listagem deve mostrar conteúdo ou seu estado vazio; a checagem de link de edição fica `SKIP` quando a conta não possui publicação própria publicada.

```sh
export SUITE_OUT=/caminho/externo-ao-repositorio/aculta-studio-visual
DISPLAY=:0 geckodriver --port 4444 &
DISPLAY=:0 tests/browser/aculta-studio/run-visual.sh
```

`SUITE_OUT` é obrigatório e deve ficar fora do repositório, em armazenamento
persistente; não usar `/tmp`. Outras variáveis: `STUDIO_PAGE` (padrão
`https://conta.aculta.toca.net.br/marley`), `STUDIO_LOGIN_URI`,
`STUDIO_ACCOUNT_CONTEXT_PAGE` (opcional; verifica a navegação interna de Conta,
por exemplo `/conta/endereco` no domínio Account),
`STUDIO_USER`, `STUDIO_DRUSH_ROOT` (padrão o checkout da suíte; para testar CSS
de outro worktree no servidor de desenvolvimento, use
`/home/piradopirata/projetos/aculta`) e `STUDIO_USER_ID` (padrão `5`). O
geckodriver cria perfil temporário por sessão. O wrapper usa `trap` para
restaurar a preferência de cor mesmo quando a suíte falha; não executar
diretamente o `.mjs`.

Para validar links de edição na listagem editorial, execute como a conta dona
dos nós sintéticos com `STUDIO_USER=aculta_visual_admin` e
`STUDIO_USER_ID=257`. A suíte exige o destino canônico
`/marley/revista/{id}/editar` e faz um GET somente leitura do Form API para
verificar a presença de campos, tipografia Fidelity e reflow; nunca envia o
formulário. Com `STUDIO_CSS_OVERRIDE=1`, a CSS candidata do worktree é injetada
somente no Firefox.

Para validar o SDC `context-tabs` numa rota real, `OCS_CONTEXT_TABS_CSS_OVERRIDE=1`
injeta a folha candidata indicada por `OCS_CONTEXT_TABS_CSS_ROOT` (padrão:
`ACULTA_ROOT`) somente no Firefox. O script mede quantidade de links, eixo e
wrapping do `<ul>`, bordas dos itens e alturas dos links. `OCS_SKIP_OPENCULTURAS=1`
pula a captura pública de referência quando o teste é focal. Exemplo no servidor
de desenvolvimento, usando o checkout da PR como candidato:

```sh
export DISPLAY=:0
export ACULTA_ROOT=/home/piradopirata/projetos/aculta
export OCS_CONTEXT_TABS_CSS_ROOT=/home/piradopirata/worktrees/opencultures-f0-adm8-e8
export OCS_CONTEXT_TABS_CSS_OVERRIDE=1
export OCS_SKIP_OPENCULTURAS=1
export STUDIO_LOGIN_URI=https://conta.aculta.toca.net.br
export STUDIO_ROUTES=https://wiki420.aculta.toca.net.br/studio/we-sativa
export OCS_WIDTHS=320,360,390,599,600,768,1024,1280,1440
export SUITE_OUT=/home/piradopirata/aculta-suite-out/ocs-context-tabs
python3 tests/browser/aculta-studio/ocs-baseline.py
```

Esse override não modifica o Runtime. A sessão ULI só abre a rota com uma conta
sintética; nenhum formulário é enviado. `baseline.json` deve indicar
`studio_capture_status=CAPTURED`, quatro itens, direção `row`, bordas
neutralizadas, links com pelo menos 44px e largura de scroll igual à largura do
viewport. A captura requer revisão visual; não prova ACL, WCAG nem o gate OCS-F11.

Os seletores usam atributos de comportamento `data-aculta-studio*`, porque o tema troca os tokens `data-portal-ui`
por classes na renderização.

## Meus Salvos — apresentação Fidelity

Arquivo `saved-content-fidelity.py`. Valida somente a apresentação candidata
dos tokens que `SavedContentController` realmente emite: abas, lista,
metadados, badges, paginação, estado vazio e cartões de recomendação. A fixture
espelha a estrutura do `item_list` e o wrapper `studio-saved-page`, com itens
demonstrativos; não acessa conteúdo pessoal, não abre `/salvos` no Runtime e
não prova consulta, ACL, cache, disponibilidade de módulos opcionais nem
funcionamento de recomendações.

Com Firefox ESR visível em `DISPLAY=:0`, execute:

```sh
SUITE_OUT=/home/piradopirata/aculta-suite-out/studio-saved-content-fidelity \
  python3 tests/browser/aculta-studio/saved-content-fidelity.py
```

O roteiro mede 320/360/390/599/600/768/1024/1280/1440px, temas claro/escuro,
overflow, alvos de 44px, aba atual, lista e estado vazio, superfície de cartão e
foco visível por Tab. `results.json` e capturas 320/1440 ficam em `$SUITE_OUT`.
O resultado é uma fixture local de CSS/HTML, não a homologação da rota ou do
gate OCS-F11.

## Meu Perfil — S-R4 (suíte read-only, ainda sem execução no Runtime)

Arquivo: `profile-s-r4.mjs`. Usa **Firefox ESR visível em X11** e geckodriver,
o mesmo protocolo WebDriver/BiDi do `visual.mjs`. Verifica os quatro SDCs
renderizados, ações do ACULTA User, isolamento com *duas sessões independentes*
e sentinelas distintas, link público condicionado à fixture, reflow em
320/375/390/768/1024/1440 px e capturas locais claro/escuro. Executa também
uma sessão separada com JavaScript desabilitado, conferindo HTML do perfil e
GET da edição de apelido.

Primeiro, no **Homelab de testes identificado com hostname, branch, Drush e
banco**, prepare **duas contas sintéticas** A e B com bio de marcador único,
sem informações pessoais. Um dos perfis deve ser público e o outro privado.
Prepare também casos adicionais de apelido ausente, foto publicada e não
publicada, conforme a matriz do handoff S-R4. A suíte é não destrutiva: ela
não cria contas, não altera perfis, não envia forms e não troca preferências.

```sh
export DISPLAY=:0
export STUDIO_PROFILE_USER_A='conta-teste-A'
export STUDIO_PROFILE_USER_B='conta-teste-B'
export STUDIO_PROFILE_SENTINEL_A='marcador-unico-A-no-perfil'
export STUDIO_PROFILE_SENTINEL_B='marcador-unico-B-no-perfil'
export STUDIO_PROFILE_PUBLIC_A='yes'
export STUDIO_PROFILE_PUBLIC_B='no'
export STUDIO_PROFILE_BASE='https://conta.aculta.toca.net.br'
export SUITE_OUT='/tmp/aculta-studio-profile-s-r4'
# Em outra sessão X11: DISPLAY=:0 geckodriver --port 4444
node tests/browser/aculta-studio/profile-s-r4.mjs
```

`GECKODRIVER` aceita a URL do servidor WebDriver (padrão
`http://127.0.0.1:4444`); `STUDIO_PROFILE_DRUSH_URI` aceita override
somente da mesma origem que `STUDIO_PROFILE_BASE`. A suíte bloqueia hosts
de produção e exige duas contas distintas. Links temporários de login são
gerados em memória por Drush e não são gravados no relatório. Evidências
(`results.json`, PNG) ficam **somente em `$SUITE_OUT`**, fora do Git e
devem ser tratadas como privadas. Status SKIP produz saída 3, FAIL produz 1,
PASS total produz 0. Não usar SKIP como aceite.

**Limitações:** o script não homologa upload/crop/modal AJAX, submissões
Form API, CSRF, cache HTTP, ACL do perfil público em MAIN, segurança de CPF,
persistência de preferência nem paridade S-R5. O modo escuro é exercitado
apenas como atributo local de apresentação, sem gravação da preferência.
A verificação de nome contendo `@` é uma triagem e não substitui uma
fixture obrigatória sem apelido. As sentinelas de fixture devem estar
visíveis no editor privado para demonstrar ausência de mistura entre contas.
Capturas devem ser inspecionadas manualmente quanto a foco, contraste,
overflow, zoom e layout, além das asserções automatizadas.

**Situação no commit de inclusão:** script preparado e sintaxe conferida em
cópia local com `node --check`; **Firefox ESR/Runtime ainda não executados**.
A S-R4 permanece sem homologação; S-R5 continua bloqueada.


## S-R5 — comparação de Conta e Perfil (suíte pré-homologação)

Arquivo `parity-s-r5.mjs`: **somente leitura**, no Firefox ESR visível,
com sessões WebDriver independentes para duas contas sintéticas. Compara
`/conta`, as nove seções de Conta, os formulários e o legado
permanente `/conta-legada`, além de `/perfil` no purpose ACCOUNT e
`/u/{apelido}` no MAIN. A política do MAIN também é examinada em sessão anônima separada, para não confundir informações próprias de um titular logado com conteúdo realmente público. A suíte verifica acesso anônimo e GET sem JavaScript,
reflow em 320/375/390/768/1024/1440 px e produz screenshots comparativas.
Nenhuma mudança de banco, permissão, preferência ou envio de formulário.

**Antes:** confirmar host, branch PR #297, status do working tree, versão
Drupal/PHP, URI e banco de teste com Drush; verificar S-R4. Preparar A e B
com apelidos válidos distintos, *sentinelas sintéticas de biografia*, estados
público/privado distintos e decisões de foto/email/redes documentadas.
Não usar contas pessoais. Executar somente no Homelab.

```bash
export DISPLAY=:0
export SR5_ACCOUNT_ORIGIN=https://conta.aculta.toca.net.br
export SR5_MAIN_ORIGIN=https://aculta.toca.net.br
export SR5_USER_A=conta-teste-A
export SR5_USER_B=conta-teste-B
export SR5_APELIDO_A=apelido-teste-a
export SR5_APELIDO_B=apelido-teste-b
export SR5_BIO_SENTINELA_A=marcador-sintetico-a
export SR5_BIO_SENTINELA_B=marcador-sintetico-b
export SR5_PERFIL_PUBLICO_A=yes
export SR5_PERFIL_PUBLICO_B=no
export SR5_DOCUMENTOS=enabled  # ou disabled se o owner CPF estiver ausente
export SUITE_OUT=/tmp/aculta-studio-s-r5
DISPLAY=:0 geckodriver --port 4444 &
node tests/browser/aculta-studio/parity-s-r5.mjs
```

`SR5_WEBDRIVER` permite alterar o endpoint WebDriver (padrão
`http://127.0.0.1:4444`). O script exige explicitamente
`/usr/bin/firefox-esr`; **não** há `headless`. URLs de produção são
recusadas. `SR5_DOCUMENTOS` sem valor resulta em SKIP e não em PASS.
Resultados `PASS/FAIL/SKIP` são gravados em `$SUITE_OUT/results.json`,
com nomes de campos/formulários e contagens, mas sem valores privados.
Capturas PNG podem conter dados da fixture: tratar como material privado,
não anexar ao Git. O exit code é 0 se não houver FAIL/SKIP, 1 para FAIL
e 3 quando existe SKIP sem FAIL.

**Diferenças deliberadas:** o cadastro Studio omite `field_nickname`;
o formulário legado em `/dados` permanece misto, enquanto a edição de
apelido do Studio vive no Perfil. Não existe rota Portal legada
`/pedidos` declarada em `aculta_portal.routing.yml`; a suíte usa
`/conta-legada` como **referência visual**, sem aprovar paridade de pedidos
por contagem não comparável. Preferências e outros formulários pertencem
ao Portal. O perfil privado de `PublicProfilePolicy` pode apresentar
apelido e foto, mas não bio nem redes; o link público é condicionado
separadamente por `PublicProfileLink`. E-mail é opt-in no perfil público,
não deve ser condenado genericamente como violação da política.

**Não cobre:** salvamento real, CSRF, AJAX, checkout, upload/crop/foto,
persistência de preferências, perfil sem nickname, cache HTTP com duas
contas concorrentes, compatibilidade Olivero, zoom real, teclado completo
nem acessibilidade WCAG. Modo claro/escuro é comparação local de geometria
e não grava preferência. Uma auditoria humana deve ler os screenshots
pareados, exercitar os formulários nativos e documentar ações, resultados,
erros e limitações. Campos dinâmicos opcionais podem exigir análise manual.
`SKIP` **não** é aprovação. A execução de S-R5 não substitui gates
pendentes da S-R4 e não promove SDCs experimentais para stable.

Matriz de casos e critérios: 
`web/modules/custom/aculta_portal/modules/aculta_studio/docs/S-R5-MATRIZ-COMPARACAO.md`.

## OCS-F0 — baseline comparativo

O script `ocs-baseline.py` captura, com Firefox ESR visível, a homepage pública e, por padrão, a entrada da Conta, Marley, Ganjalab, WE SATIVA, Conta, Perfil, Green Stash, Culturas e planos Mercado Pago. Para cada Suite resolve a URL no Domain ativo por `DomainPurposeManager::environmentPathUrl()`; não assume que paths de Suites diferentes funcionem no host da Conta. Requer `DISPLAY=:0`, `SUITE_OUT` fora do repositório e Drush local apontando para `aculta_runtime`; usa perfil temporário em `$SUITE_OUT/profiles`. `STUDIO_PAGE` limita a uma URL customizada; `STUDIO_ROUTES` aceita URLs completas ou caminhos no host `STUDIO_LOGIN_URI`. Imagens e JSON ficam em `$SUITE_OUT`. `OCS_WIDTHS=320,360,390,599,600,768,1024,1280,1440` captura a grade completa do gate; sem essa variável, o modo rápido usa 1440/390 px. Se ULI falhar, a tentativa vai a `baseline-deferred.json` e preserva a última execução bem-sucedida em `baseline.json`. A captura autentica somente a conta de teste e não homologa a operação do novo host Wiki.

Com `OCS_STUDIO_FIDELITY_CSS_OVERRIDE=1`, a folha candidata do Studio pode ser
injetada somente no Firefox. Na rota `/perfil`, o relatório mede os links e
botões dentro das ações dos SDCs `profile-identity`, `profile-bio` e
`profile-social`, falhando se não encontrar ações ou se alguma medir menos de
44px. Esse check valida apenas apresentação; não envia formulários nem testa
grants ou persistência.

Na rota Marley, o mesmo override mede a hierarquia de H1 e só permite ocultar
o título genérico quando o marcador do workspace e seu heading estão presentes.
As verificações exigem um único H1 de workspace, breadcrumb preservado e
marcadores reais; isso evita esconder o título em páginas Marley sem esse
contexto. A navegação real de cinco áreas também é medida: wrapping, ausência
de overflow e alvos de pelo menos 44×44px.

Para captura pública sem sessão, use `OCS_STUDIO_ANONYMOUS=1` junto com uma URL
pública explícita em `STUDIO_ROUTES`. O modo mantém a checagem do Runtime, não
gera ULI e pode receber a CSS candidata para verificar que consumidores MAIN,
como `/u/{apelido}`, não recebem marcadores nem tokens visuais exclusivos do
Studio. O relatório JSON substitui o trecho inicial do corpo por um marcador de omissão em perfis públicos anônimos; PNGs permanecem em `SUITE_OUT` e podem conter conteúdo visível, portanto devem ser tratados como evidência privada.
Para validar os alvos públicos, acrescente `OCS_PUBLIC_PROFILE_KEYBOARD=1` e
`OCS_PUBLIC_PROFILE_ZOOM=1`; o primeiro percorre por Tab cada link exposto e o
segundo usa zoom real de Firefox em 200%/400%, restaura com Ctrl+0 e registra
capturas com o primeiro link social enquadrado. Ambas as opções exigem uma URL
`/u/{apelido}` e modo anônimo.

Para exercitar zoom real e teclado no Perfil, combine
`OCS_PROFILE_ACTION_ZOOM=1` e `OCS_PROFILE_ACTION_KEYBOARD=1` com
`OCS_STUDIO_FIDELITY_CSS_OVERRIDE=1` e limite a execução a `/perfil` via
`STUDIO_ROUTES`. O script usa Firefox ESR visível e `xdotool` para Ctrl+0/Ctrl++
até 200% e 400%, mede overflow/alvos de 44px e percorre por Tab os sete links,
exigindo `:focus-visible` e contorno. O zoom é restaurado antes de sair. O CSS
candidato é injetado apenas na sessão; nenhum formulário é submetido.

Execução de referência no servidor de desenvolvimento:

```sh
export DISPLAY=:0
export SUITE_OUT=/home/piradopirata/suite-out/aculta-studio-ocs-f0
python3 tests/browser/aculta-studio/ocs-baseline.py
```

As capturas e os dois JSONs ficam fora do Git. Cada imagem usa slug da rota e
largura, por exemplo `openculturas-1440.png` e `studio-marley-390.png`. O JSON
registra `innerWidth`, largura de documento/scroll e altura do conteúdo.
Também registra classes de `body`, marcadores `data-aculta-studio` e conta a
Institution Bar/Domain Header. Cada captura exige exatamente um de cada; uma
ausência, duplicação ou rota não capturada faz a suíte terminar com erro.
`ACULTA_ROOT` pode apontar para o checkout servido quando o script é executado
em outra worktree; Drush e o helper WebDriver são carregados desse checkout. A captura comparativa anterior encontrou o hub `/studio` vazio e rotas de
Suite no purpose errado; esses caminhos foram aposentados por ADR-038. A
execução mais recente fica em `$SUITE_OUT/baseline.json`; estados negados são
parciais, não PASS. Ausência de overflow vale somente para rotas/estados e
larguras realmente capturados, e não aceita MF3, WCAG ou paridade visual.

O coletor marca `missingMainHint` quando uma captura `studio-*` não contém o
`main#main-content` esperado; documentos sem `main` e sem texto também recebem
`emptyDocumentHint`. Qualquer uma dessas condições força o agregado para
`PARTIAL`, mesmo se o navegador conseguiu salvar uma imagem. Isso evita tratar
uma resposta 404/blank como captura Studio válida.

### THEME-MF0 — shell nas raízes dos dez purposes (2026-10-30)

`purpose-matrix.py` rodou em Firefox ESR visível contra o servidor de
desenvolvimento; o docroot estava no commit `44946347d`. Foram 90 capturas
(dez raízes × 320/360/390/599/600/768/1024/1280/1440px), todas completas:
Institution Bar 90/90, Domain Header exatamente uma vez 90/90, sem overflow
horizontal medido e sem página/título indicador de erro 500. Artefatos em
`/home/piradopirata/aculta-suite-out/domain-header-purpose-matrix-20261030/`.
O resultado cobre apenas raízes anônimas e dimensões medidas; não valida rotas
internas, contas autenticadas, zoom, teclado, WCAG ou o gate THEME-MF3. Não é
paridade visual Studio nem aprovação OCS-F11.

### Probe de foco por teclado

`keyboard-focus.py` registra 12 passos de Tab e 12 de Shift+Tab nas rotas
Studio listadas em `STUDIO_ROUTES` (padrão Marley, Conta e Perfil) e nos
viewports `OCS_FOCUS_WIDTHS` (padrão grade completa do gate). É somente leitura: não envia
forms nem aciona links. Exige Firefox ESR visível (`DISPLAY=:0`), checkout
servido apontado por `ACULTA_ROOT`, Drush no `aculta_runtime` e uma conta de
teste existente em `STUDIO_USER`. O JSON contém alvo, `:focus-visible`, outline,
box-shadow e posição; salva uma captura no primeiro Tab por rota/largura. Tudo
fica em `$SUITE_OUT`, fora do Git. Os screenshots ainda requerem revisão visual;
o probe não homologa WCAG, todos os componentes, teclado Shift+Tab, modais,
widgets customizados nem as demais áreas/purposes.

**Execução em 2026-10-29:** `keyboard-focus.py` rodou com Firefox ESR visível
no servidor de desenvolvimento, em `/marley`, `/conta` e
`/perfil`, 320/1440 px e 12 pressionamentos de Tab por combinação. Foram
registrados 72 focos; os 72 correspondiam a `:focus-visible` e tinham outline
calculado diferente de `none`. As seis capturas estão em
`$SUITE_OUT/aculta-ocs-f0-keyboard-20261029/`; a de Marley/320 foi inspecionada
e mostra o foco em “Pular para o conteúdo principal”. Isso não valida ordem
completa, Shift+Tab, teclado de widgets, diálogos, outros estados/telas ou WCAG.

**Execução expandida em 2026-10-29:** `OCS_FOCUS_WIDTHS` cobriu os nove
viewports do gate. Para três rotas (Marley, Conta, Perfil) foram feitas 12
passagens de Tab e 12 de Shift+Tab por viewport: 648 observações, 621 alvos
focáveis (todos `:focus-visible` e com `outlineStyle` calculado diferente de
`none`) e 27 retornos esperados ao `body` ao desfazer o percurso. Foram salvas
27 capturas, uma após o primeiro Tab por rota/largura. A captura Marley/320 foi
inspecionada. Essa contagem não avalia contraste, ordem semântica completa,
componentes customizados, toda a página nem todos os estados.

### Prova de color scheme e reduced motion

`theme-modes.py` captura as rotas `STUDIO_ROUTES` (padrão Marley, Conta e
Perfil) nos viewports `OCS_MODE_WIDTHS` (320/1440). `OCS_COLOR_SCHEME` aceita
`auto`, `light` ou `dark`; modo explícito aplica `data-bs-theme` somente ao DOM
local da página. `OCS_REDUCED_MOTION=1` configura a preferência do perfil
Firefox temporário. O script registra `matchMedia`, tokens computados,
shell/overflow e screenshots em `$SUITE_OUT`; não salva preferências nem altera
a configuração Drupal. Execute cada combinação em diretório `$SUITE_OUT`
separado. Exige Firefox ESR visível, Drush no `aculta_runtime` e conta de teste.
Isso testa tokens e media queries nas rotas/estados capturados, não contraste
completo, acessibilidade nem todos os purposes.

**Execução de modos em 2026-10-29:** o probe capturou seis combinações de
rota/largura em cada modo (`light`, `dark` + reduced motion e `auto`): Marley,
Conta e Perfil em 320/1440 px. `matchMedia` confirmou dark 6/6 no modo escuro e
reduced motion 6/6 no modo combinado; ambos foram 0/6 no modo claro/auto da
sessão padrão. Os tokens registrados diferiram: claro `#f2f7f0`/`#0c3c29`,
escuro `#171513`/`#f4efe8`. Nenhum overflow horizontal foi medido nessas 18
páginas. A captura escura Marley/320 foi inspecionada. Esta matriz limita-se a
tokens e rotas/viewport indicados; não testa contraste completo, todos os
componentes, todos os purposes nem conformidade WCAG.

**Grade completa em 2026-10-29:** os três modos foram repetidos nos nove
viewports do gate, em Marley, Conta e Perfil (27 capturas por modo, 81 ao todo).
Light: dark/reduced `0/27`, tokens `#f2f7f0`/`#0c3c29`; dark + reduced:
`27/27` em ambos, tokens `#171513`/`#f4efe8`; auto no perfil padrão Firefox:
claro, `0/27` em ambos. Não houve overflow horizontal medido. Artefatos em
`$SUITE_OUT/aculta-ocs-f0-mode-{light-full,dark-reduced-full,auto-full}-20261029/`.
Isso estende o registro anterior de 320/1440; continua limitado a três rotas.

### Matriz mobile-first por purpose (somente leitura)

`purpose-matrix.py` abre anonimamente as dez raízes de `ROUTES-SITEMAP.md`
(MAIN, ACCOUNT, SUPPORT, MAGAZINE, WIKI, SHOP, COURSES, FORUM, LINKS e
EVENTOS) nos nove viewports `OCS_WIDTHS`. Registra URL final, título, H1s,
Institution Bar/Domain Header, largura/overflow e screenshot. Requer Firefox
ESR visível (`DISPLAY=:0`), `SUITE_OUT` externo e hosts do servidor de
Desenvolvimento. O purpose ACCOUNT pode redirecionar ao login; isso não é
contornado e não representa Conta/Studio autenticado ou Green Stash autorizado.
O script não cria conteúdo nem submete forms; os artefatos ficam fora do Git.
`OCS_EXTRA_ROUTES` aceita rotas adicionais no formato
`purpose=https://host-do-purpose/caminho;...`; o script rejeita hosts diferentes
dos dez hosts conhecidos. `OCS_EXTRA_ROUTES_ONLY=1` roda somente essas rotas,
com nomes de PNG distintos pelo path, sem repetir as raízes padrão.

**Snapshot THEME-MF0 de 2026-10-29:** as dez raízes canônicas foram
capturadas anonimamente nos nove viewports (90/90 páginas concluídas, sem
overflow horizontal medido). A Institution Bar esteve presente em 90/90; o
Domain Header `.aculta-header` em 18/90, somente MAIN e WIKI. ACCOUNT raiz
redirecionou a `/entrar`, portanto não cobre Conta/Studio privado. Os arquivos
JSON/PNG estão em `$SUITE_OUT/aculta-theme-mf0-purposes-20261029/`. A homepage
da Revista exibiu espaço de conteúdo vazio e rodapé; esse estado não permite
avaliar os componentes editoriais. Inspecionadas manualmente algumas telas 320
(MAIN, Fórum, Wiki, Revista e Loja); a captura MAIN/320 então registrou quebra
do título no meio da palavra, sem gerar overflow. A repetição abaixo não
reproduziu essa quebra. O baseline público não cobre as
áreas autenticadas, formulários, fixtures de conteúdo completos nem todos os
consumidores/views anexados.

**Repetição em 2026-10-30:** o mesmo comando terminou 90/90 capturas, zero
deferred e zero overflow medido nas dez raízes e nove larguras. Institution Bar
apareceu 90/90; `.aculta-header` 18/90 (somente MAIN e WIKI). A homepage da
Revista (`page-view-revista-sessoes`) permaneceu sem H1/corpo editorial visível,
com apenas rodapé; a screenshot MAIN/320 mostrou o H1 quebrando entre palavras
e “ANTIPROIBICIONISTA” inteiro, sem overflow. PNG/JSON em
`/home/piradopirata/aculta-suite-out/aculta-purposes-mobile-baseline-20261030-3c1691fc5/`.
O docroot estava no HEAD remoto `3c1691fc5`; a worktree tinha nove commits
locais adicionais. Portanto a execução atualiza o baseline remoto, não valida
esses commits locais, páginas internas, áreas autenticadas nem THEME-MF3.

**Rota extra Magazine `/noticias` (2026-10-30):** o modo de rotas adicionais
capturou a página em nove larguras (9/9, sem overflow). É um node Page público
com H1 “Notícias” e conteúdo editorial institucional; a View `aculta-news` não
renderizou rows. 320/1440 foram inspecionados visualmente. Artefatos em
`/home/piradopirata/aculta-suite-out/aculta-revista-noticias-20261030/`.
Essa rota não altera a evidência de que a raiz da Revista tem a View vazia nem
constitui teste de um node `revista`.

### Execução pós-MF1/OCS-F1 e content-grid (2026-10-29)

Após `drush cr` no HEAD servido `0b0531aa0`, `run-visual.sh` rodou em Firefox
ESR visível com a conta sintética `teste.comum`: 29 PASS, 1 SKIP esperado
(sem publicação própria para testar edição), 0 FAIL. Verificou navegação AJAX,
`aria-current`, Enter, nomes acessíveis, erros JS, modo claro/escuro e ausência
de overflow em 320/390/768/1024/1440px. Contraste calculado nos elementos
Studio observados: mínimo 7,05:1 claro e 11,51:1 escuro. A preferência original
foi restaurada e lida como `light`. Artefatos em
`$SUITE_OUT/aculta-ocs-mf2-post-63be6b8d7/`.

`ocs-baseline.py` também capturou nove viewports em MAIN público, Marley e WE
Sativa (27 PNG/JSON records); zero overflow foi medido nas 18 páginas Studio.
Marley mostrou visualmente a variante de duas colunas do `content-grid` em
599/600/768px, empilhada abaixo de 576px. A variante `columns: 3` está coberta
por story, mas não houve consumer Runtime visível com essa prop; LMS/Wiki
também não foram exercitados como consumers. A página WE Sativa renderizou sem
overflow, mas sem marcadores `data-aculta-studio`, portanto não conta como
validação de consumer Studio. Capturas em
`$SUITE_OUT/aculta-ocs-mf2-grid-20261029/`. Isso é evidência parcial, não gate
MF3/F11 ou homologação global.

## Variante Studio Fidelity do SDC Card

`studio-card-fidelity.py` usa uma fixture local que espelha o render array de
`AccountExperienceProvider` e o adaptador `PortalUiHooks`: raiz
`.studio.aculta-account-entry`, lista `.studio__actions` e cinco cartões
`patterns/card` com somente os labels existentes (Marley, Ganjalab, ACULTA
STUDIO WE SATIVA, ACULTA STUDIO GREEN STASH e Culturas). Não adiciona
descrições, tarefas ou operações. A fixture mede as folhas candidatas de
tokens, Studio e `patterns/card` nos 27
viewports, incluindo limites 48/70/85/120em e vizinhanças. Confere container,
padding, reflow, geometria, Montserrat, escala tipográfica, overflow e alvos
mínimos de 44×44px nos cinco links. Em 320px, Tab percorre a ordem dos cinco
destinos e exige `:focus-visible` com outline visível; PNGs ficam em `$SUITE_OUT`.

A execução de 2026-10-31 passou 250/250 verificações, incluindo zoom real do
navegador em 200% e 400% via `xdotool`, nomes oficiais, foco e alvos; os
viewports CSS medidos nos dois níveis de zoom foram 640px e 320px. A página é
`data:` no Firefox ESR visível e não acessa Drupal/Runtime. O teste
carrega somente os CSS do tema e um reset equivalente a `list-unstyled`, pois
não injeta a biblioteca Bootstrap. Não comprova render do preprocess no
servidor, links reais, grants, cache, integração Bootstrap ou WCAG integral.
Claro/escuro não é coberto nesta fixture.
Use `DISPLAY=:0`, defina `SUITE_OUT` e
`ACULTA_FF_PROFILE_ROOT` e execute:

```sh
DISPLAY=:0 python3 -I tests/browser/aculta-studio/studio-card-fidelity.py
```


## Purpose Magazine — captura complementar (2026-10-30)

A suíte `purpose-matrix.py` também foi executada no Firefox ESR visível com
`OCS_EXTRA_ROUTES` apontando para `magazine:/` e `/node/599`, para revisar a
View `revista_sessoes` e uma publicação sintética. Executou nove viewports
(320/360/390/599/600/768/1024/1280/1440), totalizando 18 páginas/capturas;
sem overflow medido nem violação de contagem dos headers. Essa execução
complementar não altera o escopo da matriz Studio nem substitui os gates
THEME-MF3/OCS-F11. Artefatos do Runtime ficam em
`/home/piradopirata/suite-out/aculta-revista-capa-visual-20261030/runtime-after-layout/`.


## Formulários Studio — fidelidade OpenCulturas 3.1.5

`studio-form-fidelity.py` injeta apenas as folhas locais de tokens, botões,
formulários e Studio em uma fixture Drupal-like. Verifica formulário, labels,
fieldset, campos, bordas/estados de erro, controles de imagem, botões default,
primário, reset e destrutivo, foco via teclado e isolamento dos roots Studio.
Como o Form API usa `node-revista-form` fora do Studio, compara
`body.path-marley` com `body.path-magazine`. Também valida os roots
`studio-profile-form` dos formulários existentes de bio/redes e também os
Form API de apelido e privacidade, limitados a `body.path-perfil`; confirma que
o modal privado `account-bio-dialog` mantém seu marcador de diálogo e reusa a
classe de apresentação `studio-profile-form`. Cobre 320/390/768/1440px
em claro e escuro, sem enviar formulários.

```sh
SUITE_OUT=/caminho/fora-do-repositorio/studio-form-fidelity \
ACULTA_FF_PROFILE_ROOT=/caminho/fora-do-repositorio/studio-form-fidelity/profiles \
DISPLAY=:0 python3 -I tests/browser/aculta-studio/studio-form-fidelity.py
```

Em 2026-10-30 passou 42/42 checks antes da inclusão do modal. O reteste atual
acrescenta geometria, tipografia Fidelity e confirma que o campo e as ações
Limpar/Salvar medem 44px em cada viewport e tema, sem submeter o formulário. Evidência anterior:
`/home/piradopirata/aculta-suite-out/studio-form-fidelity-final2-20261030/`.
É fixture sem Drupal/Runtime: não homologa Form API real, AJAX, estados no-JS,
ACL, cache ou fluxos dos módulos Marley/LMS/Wiki/Conta.

### GET do editor Form API da Revista — 2026-10-30

Com a conta sintética proprietária `aculta_visual_admin` (UID 257), a suíte
abriu o destino real `/marley/revista/602/editar` sem submeter o formulário.
No markup servido, `body` expõe `path-marley node--type-revista`, enquanto o
form tem `node-revista-form node-form`; por isso a folha candidata de tokens e
componentes usa essa classe exata, sem seletor amplo sobre o purpose Marley.
Firefox ESR visível passou 76/76 verificações. O GET renderizou campos de
título, conteúdo e secundários; o formulário computou Montserrat e tokens
Fidelity; não houve overflow em 320/390/768/1280px. A suíte também repetiu os
checks de Studio, navegação, teclado, contraste claro/escuro e erros JavaScript.
Capturas desktop e mobile foram revisadas; resultados estão em
`/home/piradopirata/aculta-suite-out/studio-editorial-form-fidelity-retest-20261030/`.
O wrapper restaurou a preferência de cor original. O teste não cobre submit,
save, preview, AJAX/no-JS, ACL completa, zoom nem WCAG integral; não homologa a
implantação da CSS candidata no Runtime.

No reteste de composição, a folha candidata passou a fornecer tokens ao `main`
quando ele contém `form.node-revista-form`; naquele momento, a ausência de
`body.path-marley` no seletor permitia que a mesma forma em outro purpose
herdasse Fidelity. Corrigido em 2026-10-31: tokens, heading e campos dessa
forma estão sob `body.path-marley`; Magazine conserva tipografia e tokens
globais. Na mesma fixture, os tokens já emitidos para bio/redes do Perfil agora
são traduzidos em `studio-profile-form`, que recebe Fidelity sem alterar Form
API. O modal de bio usado pela Conta permanece no estilo próprio. Firefox passou
60/60 checks em claro/escuro, quatro larguras, foco, Marley, Perfil, Magazine e
modal Conta. Capturas e JSON em
`/home/piradopirata/aculta-suite-out/studio-profile-forms-fidelity-20261031/`.

O reteste real anterior do formulário Marley passou 78/78 checks, incluindo
contagem única do shell e reflow do H1 em duas linhas a 320px. Capturas em
`/home/piradopirata/aculta-suite-out/studio-editorial-heading-fidelity-20261030/`.

As capturas `studio-profile-form-light/dark-{320,1440}.png` mostram a folha
candidata em fixture no Firefox, não são capturas do Runtime. Submit, privacy
A/B, AJAX/no-JS, cache, zoom e WCAG integral seguem pendentes.

A verificação de criação segue o link renderizado na tela de entrada da seção:
`/marley/revista/criar/nova-publicacao` leva à ação proprietária real
`/marley/revista/nova`, onde o Form API compartilha a classe
`node-revista-form`. GET somente leitura no Firefox ESR passou 87/87 checks,
com tokens e Montserrat em título/formulário e sem overflow a 320/390/768/1280px.
Capturas desktop/mobile em
`/home/piradopirata/aculta-suite-out/studio-editorial-create-form-fidelity-retest-20261030/`.
Não houve submissão; save, preview, no-JS/AJAX, grants e WCAG integral seguem
fora da evidência.

## GanjaLab — composição Fidelity candidata (OCS-F6 parcial)

`ocs-baseline.py` também mede o painel de aprendizagem do estudante no purpose
`courses` (`/ganjalab`). `OCS_STUDIO_FIDELITY_CSS_OVERRIDE=1` injeta no Firefox
os tokens Fidelity e `studio.css` do caminho indicado em
`OCS_STUDIO_FIDELITY_CSS_ROOT`, sem escrever no Runtime. O coletor força a
solicitação de Montserrat antes de medir `FontFaceSet`; revise `fontLoaded` e
`fontFaces` em `baseline.json`, além das capturas. Exemplo de execução focal:

```sh
export DISPLAY=:0
export ACULTA_ROOT=/home/piradopirata/projetos/aculta
export OCS_STUDIO_FIDELITY_CSS_ROOT=/home/piradopirata/worktrees/opencultures-f0-adm8-e8
export OCS_STUDIO_FIDELITY_CSS_OVERRIDE=1
export OCS_SKIP_OPENCULTURAS=1
export STUDIO_LOGIN_URI=https://conta.aculta.toca.net.br
export STUDIO_ROUTES=https://cursos.aculta.toca.net.br/ganjalab
export OCS_WIDTHS=320,360,390,599,600,768,1024,1280,1440
export SUITE_OUT=/home/piradopirata/aculta-suite-out/ocs-f6-ganjalab
python3 tests/browser/aculta-studio/ocs-baseline.py
```

O Runtime pode estar em uma revisão anterior às rotas canônicas; confirme a
rota por purpose e descreva respostas 403/404 como bloqueio de cobertura, sem
mudar roteamento na Frente B. Capturas autenticadas são leitura; não enviar
formulários. Uma ausência de overflow nessa tela não homologa THEME-MF3, e a
injeção candidata não comprova implantação ou paridade completa OCS-F11.
`OCS_STUDIO_COLOR_MODE=light|dark` opcional define `data-bs-theme` somente no
documento aberto pelo Firefox; `system` mantém o padrão do Runtime. Isso não
altera a preferência persistente da conta nem substitui contraste/WCAG. Com o
override candidato, o `baseline.json` registra checks automáticos de tipografia,
dois estados vazios, overflow, presença única do shell e isolamento dos tokens;
em modo dark exige também o token de texto escuro. A medição do shell inclui o
contraste dos links textuais da Institution Bar; após alternar o modo, o script
aguarda 350 ms para medir a cor estável, não um frame da transição. Esta amostra
focal não substitui a auditoria WCAG 2.2 AA de todas as superfícies e estados.

## WE SATIVA — consumidor Wiki e caminho legado do Runtime

O consumidor visual real é a classe `we-sativa__workspace`, mapeada do token
`data-portal-ui` por `PortalUiHooks`; o Wiki fornece `context-header`,
`content-section`, `context-tabs`, `content-grid`, `card` e `empty-state`. O
coletor mede o wrapper, estados vazios, seções, quatro filtros (`aria-current`,
wrapping e links de pelo menos 44px), overflow e isolamento dos headers do shell.

No Runtime HEAD `2493d7535`, o caminho oficial atual
`https://wiki420.aculta.toca.net.br/studio/we-sativa` ainda não é servido; a
rota instalada é `/wesativa`. Para inspeção **exploratória** do mesmo consumer,
use `STUDIO_ROUTES=https://wiki420.aculta.toca.net.br/wesativa` com
`OCS_STUDIO_FIDELITY_CSS_OVERRIDE=1`. Esse resultado comprova a composição do
wrapper no path legado, mas não homologa o caminho canônico nem fecha OCS-F7.
Aplique `OCS_STUDIO_COLOR_MODE=dark` para o teste visual escuro. O teste é GET,
não altera conteúdo e nunca submete ações da Wiki.

Reteste autenticado de `/wesativa` no Runtime: 279/279 invariantes em nove
larguras, nos modos claro e escuro, com CSS Fidelity candidata. Os links
textuais da Institution Bar mediram 11,34:1 e 12,95:1, respectivamente. As
capturas 320/1440px foram revisadas; veja os diretórios documentados no
`mobile-first-audit.md`. A mensagem de login único é temporária e o conteúdo Wiki
estava vazio; a captura não prova conteúdo preenchido ou a rota oficial.

## ACULTA Studio Culturas — dashboard responsivo candidato

`culturas-dashboard.py` renderiza um fixture estrutural baseado no dashboard
proprietário de `aculta_events` e injeta Bootstrap local e os tokens/CSS do tema
do checkout. Usa Firefox ESR visível em X11, confere nove viewports de 320 a
1440px, claro/escuro, reflow mobile-first dos filtros, tabela com rolagem local,
alvos de 44px, foco de teclado e shell externo fora do escopo Studio. Salva PNG
e JSON exclusivamente em `SUITE_OUT`.

```sh
export DISPLAY=:0
export SUITE_OUT=/home/piradopirata/aculta-suite-out/culturas-dashboard
DISPLAY=:0 python3 -I tests/browser/aculta-studio/culturas-dashboard.py
```

Esta fixture não acessa Runtime nem comprova markup Drupal, grants, conteúdo,
AJAX/no-JS, cache, zoom, WCAG 2.2 AA ou paridade visual com o OpenCulturas.
Resultados do fixture não aprovam OCS-F10/F11 nem THEME-MF3.

Para inspecionar a rota autenticada sem enviar formulários, `culturas-runtime.py`
usa a ULI da conta sintética e injeta apenas a CSS candidata na sessão
Firefox. A execução confirma host/checkout/`aculta_runtime` por Drush e salva
as capturas fora do Git. Não cria nem altera conteúdo, configuração ou
preferência persistente de cor.

```sh
export DISPLAY=:0
export ACULTA_ROOT=/home/piradopirata/projetos/aculta
export CULTURAS_RUNTIME_URL=https://eventos.aculta.toca.net.br/culturas
export STUDIO_LOGIN_URI=https://conta.aculta.toca.net.br
export STUDIO_USER=aculta_visual_admin
export SUITE_OUT=/home/piradopirata/aculta-suite-out/culturas-runtime
DISPLAY=:0 python3 -I tests/browser/aculta-studio/culturas-runtime.py
```

O teste lê classes/estilos computados do dashboard, confere reflow dos campos
de busca/situação e alvos de 44px, testa foco visível por teclado em
claro/escuro, presença única do shell e overflow. A ULI abre sessão
autenticada, mas não submete operações.
Uma captura sem overflow não valida ACL entre contas, cache, AJAX/no-JS,
acessibilidade completa, zoom ou paridade pareada OpenCulturas.

### Reteste canônico de Marley — 2026-10-31

A captura com `STUDIO_PAGE=https://conta.aculta.toca.net.br/marley`, conta sintética
e `OCS_STUDIO_FIDELITY_CSS_OVERRIDE=1` passou os checks estruturais em nove
larguras. O modo `STUDIO_PAGE` recebe URL explícita e não resolve automaticamente
o purpose; usar uma rota oficial do ADR-038. `/studio` é um caminho técnico
retirado e responde 404; não usar como Hub. Evidência em
`/home/piradopirata/aculta-suite-out/studio-marley-canonical-followup-20261031/`.
A CSS candidata foi injetada só no Firefox, e a página pública usada como
referência upstream não é dashboard autenticado; revisão de fidelidade pareada
segue pendente.

### Green Stash: rota canônica e baseline Runtime (2026-10-31)

A rota canônica da branch é `shop:/studio/green-stash`; o Runtime `2493d7535`
ainda usa `/greenstash`, portanto o caminho novo retorna 404 nesse HEAD. O baseline
legado foi capturado apenas para comparação em 320/390/1440px; document width
excedeu 10px a viewport de 320px e a página ainda mostrava nove links. Não tratar
esse menu antigo como contrato operacional atual nem como evidência da rota
candidata. A fixture candidata e seu limite estão no README mobile-first e no
`mobile-first-audit.md`.

### Ganjalab em claro/escuro — 2026-10-31

`ocs-baseline.py` com `OCS_STUDIO_FIDELITY_CSS_OVERRIDE=1` e
`OCS_STUDIO_COLOR_MODE=dark` passou os checks Fidelity em 320/390/1440px nos dois
modos; relatórios em
`/home/piradopirata/aculta-suite-out/studio-ganjalab-canonical-followup-20261031/`
e `/home/piradopirata/aculta-suite-out/studio-ganjalab-dark-canonical-followup-20261031/`.
A rota Runtime ainda apresenta markup anterior (“Minha conta / Área pessoal”),
enquanto o compositor atual da branch usa “Meus cursos”; os resultados são
CSS candidata sobre consumer antigo. Não homologam F6, ACL/enrollment, dashboard
novo, OCS-F11 ou THEME-MF3.

**Extensão 2026-10-31:** Ganjalab foi executado nas nove larguras documentadas
em claro e escuro, com 18/18 captures e todos os invariantes Fidelity `PASS`.
Relatórios completos em `/home/piradopirata/aculta-suite-out/studio-ganjalab-full-light-followup-20261031/`
e `/home/piradopirata/aculta-suite-out/studio-ganjalab-full-dark-followup-20261031/`.
O Runtime continua usando markup anterior; a matriz não homologa o compositor novo
nem F6/F11/MF3.

## Diagnóstico geométrico de mensagens do Drupal

`ocs-baseline.py` também registra `statusMessageDiagnostic` em cada captura:
elementos visíveis com roles/seletores de mensagem, ancestrais próximos,
classes, posição, z-index e retângulos. O diagnóstico omite texto e atributos
de valor para não incluir conteúdo da conta no relatório. A lista de folhas
CSS carregadas auxilia a identificar a origem; regras de folhas cross-origin
não são inspecionadas.

Exemplo focal no Runtime de desenvolvimento, depois de verificar host,
checkout, URI e banco e criar snapshot íntegro antes da sessão ULI:

```sh
export DISPLAY=:0
export ACULTA_ROOT=/home/piradopirata/projetos/aculta
export STUDIO_LOGIN_URI=https://conta.aculta.toca.net.br
export STUDIO_PAGE=https://conta.aculta.toca.net.br/marley
export STUDIO_USER=aculta_visual_admin
export STUDIO_USER_ID=257
export OCS_SKIP_OPENCULTURAS=1
export OCS_WIDTHS=320
export SUITE_OUT=/home/piradopirata/aculta-suite-out/status-message-diagnostic
python3 tests/browser/aculta-studio/ocs-baseline.py
```

Para exercitar o reflow de forma não autenticada e sem criar uma sessão, use
um purpose público, injete a folha candidata local e insira um aviso sintético
sem dados da conta no browser:

```sh
export DISPLAY=:0
export ACULTA_ROOT=/home/piradopirata/projetos/aculta
export OCS_STUDIO_FIDELITY_CSS_ROOT=/home/piradopirata/worktrees/opencultures-f0-adm8-e8
export OCS_STATUS_FLOW_CSS_OVERRIDE=1
export OCS_STATUS_FLOW_FIXTURE=1
export OCS_STUDIO_ANONYMOUS=1
export OCS_SKIP_OPENCULTURAS=1
export STUDIO_PAGE=https://revista.aculta.toca.net.br/
export OCS_WIDTHS=320,390,768,1280
export SUITE_OUT=/home/piradopirata/aculta-suite-out/status-message-flow-candidate
python3 tests/browser/aculta-studio/ocs-baseline.py
```

O fixture confirma a geometria da candidata, não substitui a captura real do
aviso ULI. Para registrar o aviso real, a captura autenticada cria uma sessão
ULI no Runtime: faça snapshot antes, não submeta formulários e registre somente
metadados geométricos. A coleta omite texto e valores; o resultado identifica
estilos computados na página servida, sem provar que uma folha candidata local
está implantada. Se um purpose não tiver `.aculta-highlighted`, o fixture
registra `SKIP_NO_HIGHLIGHTED_REGION`; isso não é falha de reflow. A medição da
rolagem horizontal permanece no registro da captura, mas não reprova o teste
específico de posição da mensagem.

## Tabelas de listagem Marley/Wiki — fidelidade e mobile

`studio-listing-fidelity.py` cobre o consumer visual dos elementos Drupal atuais
`studio-listing` e `studio-listing-wrap`, aplicados pelo hook de apresentação do
tema. A fixture reproduz a raiz `.studio`, a tabela semanticamente marcada e as
seis colunas usadas em fluxos editoriais/revisão. Em Firefox ESR visível mede
320/360/390/599/600/768/1024/1280/1440px, claro/escuro, ausência de overflow do
documento, rolagem horizontal confinada ao wrapper, ritmo e cabeçalho de tabela
e foco real por teclado. Não submete formulários, não acessa conteúdo, não
valida ACL/cache/AJAX/no-JS nem substitui o GET das listagens Drupal.

```sh
export DISPLAY=:0
export SUITE_OUT=/caminho/fora-do-repositorio/studio-listing-fidelity
export ACULTA_FF_PROFILE_ROOT="$SUITE_OUT/profiles"
DISPLAY=:0 python3 -I tests/browser/aculta-studio/studio-listing-fidelity.py
```
