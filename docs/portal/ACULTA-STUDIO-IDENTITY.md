# ACULTA Studio — contrato de identidade e rotas

## ADR-038 - caminho aprovado para as Suites (implementação futura)

Marley `conta.aculta.*/marley`; Ganjalab `cursos.aculta.*/ganjalab`; WE SATIVA `wiki.aculta.*/wesativa`; Green Stash `loja.aculta.*/greenstash`; Culturas `eventos.aculta.*/culturas`. `/studio` e todo `/studio/*` serão removidos e responderão 404, sem aliases/redirects. `aculta_studio` permanece framework compartilhado. A raiz de `conta` será entrada de Conta, fora de `/studio`; Conta e Perfil permanecem separados. As referências de rota anteriores neste arquivo descrevem o estado pré-migração e não são destino para implementação nova. Consulte ADR-038.

## Histórico pré-ADR-038

As referências de path no restante deste arquivo registram o estado anterior ao contrato acima.

## Contrato histórico — Studio dev.18, Portal dev.30 e User dev.74

- **`/` no ACCOUNT** é o hub público do ACULTA Studio,
  `aculta_studio.hub`; `/studio` é seu caminho técnico interno,
  respondendo 404 a acesso HTTP direto.
- **`/conta-legada`** é a Minha Conta original **permanente**,
  `aculta_portal.dashboard`, incluindo shell e Form API atuais.
- **`/studio/conta`** gerencia dados privados: `/studio/conta/dados` contém
  somente cadastro privado, sem apelido; `/studio/conta/documentos`
  delega ao mesmo Portal/owner opcional
  de CPF/CNPJ e oculta links indisponíveis.
- **`/studio/perfil`** gerencia somente identidade pública e
  `/studio/perfil/apelido` edita apenas `Profile.field_nickname`, com owner
  ACULTA User. `/u/{apelido}` continua o perfil público em MAIN.
- `/minha-conta` e `/studio/pessoal` seguem indisponíveis,
  **sem aliases temporários**.

A implementação S-R3-B2 está no Git, com testes novos escritos, porém
sem execução comprovada de PHPUnit/Kernel/Runtime/Firefox. O histórico
dev.17 e anterior aparece abaixo.
Os parágrafos de dev.7–16 abaixo descrevem decisões históricas;
aplicar sempre este contrato vigente.

## Identidades atuais — Conta/Perfil separados (dev.13)

Nomes oficiais das áreas integradas: **ACULTA STUDIO WE SATIVA** em
`/studio/we-sativa` (início em `/studio/we-sativa/inicio`) e **ACULTA STUDIO
GREEN STASH** em `/studio/green-stash`. Os providers continuam adaptadores do
Studio; Wiki e Commerce mantêm seus dados, permissões e operações. Paths
anteriores aposentados respondem 404, sem alias temporário.

- `/studio` — hub;
- `/studio/conta` — **Minha Conta Studio**, dados privados;
- `/studio/perfil` — **Meu Perfil Studio**, identidade pública editável;
- `/studio/marley`, `/studio/ganjalab`, `/studio/we-sativa` —
  experiências distintas;
- `/` no Domain ACCOUNT — **hub do ACULTA Studio** (caminho interno `/studio`);
- `/conta-legada` — **Minha Conta legada permanente**, com shell
  e formulários preservados para comparação;
- `/minha-conta` — não é rota; responde 404;
- `/u/{apelido}` no purpose MAIN — perfil público independente.

Não existe `/studio/pessoal` nem alias temporário. A fase dev.13
ainda não foi homologada em Runtime. Esta seção substitui as decisões
de dev.7–12 e qualquer intenção de retirar a Conta legada.
[Contrato oficial](../architecture/ACULTA-STUDIO-CONTA-PERFIL-SEPARADOS.md).


## Acréscimo P2-A (dev.12): Meu Perfil privado

`/studio/pessoal/perfil` foi criado como interface privada de edição
composta a partir do ACULTA User. **Não é alias de `/u/{apelido}`**:
a última permanece perfil público canônico apenas no purpose `main`,
e o link só aparece quando `PublicProfileLink` o autoriza. Links de
apelido e privacidade ainda usam os formulários atuais da Conta.
Runtime/Firefox não homologados neste incremento.

## Contrato atual sem aliases (2026-10-24, dev.11)

Somente URLs canônicas: `/studio` (hub, route ID `aculta_studio.hub`),
`/studio/marley` (entrada editorial),
`/studio/pessoal` (Minha Conta inicial),
`/studio/ganjalab` (aprendizagem) e
`/studio/we-sativa` (Wiki). Não há redirect para
`/studio/inicio`, `/studio/Marley`, `/ganjalab` ou
`/minha-aprendizagem`: os caminhos transitórios foram retirados em
desenvolvimento. **O texto de dev.7–10 abaixo é histórico e foi superado
pela limpeza global de aliases.** A execução no Runtime ainda é pendente.



**Estado:** contrato vigente da refatoração iniciada em 2026-10-24. Este arquivo é a fonte de verdade para nomes, IDs e URLs das experiências.

