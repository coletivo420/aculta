# Decisões — ACULTA Fórum

Data: 2026-10-15. Fonte: decisões explícitas do responsável nesta sessão.
Atualização do responsável: marca **Growroom420**, prefixo **`growroom420.aculta`**.
O acervo de origem continua identificado como Growroom nas fontes históricas.
Este documento registra histórico e decisões vigentes; não descreve alterações já aplicadas.

## Decisões vigentes de reconstrução — 2026-10-19

As decisões abaixo foram fornecidas explicitamente pelo responsável e governam
as fases novas. Itens antigos incompatíveis são preservados como histórico, mas
não orientam a implementação futura. Decisões antigas compatíveis continuam
válidas (purpose `forum`, acesso privado de LMS, e-mail de participação,
notificações existentes, autoria histórica em entidade própria e não conceder
conquistas na importação).

| ID | Decisão vigente | Efeito |
| --- | --- | --- |
| F-D22 | Substituir definitivamente `drupal/forum` por motor ACULTA baseado em Node, Comment, Taxonomy, Views, Search API, History, Flag e APIs Core/contrib | Retirada executada no Runtime de teste em dev.50 após inventário, substituição dos consumidores, restore point e autorização operacional. Produção não foi alterada. |
| F-D23 | Um motor de discussões compartilha quatro contextos: Growroom público, Publicações, LMS e Wiki | API de contexto/provider; revalidação de Entity Access e política específica por contexto. |
| F-D24 | Growroom contém somente tópicos públicos independentes; discussões internas de curso/turma/atividade ficam no LMS | Discussão geral sobre cursos pode ser pública; conteúdo pedagógico interno não aparece em busca, listagens ou aviso públicos. |
| F-D25 | Publicação usa Comments na própria entidade; é proibido criar tópico público automaticamente por publicação | Sincronização e backfill removidos; `LegacyPublicationTopicLookup` somente leitura preserva compatibilidade até corte dos vínculos históricos. |
| F-D26 | Wiki terá uma discussão Comment por verbete dentro da página; não cria tópico público nem altera revisão editorial | Só contratos/adaptadores preparados agora; reconstrução funcional da Wiki permanece fora de escopo. |
| F-D27 | `aculta_user` é dono único de cartões e dados de identidade/autoria; `aculta_forum` não possui builder, renderer ou consulta privada própria | Reutilizar/estender serviços de User; ACULTA420 desenha. |
| F-D28 | ACULTA420 é o único dono de markup visual, SDC, CSS, Bootstrap e presets | Portal fornece contrato neutro com acesso/cache; não referenciar componentes do tema do PHP. |
| F-D29 | `aculta_forum` inicia versão própria `0.1.0-dev.1`; cada outro módulo conserva sua linha/versionamento | Fases e commits seguem o roadmap; documentação não avança versões runtime alheias. |
| F-D30 | Fase `0.1.0-dev.1` é auditoria/documentação sem mudanças no Runtime ou exclusão | Decisão não autoriza `updb`, cim, uninstall, composer remove ou apagar conteúdo. |
| F-D34 | O Growroom420 público não tem níveis de visibilidade por categoria/papel; discussões internas do LMS dependem de Group e matrícula contextual | Regras antigas de realms na taxonomia `forums` são aposentadas. Views/avisos devem revalidar acesso; nenhum papel global abre curso/turma. Config Sync e remoção contrib permanecem etapas próprias. |

### Decisões históricas substituídas

- **F-D06** (contas User bloqueadas para autores históricos) já havia sido
  substituída por F-D17; permanece revogada. F-D17 e a entidade
  `aculta_legado_autor` continuam compatíveis e vigentes.
- Arquitetura antiga que mantinha Forum contrib como fonte dos tópicos e
  estratégia de sincronização da Magazine está substituída por F-D22/F-D25.
- Propostas de chat automático por tópico/curso/verbete e de membership de
  conversas derivada de assinaturas foram superadas por F-D41. A distinção
  entre discussão contextual (Comment) e conversa privada (Private Message)
  permanece obrigatória.
- A afirmação de que a próxima fase era F4 e os planos F1–F9 descrevem uma linha
  anterior; são histórico de execução, não roadmap vigente. Consulte
  `ROADMAP.md` para as versões novas.
- F-D02 segue válido: purpose interno `forum` e endereços por ambiente continuam
  sob DomainPurposeManager/Deployer. A substituição do motor não altera domínio
  por si só.

## Aprovado

