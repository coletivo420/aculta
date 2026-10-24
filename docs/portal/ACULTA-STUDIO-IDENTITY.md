# ACULTA Studio — contrato de identidade e rotas

**Estado:** contrato vigente da refatoração iniciada em 2026-10-24. Este arquivo é a fonte de verdade para nomes, IDs e URLs das experiências.

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

| Experiência | Nome público | Machine ID | Responsabilidade |
| --- | --- | --- | --- |
| Plataforma | ACULTA Studio | `aculta_studio` | Infraestrutura transversal de interface; permanece inalterada. |
| Editorial | ACULTA Studio Marley | `marley` | Criação, edição e moderação, com abas Revista, Wiki, Cursos, Fórum e Galerias. |
| Aprendizagem | ACULTA Studio Ganjalab | `ganjalab` | Dashboard e interface estudantil; regras e dados acadêmicos permanecem no LMS. |
| Contribuição Wiki | WE SATIVA | `we_sativa` | Experiência independente de contribuição, preservando ownership da Wiki. |

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
