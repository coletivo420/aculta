# Roadmap — ACULTA Links

Seguir o ciclo Dev → RC → Alpha e a autorização de escopo do responsável. Cada
fase fecha com commits atômicos, verificações, changelog e índice de estado.

## 0.1.0 — fundação e auditoria

Inventário local, pesquisa de referências e licenças, matriz, integração,
segurança e modelo proposto. Sem remover conteúdo ou alterar Runtime. Esta
entrega conclui documentação/fundação; nenhum código upstream foi copiado.

## 0.2.0 — migração preservadora e entidades base (em andamento)

1. Landing, formulário, configuração, presenter e hooks específicos pertencem
   ao submódulo; o update `10002` migra sem remover o original e o formulário
   dual-write mantém rollback. A integração usa os contratos atuais de Domain e
   tema.
2. Atualizar integração neutra Portal–tema e rota administrativa, com paridade e
   rollback demonstrados antes de corte.
3. As três entidades base estão iniciadas (`aculta_link`, `aculta_link_page`,
   `aculta_link_item`) com revisões e acesso privado; completar formulários e
   listagem com filtros de proprietário, publicação e histórico para as três entidades.
   A integridade de composição em 0.4.0-dev.17 exige proprietário comum entre
   item, página e destino, tanto na validação quanto na leitura pública.
   Filtros independentes de publicados/rascunhos nas três listas estão implementados em 0.4.0-dev.26; a seleção acontece antes da paginação e preserva os filtros das outras listas.
4. O inventário funcional dos arquivos Laravel/Blade prioritários do LinkStack
   `v4.8.6` está registrado em `LINKSTACK-PORTABILITY.md`, fixado no commit
   `dca586e8b8946a779cb212d21791cff6308fa7c0`. Faltam auditoria de
   licença/proveniência dos assets de terceiros e análise de compatibilidade
   jurídica antes de qualquer cópia. A auditoria por arquivo do Linky
   `2.0.0-beta1` está registrada em `LINKY-PORTABILITY.md`; confirmar cabeçalhos
   individuais e a compatibilidade Drupal 11 antes de eventual reutilização.
   Nenhum código upstream foi copiado.
5. Testar access, revisão, slug, cache, Domain, reversão e configuração; papéis
   não recebem permissões automaticamente.

Em 2026-10-20, os updates incrementais 10003–10007 foram aplicados no Runtime
de teste após backup verificado; o `updb` também executou o update aditivo LMS
10002 pendente. Não há updates pendentes. A integração Node passou por smoke
HTTP no purpose `links`; produção e revisão visual continuam fora do gate
concluído. A auditoria de licença por arquivo continua pré-requisito para
qualquer cópia upstream.

Na versão 0.4.0-dev.22, um erro de namespace na truncagem de descrição Metatag
foi reproduzido como HTTP 500 e corrigido para a API `Unicode` do Drupal
Component. Após `cr`, Firefox ESR via WebDriver BiDi confirmou 390 × 844 e
1440 × 1000 sem overflow e foco visível por teclado. Simulação isolada em
`data-bs-theme="dark"` manteve contraste computado mínimo de 9,72:1 nos textos
testados. Leitor de tela, troca de modo no Portal, a11y integral e revisão
independente ainda precedem RC.

## 0.3.0 — dashboard e editor (em andamento)

Listas paginadas, CRUD Core, operações por entidade, acesso a página pública/preview,
composição ordenável por tabela Drupal, inclusão contextual de botão a partir
de destino do proprietário e atalho no dashboard estão implementados. Em
0.4.0-dev.23, as ações rápidas são lista semântica e as tabelas recolhem colunas
secundárias em telas estreitas. Firefox autenticado via WebDriver BiDi confirmou
390 × 844, sem overflow horizontal (`scrollWidth=378`). Ainda faltam navegação
fluida entre páginas e editor de identidade em uma experiência coesa inspirada
no LinkStack. Em 0.4.0-dev.27, o organizador também edita título e descrição
inline e pré-valida todas as linhas para impedir submits parciais. Uma conta pode ter várias páginas; equipes complexas não são
prioridade.

Em 0.4.0-dev.28, o filtro de publicação deixou de emitir uma classe visual do
módulo que não tinha consumidor; os links e o cache permanecem iguais e o gate
de fronteira módulo–tema volta a passar. Filtros e editor continuam na fase
0.3.0; revisão visual autenticada continua pendente antes de RC.

