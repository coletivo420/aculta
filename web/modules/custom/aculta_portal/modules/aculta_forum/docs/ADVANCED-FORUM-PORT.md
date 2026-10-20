# Matriz de capacidades — Advanced Forum

Pesquisa documental concluída na fase `0.1.0-dev.1`. A referência funcional
histórica é a linha Drupal 7 (Advanced Forum 7.x-2.8 e documentação de recursos),
com Author Pane e módulos auxiliares como dependências separadas. Código legado
serve somente para inventário de comportamento; não será executado nem portado
como implementação PHPTemplate. O projeto upstream está sem release estável
suportada atual; qualquer comportamento final é contrato ACULTA e API Drupal 11+.

Fontes: [projeto Advanced Forum](https://www.drupal.org/project/advanced_forum),
[matriz de features upstream](https://www.drupal.org/node/841056),
[documentação dos estilos](https://www.drupal.org/docs/extending-drupal/contributed-modules/contributed-module-archive/contrib-modules-for-building-the-site-functionality/social-networking-and-collaboration/community-building-and-social-networking-61),
[releases](https://www.drupal.org/project/advanced_forum/releases).

## Temas, extensibilidade e estilo

| Capacidade histórica | Tratamento ACULTA | Dono/limite |
| --- | --- | --- |
| Temas selecionáveis, herança de subestilo e 22 templates PHPTemplate | Substituir por seis presets de composição SDC/Bootstrap responsivos; sem copiar TPL ou CSS legado | ACULTA420; projeto decidiu 6 presets. |
| Naked / Naked Stacked | Referência para presets neutros lateral e empilhado | ACULTA420; tokens semânticos. |
| Blue Lagoon / Blue Lagoon Stacked | Referência para presets de ênfase cromática, incluindo modos claro/escuro | ACULTA420; não reutilizar paleta antiga. |
| Boxy / Boxy Stacked | Referência para presets de contorno/superfície, incluindo modo empilhado | ACULTA420. |
| Alteração por template suggestion/preprocess hooks | Usar SDC e contrato neutro; `#[Hook]`/DI no Portal apenas para preparar dados | Portal integra; tema apresenta. |
| Page Manager/Panels e style plugins | Não portar como dependência; Views/CTools configuram listagens com filtros de acesso fixos | Core/CTools existente. |
| Botões de ação por imagem | Substituir por ações semânticas, Bootstrap Icons, teclado e rótulos acessíveis | ACULTA420 + APIs Flag/Comment/Node. |
| Preview de publicação | Usar preview/formulários do Core, preservando acesso e renderização | Form API/Entity API. |

## Visão geral e categorias

| Capacidade | Tratamento | Critério/nota |
| --- | --- | --- |
| Categorias e subcategorias / containers separados | Portar com Taxonomy `aculta_forum_categories` | Hierarquia explícita, breadcrumbs e acessibilidade. |
| Busca em todos os fóruns | Portar via Search API com escopo/purpose | Nunca indexar LMS privado na busca pública. |
| Marcar todos os fóruns como lidos | Implementado em dev.29 pela confirmação e Core Batch sobre History | Por conta autenticada; tópicos publicados e access-aware no purpose público. Kernel MariaDB prova escopo e grava somente o History da pessoa. |
| Ícone por fórum/categoria (Taxonomy Image) | Portar por referência Media/Imagem através de `aculta_image` | Definir finalidade do catálogo antes de campo. |
| Ícones de pasta com novos posts / legenda | Portar como status textual e visual acessível | Ícone nunca é único indicador. |
| Novos posts/tópicos com links | View autenticada de não lidos usa History e `node_new_comments` para contagem e link à primeira resposta nova | Cache sem compartilhamento; o Runtime ainda não tem resposta aprovada para validar o caso positivo de paginação/âncora. |
| Último tópico/atividade e último autor | Portar em View/query cacheável com access | Não vazar nomes ou existência de contexto privado. |
| Estatísticas de usuários ativos, membro recente, membros/tópicos/posts | Portar apenas estatísticas úteis e aprovadas | “Online agora” fica sujeito à política de privacidade; não inferir de visualização de página. |
| Hierarquia e contagens dependentes de node access | Portar com query access e agregação segura | Não confiar em contagem que ignora grants/Group. |

## Listagens de categoria e tópico

| Capacidade | Tratamento | Critério/nota |
| --- | --- | --- |
| Listagem de tópico como Views personalizável | Portar via Views | Filtros de Domain/contexto/access permanecem fixos. CTools só expõe opções aprovadas. |
| Busca em categoria e em tópico | Portar via Search API contextual | Aplicar access igual às rotas. |
| Pager no topo e paginação contextual | Portar via pager Views/Core | Links preservam query, contexto e canonical. |
| Seção separada para fixados | Portar se suportado pela entidade/Flag/config validada | Ordenação previsível e acessível. |
| Reorganizar categoria/subcategoria | Planejar com Taxonomy parent/weight | dev.9; preservar acesso fixo, validar hierarquia e impacto em Views/breadcrumbs. |
| Mover tópico entre categorias e em lote | Planejar atualização do Node existente via Entity API | dev.9; mesmo vocabulário/purpose público, access na origem/destino e confirmação; preservar ID, revisão, alias e Comments. Não criar Node duplicado. |
| Tópico-sombra/redirect | Não padrão; avaliar apenas com decisão específica de URL | Não duplicar entidades; preservar canonical e links conforme política de slug. |
| Mover resposta, dividir ou mesclar tópico | Planejar movimento de Comment/subárvore no mesmo contexto; split/merge após prova adicional | dev.12; estado de moderação e autoria preservados, sem mover entre Fórum/Revista/LMS/Wiki; acervo histórico imutável. Ver `MOVE-TOOLS.md`. |
| Tópico popular/hot e contagem de visualizações | Portar contagem com cache/decisão de métrica | Visualização ≠ sessão/pessoa ativa; proteção contra bots e duplicidade. |
| Contagem de tópico/respostas e estatística breve | Portar por serviço/View cacheável | Sem COUNT caro em cada render e sem contar itens inacessíveis. |
| Respostas/comentários por tópico criado | Reutilizar `comment_count`/`comment_entity_statistics` do Core e exibir somente comentários publicados e acessíveis | Planejado em dev.9; invalidar em aprovação, despublicação, remoção e movimento; pendências/rejeitados não aparecem ao público. |
| Acompanhar e salvar tópicos | Flags individuais `seguir_topico` e `salvar_topico` aplicadas somente a `aculta_forum_topic`; a associação ao bundle removido do Forum contrib foi retirada após o uninstall | Flag conserva o storage e permissões por usuário; notificações por acompanhamento ficam dependentes da integração Messenger futura. |
| Ordenação por atividade/data | Portar com sort Views permitido | Não expor configuração editorial perigosa em bloco. |
| Datas relativas/absolutas e teaser em hover | Portar com formato acessível | Hover tem alternativa de foco; preferir informação disponível sem hover. |
| Ícones/legenda de estado | Portar como estado textual mais ícone | Novo, fechado, fixado e moderação devem ter rótulo. |
| Última postagem no tópico de abertura quando não há replies | Portar | Não inventar comentário; link para tópico canônico. |

## Thread e Author Pane

| Capacidade | Tratamento | Critério/nota |
| --- | --- | --- |
| Resposta linear ou encadeada | Portar como modos configurados do campo Comment | Nativo Comment; sem entidade de resposta própria. |
| Painel de autor lateral e layout empilhado | Portar em variante `aculta_user` + preset do tema | Ver `aculta_user/docs/AUTHOR-PANE-PORT.md`. |
| Ano de entrada do participante | Mostrar ano de criação da conta ativa em cartões públicos do Fórum | Perfil privado oculta a data; autores Growroom históricos não recebem data de conta inferida. |
| Nome/avatar/links de perfil | Reutilizar Author Card API de `aculta_user` | Privacy/access antes de construir dados. O ano da conta aparece apenas em perfil público; estatísticas por autor aguardam fonte/access/cache próprios. |
| Mensagem privada/amizade | Reutilizar API de `aculta_user` e `aculta_messenger` | Só participantes atuais e conforme permissão; nada para legados. |
| Email e campos opcionais | Não mostrar por padrão; somente dado público autorizado | Email privado não integra view-model público. |
| Assinaturas de fórum | Candidato a port; requer política/sanitização de conteúdo própria | Não inserir HTML livre ou elevar formato. |
| Cabeçalho: total, novas respostas e última resposta | Portar | Totais respeitam access; links a primeira/última não lida. |
| Botões responder/citar/editar/excluir | Portar com Comment/Form/Access APIs | Permissão nativa e política de confirmação de email. |
| Numeração de posts e permalink | Portar com permalink estável de comentário e anchor acessível | Não usar posição de paginação como ID permanente. |
| Link para resposta pai (“Responder a #N”) | Portar se estrutura Comment encadeada habilitada | Link para comentário pai com access/canonical. |
| Navegação próximo/anterior tópico | Portar via View/contexto | Omitir se lista privada não puder ser enumerada com segurança. |
| Navegação topo | Portar como affordance acessível | Respeitar movimento reduzido. |
| Comentário-título oculto quando vazio | Portar configuração/display do Comment | Não renderizar título duplicado. |
| Preview de resposta | Usar Form API/preview do Drupal | Sem template legado. |
| Anexos | Portar por Media/File, `aculta_image` e handlers existentes | Arquivo sempre revalida acesso do contexto. |
| Resposta citada | Portar com sanitização e referência ao original | Dev.53: ação **Citar** usa pai e rota Core, valida acesso de leitura/escrita no contexto e preenche texto filtrado, escapado e limitado. Seleção de trecho e contextos sem provider ainda não estão implementados. |
| Conteúdo não publicado | Estado/aviso só para quem tem permissão de moderação | Não revelar existência a leitores sem access. |
| Assinatura de autor | Capacidade confirmada pelo post template/Author Pane | Não port automático; exige campo, formato permitido, moderação e visibilidade aprovados. |
| IP e Fasttoggle no Author Pane | Presentes na seção administrativa do template histórico | Excluir do cartão público. A moderação fica em interface separada e autorizada. |
| Pontos Userpoints | Variável opcional no Author Pane | Excluir integração histórica de terceiros; não substituir por ranking. |

## Views complementares e estatísticas

| Capacidade | Tratamento | Critério/nota |
| --- | --- | --- |
| Tópicos ativos/novos | Portar como Views por contexto | Ordenação por atividade; acesso fixo. |
| Minhas postagens | Portar como Views/EntityQuery com owner access consciente | Conta pode ver autoria sem ganhar acesso ao conteúdo privado. |
| Sem resposta | Portar como View com contagem correta e access-aware | Não inferir ausência por comentário não acessível. |
| Atividade recente/participação por autor/categoria | Portar mediante orçamento/perf e privacidade | Agregações cacheáveis, sem estatística privada em cartão público. |
| Usuários online | Revisar pertinência e consentimento | Só se houver política clara; atividade de request não prova presença humana. |
| Estatísticas globais (tópicos/respostas/pessoas) | Portar com cache tags e invalidação | Considerar publicação, moderação e acesso. |
| OG RON (Group topic lists como fórum) | Não portar como mecanismo próprio; revisar adapter LMS | Group fornece sua integração/acesso; não duplicar listagem. |

## Classificação geral

O objetivo é portar capacidades pertinentes, não APIs, templates, permissões,
bugs ou decisões de segurança do sistema Drupal 7. Recursos condicionais
(tópicos-sombra, online, assinatura, popularidade/visualizações, citações e
attachments) permanecem no inventário e exigem confirmação no desenho da fase
funcional correspondente. A validação completa só acontece em RC; nesta fase
registramos contrato e perguntas técnicas.
