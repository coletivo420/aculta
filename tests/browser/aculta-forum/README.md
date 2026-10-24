# Suíte visual X11 do ACULTA Forum

`progress-visual.mjs` verifica a apresentação e a interação da barra de progresso
em Firefox visível numa sessão X11. Ela usa uma página pública de publicação que
já carrega a biblioteca de discussão e injeta seis mensagens **somente no DOM**.
Não autentica, não envia formulários e não grava Nodes ou Comments.

## Pré-requisitos

- Node.js 22+, Firefox ESR e geckodriver;
- uma sessão gráfica X11 ativa (por exemplo, `DISPLAY=:0`);
- o Runtime de teste ACULTA e uma URL pública de publicação com
  `field_aculta_discussion` renderizado;
- geckodriver acessível em `http://127.0.0.1:4444` ou `GECKODRIVER`.

## Busca pública

`search-visual.mjs` verifica a página somente leitura `/pesquisar` em Firefox
visível. Ela exige uma query sem resultados, percorre 390/768/1024 CSS px nos
modos claro/escuro e confere formulário, estado vazio, CAPTCHA, enquadramento e
contraste. Não submete formulários nem cria conteúdo.

```sh
DISPLAY=:0 geckodriver --host 127.0.0.1 --port 4448
```

Em outro terminal:

```sh
DISPLAY=:0 GECKODRIVER=http://127.0.0.1:4448 \
SUITE_PAGE='https://<host-de-teste>/pesquisar?keys=<termo-sem-resultados>' \
SUITE_OUT='/home/<usuario>/private/aculta/visual-tests/forum-search' \
node tests/browser/aculta-forum/search-visual.mjs
```

Use host declarado no Deployer e diretório privado fora do checkout. O script
encerra sua própria sessão WebDriver e salva capturas/resultados com modo `0600`.

## Execução

```sh
DISPLAY=:0 geckodriver --host 127.0.0.1 --port 4444
```

Em outro terminal da mesma sessão X11:

```sh
DISPLAY=:0 SUITE_PAGE='https://<host-de-teste>/<publicacao>' \
SUITE_OUT='/home/<usuario>/private/aculta/visual-tests/forum-progress' \
node tests/browser/aculta-forum/progress-visual.mjs
```

Use somente hosts de teste declarados em
`aculta_deployer/config/deploy.json`. `SUITE_OUT` deve ser um caminho absoluto
fora do checkout; arquivos são criados com permissões privadas. A suíte valida
viewports CSS exatos de 320, 360, 390, 500 e 900 px via WebDriver BiDi,
estados claro/escuro, overflow introduzido pelos controles, tamanho mínimo do
controle móvel e anúncio da primeira mensagem marcada como não lida. Capturas
PNG e `results.json` ficam no diretório indicado.

Ela não substitui inspeção com leitor de tela, dispositivo físico ou validação
de comentários reais autenticados pelo History. A página deve ser de teste e
somente leitura; a suíte encerra sua própria sessão WebDriver ao terminar.

## Smoke de organização administrativa

`admin-content-structure.mjs` autentica temporariamente o uid 1 com um link
único gerado por `drush uli --no-browser` no host ACCOUNT, depois confirma a
sessão compartilhada e navega no MAIN de teste. É GET-only: não grava conteúdo,
comentários ou configurações. Confirma que `/painel-administrativo/forum`
apresenta links somente para Tópicos, Comentários e Configurações; abre as páginas
canônicas dessas funções e as três subabas de estado dos comentários; e confirma
que os caminhos antigos sob `/painel-administrativo/conteudo/forum` redirecionam
para a entrada central. Também verifica os links públicos no purpose Growroom e
viewports CSS responsivos.

Use o mesmo procedimento para iniciar geckodriver e defina `DISPLAY` e `SUITE_OUT`
fora do checkout. Capturas e resultados são privados; não copie o link único de
login nem dados de usuário para o relatório. A suíte também valida a fila em
390×844 e 768×1024 CSS px. Ela reporta o overflow horizontal observado na tabela
responsiva do tema administrativo; não aplica CSS nem altera o tema/contrib.

