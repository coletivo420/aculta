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

