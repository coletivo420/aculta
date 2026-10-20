# Estado atual — ACULTA Links

## Git e escopo

- Branch: `docs/aculta-forum-0.1.0-dev.1`.
- PR associada: [#297](https://github.com/coletivo420/aculta/pull/297), cujo
  assunto é o Fórum; não foi criada PR para Links.
- HEAD inicial da auditoria: `1d54c26faf4ca81f00deea6344449bdc97ee53b8`.
- O working tree e o index estavam limpos no início. A branch contém trabalho de
  outras frentes; o commit inicial de Links foi documental. A fase 0.2 começa
  agora com entidades próprias, sem habilitação no Runtime.

## Implementação encontrada

O Domain `links_aculta_org` atende `links.aculta.org`, com purpose `links`. O
`PurposeFrontPathProcessor` traduz internamente a raiz pública `/` para
`/redes-sociais`, preservando a URL de navegador. O conteúdo da página é uma
entidade Node `page` associada a Domain Source; o hook do Portal identifica esse
purpose e seleciona `node--social-links.html.twig` no ACULTA420.

O Portal constrói o botão para MAIN, os botões editoriais configurados, os
canais oficiais e o rodapé. Uma View já existente fornece teasers de projetos.
Há também um campo `field_social_links` em Node `page`, mas o template dedicado
não consome o render array desse campo; seu uso efetivo precisa ser confirmado
com dados/editoria antes de qualquer migração.

## Classificação

| Recurso | Estado | Decisão |
| --- | --- | --- |
| Domain `links_aculta_org` e mapeamento do purpose | EXISTENTE E FUNCIONAL (config/código; DNS de produção não confirmado) | Preservar; resolução pertence ao Domain e ao `DomainPurposeManager`. |
| Página raiz e mapeamento para `/redes-sociais` | EXISTENTE E FUNCIONAL por código/configuração | Preservar até substituição com conteúdo equivalente e smoke test. |
| Canais sociais institucionais e botões configuráveis | EXISTENTE E FUNCIONAL por código; estado visual não revalidado | Portar a propriedade para o submódulo em etapa própria, preservando valores. |
| View `aculta_projects`, display `social_links` | EXISTENTE E FUNCIONAL como integração editorial compartilhada | Reutilizar via Views; não copiar Nodes nem criar dados espelhados. |
| Pattern `aculta420:social-links-page` e template Node dedicado | EXISTENTE, apresentação experimental | Preservar nesta fase; adaptar quando o contrato de micropágina estiver definido. |
| `node.page.field_social_links` | EXISTENTE; consumidor no template dedicado não localizado | Investigar preenchimento e referências; não remover nem migrar nesta fase. |
| Redes pessoais e perfil `/u/{apelido}` do `aculta_user` | EXISTENTE E FUNCIONAL em outro purpose/contrato | Preservar como capacidade compartilhada, com exposição explícita. |
| Links curtos, micropáginas por pessoa, editor e analytics | AUSENTE no purpose `links` | Projetar sem duplicar usuários ou autenticação. |
| Linky/LinkStack na instalação | AUSENTE (não são dependências do projeto) | Usar como fontes de estudo; nenhuma dependência Composer adicionada. |

## Consumidores e acoplamentos

- `DomainPurposeManager`, registro do Domain e processador de raiz pertencem à
  infraestrutura comum do Portal.
- `SocialLinksPagePresenter`, `SocialLinksSettingsForm`, callbacks AJAX,
  configuração `aculta_portal.social_links`, rota/menu de configuração e hooks
  de preprocess/theme suggestion implementam especificamente a landing LINKS.
- O hook `preprocess_page` do Portal injeta o rodapé de links no shell do tema.
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
- Access de páginas e destinos permanece privado, sem canonical pública,
  rota pública. O dashboard e os formulários/listagens ficam no MAIN, em rotas
  administrativas sob `/painel-administrativo/links/**`.
- Testes Unit e Kernel passam no banco dedicado. Módulo não instalado,
  configuração de produção não alterada e landing atual preservada.

## Pendências para a migração

1. Inventariar conteúdo/valores da página no Runtime documentado antes de uma
   migração funcional; esta fase não acessou nem alterou Runtime.
2. Migrar formulário, configuração e presenters para `aculta_links` sem mudar
   rota, URL, domínio, conteúdo ou apresentação.
3. Atualizar consumidores do Portal de forma atômica e demonstrar paridade.
4. Entidades e gestão administrativa básica existem em código; a edição de
   itens, migração da landing e publicação Domain permanecem pendentes.
5. Resolver a ambiguidade de licença da tag LinkStack antes de copiar qualquer
   asset ou código.


## Painel administrativo 0.2.0-dev.2

`aculta_links` declara painel próprio e formulários Core de criação, edição, exclusão e histórico de revisões para páginas, destinos e itens. O painel filtra por proprietário, pagina resultados e revalida acesso de edição de cada entidade. As rotas ficam em `main` e não ficam ativas até habilitação do módulo; a landing pública e sua configuração continuam no Portal.