## Identidade vigente de rotas — P1-C, dev.10 (2026-10-24)

A decisão definitiva para a entrada editorial é **`/studio/marley`
(totalmente minúscula)**. A implementação no Git tem:

- **`/studio`** — hub geral das experiências autorizadas, em `account`;
- **`/studio/marley`** — Marley, preservando o route ID
  `aculta_studio.home`, controller, abas e subrotas minúsculas;
- **`/studio/Marley`** — alias 302 temporário, apenas GET/HEAD, para o
  caminho canônico `/studio/marley`;
- **`/studio/inicio`** — alias 302 GET/HEAD para o hub em `/studio`;
- **`/studio/pessoal`** — entrada mínima pessoal; sem migração dos formulários.

A navegação unificada contextual definitiva e o editor Meu Perfil continuam
planejados. As descrições das dev.7–9 abaixo são históricas e não definem
a rota canônica atual. **Implementado no Git, ainda sem homologação PHPUnit/
Runtime/Firefox ESR/X11 para esta fase.** O perfil público
`/u/{apelido}` e a raiz `/` do Domain ACCOUNT permanecem intactos.

## Evolução planejada — Hub geral e Meu Espaço (P0, 2026-10-24)

**Decisão de produto para implementação futura, ainda não aplicada às rotas:** o
Studio passará a ter hub geral em `/studio` e a experiência pessoal
**Meu Espaço**, provider candidato `pessoal`, em `/studio/pessoal`.
A navegação será global por experiências e contextual por seções; a edição do
perfil ocorrerá no Studio, enquanto `/u/{apelido}` permanece a página pública
independente em `main`.

**Não confundir o alvo com o estado instalado:** atualmente `/studio` atende
à compatibilidade editorial do Marley; a entrada `/studio/pessoal` foi criada
na dev.8 apenas com links para a Conta, ainda sem homologação Runtime. O editor
de perfil e o hub permanecem futuros; a raiz `/` do domínio `account`
continua sendo Minha Conta. A tabela abaixo
descreve somente identidades/URLs **existentes**. Marley, Ganjalab e WE SATIVA
não mudam de nome nem de URL nesta fase. O contrato detalhado e o plano P0–P7
estão em [Hub e experiência pessoal](../architecture/ACULTA-STUDIO-PERSONAL-EXPERIENCE.md).

## Implementação intermediária STUDIO-P1-B (2026-10-24)

O registro compartilhado agora inclui também Marley, por provider que
consulta o `MarleyPresenter` sem transferir seu ownership editorial. A
prévia do futuro hub está em **`/studio/inicio`** (código dev.9, ainda sem
homologação Runtime), com links apenas para experiências autorizadas.

**A URL `/studio` não mudou de dono:** continua no controller histórico
do Marley até a fase de corte validada. `/studio/pessoal` já existe como
entrada mínima da Conta, mas não contém o editor de perfil. Nenhum alias,
formulário, dado pessoal ou shell foi migrado.

## Identidades

**Nomes e caminhos oficiais das áreas integradas ao Studio:** ACULTA STUDIO
GREEN STASH (`/studio/green-stash`) e ACULTA STUDIO WE SATIVA
(`/studio/we-sativa`). Green Stash é gestão comercial dentro do Studio;
WE SATIVA é contribuição Wiki dentro do Studio. Os IDs técnicos e os owners
Commerce/Wiki permanecem, sem shell ou armazenamento paralelos.

| Experiência | Nome público | Machine ID | Responsabilidade |
| --- | --- | --- | --- |
| Plataforma | ACULTA Studio | `aculta_studio` | Infraestrutura transversal de interface; permanece inalterada. |
| Editorial | ACULTA Studio Marley | `marley` | Criação, edição e moderação, com abas Revista, Wiki, Cursos, Fórum e Galerias. |
| Aprendizagem | ACULTA Studio Ganjalab | `ganjalab` | Dashboard e interface estudantil; regras e dados acadêmicos permanecem no LMS. |
| Gestão comercial | ACULTA STUDIO GREEN STASH | `green_stash` | Área integrada de administração da Loja; Commerce mantém o ownership operacional. |
| Contribuição Wiki | ACULTA STUDIO WE SATIVA | `we_sativa` | Área integrada de contribuição; Wiki mantém dados, formulários e decisões de acesso. |

WE SATIVA e os demais módulos mantêm suas identidades. Marley e Ganjalab compartilham a plataforma, não dados, privilégios ou regras de negócio. Nenhuma entidade, matrícula, avaliação, permissão ou armazenamento foi criado ou migrado por esta refatoração.

## URLs canônicas

Todas as rotas de Studio usam o purpose `account`; o Portal resolve o domínio de cada ambiente.

- Marley: `/studio/marley`, `/studio/marley/{aba}`, `/studio/marley/{aba}/{secao}` e `/studio/marley/{aba}/{secao}/{sub}`.
- Ganjalab: `/studio/ganjalab`.
- WE SATIVA: `/studio/we-sativa` e `/studio/we-sativa/inicio`.

