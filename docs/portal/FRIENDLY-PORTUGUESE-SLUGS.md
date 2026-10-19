# Política de URLs e slugs amigáveis em português

Status: **requisito transversal das features de produto** (ver `ROADMAP.md`, F2–F4)

## Objetivo

Toda experiência humana e pública do ecossistema ACULTA deve apresentar URLs
claras, estáveis e amigáveis em português, em todos os Domains/purposes.

A regra vale para:

- MAIN;
- ACCOUNT;
- SUPPORT;
- MAGAZINE;
- WIKI;
- SHOP;
- COURSES;
- FORUM.

O objetivo não é traduzir internamente todas as rotas Drupal. O objetivo é que
a **URL canônica navegável apresentada ao usuário** use slugs em português
quando a rota representa conteúdo ou uma ação humana.

## Princípio

Valores de formulário/conteúdo podem conservar maiúsculas e minúsculas para
exibição. Antes da geração de uma URL pública, o Portal oferece
`Drupal\aculta_portal\Routing\PublicRouteSlug::normalize()` para aparar espaços
externos e converter o parâmetro em minúsculas. O utilitário não translitera,
remove pontuação, corta o valor nem o grava: a feature proprietária valida a
gramática completa e mantém o valor original para apresentação. Assim, uma
mudança de caixa não cria uma segunda identidade ou caminho para o mesmo item.

Arquivos enviados usam a sanitização de nomes nativa do Drupal configurada em
`config/sync/file.settings.yml`; uploads novos são transliterados, minúsculos e
separados por hífen. Os arquivos já existentes não são renomeados
retroativamente por essa configuração.

```text
rota técnica interna
        ↓
alias/rota pública amigável em português
        ↓
Domain purpose correto
        ↓
canonical/navegação consistente
```

Exemplo:

```text
rota interna Core/contrib
/user/login
        ↓
ACCOUNT
/entrar
```

A rota técnica pode continuar existindo para compatibilidade do Drupal, mas a
experiência pública deve preferir o alias/rota amigável.

## Padrão de slug

Slugs humanos devem:

- usar português;
- usar minúsculas;
- usar hífen entre palavras;
- evitar IDs quando existe título/semântica estável;
- usar transliteração ASCII no path quando necessário;
- evitar abreviações internas;
- evitar nomes de classes/plugins/modules;
- evitar inglês quando existe termo natural em português;
- permanecer estáveis depois de publicados.

Exemplos:

- `/minha-conta`;
- `/meus-dados`;
- `/recuperar-senha`;
- `/minha-participacao`;
- `/meus-cursos`;
- `/meu-apoio`;
- `/criar-topico`;
- `/contribuicoes`;
- `/categorias`.

Não usar acentos no slug canônico:

- preferir `/seguranca` a `/segurança`;
- preferir `/participacao` a `/participação`.

O texto visível continua usando português correto com acentos.

## Rotas técnicas que não precisam ser traduzidas

Não criar aliases artificiais para endpoints cuja estabilidade pertence ao Core
ou ao módulo upstream, salvo necessidade específica de UX:

- OAuth redirect/callback;
- webhooks;
- endpoints AJAX;
- endpoints JSON/API;
- rotas de autocomplete;
- callbacks internos;
- cron;
- batch;
- admin internals;
- rotas temporárias de reset com parâmetros/tokens;
- rotas técnicas de Media Library;
- endpoints Commerce/payment.

Essas rotas não devem aparecer como navegação principal do usuário quando uma
rota pública amigável existir.

Exceção específica ACULTA: o fluxo OAuth do Social Auth usa
`/oauth/{provedor}` para iniciar e `/oauth/{provedor}/retorno` para callback.
Para o Google, os caminhos são `/oauth/google` e `/oauth/google/retorno`. O
callback continua sendo um endpoint técnico, não um link de navegação; o slug
`retorno` identifica a conclusão do fluxo em português.

## ACCOUNT