| ID | Decisão | Consequência |
| --- | --- | --- |
| F-D01 | Reconstruir o Growroom usando o Wayback, sem backup próprio disponível | Medir cobertura e preservar lacunas; não prometer conteúdo não arquivado |
| F-D02 | Prefixo público `growroom420` em lugar de `forum` | Deployer, Domain/aliases, infraestrutura e URLs serão atualizados por ambiente |
| F-D03 | Leitura comunitária pública; postagem por cadastrados | Permissões nativas e regras atuais de participação |
| F-D04 | Discussões do LMS reservadas a matriculados e equipe | Acesso, busca, cache e notificações dependem do contexto do curso |
| F-D05 | Acervo fechado e nova conversa vinculada | A postagem atual não modifica a sequência histórica |
| F-D06 | Contas históricas bloqueadas com papel Usuários legados do fórum | Preservação de `uid` de autoria sem login ativo |
| F-D07 | Nomes individuais e memorial coletivo | Cada autor mantém nome e conteúdo; o link público comum é `/u/lendashistoricas` |
| F-D08 | Sem página individual pública de legado | Não gerar `/u/<apelido>`, subpáginas do memorial ou `/user/<uid>` público para essas contas |
| F-D09 | Avatar especial com o sistema atual | Acervo/File/Image e caixas existentes, sem biblioteca de avatar paralela |
| F-D10 | Reutilizar galerias do Portal | Respeitar a publicação atual na Revista; referências a partir do Fórum |
| F-D11 | Loja integrada por discussões e dúvidas | Dados de pedidos e contato pessoal não viram conteúdo público |
| F-D12 | Abrir projetos opcionais `aculta_forum` e `aculta_game` | Documentação agora; runtime por fases posteriores |
| F-D13 | Participação no Fórum (criar, editar o próprio conteúdo, comentar e denunciar) exige e-mail confirmado (papel `email_confirmed`); leitura e seguir/salvar ficam para contas cadastradas | Ver `EMAIL-CONFIRMATION-POLICY.md`, seção 10. Lacuna: conteúdo histórico importado não passa por essa regra |
| F-D14 | Fóruns restritos a associados e a cursos. Visibilidade definida por fórum. **Substituída por F-D34 em 2026-10-20; o nível associado já havia sido superado por F-D19.** | Decisão histórica de 2026-10-16: realms por fórum e ocultação 404. A implementação de `RestrictedForumGrants`/`RestrictedForumHooks` foi retirada em dev.49. O Growroom é público; LMS depende de Group/membership por curso, turma e atividade. |
| F-D15 | Hierarquia de papéis para visualização: authenticated < email_confirmed < associado < content_editor < administrator. content_editor é o moderador do Fórum e vê os níveis restritos. Visitante e bots recebem 404 em conteúdo restrito | **Aprovado pelo responsável (2026-10-16).** Detalhes e dúvidas abertas em `docs/portal/PAPEIS-PARTICIPACAO.md` |
| F-D16 | Moderação do Fórum pelo content_editor confirmado; a regra histórica anterior excluía autorias de `usuarios_legados_forum`, mas mantinha bypass de administrador | **Aprovado em 2026-10-16; escopo de conteúdo legado supersedido por F-D31/F-D32 e pela limpeza F-D33.** A regra de moderação continua para conteúdo ativo novo. |
| F-D17 | Autores históricos não são usuários do Drupal: sem conta, sem login, fora da lista de usuários. Ficam em entidade própria `aculta_legado_autor`, com tabela própria (mesmo banco do site; banco separado é alternativa a decidir). Conteúdo histórico tem uid 0 e campo `field_autor_legado` | **Aprovado pelo responsável (2026-10-16).** Substitui a regra anterior de contas bloqueadas com papel `usuarios_legados_forum` e perfil com origem. Substitui também a regra "não criar tabela de autores paralela" de `ARQUITETURA.md` |
| F-D18 | Confirmação manual de e-mail pelo administrador, com permissão própria, registro em log sem o endereço | **Aprovado e implementado (2026-10-16)**, `PAPEIS-PARTICIPACAO.md`, seção 5 |
| F-D19 | Associado é marcador puro: tabela própria separada dos usuários e dos papéis, sem acesso a nenhuma função do site. Usado em relatórios e páginas da associação. Remove o nível \"associado\" da visibilidade dos fóruns e o papel `associado` | **Aprovado pelo responsável (2026-10-16).** Supera o papel `associado` de F-D14 e F-D16. Ver `docs/portal/PAPEIS-PARTICIPACAO.md` |
| F-D20 | Nível de visibilidade "marcadores de associado": fóruns visíveis somente para contas com a marca de associado e para moderadores. Padronização: caminhos internos em `/painel-administrativo/` | **Aprovado pelo responsável (2026-10-16).** Ver `docs/portal/PAPEIS-PARTICIPACAO.md` |
| F-D21 | Avisos e resumos de resposta reaproveitam o sistema de mensagens e o de avisos. Aviso no site obrigatório para quem segue o tópico. Resumo por e-mail opt-in, diário e só quando houve novidade, agrupado por tópico. Agrupamento geral por tipo + assunto com contador. Nenhum sistema novo | **Aprovado pelo responsável (2026-10-16).** Ver `docs/portal/AVISOS-E-NOTIFICACOES.md` |
| F-D31 | O acervo histórico importado do Growroom é somente leitura: não editar, excluir, aprovar/desaprovar nem reabrir comentários, inclusive para `content_editor` e `administrator`. A autoria é a referência `field_autor_legado`; tópico histórico tem Comment field `Closed` durante importação e em todo presave. O módulo Drupal Forum contribuído é implementação legada distinta e será removido no processo seguro de retirada. | **Determinado pelo responsável (2026-10-19); supersedido por F-D33 em 2026-10-20 quanto à preservação do conteúdo no Runtime de teste.** |
| F-D32 | Os Nodes `forum` e Comments `comment_forum` existentes/restaurados representam o acervo legado do Growroom até sua migração segura; não são um segundo fórum independente. Não aplicar moderação inicial, não permitir novos tópicos, reabrir discussões nem permitir alterações/moderação por `content_editor`. Ao importar para os bundles ACULTA, usar `field_autor_legado` e preservar a imutabilidade. | **Determinado pelo responsável (2026-10-20); supersedido por F-D33 para a decisão de manter esses dados no Runtime de teste.** |
| F-D33 | O conteúdo antigo do Fórum deve ser apagado do banco do Runtime de teste; o novo ACULTA Forum fica sem tópicos, respostas e categorias legadas importados. Excluir Nodes `forum`, Comments `comment_forum` e termos do vocabulário `forums`, remover vínculos antigos `field_discussao` das publicações sem alterar o conteúdo editorial e preservar a estrutura nova `aculta_forum_categories`. Produção não está incluída. | **Determinado pelo responsável (2026-10-20); limpeza, uninstall oficial e remoção Composer concluídos no Runtime de teste em dev.50. Produção não foi consultada nem alterada.** |
| F-D35 | Após a migração, tópicos/respostas históricas do Growroom usam os bundles ACULTA e `field_autor_legado`; não recebem a moderação inicial comum de primeiro comentário | `aculta_user` verifica autoria histórica no tópico e no Comment compartilhado; LMS segue sem moderação automática. |
| F-D36 | Tópicos e comentários importados do Growroom marcados com `field_autor_legado` não participam da fila nem das ações de denúncia/moderação do Fórum; a fila pública não agrega Revista, LMS ou Wiki | Derivado da regra do responsável de que o Fórum legado restaurado não recebe moderação, não pode ser reaberto nem alterado por `content_editor`; aplicado em código na dev.51. |
| F-D37 | O Fórum geral terá seção administrativa própria em `/painel-administrativo/forum`, com entrada no menu lateral; a aba e as operações atualmente sob Conteúdo serão transferidas para lá | Determinado pelo responsável em 2026-10-20; menu, Views e ações migrados em dev.115. Revista, LMS e Wiki continuam na árvore dos seus contextos. Configuração de moderação do primeiro comentário permanece no `aculta_user` por ser compartilhada; o menu do Growroom oferece link para ela sem duplicação. Runtime de teste parcial; conferir matriz dev.115. |
| F-D38 | `/painel-administrativo/forum` é a página central do Fórum; os caminhos administrativos específicos redirecionam para ela e suas ferramentas não são replicadas na página. Rotas genéricas Core e ferramentas compartilhadas permanecem sob seus sistemas | Determinado pelo responsável em 2026-10-20. Implementado em dev.120 e ampliado em dev.121: o painel apresenta resumo do Growroom420 e dos contextos de discussão, mas não incorpora ferramentas administrativas. Runtime partial (uid 1 confirma painel HTTP 200 e sete redirects GET 302; Firefox/X11 confirma 23 verificações, 12 paths estáticos e viewports 390/768/1024; rotas dinâmicas de movimento não foram testadas por HTTP sem fixtures). Esta decisão mantém redirecionamento de caminhos de ferramenta até futura decisão sobre localização de operações. A abrangência dos redirects foi refinada por F-D39. |
| F-D39 | A entrada `/painel-administrativo/forum` oferece links somente para as funções filhas do primeiro nível: tópicos, comentários e configurações. As páginas filhas usam as abas administrativas Drupal. As filas publicadas, pendentes e rejeitadas são subabas de Comentários; não aparecem como links na raiz. Caminhos canônicos de ferramentas permanecem funcionais; caminhos antigos sob Conteúdo continuam redirecionando à entrada. | Determinado pelo responsável em 2026-10-20, após verificar que a raiz estava praticamente vazia. Implementação em dev.122 reativa os Page displays de Views sob a seção canônica, configura a rota de formulário e limita as listagens ao Domain ativo Growroom. Runtime/X11 será registrado após rebuild/cache e smoke direcionado. F-D38 permanece no histórico, mas a interpretação de redirecionar também os caminhos canônicos foi substituída. |
| F-D40 | A ferramenta de mover comentário deve abrir dentro da área administrativa Comentários no caminho canônico `/painel-administrativo/forum/comentarios/{comment}/mover`; caminhos antigos permanecem redirects para o Painel Growroom420. | Determinado pelo responsável em 2026-10-20 em resposta à proposta de reativar o formulário de movimento. A flag `comment_move_enabled` continua `false` por padrão; a decisão define localização, não habilita a operação. |
| F-D41 | **Separar definitivamente discussões de conteúdo, avisos e conversas privadas:** seguir conteúdo do Fórum gera avisos no site e, quando habilitado, resumo por e-mail por meio do `aculta_messenger`; não cria chat, thread privada ou grupo automático de seguidores. Conversas entre membros usam exclusivamente a mensageria compartilhada do Messenger, por iniciativa autorizada de um usuário. | Determinado pelo responsável em 2026-10-24. Esclarece F-D21 sem alterar sua política de `seguir_topico`, agregação `aculta_aviso` e digest opt-in. Discussões LMS/Wiki/Revista permanecem Comments contextuais nas entidades próprias; propostas de chat por tópico/turma/verbete ficam substituídas no roadmap. API de eventos ADR-XK-02 deve ser adotada gradualmente, sem duplo disparo nem migração de storage sem prova. |

