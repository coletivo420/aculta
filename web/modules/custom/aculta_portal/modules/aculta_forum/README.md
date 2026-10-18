# ACULTA Fórum — Growroom420

Projeto do futuro submódulo opcional `aculta_forum`, integrante do `aculta_portal`.
Plano aprovado pelo responsável em **2026-10-15**.

**Estado do código (2026-10-17).** Este submódulo ainda contém só documentação (sem `.info.yml`,
rotas ou importadores). A funcionalidade do fórum já existe em outros módulos do Portal:

| Parte do fórum | Onde está | Documento |
| --- | --- | --- |
| Identidade histórica e memorial (F2) | `aculta_user` (`LegacyAuthor`, `LegacyMemorialController`) | `aculta_user/docs/CLASSES-E-HOOKS.md` |
| Fóruns restritos e marcador de associado (F9) | `aculta_user` (`RestrictedForumGrants`, `AssociadoMarcador`) | `aculta_user/docs/CLASSES-E-HOOKS.md` |
| Denúncias e moderação (F5.1) | `aculta_user` (`ReportQueue`) e `aculta_portal` (confirmação de e-mail) | `aculta_user/docs/CLASSES-E-HOOKS.md` |
| Avisos de resposta e de atualização (F5.2) | `aculta_messenger` (`AvisoService`, `AvisoRetencao`) | `aculta_messenger/docs/AVISOS-E-MENSAGENS.md` |
| Classes e hooks completos | cada módulo | `aculta_user/docs/CLASSES-E-HOOKS.md`, `aculta_messenger/docs/CLASSES-E-HOOKS.md` |

## Missão

Reconstruir a memória pública recuperável do Growroom e oferecer uma comunidade
canábica, cultural e antiproibicionista integrada ao Portal. Preservar a autoria
histórica e permitir novas conversas vinculadas ao acervo, com as ferramentas
atuais de identidade, mensagens, galerias, conhecimento, loja e cursos.

## Decisões aprovadas

- O endereço público passa do prefixo `forum` para **`growroom420.aculta`**. A mudança
  operacional será provisionada por ambiente pelo Deployer; o purpose interno
  continua `forum`, evitando reconstruir a política multidomínio.
- Áreas comunitárias têm leitura pública e postagem por conta cadastrada; as
  discussões futuras do LMS ficam reservadas a matriculados e equipe autorizada.
- O acervo histórico fica fechado a novas respostas. A ação **Continuar esta
  discussão** abre uma conversa atual vinculada ao tópico histórico.
- Autores recuperados têm contas User bloqueadas e o papel **Usuários legados do
  fórum**. Cada autor mantém seu nome e a autoria individual de suas publicações.
- Há **um único memorial coletivo em `/u/lendashistoricas`**, sem página pública
  individual para legados. Links de autoria desses usuários apontam ao memorial.
- Um avatar especial identifica as lendas históricas, usando o sistema de avatar
  existente. As caixas de autoria e os componentes do ACULTA420 são reaproveitados.
- A loja se integra por discussões e dúvidas; pedidos e atendimento pessoal ficam
  privados. Galerias reutilizam `aculta_gallery`, conforme sua política vigente.
- `aculta_game` integra conquistas e missões, sem ranking ou benefícios financeiros.

## Fontes de verdade

| Dado | Dono existente | Integração do projeto |
| --- | --- | --- |
| Tópico, respostas e estrutura | Forum contrib, Node, Comment e Taxonomy | Experiência, vínculos e apresentação neutra |
| Autor e estado da conta | User e `aculta_user` | Identidade histórica bloqueada; memorial coletivo |
| Perfil e avatar | Profile, File/Image e AvatarPool | Metadados históricos e avatar reservado |
| Amizade e mensagens | `aculta_user` e `aculta_messenger` | Ações permitidas para participantes atuais |
| Seguir, salvar e sinalizar | Flag | Controles e filas de moderação |
| Galerias e arquivos | `aculta_gallery`, Media Gallery, Media e File | Recuperação e referências, sem álbum paralelo |
| Matrícula e progresso | Group e Drupal LMS | Discussões reservadas por contexto de curso |
| Produto e pedido | Drupal Commerce | Discussão vinculada e URLs corretas |
| Concessões de conquistas | Base atual `aculta_badge_award`; futuro `aculta_game` | Eventos atuais, excluindo importação histórica |

## Documentos do projeto

- [Decisões](docs/DECISOES.md): aprovado, proposto e pendente.
- [Arquitetura](docs/ARQUITETURA.md): fronteiras, acesso, autoria e integrações.
- [Reconstrução](docs/RECONSTRUCAO.md): Wayback, proveniência e importação.
- [Pesquisa](docs/PESQUISA.md): soluções modernas e evidência de código existente.
- [Roadmap](docs/ROADMAP.md): entregas, dependências e critérios de aceite.
- [Changelog](CHANGELOG.md).
- [ACULTA Game](../aculta_game/README.md).

Aplicam-se os [guardrails](../../../../../../docs/portal/GUARDRAILS.md), o
[padrão Drupal 11+](../../../../../../docs/portal/DRUPAL-11-STANDARDS.md) e o
[design system](../../../../../../docs/portal/COMPONENT-DESIGN-SYSTEM.md).

## Estado verificável

Situação por fase (ver [Roadmap](docs/ROADMAP.md)): F1 executada; F2 com estrutura
pronta e pendente de dados e da arte do avatar; F5.1 e F5.2 implementadas no
servidor de testes; F9 parcial (nível de curso depende da F8). F3 depende da lista
de autores da importação. A próxima fase é a F4.

F1 executada no servidor de testes em 2026-10-15. O prefixo
`growroom420.aculta.toca.net.br` resolve para o Homelab, tem certificado TLS
válido e mantém o purpose `forum`; o hostname anterior responde 404 sem
redirect. O Deployer mantém `growroom420.aculta.org` como destino de produção,
sem DNS publicado porque a origem Hostinger ainda não atende o novo hostname.

A configuração versionada inclui a View `aculta_forum_topics` com um bloco
CTools Views de tópicos recentes, colocado somente na página inicial do purpose
`forum`. O Runtime permanece sem atualização: `RUNTIME STATUS: DEFERRED`.

O inventário CDX completo do prefixo histórico `/board/`, com corte temporal e
contagens, está em [Reconstrução](docs/RECONSTRUCAO.md); o JSON está em
armazenamento privado do Homelab e não no Git. A F1 não migrou conteúdo e não
criou contas, papéis ou avatares. F2 em diante permanecem pendentes.