Inventário mínimo de URLs humanas a manter/padronizar:

- `/entrar`;
- `/recuperar-senha`;
- `/minha-conta`;
- `/meus-dados`;
- `/endereco`;
- `/foto`;
- `/seguranca`;
- `/conexoes`;
- `/meus-cursos`;
- `/meu-apoio`;
- `/minha-participacao`.

O inventário real do módulo deve decidir quais já existem e quais precisam de
migração. Não criar rota duplicada quando o path atual já atende ao padrão.

## SUPPORT

URLs humanas devem usar vocabulário de apoio/doação, por exemplo:

- `/apoiar`;
- `/como-apoiar`;
- `/meu-apoio` quando semanticamente pertencente ao SUPPORT;
- páginas de confirmação/status com nomes compreensíveis quando não forem
  endpoints técnicos do Commerce.

Registro de decisão: a página pública de apoio é a página inicial do subdomínio de apoio (`/`). A rota interna é `/apoio`: GET/HEAD direto responde 301 para `/`, como os demais subdomínios com home interna (Wiki, Cursos, Conta, Revista), e outros métodos respondem 404; o histórico de doações fica em Minha Conta > Meu apoio (`/meu-apoio`). Caminhos antigos, como `/apoie`, respondem 404; não há redirecionamento de slug (ver `docs/architecture/multidomain.md`, "Página de apoio (SUPPORT)").

Checkout, payment callbacks e webhooks permanecem sob APIs upstream.

## MAGAZINE

Conteúdo editorial deve evitar aliases técnicos de entidade.

Padrões candidatos:

- artigos por título;
- perfil de autor em `/u/<apelido>` (não `/autores/<nome>`; ver "Exceção registrada: perfil público");
- `/categorias/<termo>`;
- `/arquivo` quando houver;
- landing pages editoriais em português.

Pathauto deve ser preferido quando adequado.

## WIKI

Padrões candidatos:

- `/verbete/<titulo>`;
- `/categoria/<termo>`;
- `/contribuicoes`;
- `/criar-verbete` para a ação humana pública, quando a rota Portal for
  responsável pela navegação.

Rotas técnicas de revisions/Diff podem manter paths internos se não forem
expostas como canonical pública; links de UI podem receber aliases amigáveis
quando houver benefício e compatibilidade.

## SHOP

Quando o storefront for implementado:

- `/produtos`;
- `/produto/<titulo>`;
- `/categorias/<termo>`;
- `/carrinho`;
- `/pedidos` somente onde access/ownership forem apropriados.

Não substituir rotas técnicas de checkout/payment de Commerce apenas para
traduzir path.

## COURSES

Padrões humanos:

- `/cursos`;
- `/curso/<titulo>` quando a entidade permitir alias estável;
- `/meus-cursos` na experiência de Conta;
- ações de curso devem usar vocabulário em português quando forem rotas Portal.

Rotas LMS técnicas podem permanecer internas.
O download de anexo de uma resposta é a exceção navegável: o Portal substitui a
rota contrib `lms_file_upload.answer_file_download` por
`/resposta/{lms_answer}/baixar-arquivo` em
`UploadedAnswerRouteSubscriber`, mantendo o mesmo nome de rota para o plugin e
aplicando a checagem de matrícula vigente descrita no contrato LMS.

## FORUM

Exceção administrativa solicitada pelo responsável em 2026-10-15:
**`/painel-administrativo/game`**, título **Game**, abaixo de Avisos.
É área administrativa no MAIN, protegida por `administer aculta game`;
não representa slug de conteúdo público em inglês.

Decisão aprovada em 2026-10-15, com implementação futura no
[ACULTA Fórum](../../web/modules/custom/aculta_portal/modules/aculta_forum/README.md):
a marca/prefixo público será Growroom420; o purpose interno permanece `forum`.
O memorial dos autores legados usa exatamente **`/u/lendashistoricas`**, uma
página coletiva no contexto MAIN dos perfis. É uma exceção explícita à separação
de palavras por hífen. Autores preservam nomes individuais, mas não têm página
pública individual `/u/<apelido>` nem subpágina de memorial. Todos os seus links
de autoria levam ao coletivo. Reservar esse literal e verificar colisões antes
de registrar a rota. Esta decisão não altera as rotas atuais nesta entrega.