"Memorial" significa memória da comunidade; não afirma falecimento das pessoas.
O bloqueio é técnico e não constitui punição, expulsão ou avaliação da pessoa.

## Diretrizes de implementação aprovadas pelo plano

Forum/Node/Comment/Taxonomy preservam tópicos, respostas e estrutura. O Portal
prepara URLs, acesso e cache; ACULTA420 apresenta contratos neutros. Não criar
novas entidades Topic/Reply, outro Messenger, outra galeria ou outro checkout.

Matrícula não será convertida em um papel global de curso. Amizade não concede
acesso a conteúdo reservado. Importação não concede conquistas contemporâneas.

## Resultado operacional da F1 — 2026-10-15

- O host de teste `forum.aculta.toca.net.br` foi aposentado sem redirect: HTTP e
  HTTPS respondem 404. O DNS A e o certificado válidos permanecem no Homelab
  para manter essa resposta controlada; novas referências usam
  `growroom420.aculta.toca.net.br`.
- `forum.aculta.org` não tinha registro DNS na zona Cloudflare de produção. O novo
  `growroom420.aculta.org` permanece como destino mapeado no Deployer, mas não
  recebeu DNS: o TLS direto ao IP de origem Hostinger falhou, então o hostname
  precisa ser cadastrado e validado na origem antes de publicar o registro.