## Movimentação de comentário no Runtime de teste

`comment-move-runtime.mjs` faz um smoke autenticado do formulário real, inclusive
token CSRF e POST, e limpa seus dois tópicos e uma árvore ramificada de quatro
Comments pendentes/rejeitados pela Entity API em `finally`. A prova confirma dois ramos,
um com resposta aninhada, preservando CID, autoria, texto, estado, `pid`/`thread`
e destino. No modo de estados mistos, uma resposta pendente recebe a marca
administrativa `field_aculta_rejected`; o roteiro confirma essa distinção após
o movimento. Os tópicos fixture usam o marcador Core `SynchronizableInterface`
durante o insert para não criar avisos de publicação nem efeitos de Game; o
roteiro exige fila Messenger vazia no preflight e confirma que ela continua
vazia. A operação exige explicitamente
`ALLOW_RUNTIME_MUTATION=MOVE-COMMENT-FIXTURE-ON-TEST-RUNTIME`, `DISPLAY=:0` e um
`SUITE_OUT` absoluto privado fora do checkout. O script confirma `hostname=toca`,
branch dedicada, host MAIN de teste resolvido pelo Deployer, URI Drush e banco
`aculta_runtime`; recusa executar se houver conteúdo do Fórum ou se a opção já
estiver configurada. Ela ativa `comment_move_enabled` somente durante o smoke e
remove a configuração ausente no final. O roteiro também cria uma Flag de
denúncia com a conta QA, verifica a permanência da mesma Flagging e sua presença
na fila ACULTA User depois do movimento, e exclui a Flagging antes de remover os
Comments. Antes de usar, confirme que a configuração ativa da Flag
`sinalizar_comentario` corresponde ao bundle versionado
`aculta_discussion_post`. O comentário é criado pendente para não gerar badge
nem aviso de resposta publicada.

O roteiro não executa `drush cr`: o smoke muda somente entidades e uma
configuração temporária, sem trocar definições de rota/serviço, e evita um
rebuild global desnecessário no meio do caso. O bloqueio histórico do mapper do
`aculta_mailer_template` foi resolvido quando sua rota passou a ser declarada;
em 2026-10-20 `drush cr` passou e o route provider confirmou essa rota e as
quatro rotas administrativas de tópicos/comentários do Fórum. Não rode em
produção.

O formulário é aberto em 390×844, 768×1024 e 1024×768 CSS px; o roteiro verifica
overflow horizontal, destino, confirmação e token CSRF. As capturas ficam no
diretório privado de `SUITE_OUT`.

O roteiro pressupõe as instâncias versionadas `field_autor_legado` nos bundles
do Fórum e Comment ACULTA; se faltarem no Runtime, primeiro faça uma importação
parcial limitada a esses dois arquivos, com backup e revisão dos alvos.

## F-GROUP — tópico e anexo restritos

`fgroup-runtime.py` valida, em Firefox ESR visível, o acesso de duas contas ao
tópico restrito da fixture F-GROUP: o associado ativo com membership deve ver o
título e o tópico na listagem; o suspenso com membership deve receber 403 e não
ver o título. Cada conta roda em um **perfil novo** porque o cookie de sessão
usa `Domain=.aculta.toca.net.br`; reaproveitar o perfil faz o segundo login
herdar a sessão do primeiro, o que produz falso positivo.

Pré-requisito: a fixture provisionada por
`aculta_forum/tests/runtime/fgroup-provision.php` e o desligamento posterior com
`teardown`. Gere os links únicos no host de conta, sem imprimi-los:

```sh
export DRUSH_OPTIONS_URI=https://conta.aculta.toca.net.br
L1=$(php vendor/drush/drush/drush.php user:login --name=fgroup_teste_ativo_membro --no-browser | tail -1)
L2=$(php vendor/drush/drush/drush.php user:login --name=fgroup_teste_suspenso_membro --no-browser | tail -1)
DISPLAY=:0 SUITE_BASE=https://growroom420.aculta.toca.net.br \
SUITE_TOPIC_URL=https://growroom420.aculta.toca.net.br/node/<nid> \
SUITE_OUT=/home/<usuario>/private/aculta/visual-tests/forum-fgroup \
SUITE_LOGIN_ATIVO="$L1" SUITE_LOGIN_SUSPENSO="$L2" \
python3 tests/browser/aculta-forum/fgroup-runtime.py
```