A rota `/studio/ganjalab` pertencia à antiga entrada editorial e agora pertence exclusivamente ao dashboard de aprendizagem. Essa colisão impede preservar o mesmo caminho como alias editorial. As rotas canônicas editoriais ficam em `/studio/marley`.

## Compatibilidade

As seguintes entradas antigas redirecionam com HTTP 302 em requisições GET/HEAD autenticadas e autorizadas, mantendo o propósito `account`:

- `/ganjalab` e níveis editoriais profundos → `/studio/marley` e o nível equivalente.
- `/ganjalab420`, `/minha-aprendizagem` e `/studio/ganjalab420` → `/studio/ganjalab`.
- `/studio/ganjalab/{aba}` e níveis mais profundos → árvore correspondente de `/studio/marley`; o prefixo sem níveis é reservado à experiência estudantil.

POST e demais métodos não são redirecionados por essas rotas. Recebem 405 e não executam ações em outro destino. Não há redirect permanente 301.

IDs de rotas canônicas (`aculta_studio.home`, `area`, `section`, `tab` e `aculta_lms.student_dashboard`) são mantidos onde são contratos funcionais. Os plugins de área mantêm seus IDs (`revista`, `wiki`, `cursos`, `forum`, `galerias`). O provider de estudante usa o ID `ganjalab`.

## Histórico técnico

Registros e documentos de fases anteriores podem mencionar GanjaLab como identidade editorial e GanjaLab420 como identidade estudantil. Essas grafias descrevem corretamente decisões e implementações daquele período, mas não são nomes ou caminhos vigentes. O documento [GANJALAB420-TO-ACULTA-STUDIO-MIGRATION](../architecture/GANJALAB420-TO-ACULTA-STUDIO-MIGRATION.md) permanece como histórico auditável; esta tabela e as rotas acima prevalecem para o estado atual.

## Contrato futuro de identidade e paths - ADR-038

## Contrato futuro: Suites do ACULTA Studio por domínio (ADR-038)

Cada Suite responde em seu próprio domínio/purpose e sob o caminho-base abaixo. O prefixo público `/studio` e todas as rotas `/studio/*` serão retirados e responderão 404, sem alias ou redirect. `aculta_studio` permanece como infraestrutura compartilhada de composição e registry, não como raiz de URL.

| Suite | Purpose | Host de produção | Host de teste | Caminho-base |
| --- | --- | --- | --- | --- |
| Marley | `account` | `conta.aculta.org` | `conta.aculta.toca.net.br` | `/marley` |
| Ganjalab | `courses` | `cursos.aculta.org` | `cursos.aculta.toca.net.br` | `/ganjalab` |
| WE SATIVA | `wiki` | `wiki.aculta.org` | `wiki.aculta.toca.net.br` | `/wesativa` |
| Green Stash | `shop` | `loja.aculta.org` | `loja.aculta.toca.net.br` | `/greenstash` |
| Culturas | `eventos` | `eventos.aculta.org` | `eventos.aculta.toca.net.br` | `/culturas` |

Manter Conta e Perfil como experiências diferentes, fora das Suites. A raiz de `conta` passa a uma página de entrada da Conta com navegação para Marley e links absolutos às outras Suites; não aponta internamente para `/studio`. A Conta legada continua em `/conta-legada`. Planejar caminhos `/conta/*`, `/perfil/*` e `/salvos` para absorver as rotas pessoais atuais, preservando owners e sem misturar dados.

Regras de implementação:
- Rotas declaram o purpose correto e validam usuário, módulo owner e permissão no servidor. Purpose incorreto responde 404, inclusive POST. Links cross-domain são absolutos via `DomainPurposeManager`; não enviar POST/proxy de escrita entre purposes.
- Preservar route IDs e owners sempre que possível. Rotas de negócio podem ser declaradas pelo módulo owner e ter path/purpose ajustados conforme a Suite responsável.
- Novas Suites escolhem host/purpose e slug próprio antes de declarar rotas; não introduzir `/studio/{suite}`.
- Hosts de Conta, Cursos, Loja e Eventos já constam nos registros atuais. Wiki ainda usa `wiki420.aculta.org` e `wiki420.aculta.toca.net.br`; migração para `wiki.aculta.*` depende de config Deployer/Domain, DNS, TLS/vhost e homologação.
- Atualizar `PurposeFrontPathProcessor`, `FrontPathPolicy`, subscriber de propósito, navegação e testes para a raiz de cada purpose e as rotas da Suite. Os cinco caminhos da matriz são rotas válidas apenas no purpose respectivo.
- Remover todas as rotas `/studio` e `/studio/*` após mover destinos. Sem redirects legados; canonical e links apontam diretamente aos novos hosts/caminhos.
- Migrar links internos/externos, login/retorno, e-mail, notificações, sitemap, canonical, cache e testes. A troca de URL não transfere ownership de entidades, formulários, ACL ou workflows.

Estado em 2026-10-30: decisão e plano aprovados; nenhum corte de rotas, configuração de domínio, DNS ou validação de Runtime é declarado por este documento.

O conteúdo histórico abaixo descreve o estado anterior e não deve orientar novas rotas.