Na implementação das rotas de produto, definir desde o início aliases amigáveis:

- root do subdomínio Forum como landing; o caminho interno `/discussoes` é oculto e responde 404 diretamente;
- `/topico/<titulo>`;
- `/criar-topico`;
- `/categoria/<termo>` ou equivalente do Forum upstream;
- `/minhas-respostas` e `/meus-topicos` na Conta quando aplicável.

Não criar storage/roteamento paralelo ao Forum; aliases devem envolver APIs e
entidades upstream.

## MAIN

Páginas institucionais e hubs devem preferir nomes humanos estáveis, por exemplo:

- `/sobre`;
- `/carta-de-principios`;
- `/contato`;
- `/participar`;
- `/projetos`.

O inventário final deve ser baseado nas páginas reais versionadas/configuradas,
não apenas nesses exemplos.

## Pathauto e aliases

Quando Pathauto resolver o caso:

- usar patterns por bundle/taxonomy;
- transliterar;
- gerar aliases estáveis;
- não duplicar lógica em PHP.

Para rotas custom do Portal:

- definir path amigável diretamente na route quando isso não quebrar API;
- ou manter rota interna + redirect/alias compatível.

Não fabricar config sync final sem Runtime.

## Mudança de slugs existentes

Ao trocar uma URL pública já usada:

1. inventariar path atual;
2. definir novo path;
3. preservar canonical;
4. não criar redirecionamento: o caminho antigo responde 404 (ver regra abaixo);
5. atualizar menus;
6. atualizar breadcrumbs;
7. atualizar sitemap;
8. atualizar links cross-domain;
9. atualizar Search/Views;
10. testar links salvos/deep links.

Caminhos antigos respondem 404. Esse é o comportamento padrão do projeto, válido antes e depois da versão estável: não há redirecionamento 301 de slug. Redirecionamentos de slugs antigos criados em ciclos anteriores foram removidos do runtime.

## Canonical e SEO

A URL canonical deve:

- usar o Domain de produção;
- usar o slug público aprovado;
- evitar aliases Homelab;
- evitar path técnico quando existe alias público;
- ser consistente com sitemap e metadata.

Homelab continua usando os aliases `*.toca.net.br` para navegação, com a mesma
estrutura de path amigável.

## Domain purpose

O mesmo conceito deve manter o mesmo slug entre ambientes.

Exemplo:

```text
Homelab:
https://conta.aculta.toca.net.br/meus-cursos

Produção:
https://conta.aculta.org/meus-cursos
```

Somente hostname muda.

Não criar paths diferentes por ambiente.

## AJAX / HTMX

Navegação parcial deve usar a mesma URL pública amigável da navegação full-page.

History API deve registrar o slug público.

Não expor path técnico apenas porque a requisição foi AJAX.

## Forms

Form action pode continuar apontando para rota técnica Core/contrib quando essa
é a API correta.

A página que apresenta o form deve usar slug humano.

## Access

Alias não altera access.

A ordem continua:

```text
route/entity access
      ↓
Domain purpose
      ↓
URL pública
```

Um alias amigável nunca pode ser usado para contornar a policy wrong-host.

## Inventário obrigatório

Antes da implementação, gerar tabela de todas as rotas públicas do Portal:

| Purpose | Route name | Path atual | Path amigável | Canonical | Migração |
| --- | --- | --- | --- | --- | --- |

Classificar cada rota como:

- KEEP;
- RENAME;
- REDIRECT;
- TECHNICAL;
- ADMIN;
- CALLBACK.

Esse inventário inclui rotas fornecidas por Core/contrib que aparecem
diretamente na UX ACULTA.

