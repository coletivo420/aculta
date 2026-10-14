# Guardrails do Portal

Regras que bloqueiam regressão no `aculta_portal`. São normativas: quem altera o Portal deve
cumpri-las, e o gate ou a revisão deve checá-las. Não são roadmap nem relatório.

Documentos normativos relacionados: [DRUPAL-11-STANDARDS.md](DRUPAL-11-STANDARDS.md),
[SOURCE-OF-TRUTH.md](SOURCE-OF-TRUTH.md), [ARCHITECTURE.md](ARCHITECTURE.md),
[ADMIN-DOMAIN-POLICY.md](ADMIN-DOMAIN-POLICY.md),
[CROSS-DOMAIN-REQUEST-POLICY.md](CROSS-DOMAIN-REQUEST-POLICY.md),
[PAYMENT-DOMAIN-POLICY.md](PAYMENT-DOMAIN-POLICY.md),
[DOMAIN-PRESENTATION-CONTRACT.md](DOMAIN-PRESENTATION-CONTRACT.md),
[AJAX.md](AJAX.md), [COMPONENT-DESIGN-SYSTEM.md](COMPONENT-DESIGN-SYSTEM.md),
[AGENT-TOKEN-ECONOMY.md](AGENT-TOKEN-ECONOMY.md) e
[FRIENDLY-PORTUGUESE-SLUGS.md](FRIENDLY-PORTUGUESE-SLUGS.md).

## Fronteiras gerais

- O Portal integra capacidades existentes. Não substitui storage, regra de negócio ou
  decisão de access de Core ou contrib.
- Não criar storage paralelo quando o Core ou o contrib já tiver a capacidade.
- Não usar service locator (`\Drupal::`) em código novo de `src/`.
- Não hardcodar hostname ou domínio em controllers, rotas, templates ou config de tema.
- Decisão por purpose vem de `DomainPurposeManager` e do Domain, nunca de hostname.
- O tema recebe contexto neutro de apresentação. Não recebe entidade `Domain`, storage nem
  decisão de access.
- Não criar CMS, catálogo, carrinho, pedido, pagamento ou LMS paralelo a Core ou contrib.

## Fórum (`forum`)

- Não criar entidade Topic, Reply, tabela de fórum ou tabela de comentários. Usar Forum,
  Node e Comment nativos.
- Não criar `ForumController` só para reconstruir a listagem nativa. Mapear rotas nativas antes
  de escrever subscriber.
- Acesso direto a um tópico em host incorreto é bloqueado pela política multidomínio.
- Acompanhar tópico e favoritos usam Flag, não storage custom.
- Moderação reutiliza permissões, Comment, Node, Views e ferramentas administrativas. O Portal
  Admin pode ser hub de acesso e resumo, mas não um segundo CRUD de moderação.
- Conta agrega participação sem duplicar comentários.

## Revista (`magazine`)

- O Portal não cria CMS editorial paralelo. Não recriar formulários de Node ou Taxonomy.
- Conteúdo editorial preserva revisões, workflow e propriedades do Node.
- Plugins de schema custom do Portal são dívida auditável: só ficam quando o upstream instalado
  não cobre a propriedade com a mesma semântica.
- Analytics e Search Console são integrações de plataforma, não lógica editorial. Search Console
  observa URLs canônicas e sitemap reais.

## Loja (`shop`)

- O Portal não cria catálogo, carrinho, pedido ou pagamento paralelo.
- Não transformar Donation Order Items em produtos de loja só para reaproveitar UI.
- Não transformar compra de produto em "apoio" para contornar a modelagem Commerce.
- Não hardcodar `loja.aculta.org` em controllers. Usar `DomainPurposeManager`.
- Não adicionar eventos de mensuração (`purchase`, `add_to_cart`) antes de existir o fluxo Commerce
  correspondente e um plano de mensuração revisado.
- Antes de ativar catálogo: finalidade, política comercial, Product Types, checkout, gateway,
  LGPD, testes em MariaDB, isolamento de Domain e exportação de configuração. Nenhuma dessas
  decisões é tomada sem especificação.

## Wiki (`wiki`)

- O Portal não cria entidade, tabela ou índice próprio para espelhar verbetes.
- Dashboards e listagens consultam entidades e revisões reais. Não criar contador persistido no
  Portal só para dashboard.
- Conteúdo unpublished ou restrito não vaza metadata em cards, busca ou listagem.
- A Wiki não recria CRUD editorial.

## Minha Conta

- Semântica de apresentação: entity metadata → componente → decisão de access. Nunca o caminho
  inverso, e nenhum componente decide access.
- Conteúdo não publicado (curso, verbete, pedido Commerce) não aparece por apresentação, mesmo
  quando o componente só mostra metadata.
- Extração de componentes não altera OAuth, não substitui Form API, não duplica Social Auth,
  Email Confirmer, LMS ou Commerce, e não cria dependência obrigatória de SDC ainda inexistente.
