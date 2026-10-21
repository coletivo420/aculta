# Estado atual — ACULTA Links

## Git e escopo

- Branch: `docs/aculta-forum-0.1.0-dev.1`.
- PR associada: [#297](https://github.com/coletivo420/aculta/pull/297), cujo
  assunto é o Fórum; não foi criada PR para Links.
- HEAD inicial da auditoria: `1d54c26faf4ca81f00deea6344449bdc97ee53b8`.
- O working tree e o index estavam limpos no início. Desde então, Links evoluiu
  em commits isolados; outras frentes continuam compartilhando a branch. O
  módulo e seus updates incrementais estão habilitados no Runtime de teste.

## Implementação encontrada

O Domain `links_aculta_org` e o purpose `links` existem. O
`PurposeFrontPathProcessor` traduz internamente a raiz pública `/` para
`/redes-sociais`, preservando a URL de navegador. Em 2026-10-20, a consulta ao
Runtime encontrou zero Nodes com `field_domain_source = links_aculta_org`, sem
alias para `/redes-sociais`; antes da restauração, raiz e caminho interno
retornavam 404. A rota agora declarada em `aculta_links` mantém o caminho interno
bloqueado para acesso direto, renderiza o Node editorial correspondente se o
alias existir e for da origem Domain `links`, ou usa o fallback existente de
botões e View. Após rebuild final: route provider confirma `/redes-sociais`, HTTP
da raiz 200, path direto 404, resposta inclui ACULTA/botão/projetos. Produção e
revisão visual da landing permanecem pendentes.

O Portal constrói o botão para MAIN, os botões editoriais configurados, os
canais oficiais e o rodapé. A landing do submódulo reutiliza botão/configuração
e uma View existente para teasers de projetos. O formulário/settings, channels,
footer, presenter e hooks continuam no Portal até migração atômica futura.
Há também um campo `field_social_links` em Node `page`, mas o template dedicado
não consome o render array desse campo; seu uso efetivo precisa ser confirmado
com dados/editoria antes de qualquer migração.

## Classificação

| Recurso | Estado | Decisão |
| --- | --- | --- |
| Domain `links_aculta_org` e mapeamento do purpose | EXISTENTE E FUNCIONAL (config/código; DNS de produção não confirmado) | Preservar; resolução pertence ao Domain e ao `DomainPurposeManager`. |
| Rota raiz e mapeamento para `/redes-sociais` | RESTAURADA; Runtime HTTP 200 na raiz e 404 no caminho direto após rebuild | Rota preserva Node editorial de origem correta; fallback reutiliza configuração/Views sem editar política compartilhada do Portal. |
| Canais sociais institucionais e botões configuráveis | EXISTENTE E FUNCIONAL por código; estado visual não revalidado | Portar a propriedade para o submódulo em etapa própria, preservando valores. |
| View `aculta_projects`, display `social_links` | EXISTENTE E FUNCIONAL como integração editorial compartilhada | Reutilizar via Views; não copiar Nodes nem criar dados espelhados. |
| Pattern `aculta420:social-links-page` e template Node dedicado | EXISTENTE, apresentação experimental | Preservar nesta fase; adaptar quando o contrato de micropágina estiver definido. |
| `node.page.field_social_links` | EXISTENTE; consumidor no template dedicado não localizado | Investigar preenchimento e referências; não remover nem migrar nesta fase. |
| Redes pessoais e perfil `/u/{apelido}` do `aculta_user` | EXISTENTE E FUNCIONAL em outro purpose/contrato | Preservar como capacidade compartilhada, com exposição explícita. |
| Links curtos, micropáginas por pessoa e editor | EXISTENTE no purpose `links`; rotas/build registrados no Runtime de teste | Preservar entidades Drupal; smoke temporário de referência Node em HTTP concluído, sem declarar revisão visual completa. |
| Analytics de destinos | EXISTENTE PARCIAL e DESATIVADO por padrão: cliques GET em códigos curtos são agregados por link/dia em fila; sem page views, visitantes únicos ou origem | Habilitação administrativa exige validação da base legal; agregados expiram em 90 dias e o painel limita-se às entidades autorizadas. |
| Linky/LinkStack na instalação | AUSENTE (não são dependências do projeto) | Usar como fontes de estudo; nenhuma dependência Composer adicionada. |

## Hardening atual

- `aculta_link_item` valida em Entity Validation que item, página e destino
  tenham o mesmo proprietário. O handler também nega a visualização pública de
  composições inconsistentes antigas.
- Redirects curtos podem contar GETs válidos com agregação diária sem
  identificadores de visitante, retenção de 90 dias e coleta explicitamente
  desativada por padrão. A base legal precisa ser validada antes da ativação.
- Ainda não há revisão visual/acessível completa nem rate limiting dedicado.
  Updates Links 10003–10007 foram aplicados no Runtime de teste com backup; o
  update aditivo LMS 10002 também foi executado por ser parte do mesmo `updb`.
  Um smoke temporário HTTP confirmou destino Node e URL Domain; nenhuma fixture
  permaneceu. Produção não foi alterada nem verificada.
- Uma mudança não commitada do LMS trocou `AnswerEvaluationEventHooks` por
  `AnswerLifecycleEventHooks` e causou uma tentativa de save interrompida por
  cache de discovery antigo; a fixture parcial foi limpa por identidade exata.
  Após `cr`, Drush voltou a executar normalmente. Nenhum arquivo LMS foi
  alterado por Links.

## Consumidores e acoplamentos

- `DomainPurposeManager`, registro do Domain e processador de raiz pertencem à
  infraestrutura comum do Portal.
- Presenter, preprocess do footer/node, View de projetos e theme suggestion
  agora pertencem ao submódulo. Portal fornece Domain e suas APIs compartilhadas.
  O update/cópia preserva `aculta_portal.social_links` para rollback com
  dual-write temporário.
- O template Node dedicado chama o SDC `social-links-page`; esse SDC também
  depende do contrato neutro de apresentação do tema.
- A View e o conteúdo de projetos são editoriais compartilhados, não propriedade
  do sistema de micropáginas.
- `aculta_user` disponibiliza links pessoais em perfil público conforme sua
  política de visibilidade; não é o mesmo produto que `links.aculta.org`.

## Fundação de dados 0.2.0 em desenvolvimento

- `aculta_link`: destino pelo campo Link Core, dono Drupal User, publicação,
  Views data e revisão.
- `aculta_link_page`: título, slug, apresentação e dono; páginas começam
  despublicadas.
- `aculta_link_item`: referência à página/destino, rótulo, descrição, ordem e
  ativação; o access handler confere ownership dos dois alvos.
- Access de páginas e destinos permanece privado por proprietário até publicar;
  páginas publicadas têm a rota `/pagina/{slug}` em `links`, e a prévia exige
  edição e não é cacheada. O dashboard e os formulários/listagens ficam no MAIN,
  em rotas administrativas sob `/painel-administrativo/links/**`.
- Testes Unit e Kernel passam no banco dedicado. Módulo habilitado no Runtime de
  teste em 2026-10-20; `config/sync/core.extension.yml` registra a extensão e
  não houve importação global. Updates 10003–10007 foram aplicados com dump
  privado verificado. Produção não verificada.

## Pendências para a migração

1. Inventário do Runtime: zero Node de origem links e zero alias `/redes-sociais`;
   fallback restaura a landing sem mudar conteúdo/configuração. Nenhum dado foi
   migrado.
2. Migrar formulário, configuração e presenters para `aculta_links` sem mudar
   rota, URL, domínio, conteúdo ou apresentação.
3. Atualizar consumidores do Portal de forma atômica e demonstrar paridade.
4. Entidades e gestão administrativa básica existem em código; integração direta
   com aculta_user, perfil visual, SEO e outras entidades permanece pendente.
5. Resolver a ambiguidade de licença da tag LinkStack antes de copiar qualquer
   asset ou código.


## Painel administrativo 0.2.0-dev.2

`aculta_links` declara painel próprio e formulários Core de criação, edição, exclusão e histórico de revisões para páginas, destinos e itens. O painel filtra por proprietário, pagina resultados e revalida acesso de edição de cada entidade. As rotas ficam em `main`; módulo habilitado no Runtime de teste e rotas registradas. A apresentação da landing é preservada, enquanto configuração e presenters continuam parcialmente no Portal.


## Micropáginas públicas 0.4.0-dev.1

`/pagina/{slug}` está ativa no Runtime de teste no purpose `links`, com status publicado e acesso revalidado em página, item e destino. A prévia em `/pagina/{slug}/previa` é hospedada no mesmo purpose para usar o tema público, exige edição e não é cacheada. Micropáginas guardam preset de apresentação revisionável; o update `10001` foi aplicado no Runtime após confirmar que não havia páginas existentes. ACULTA420 apresenta o SDC existente. Landing raiz restaurada; integração com perfil público/avatares pendente. Produção não foi verificada.

## SEO por micropágina 0.4.0-dev.12

Metatag usa um alter hook OOP em rotas públicas de micropágina para preencher
título, descrição, canonical de Domain, Open Graph e Schema.org. Defaults
globais e por tipo continuam sob administração Metatag; seus cache tags são
incluídos no render da página. Preview, dashboard e rotas de CRUD recebem
`noindex, nofollow`. A política privada tem teste Unit. HTTP canônico da
referência Node foi conferido no Runtime de teste; metadata da página, visual e
acessibilidade completos ainda não foram revisados.

## Links curtos 0.4.0-dev.13

O primeiro save de um destino Drupal atribui um código aleatório de 12
caracteres; `/redirecionar/{codigo}` consulta exclusivamente entidades
publicadas, revalida Entity Access e valida a URI antes de responder 302. O
dashboard mostra o link curto no host do purpose `links`. O update `10004`
instala o campo sem reescrever destinos existentes; entidades antigas ganham
um código quando forem salvas novamente. Runtime/migração estão DEFERRED por
falha externa do container `aculta_lms`.

## QR de links curtos 0.4.0-dev.14

O dashboard baixa um SVG local que codifica a URL canônica do redirect curto
no purpose `links`. O endpoint revalida publicação, access e destino; SVG não
é persistido e nenhum evento de scan é gravado. A existência e o purpose da
rota são cobertos pelo Kernel; download e headers no Runtime continuam DEFERRED
devido ao bootstrap compartilhado quebrado do `aculta_lms`.

## Referência selecionável a Node 0.4.0-dev.21

Destinos reutilizáveis aceitam um Node pelo widget Core `entity_reference_autocomplete`
ou uma URL/caminho pelo campo Link, nunca ambos. `LinkDestinationChoice` exige
uma origem, e páginas/redirects passam pela resolução existente de Domain,
publicação, acesso anônimo e cache. O update 10007 instala o campo revisionável
sem alterar destinos existentes. Em 2026-10-20, updates 10003–10007 foram
aplicados ao Runtime de teste após backup; Drush confirmou storage instalado e
zero updates pendentes. Smoke com Node público confirmou HTTP 200 e canonical
no purpose da origem; acesso anônimo ao dashboard respondeu 403. Uma micropágina
temporária e seus itens foram excluídos, e seu endereço voltou a 404. A fixture
de inspeção visual foi removida sem screenshot: Firefox estava ocupado e o
headless não terminou. Update LMS 10002 foi aplicado junto, de forma aditiva,
por compartilhar o comando. Uma nova tentativa foi bloqueada pela edição
incompleta de hook LMS; o único destino temporário gravado foi removido por
identidade exata e sua ausência confirmada. Produção continua não verificada.

## Erro de metadata 0.4.0-dev.22

O primeiro GET de uma micropágina temporária, após o hook LMS ser restaurado,
retornou HTTP 500. O registro `watchdog` identificou `Class
"Drupal\Core\Utility\Unicode" not found` em `LinkPageMetatagHooks`; a API
atual do Core fica em `Drupal\Component\Utility\Unicode`. A referência de
namespace foi corrigida; Unit e Kernel passaram. Após `cr`, a página temporária
respondeu 200 e exibiu title Metatag e destino canônico esperado. Firefox ESR
via WebDriver BiDi foi inspecionado em 390 × 844 e 1440 × 1000 sem overflow; o
focus por teclado mostrou outline de 3 px. Em sessão isolada com tema escuro, os
tokens de título, descrição e botão atingiram contraste computado de 12,95:1,
9,72:1 e 14,50:1. Página, item e destino temporários foram excluídos pela
Entity API, e o slug voltou a 404. Troca de modo no Portal, leitor de tela e
revisão completa de acessibilidade continuam pendentes.
