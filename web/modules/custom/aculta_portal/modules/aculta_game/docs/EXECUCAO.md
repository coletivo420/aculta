# Execução por fases — ACULTA Game

Autorização de 2026-10-15: implementar por fases e commits na mesma PR #286.
Esta autorização substitui a restrição anterior de abertura somente documental.

## G1 — fonte única e integração inicial

Classificação MINOR: Game `0.1.0-dev.1`, User `0.4.0-dev.3`, Portal `0.4.0-dev.33`.

Inventário do Runtime confirmado em `toca`, raiz `/home/aculta`, URI de teste
`https://aculta.toca.net.br`, MariaDB `aculta_runtime`, Core 11.4.10 e PHP 8.4.26.
PHP 8.5 não está disponível para validação neste host.

- Entidade `aculta_badge_award`: provider `aculta_user`; dois registros, um
  `primeiro_comentario_forum` e um `perfil_basico`; zero pares ativos duplicados.
- Nenhum update de banco pendente. Configuração apresenta divergências amplas
  prévias, inclusive `core.extension`; não realizar import/export global.
- `BadgeCatalog`, `BadgeService` e entidade permanecem no User com os mesmos
  identificadores, storage e campos. Nenhuma migração ou alteração de schema.
- `BadgeEvaluationHooks` transferido integralmente para Game, sem avaliador
  duplicado. G1 preserva os critérios; suas correções pertencem a G2.
- Game depende de Portal e User; não cria dependência inversa, tabela ou cópia de
  concessões. Desativar Game interrompe os gatilhos, sem remover os fatos no User.
- O Status descobre Game pelo registro de extensões. Não há helper novo em G1.

Digest SHA-256 de IDs e valores serializados antes da ativação:
`76d19ccb5767ed0c6ca428e9dc5c8d3d9e011e15f49368c3a8182eb72978e53d`.
O digest permite comparar preservação sem registrar dados pessoais neste documento.

### Fontes de API conferidas