Em 0.4.0-dev.24, os formulários Drupal para destino, micropágina e item foram
corrigidos para `ContentEntityForm` após inspeção autenticada encontrar ausência
dos widgets. Kernel e Firefox autenticado a 390 × 844 confirmam os campos dos
três formulários sem overflow. Edição preenchida e submissão funcional ainda
precisam de validação específica. O formulário de edição de destino preexistente
foi confirmado a 390 × 844 sem salvar; páginas/itens e submissão continuam
pendentes.

O editor de composição foi inspecionado no mesmo viewport com uma micropágina,
um item e um destino temporários criados pela Entity API. Tabela, texto, estado,
controle para revelar pesos numéricos e fallback sem JavaScript foram vistos sem
overflow; nenhum formulário foi enviado. As três entidades de fixture foram
removidas pela Entity API após revalidar IDs, proprietário e referências, e a
consulta posterior confirmou a ausência do título, slug e IDs. Edição visual de
página/item e submissão real permanecem pendentes.

Em 0.4.0-dev.25, Kernel executa a submissão do editor pela Form API: a ordem e
o estado do item são persistidos em nova revisão; um item de outra página é
rejeitado mesmo sob o mesmo proprietário. A validação HTTP/browser do submit e
a edição visual dos formulários de página/item continuam pendentes.

## 0.4.0 — interface pública e presets

Rota pública, prévia privada e presets governados `classic`, `soft` e `outline` implementados em 0.4.0-dev.4; em 0.4.0-dev.5 a landing raiz foi restaurada com preservação do Node editorial e fallback dos dados atuais. Em 0.4.0-dev.9, o template da micropágina passou a mostrar seu título e a omitir a marca institucional da ACULTA. Em 0.4.0-dev.12, o alter hook do Metatag fornece metadata por página e canonical via `DomainPurposeManager`; defaults permanecem controlados pela configuração central. Faltam controles adicionais de aparência e revisão visual/acessível antes de RC.
Identidade pública mínima opt-in do proprietário foi conectada a `aculta_user` e ao slot ACULTA420 em 0.4.0-dev.15. Revisão visual/acessível segue pendente.

## 0.5.0 — métricas e ferramentas

QR Code SVG da URL canônica para páginas publicadas foi implementado em
0.4.0-dev.10, com dependência MIT Endroid e licenças transitivas registradas.
Janelas de disponibilidade por item foram implementadas em 0.4.0-dev.11 com
campos revisionáveis, edição no fuso padrão do portal, persistência UTC,
validação do intervalo e expiração de cache no próximo limite. Redirects curtos
foram implementados em 0.4.0-dev.13 e QR SVG correspondente em 0.4.0-dev.14.
Em 0.4.0-dev.16, GETs válidos dos redirects podem alimentar contagens diárias
por destino via Queue API, com retenção de 90 dias e controle administrativo
desligado por padrão. Page views, CTR por micropágina, origem/campanhas e cliques
dos links diretos continuam fora da métrica. Base legal precisa ser validada.

## 0.6.0 — integração do ecossistema

Integração de identidade com `aculta_user`, Domain de páginas e redirects e
destinos `entity:node/{id}` por `field_domain_source` implementados. Em
0.4.0-dev.21, Entity Reference selecionável para Nodes foi implementado com o
widget Core e as mesmas regras de resolução segura. Permanecem referências
editáveis para outras entidades além de Node, rotas de Group/cursos, Commerce,
campanhas e mídia; cada conteúdo seguirá sob seu módulo proprietário.

## 0.7.0 — administração e hardening

Administração global, moderação, limites, observabilidade, cache e proteção contra
abuso, com configuração ambiental no aculta_deployer. A coleta agregada de
redirects está desligada por padrão (0.4.0-dev.16), e a propriedade das
composições é validada no save e no Entity Access (0.4.0-dev.17). Ainda faltam
avaliação de abuso/rate limit, auditoria final de cache e revisão independente.

## 1.0.0 — revisão e lançamento

Auditar acesso, testes, Domains, a11y, desempenho, atualizações, licenças e
rollback. Seguir gates Dev → RC → Alpha; sem deploy, tag, PR ou merge automático.
O smoke de Runtime de teste em 2026-10-20 não equivale a RC, validação Alpha ou
prontidão de produção. A revisão visual/acessível completa, revisão de código
independente, decisão legal sobre analytics e auditoria licenciada por arquivo
permanecem gates explícitos.
