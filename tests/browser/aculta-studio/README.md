# Revisão visual e de navegação do ACULTA Studio

Suíte no Firefox ESR visível na sessão X11 local (`DISPLAY=:0`), pelo geckodriver (WebDriver W3C e BiDi). Não usar headless para validação. Confere a navegação da Suite Marley, as abas e o painel, a navegação AJAX
(troca de aba sem recarregar a página), `aria-current`, largura de 320 a 1440 px emulada pelo BiDi, contraste claro/escuro,
navegação por teclado, ausência de rolagem horizontal e de erros de JavaScript.
Também confere a composição vertical/horizontal em 320/360/390/599/600/767/768/1024/1280/1440px.
`STUDIO_CSS_OVERRIDE=1` injeta somente o bloco de tokens de fidelidade escopado
ao Studio e `studio.css` do checkout indicado por `STUDIO_CSS_ROOT`, incluindo
as fontes WOFF2 em data URIs, antes das medições. Também confirma que o gutter
global do shell não muda. Esse modo verifica os estilos candidatos sobre o markup servido e
**não** comprova que a versão candidata está implantada no Runtime nem constitui
por si só prova de paridade com o OpenCulturas.

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

Os seletores usam atributos de comportamento `data-aculta-studio*`, porque o tema troca os tokens `data-portal-ui`
por classes na renderização.

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