- [Metadados de extensão](https://www.drupal.org/docs/develop/creating-modules/let-drupal-know-about-your-module-with-an-infoyml-file).
- [Hooks OOP e autowiring](https://api.drupal.org/api/drupal/core!lib!Drupal!Core!Hook!Attribute!Hook.php/class/Hook/11.x).
- Código instalado: `web/core/lib/Drupal/Core/Hook/Attribute/Hook.php` e APIs de
  entidade; baseline do projeto preservado, sem declaração de Drupal 12 ready.

### Validação

- PHP lint PASS; gate Drupal 11+ PASS (773), fronteira PASS (1036), endereços
  PASS (1722); `git diff --check` PASS. Os gates incluem o checkout compartilhado.
- `drush pm:enable aculta_game -y` e `cache:rebuild` concluídos no Runtime
  confirmado. A instalação acionou a atualização de tradução pt-br de um objeto
  de configuração pelo mecanismo existente; não houve importação global.
- Container resolveu `Drupal\aculta_game\Hook\BadgeEvaluationHooks`; a classe
  antiga não existe. Extensão habilitada e provider da entidade continua User.
- Dois registros após ativação; digest integral idêntico ao anterior. IDs, valores
  e revogações preservados, sem escrita de concessões durante a verificação.
- Nenhuma importação histórica ou envio de aviso executado. G1 concluída;
  próxima fase G2 corrige critérios e integridade. UI, missões e privacidade ainda
  pendentes; nenhuma declaração de validação ponta a ponta dessas fases.

## G2 — implementação dos gatilhos e proteção da API

Classificação PATCH: Game `0.1.0-dev.2`, User `0.4.0-dev.4`, Portal `0.4.0-dev.34`.

- Comment insert/update: publicado agora, antes não publicado; pai é tópico
  publicado. Aprovação posterior passa pelo mesmo avaliador idempotente.
- Wiki: revisão publicada/default nova, ou aprovação de revisão antes não
  publicada. Um save comum sem nova revisão não concede. Autoria vem da revisão.
- LMS: interface instalada `CourseStatusInterface::getUserId()`, ausente no
  caminho anterior que chamava `getOwnerId()`; tratar insert e transição a passed.
- Pedido: apenas transição para completed; preservar a abrangência anterior de
  Apoiador até a decisão de critérios, sem introduzir recompensas por gasto.
- Entidades `isSyncing()` são ignoradas, como usa o Migrate Core instalado.
  Importadores próprios devem preservar esse contrato. Conta bloqueada ou com
  papel `usuarios_legados_forum` não recebe nova conquista pela API.
- Lock persistente por pessoa/conquista, com tentativa adicional após espera
  curta. Falha de lock lança exceção, em vez de perder uma avaliação silenciosamente.
  Callback do Transaction Manager libera após a transação externa, inclusive
  rollback; fora de transação libera ao fim da operação. Revogação usa o mesmo lock.
- Sem restrição SQL nova: a serialização vale para chamadas pela API única,
  dentro do prazo do lock (120 segundos). Código não deve criar concessões
  diretamente pelo storage. Unicidade em escrita arbitrária não está comprovada.

Fontes: [Lock API](https://api.drupal.org/api/drupal/core!lib!Drupal!Core!Lock!LockBackendInterface.php/interface/LockBackendInterface/11.x),
[callback após transação](https://api.drupal.org/api/drupal/core!lib!Drupal!Core!Database!Transaction!TransactionManagerInterface.php/function/TransactionManagerInterface::addPostTransactionCallback/11.x)
e [entidade original](https://api.drupal.org/api/drupal/core!lib!Drupal!Core!Entity!EntityInterface.php/function/EntityInterface::getOriginal/11.x).
Conferidos Core instalado, `EntityContentBase` do Migrate, armazenamento de revisão
e interface do LMS 1.2.3. Perfil básico/WhatsApp continuam critérios anteriores,
pendentes de decisão de produto; nenhum dado adicional é solicitado nesta fase.

### Validação e limites

PHP lint e gates Drupal 11+ (773), fronteira (1036), endereços (1722) PASS;
`git diff --check` PASS. Cache rebuild concluído; container resolveu o serviço com as novas dependências,
anônimo inelegível e os dois registros mantiveram o digest integral de G1. Não foram executadas simulações concorrentes ou fluxos de
publicação/aprovação de conteúdo. **RUNTIME STATUS: DEFERRED** para a matriz
funcional de G2; a implementação não encerra sua homologação. G3 permanece pendente.

## G3 — conta, perfil público e privacidade

Classificação MINOR: Game `0.1.0-dev.3`, User `0.4.0-dev.5`, Portal `0.4.0-dev.35`.

O checkout é compartilhado: uma execução paralela incluiu os arquivos iniciais de
G3 no commit publicado `828b076`, junto de alterações do Messenger. O commit de
registro da fase preserva esse histórico e reúne marcadores de versão, contratos,
revisão final e evidência; não representa um segundo backend ou implementação.

- Aba existente `/atividade/conquistas`, no purpose ACCOUNT, passa a mostrar
  conquistas reais e o formulário da própria pessoa. Sem UID fornecido pelo cliente.
- Perfil `/u/{apelido}` no MAIN recebe apenas a projeção pública, sem atores,
  evidências, nomes de cursos, notas, pedidos ou dados cadastrais. Sem conquistas
  visíveis, nenhum título/contador de itens ocultos é publicado.
- Perfil privado prevalece, conforme a política existente de perfis públicos
  obrigatórios. Contas bloqueadas e legadas não têm projeção pública de conquistas.
- Preferências públicas por padrão; ocultação global/individual, destaques e
  ordem por peso. Destaque não contorna ocultação. Lista própria mostra os fatos
  independentemente das escolhas públicas.
- Preferências via User Data do User; nenhum campo/schema/configuração novo.
  Desinstalar Game não apaga escolhas mantidas pelo User. Um link em
  Configurações → Privacidade leva ao formulário já integrado à aba.
- User oferece alters neutros, sem depender de Game. Renderização usa Form API,
  details e item list existentes, sem CSS, JS, ícone ou classe visual nova.
- Helper tem tag e metadados do Status. Gate passa a conferir serviços opcionais
  no YAML de seus módulos, além dos serviços do Portal principal.
- Novas caixas de autoria, missões e avisos permanecem nas fases seguintes.

### APIs e cache

[User Data](https://api.drupal.org/api/drupal/core!modules!user!src!UserDataInterface.php/interface/UserDataInterface/11.x)
e [cache de render arrays](https://www.drupal.org/docs/drupal-apis/render-api/cacheability-of-render-arrays)
conferidos com o código Core instalado. Preferências invalidam a tag individual;
entidade/lista de concessões, perfil e usuário propagam suas tags. Formulário
privado não é cacheável; lista própria varia por pessoa, pública por idioma.

### Validação e limites

Cache rebuild concluído no Runtime previamente confirmado. Container resolveu
helper e hooks. As listas próprias e públicas dos dois titulares de conquistas
foram renderizadas sem erro. Form API renderizou as seis escolhas, ocultação
global e ação de salvar, sob troca transitória de conta em memória, revertida no
fim; nenhuma preferência, conta, conteúdo ou conquista foi alterada pelo smoke.

PHP lint PASS; gates Drupal 11+ (774), fronteira (1040) e endereços (1726) PASS;
`git diff --check` PASS.

**RUNTIME STATUS: DEFERRED** para submissão no navegador, duas sessões, ocultação,
destaques, cache após POST e inspeção de acessibilidade/mobile. Renderização não
equivale a essas verificações. G3 implementada, homologação funcional pendente.

## G4 — missões e administração inicial

Classificação MINOR: Game `0.1.0-dev.4`, Portal `0.4.0-dev.37`.
Código inicial incluído por execução paralela em `5b325e5`; o commit da fase
consolida versões e evidências, preservando o histórico compartilhado.

- Três missões unitárias: contribuição no Growroom420, revisão publicada da
  Wiki e primeiro curso aprovado. Critério versão 1, um marco por missão.
- Conclusão consulta concessões ativas produzidas pelos gatilhos G2, sem nova
  avaliação do passado, saldo, tabela de progresso ou detalhes de fontes privadas.
- Configuração `aculta_game.missions`, com schema e catálogo fechado. Controle
  administrativo altera disponibilidade; desativar não apaga conquistas.
- Caixa Conquistas e missões na visão geral resolve a falta de entrada destacada.
  A interface anterior estava acessível somente pela aba Atividade ou Privacidade.
- `/painel-administrativo/game`: MAIN, permissão própria, peso 9 após Avisos (8).
  Administração manual e auditoria permanecem em G5.

### Aplicação e recuperação

Runtime confirmado; apenas update Game 10001 pendente, configuração de missões
ausente e dois registros de conquistas. O update instala somente essa configuração
se ausente, preservando-a em reexecuções. Nenhuma mudança de entidade/schema ou
importação/exportação global. Recuperação possível restaurando apenas o estado
anterior dessa configuração, mantendo concessões; não executada.

Update e cache rebuild concluídos. O Drush reportou inconsistência preexistente:
módulo de driver `mysql` não registrado como instalado, apesar de bootstrap e
conexão MariaDB funcionais. Nenhuma alteração do registro global de extensões.

### Falha encontrada e corrigida

Perfil anônimo retornava 500 em MessengerCounts/Flag: consulta de dispensa com
uid 0 sem sessão. Correção `a0afe29` limita consultas de dispensa aos autenticados,
conforme política existente, inclusive na prévia. Perfil anônimo voltou a responder.

### Evidência funcional

- Firefox **8/8**: entrada na visão geral, navegação AJAX, formulário, três missões,
  mobile 390 px sem rolagem horizontal, administração e posição abaixo de Avisos.
- HTTP com sessões independentes **17/17**: salvar ocultação global/individual,
  reexibição/cache, destaque, acesso pela privacidade, salvar ativação/desativação
  administrativa e efeito na conta, membro sem acesso, GET no host errado
  canonicalizado e POST no host errado negado.
- Preferências da conta `teste.comum` e disponibilidade da missão restauradas.
  Nenhuma criação/remoção de usuário, conteúdo ou concessão pelo teste.
- Capturas e resultados locais: `.suite-out/game/`, fora do Git. Suítes em
  `tests/browser/aculta-game/smoke.mjs` e `tests/runtime/aculta-game/http-smoke.py`.
  Hosts conferidos no Deployer; banco restrito a `aculta_runtime`.

G3 agora tem submissão, privacidade e cache entre sessões verificados. Continuam
pendentes a concorrência e jornadas de concessão de G2, leitor de tela e PHP 8.5.
Não extrapolar os resultados de G4 para esses casos.

## G5.1 — definições, concessão manual e histórico

Classificação MINOR: Game `0.1.0-dev.6`, User `0.4.0-dev.7`, Portal `0.4.0-dev.39`.
Parte inicial incluída pela execução paralela em `07fc35c`; o registro da fase
preserva esse histórico, com versões, controles e evidência.

### Decisão e implementação

- Responsável aprovou Apoiadore por pedido concluído, apoio direto no sistema de
  doações ou administrador, e criação/edição de conquistas na área Game.
- `/painel-administrativo/game/conquistas`: catálogo, criação e edição de nome,
  descrição, disponibilidade, imagem existente e formas de concessão fechadas:
  manual, pedido, doação, comentário, Wiki, LMS e perfil. Identificador permanente;
  mudança nas formas avança a versão. Pausar preserva conquistas anteriores.
- `/painel-administrativo/game/conquistas/conceder`: motivo obrigatório, ator e
  data. `/painel-administrativo/game/historico`: leitura paginada da entidade real.
  Motivos administrativos não entram no contrato público.
- Origem pertence à concessão: manual em definição mista pode ser revogada;
  automática anterior não se torna revogável por editar a definição.
- Commerce Donation Flow usa item `donation`; pedido `completed` despacha os
  gatilhos de pedido e, quando aplicável, doação. Coincidência não duplica prêmio.
  Sync/importações e contas bloqueadas/legadas continuam excluídos.

### Fonte de verdade e persistência

`AchievementDefinitions` do User usa `aculta_user.achievement_definitions`, com
schema e defaults compatíveis para os sete IDs existentes. Configuração contém
somente definições, não concessões/progresso. A
[Simple Config API](https://www.drupal.org/docs/drupal-apis/configuration-api/simple-configuration-api)
atende o catálogo pequeno de critérios fechados: não há regras executáveis,
novo backend ou motor concorrente. Sem configuração, defaults são usados; a
primeira edição grava override. User preserva os dados se Game for removido.

Edições sob lock atualizam apenas um item. Tags de configuração invalidam perfil,
conta e catálogo. Labels são texto, não templates confiáveis. Imagens vêm do
catálogo importado. Core/contrib e o SupportController instalado foram conferidos;
não há pagamento, carteira, checkout ou matrícula paralelos.

### Atualização e preservação

Antes: somente User 10003 pendente, duas concessões. O update adiciona campos
opcionais `motivo_concessao` e `motivo_revogacao` pela
[Entity Definition Update API](https://api.drupal.org/api/drupal/core!lib!Drupal!Core!Entity!EntityDefinitionUpdateManagerInterface.php/function/EntityDefinitionUpdateManagerInterface::installFieldStorageDefinition/11.x).
Não altera colunas antigas/provider; verifica campos instalados em reexecução.
Não oferecer rollback automático que apague motivos já registrados.

Update/cache rebuild concluídos no Runtime confirmado. Nove colunas anteriores,
ordenadas por ID, mantiveram duas linhas e o mesmo SHA-256 antes/depois:
`5427565f39a84256ee8a01b4c9a61e01a7e4f54dc87a00bc7dc359e9ae918822`.
Inconsistência preexistente do registro mysql continuou reportada, sem impedir a
atualização pontual. Sem import/export global de configuração.

### Verificação

- **34/34 HTTP**: 17 regressões de conta/privacidade/admin e 17 de criação/edição,
  critérios, versão, integração, concessão, imagem, motivo reservado, revogação,
  histórico e negação de acesso. Fixture/registro próprios removidos ao fim;
  contas, concessões originais e configuração anterior preservadas.
- Evento de pedido concluído com item donation repetido em transação: uma
  concessão ativa. Rollback sem persistir prêmio ou pedido. Não comprova fluxo
  Mercado Pago ponta a ponta, concorrência ou jornada completa LMS.
- Três PNGs servidos por HTTP com hashes originais. Nenhum e-mail ou cobrança.
- Firefox repetido após a integração administrativa e das imagens: **8/8**;
  entrada pela conta, AJAX, missões, controles mobile e administração preservados.
- Avisos e preferências de e-mail foram concluídos em G5.2; ECA não foi instalado.

## G5.2 — apresentação, avisos, e-mail e imagem — Runtime validado

Classificação MINOR: Game `0.1.0-dev.7`, User `0.4.0-dev.8`, Portal
`0.4.0-dev.40`. UX pesquisada no modelo administrativo do GamiPress: critérios
com nomes e descrições agrupados, estado/condição por conquista e preferências
de exibição. A implementação preserva o catálogo e a concessão Drupal existentes;
nenhum código ou plugin GamiPress foi instalado ou copiado.

- Editor separa identificação, imagem e condições por área da comunidade,
  formação, apoio à associação e equipe. Explica que qualquer condição marcada
  concede a conquista. Critérios continuam fechados e não executam código.
- Imagem continua com upload. “Remover imagem atual” abre confirmação dedicada;
  só o POST confirmado remove a referência. Arquivo gerenciado é apagado quando
  nenhuma outra área o utiliza; imagens empacotadas permanecem no módulo.
- Conta oferece uma área unificada de conquistas com estado, condição, visibilidade,
  destaque e ordenação. Catálogo de troféus duplicado foi removido. Aviso
  automático aparece em Avisos/Messenger, pode ser dispensado e conta na badge.
- E-mail é enfileirado no evento de nova concessão. A pessoa pode desativá-lo em
  suas preferências de conquistas. Worker valida concessão ativa, conta e envio
  idempotente antes de chamar Mail API. Não executamos cron nem enviamos e-mail real.

### Verificação no Homelab

- `drush cr`: concluído; Drupal local confirmou Runtime `aculta_runtime` e MAIN
  `aculta.toca.net.br`.
- Rota de confirmação descoberta pelo router Drupal e aberta no Firefox. Controles
  “Confirmar remoção da imagem” e “Voltar sem remover” renderizados.
- Firefox: **14/14**; editor de upload/condições, conta, mobile, catálogo sem
  duplicação, rota de confirmação e posição do item Game após Avisos.
- Exclusão real de arquivo `public://` verificada via serviço User com imagem de
  fixture; referência e fixture removidas. Arquivos originais do catálogo intactos.
- Gates: Drupal 11 **776**; fronteira módulo/tema **1066**; endereços
  dependentes de ambiente **1754**; slugs públicos **364** (20 exceções contrib
  da linha de base). `git diff --check` passou.
- Entrega de e-mail pelo transporte do ambiente, leitores de tela e concorrência
  de avisos não foram testados.

## G5.3 — conquistas no perfil público — Runtime validado

Classificação MINOR: Game `0.1.0-dev.8`, Portal `0.4.0-dev.41`, ACULTA420
`0.6.0-dev.54`. A referência GamiPress passa o usuário visualizado ao perfil e
mostra apenas conquistas obtidas, sem busca/filtro no perfil
([exemplo oficial de perfil](https://gamipress.com/snippets/buddypress-integration/custom-user-profile-tab-with-gamipress-shortcodes/)).
O ACULTA aplica a lista no perfil já existente `/u/<apelido>`.

- Os itens publicados são cartões responsivos com imagem própria ou símbolo
  ilustrativo de fallback, nome,
  descrição pública editorial e marcador “Em destaque”. Ordenação coloca os
  destaques e pesos escolhidos à frente.
- `hide_all`, preferência individual, perfil privado, conta inelegível e contas
  legadas continuam filtradas pelo mesmo presenter; não são exibidos detalhes
  de curso/pedido, origem, concessor ou motivo.
- Contrato de tema: `profile-achievements*` é token neutro do Game; ACULTA420
  traduz os tokens e define grade/cartões com tokens semânticos, em ambos modos.
- Cache conserva tags da concessão, perfil e usuário e tags de preferências e
  configuração. A rota continua limitada ao propósito MAIN.
- Perfil real do Runtime com conquista ativa exibiu o título e a seção; Firefox
  passou 19/19 checks incluindo avatar, fallback SVG, cartões desktop/mobile e
  ausência de rolagem horizontal. Gates Drupal 11, fronteira, Foundation,
  slugs/endereço e `git diff --check` passam.
- Imagens próprias devem ser quadradas, com **512 × 512 px** recomendados; o
  campo de envio informa esse tamanho, os formatos aceitos, o limite de 2 MB e
  o máximo de 4096 × 4096 px. Nesta fase, o fallback SVG foi substituído por
  PNGs temáticos próprios e um selo raster geral.

## G5.4 — arte cultural das conquistas — Runtime a validar

Game `0.1.0-dev.9`, Portal `0.4.0-dev.42`. Os selos históricos importados do
Coletivo 420 permanecem intactos. As conquistas de primeiro comentário no Fórum,
primeira edição na Wiki, primeiro curso e perfil básico recebem arte específica;
novas definições usam o selo geral de cannabis e antiproibicionismo.

- Cinco imagens PNG RGBA otimizadas em 512 × 512 px e com fundo transparente fora
  do selo. Cores e linguagem visual foram inspiradas nos materiais históricos,
  sem editar ou reutilizar suas imagens.
- `AGENTS.md` passa a exigir que futuras imagens considerem a identidade cultural
  canábica e antiproibicionista; origem, intenção e dimensões estão em
  `docs/portal/IMAGE-ART-DIRECTION.md`.
- A associação de imagem à definição respeita imagem enviada por UUID primeiro,
  arte histórica importada em seguida, arte temática por identificador depois e
  o selo geral como fallback final.

### Validação e limites

- PHP lint de `AchievementPresentation` e `AchievementDisplayForm`, `node --check`
  do smoke browser e `git diff --check`: PASS.
- Gate Drupal 11+ (776), fronteira módulo/tema (1069), endereços de ambiente
  (1759) e slugs públicos (364 rotas): PASS.
- As cinco artes novas foram verificadas como PNG 512 × 512; links da documentação
  resolvem para o guia. O smoke browser foi atualizado para conferir as quatro
  artes do catálogo e a arte de perfil.
- **RUNTIME STATUS: DEFERRED**. O Drush deste checkout confirma o banco
  `aculta_runtime`, mas resolve o site URI como `http://default`; não foi
  executado cache rebuild nem teste HTTP nesse contexto. O smoke browser e a
  apresentação das novas imagens precisam ser validados no Runtime com o URI de
  teste correto.