- Nenhuma regra funcional migra para o tema.
- Substituição de fluxo acontece por fluxo, nunca com remoção total antecipada.

## Foto de perfil (`aculta_user`)

- Sorteio é prévia: só "Salvar foto do perfil" grava a foto. Fechar sem salvar descarta a escolha.
- Foto personalizada tem "Remover foto" (imediato, sorteia do acervo). Avatar do sistema tem "Sortear novo avatar" e
  caixa de envio, sem "Remover".
- Recorte só do arquivo recém-enviado; aplicar o recorte troca a foto e recarrega o painel (sem reabrir o popup).
- Foto importada por OAuth é personalizada.
- Regras completas, contrato do sorteio e invariantes: `web/modules/custom/aculta_portal/modules/aculta_user/docs/FOTO-PERFIL.md`
  e `docs/portal/ANTI-REGRESSAO.md`. Gate: `php scripts/validate-aculta-user-photo.php`.

## AJAX e formulários

- O Portal não mantém um segundo framework de AJAX. Usa Drupal AJAX, Views AJAX ou Form API.
- Toda interação assíncrona tem fallback de página inteira.
- Um SDC pode apresentar a moldura visual de uma seção, mas não recria Form API nem decide
  submissão. Upload e crop continuam no widget contrib.

## Como verificar

- Gate Drupal 11+: `php scripts/validate-aculta-portal-drupal11.php`.
- Revisão de PR: checar cada regra que o diff toca. Regra violada é finding, não nota.
- Regressão: comportamentos já aceitos são invariantes em `docs/portal/ANTI-REGRESSAO.md`; diff que toca um deles precisa do caso executado.

## Links de perfil (`/u/<apelido>`)

Regra (fixada pelo responsável): todo link para o perfil público é gerado a partir do apelido **atual** do usuário,
pela rota `aculta_user.perfil_publico` no propósito `main` (`DomainPurposeManager::routeUrl`). Nunca escrever o
caminho `/u/...` à mão, nunca guardar a URL do perfil em campo, configuração ou cache sem tags do perfil.

- **Por quê:** o apelido pode mudar. Um link escrito à mão ou guardado fica apontando para o endereço antigo
  (que responde 404, conforme a regra de slug estável). Ao gerar pela rota, a URL acompanha o apelido.
- **Cache:** quem gera o link registra o perfil e o usuário na cache (`getCacheTags()` / `addCacheableDependency`),
  para que a troca de apelido invalide as páginas que mostram o link (caixa de autores, faixa da postagem,
  botão "Ver Perfil" da Minha conta).
- **Domínio:** o link é absoluto para o host do propósito `main` (ex.: a Revista aponta para o domínio principal),
  porque o perfil só existe nele. Relativo só funciona dentro do próprio host principal; por isso o link é
  gerado pelo serviço de domínio, sem host fixo no código.
- **Gate:** `php scripts/validate-profile-links.php` reprova caminho `/u/` literal e geração de link de perfil
  sem registro na cache. Deve ser PASS em toda mudança de perfil, caixa de autores ou faixa de postagem.

## Papéis editoriais: função única

Decisão do responsável (2026-10-14). Vale para toda criação e moderação de conteúdo.

- **Uma só função editorial:** `content_editor` (Editor de conteúdo). Autor, editor e revisor da Wiki, e editor, autor e moderador do Fórum, são a mesma função. Não criar papéis novos para isso.
- **Única exceção:** `wiki_contributor` (Colaborador da Wiki420). Continua sendo concedido automaticamente a toda conta com e-mail confirmado (`email_confirmed`).
- `wiki_editor` foi removido: suas permissões estão em `content_editor`.
- Perfil público obrigatório para quem edita: somente `content_editor` (`PublicProfilePolicy`).
- Gate: `php scripts/validate-aculta-user-roles.php`.

Motivo: poucos papéis na criação de conteúdo. Um papel por função evita combinações de permissões difíceis de auditar.

## Modelo visual: Notícia e Editorial são iguais

Regra do responsável (2026-10-14). Vale para toda alteração de exibição dos conteúdos publicados.

- **Notícia (`article`) e Editorial (`blog`) têm o mesmo modelo visual.** As visualizações padrão, teaser e completa têm os mesmos campos, a mesma ordem e as mesmas configurações de exibição.
- Mudança visual em um dos dois tipos vale para o outro na mesma entrega. Não criar exceção de layout por tipo.
- **Única diferença permitida de formulário:** o Editorial passa pelo fluxo de moderação (estado de moderação), que é funcional. Não há outra diferença registrada.
- Gate: `php scripts/validate-content-visual-parity.php` (deve ser PASS em toda mudança de exibição de Notícia ou Editorial).

Motivo: o leitor percebe a mesma página com dois layouts diferentes quando os tipos divergem, e a manutenção dobra.
