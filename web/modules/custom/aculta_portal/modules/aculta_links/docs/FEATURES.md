# Funcionalidades

## Página institucional de links

- A raiz `links.aculta.org/` usa Domain/rota existentes para preservar o Node
  editorial associado ao alias `/redes-sociais`; sem conteúdo correspondente,
  usa canais, botões e a View de projetos existentes.
- Formulário, configuração, presenter e hooks específicos pertencem ao
  `aculta_links`. Os valores da chave antiga continuam em dual-write durante a
  janela de rollback.
- O botão fixo aponta para a raiz do purpose `main`; canais usam validação
  HTTPS/host, e botões adicionais aceitam HTTPS ou caminho interno seguro.

## Fundação 0.2.0 em desenvolvimento

- Entidades `aculta_link`, `aculta_link_page` e `aculta_link_item`, revisionáveis;
  a página é criada em rascunho e os itens ordenam referência tipada ao destino.
- Handler de acesso e permissões explícitas, com propriedade Drupal User e
  teste Unit/Kernel de storage, referências, revisões e isolamento entre contas.
- Dashboard e formulários Core para micropáginas, destinos e itens, com listas
  paginadas por proprietário, revisões e cache por usuário. Rotas administrativas
  pertencem ao MAIN; o acesso revalida permissões e Entity Access. A lista de
  micropáginas inclui endereço publicado ou prévia privada.
- Editor de itens com ordenação, publicação individual e link para prévia
  privada, além da rota pública `/pagina/{slug}` no purpose `links`; o módulo
  foi habilitado no Runtime de teste em 2026-10-20. A prévia pública no host
  `links` usa `/pagina/{slug}/previa`, revalida propriedade e não é cacheada.
  A rota root da landing foi restaurada em `0.4.0-dev.5`; a migração dos contratos
  de configuração/apresentação permanece pendente.

## Lacunas

Dashboard visual equivalente ao LinkStack, identidade herdada explícita do
`aculta_user`, editor completo de identidade/aparência, mais temas, componentes
referenciados e analytics ainda precisam ser concluídos. SEO via
Metatag, QR e agendamento de itens estão disponíveis. Uma conta Drupal pode possuir várias páginas;
equipes complexas não fazem parte do escopo inicial.

## Em implementação — 0.2.0-dev.2

- Painel administrativo em `main`, com listas separadas e paginadas para micropáginas, destinos reutilizáveis e itens de página.
- Formulários de entidade Drupal para CRUD e revisões, protegidos pelos handlers de acesso e permissões do módulo.
- Ainda não existe dashboard público do proprietário, preview ao vivo ou apresentação de identidade herdada de `aculta_user`.

## Editor de composição — 0.3.0-dev.1

A rota administrativa `/painel-administrativo/links/pagina/{aculta_link_page}/editar-itens` lista itens da página, permite alterar ordem por tabela arrastável com campo numérico como fallback e ativar/desativar individualmente. Cada item salvo cria revisão. A edição do texto e destino permanece no formulário individual da entidade.

## Micropágina pública — 0.4.0-dev.1

- `/pagina/{slug}` renderiza somente micropágina publicada no purpose `links`.
- Itens publicados aparecem em ordem por peso e ID; item, página e destino precisam estar publicados e acessíveis.
- Destinos permitem URIs Drupal internas (`internal:`/`entity:`) ou HTTPS sem credenciais e sem portas não padrão.
- Contrato de render é neutro no módulo; ACULTA420 reutiliza o SDC `social-links-page`.
- Micropáginas exibem o próprio título e não herdam a marca institucional da ACULTA; a landing preserva o logo ACULTA. Identidade Drupal do proprietário ainda não é herdada.
- Micropágina publicada oferece SVG QR em `/pagina/{slug}/codigo-qr.svg`; codifica a URL canônica gerada por Domain. Sem QR para rascunhos, arquivo persistido ou medição de scans.
- Itens aceitam `available_from` e `available_until` revisionáveis, com edição no fuso padrão do site e armazenamento UTC. Itens fora da janela não são renderizados na página pública; a prévia privada mantém todos os itens publicados do proprietário visíveis. O cache vence na próxima transição e intervalos inválidos são recusados.
- Metatag produz title, description, canonical URL de Domain, Open Graph e Schema.org para a rota pública; rotas de edição, preview e CRUD de entidade são `noindex, nofollow`.
- Destinos têm códigos curtos gerados automaticamente e estáveis de 12 caracteres. Links publicados resolvem por `/redirecionar/{codigo}` no purpose `links`, após revalidar Entity Access e a constraint de destino. Resposta 302 não grava cliques; analytics segue planejado.