- No teste, `/sitemap.xml` responde 200 e não lista FORUM. Isso segue a política
  já instalada em `PurposeNodeUrlGenerator::INDEXABLE_PURPOSES`, que exclui
  FORUM e ACCOUNT. A verificação global `sitemap --env=test` ainda falha porque
  LINKS responde 404; a F1 não alterou LINKS.

## Propostas técnicas a comprovar

- Manter purpose `forum` e os IDs Domain existentes ao trocar hostname/aliases.
- Usar Migrate API, identificadores estáveis de origem e importação repetível.
- Reservar `lendashistoricas` antes da resolução genérica `/u/{apelido}` e impedir
  que o cadastro comum ocupe esse apelido. Inspecionar colisões antes de alterar dados.
- Fazer todos os links públicos de autor legado apontarem à mesma rota do memorial,
  preservando o nome individual como texto, inclusive nos comentários e nas Views.
- Reutilizar a pilha Message para novas respostas, com um único dono da entrega.

## Decisões posteriores, sem bloqueio da documentação

| Tema | Recomendação para a fase correspondente |
| --- | --- |
| Host antigo após a mudança | Definir política explícita antes de desligar; não introduzir redirect genérico ou replay de POST |
| Capturas adicionais | Usar a captura indicada como referência; inventariar anteriores e decidir o corte temporal antes da importação integral |
| Pessoa atual que reivindica autoria histórica | Processo verificável próprio; não vincular por coincidência de nome |
| Galeria aberta diretamente no Fórum | Nova decisão sobre D13 do `aculta_gallery`; inicialmente usar referências para a Revista |
| Curso versus turma | Validar o contexto das atividades reutilizadas antes de definir chave e alcance da discussão |

Decisões deste projeto não alteram retroativamente o funcionamento das extensões
instaladas. Cada aplicação exige código/configuração, evidência e registro no PR.
