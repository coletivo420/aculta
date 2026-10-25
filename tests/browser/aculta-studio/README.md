# Revisão visual e de navegação do ACULTA Studio

Suíte no Firefox ESR visível na sessão X11 local (`DISPLAY=:0`), pelo geckodriver (WebDriver W3C e BiDi). Não usar headless para validação. Confere a seção "Estúdio" no menu da conta, as abas e o painel, a navegação AJAX
(troca de aba sem recarregar a página), `aria-current`, largura de 320 a 1440 px emulada pelo BiDi, contraste claro/escuro,
navegação por teclado, ausência de rolagem horizontal e de erros de JavaScript.

Somente no servidor de teste, com a conta `teste.comum` (login único por Drush, sem senha no repositório). O
Studio precisa estar habilitado no Runtime e a conta precisar de uma aba visível (permissão `create revista content`).
O wrapper consulta a rota `aculta_studio.home` antes de abrir o Firefox e interrompe com diagnóstico se o Runtime ainda não expõe `/studio/marley`.
A listagem deve mostrar conteúdo ou seu estado vazio; a checagem de link de edição fica `SKIP` quando a conta não possui publicação própria publicada.

```sh
DISPLAY=:0 geckodriver --port 4444 &
DISPLAY=:0 tests/browser/aculta-studio/run-visual.sh
```

Variáveis: `STUDIO_PAGE` (padrão `https://conta.aculta.toca.net.br/studio/marley`), `STUDIO_LOGIN_URI`, `STUDIO_USER`,
`SUITE_OUT` (padrão `/tmp/aculta-studio-visual`), `STUDIO_USER_ID` (padrão `5`) e `GECKODRIVER`. O geckodriver cria perfil temporário por sessão. O wrapper usa `trap` para restaurar a preferência de cor mesmo quando a suíte falha; não executar diretamente o `.mjs`.

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
`/studio/conta`, as nove seções de Conta, os formulários e o legado
permanente `/conta-legada`, além de `/studio/perfil` no purpose ACCOUNT e
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