O script imprime JSON e grava capturas e `results.json` com modo `0600` em
`SUITE_OUT`. Não cria conteúdo além do login, não altera senhas e não roda em
produção. O anexo e o download privado são medidos pelos scripts de
`aculta_forum/tests/runtime/`, não pelo navegador.

## Temas Olivero e ACULTA420 em `/categorias`

`categorias-visual.py` mede a página `/categorias` no ambiente local com o tema
ativo: h1, categorias, links `?categoria=`, contagem de tópicos, ausência de
erros e de overflow, com capturas em 1024 px e na menor largura de janela que o
Firefox aceita (o script registra a largura real; não usa BiDi). Ele reaproveita
`wdlib.py` de `tests/browser/aculta-cpf-checkout/`, resolve os hostnames de
teste para 127.0.0.1 e não troca o tema. Use `SUITE_THEME=olivero` (padrão) ou
`SUITE_THEME=aculta420`; o caso Olivero exige esse tema como
`system.theme.default` no Runtime local, com restauração garantida por `trap`.

```bash
cd /home/piradopirata/projetos/aculta
vendor/bin/drush theme:enable olivero -y
restore() {
  vendor/bin/drush config:set system.theme default aculta420 -y >/dev/null
  vendor/bin/drush cache:rebuild >/dev/null
}
trap restore EXIT
vendor/bin/drush config:set system.theme default olivero -y
vendor/bin/drush cache:rebuild >/dev/null
DISPLAY=:0 SUITE_THEME=olivero \
SUITE_OUT='/home/<usuario>/private/aculta/visual-tests/forum-categorias-olivero' \
  python3 -I tests/browser/aculta-forum/categorias-visual.py
```

O script confirma que o HTML carrega assets só do tema informado. Com
`SUITE_THEME=aculta420`, ele mede a grade do tema, o contador de cada cartão e
o reflow; a inspeção de todas as combinações de cor continua na suíte
`forum-index-visual.mjs`.

## Painel autenticado — leitura somente (ambiente local)

`authenticated-readonly.py` autentica com um link único gerado por `drush uli`
no host de conta e navega **somente por GET** pelo painel do Fórum, pela fila de
publicações revisionáveis, pelo formulário de mover tópico e pelas
configurações. Não envia formulário, não move conteúdo e não altera
configuração.

```bash
DISPLAY=:0 SUITE_UID=1 \
SUITE_OUT='/home/<usuario>/private/aculta/visual-tests/forum-autenticado' \
  python3 -I tests/browser/aculta-forum/authenticated-readonly.py
```

- Ambiente: **local**, com `aculta.toca.net.br`, `conta.aculta.toca.net.br` e
  `growroom420.aculta.toca.net.br` resolvidos para `127.0.0.1` no perfil do
  Firefox.
- `SUITE_TOPIC_NID` seleciona um tópico público elegível; quando vazio, o
  primeiro tópico publicado é descoberto por `drush`. O padrão é seguro para
  leitura.
- `SUITE_UID` padrão `1` porque, no dump local atual, só o uid 1 tem
  simultaneamente `move aculta forum topics` e as permissões das configurações.
  Isso não prova autorização de não-superusuário: essas regras estão nos Kernels
  access-aware. O link único nunca é impresso nem gravado.
- Cobre: login, painel (Conteúdo/Comentários/Revisões/Configurações), fila de
  moderação, formulário de mover tópico com opções e confirmação, configurações
  e reflow em 1024 e 390 CSS px. Ignora o aviso global de atualizações de
  segurança, que não é do Fórum.
- Limitação: GET-only; submissão real do formulário de mover tópico continua
  coberta por Kernel/serviço, não pela UI.