## Testes

Para cada mudança de slug:

- host correto;
- host incorreto;
- anonymous/authenticated;
- old path;
- new path;
- redirect;
- canonical;
- breadcrumb;
- menu;
- AJAX/history;
- sitemap;
- Search;
- cache;
- access.

## Verificação

Antes de publicar uma mudança de rota pública:

- inventário completo das rotas públicas;
- nenhuma navegação principal expondo slug técnico ou em inglês sem justificativa;
- ACCOUNT, Wiki, Courses, Support, Magazine e Fórum com slugs em português;
- Shop seguindo a política apenas no escopo realmente implementado;
- redirects para mudanças públicas;
- canonical e sitemap coerentes;
- paridade de Domain entre Homelab e produção.

## Diretiva e gate

A diretiva obrigatória para agentes está em `AGENTS.md` (seção "URLs e slugs públicos em português"). O gate `php scripts/validate-public-slugs.php`:

- reprova segmento numérico ou termo técnico em inglês em rotas customizadas e do tema;
- para rotas de contrib, usa uma linha de base que só pode diminuir (DT-P20). Rota nova fora dela reprova; rota corrigida sai dela;
- o caso `/course/1/0/1` foi corrigido em DT-P21 (0.2.0-dev.1): a rota do LMS é `/curso/{curso}/{lição}/{atividade}`, com slugs derivados dos títulos (`src/Lms/`). O Portal substitui a rota contrib e sai da linha de base; a rota numérica antiga responde 404, que é o padrão do projeto para caminhos antigos (sem redirecionamento de slug; ver DT-P22).

Exceções permanentes: `/admin`, `/ajax`, `/api`, callbacks técnicos e arquivos `.json`.

## Registro de trocas de slug

Toda troca de caminho público entra aqui, com a instrução de deploy. O caminho antigo responde
404; não há redirecionamento de slug.

| Versão | Antes | Depois | Como chega à produção |
| --- | --- | --- | --- |
| 0.3.0-dev.4 | `/cart` | `/carrinho` | Código (`CartFriendlyRouteSubscriber`). Sem configuração a aplicar: o deploy do código já traz a rota. Após o deploy, `/cart` responde 404 e `/carrinho` 200 no host principal. |
| 0.3.0-dev.5 | `/donate`, `/donate/{valor}/now`, `/donate/{pedido}/{etapa}` | `/apoiar`, `/apoiar/{valor}/agora`, `/apoiar/{pedido}/{etapa}` | Código (`DonationFriendlyRouteSubscriber`). Sem configuração a aplicar. Após o deploy, `/donate` responde 404. |

Verificação após cada deploy: `/carrinho` 200 e `/cart` 404 no host principal; o host da loja redireciona
`/carrinho` para o principal, conforme a política de pagamento.

## Exceção registrada: perfil público (`aculta_user`)

- Caminho: `/u/<apelido>` (perfil público de cada pessoa usuária, em `aculta.*`).
- Motivo: `/u/` é abreviação curta de "usuário", usada para o caminho público do perfil. O apelido é a parte em português.
- Decisão do responsável em 2026-10-13. Alternativa não adotada: `/perfil/<apelido>`.
- Apelido: minúsculas, sem acento, único; palavras reservadas em `docs/portal/ACULTA-USER-PLAN.md` (B1).
- Perfil privado responde 404 (decisão 40 do plano).


## Escopo do gate para rotas contrib

`scripts/validate-public-slugs.php` mede as rotas contrib só dos módulos habilitados em
`config/sync/core.extension.yml`. O motivo é que um submódulo presente no código e não habilitado
(caso de `message_subscribe_ui`) não registra rota no site, então não há URL público a corrigir. O
baseline DT-P20 continua cobrindo as rotas dos módulos habilitados e só pode diminuir. Ao habilitar um
módulo contrib com rota em caminho inglês ou numérico, o gate passa a falhar até que ele receba um
caminho em português por override do Portal.
