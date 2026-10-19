# Roadmap do aculta_portal

Atualizado após os merges das PRs #238 e #239 (2026-10-13 UTC; estado atual: [RELATORIO-ESTADO-APOS-PR-238-239.md](RELATORIO-ESTADO-APOS-PR-238-239.md)). Substitui o roadmap de modernização
P0–P10 e a lista antiga de prioridades de produto.

Dívidas e pendências, com evidência, estão em [`docs/operations/DEBT-REGISTER.md`](../operations/DEBT-REGISTER.md).
Este roadmap planeja as fases a partir desse registro. Não planeja lançamento 1.0.

Referências: [DRUPAL-11-STANDARDS.md](DRUPAL-11-STANDARDS.md) (norma),
[SOURCE-OF-TRUTH.md](SOURCE-OF-TRUTH.md) (fontes de verdade) e
[RELEASES.md](../operations/RELEASES.md) (gates de release).

- Escopo Associado ID e migração: [ACULTA-ASSOCIADO-ID.md](../modules/ACULTA-ASSOCIADO-ID.md).

- Apresentação Wiki420 e política de autoria pública: [WIKI.md](WIKI.md); Runtime DEFERRED até inspeção visual.

## Estado atual

- Versão: **0.4.0-dev.17** (`aculta_portal.info.yml` e `CHANGELOG.md`; linha 0.4.x em desenvolvimento, com leitura de documentos existentes separada dos requisitos de gravação). Sem tag: tags só por pedido explícito do responsável. A linha 0.2.0 foi fechada antes desta revisão.
- Submódulos: `aculta_deployer` 0.1.21 (`info.yml`, `VERSION` e `CHANGELOG.md`; falhas de teste e de `check`, DT-O12), `aculta_associado_id` 0.3.0-dev.1 (migração do legado `aculta_id_cpf` pendente; Runtime DEFERRED), `aculta_portal_sitemap` 0.1.1, `aculta_messenger` 0.1.1-dev.11, `aculta_gallery` 0.1.0-rc.1, `aculta_magazine` 0.1.0-dev.5.
- Tema: `aculta420` 0.6.0-dev.35 (`aculta420.info.yml`, `CHANGELOG.md` e `docs/roadmap.md` coerentes). Gate Foundation PASS (341 checks) desde 2026-10-15: DT-T19 resolvida em 0.6.0-dev.22.
- Estado detalhado, com o que funciona, o que está DEFERRED e o que falta: [RELATORIO-ESTADO-APOS-PR-238-239.md](RELATORIO-ESTADO-APOS-PR-238-239.md). O relatório [de 2026-10-12](historico/RELATORIO-ESTADO-2026-10-12.md) é um snapshot anterior às PRs #238 e #239.
- Modernização Drupal 11+ (P0–P10) e auditoria P10-R concluídas e mescladas (PRs #90 e #91).
- Migração do envio de e-mail para Mailer Plus (M0 a M2) concluída no código e validada no runtime de teste. Entrega em produção: decisão do responsável no fim do projeto.
- Verificações desta mudança (2026-10-13): Drupal 11+ PASS (659 checks), domínio do Associado ID PASS (248 checks), Unit do Associado ID PASS (47 testes/107 asserções), Unit do Portal PASS (63 testes/118 asserções) e lint PHP PASS. Evidência anterior de fronteira módulo/tema e endereços permanece registrada na revisão anterior. Foundation PASS em 2026-10-15 (341 checks, via Drush; DT-T19 resolvida).
- Pendências abertas: ver DEBT-REGISTER (estado vivo; este roadmap não repete a contagem).

## Próximos passos (replanejamento de 2026-10-15)

Ordem recomendada, com o registro de dívidas como fonte de estado:

1. **DT-P20 (Portal):** duas rotas de `message_subscribe_ui` (`/user/{user}/message-subscribe`) estão fora da linha de base do gate de slugs. Decidir entre corrigir a rota ou registrar a exceção com justificativa; o gate não pode ficar em FAIL.
2. **DT-O12 (`aculta_deployer`):** `tests/run.php` aborta no teste de build e `check` avisa sobre `cookie_domain` do `services.local.yml`. Antes da próxima marcação, corrigir os testes.
3. **Operação (DT-O02, DT-O04, DT-O05):** decisões e ajustes de ambiente, sem mudança de código.
4. **Features (F1–F4):** só depois dos itens 1 a 3, ou por priorização explícita do responsável.

## Ordem de execução

1. **Saneamento (S0–S6)**: resolver as dívidas antes de qualquer feature.
2. **Features de produto (F1–F4)**: somente depois de S0–S4 estarem fechadas, ou de a fase
   ser explicitamente priorizada pelo responsável.

Cada fase segue a política de `docs/versioning.md`: classificar antes de codar, registrar a
versão alvo, validar e só então taguear.

## Saneamento

### S0 — Higiene documental (concluída nesta revisão)

- Corrigir referências a PRs e estados obsoletos (DT-P16, DT-P17, DT-P18).
- Critério verificado: busca nos documentos atuais não encontrou PR antiga descrita como integração em curso nem P10-R descrita como etapa futura. A auditoria P10-R permanece referenciada como concluída; suas evidências foram removidas na limpeza documental e estão preservadas no commit `9c95420`.

### S1 — Fronteira de apresentação (DT-P01) — concluída em 0.2.0-dev.20

- As duas páginas de template do módulo (`aculta-portal-shell`, `aculta-portal-photo-editor`)
  usam classes `aculta-*` do tema. Mover a apresentação para o tema, ou trocar essas classes por
  nomes neutros de contrato.
- Critério: nenhuma classe `aculta-*` em `web/modules/custom/aculta_portal/templates/`; o gate
  do Portal passa a impedir o retorno.

### S2 — Tipagem e cobertura (DT-P02, DT-P03) — parcial: strict_types, login e domínio concluídos; pagamento pendente de decisão

- `declare(strict_types=1)` em `PortalHooks.php`; remover o teto correspondente do gate.
- Testes de Kernel existentes para login (`EmailConfirmationPolicyKernelTest`) e domínio
  (`DomainPurposeManagerKernelTest`, 7 testes). Um teste de Kernel de pagamento segue pendente:
  sua instalação exige decidir sobre o ambiente de teste do Commerce (DT-P03).
- Critério: teto de `strictTypesDebt` vazio; cobertura de Kernel para login e domínio; pagamento
  depende da decisão registrada no DEBT-REGISTER.

### S3 — Validação em ambiente real (DT-P04, DT-P05, DT-P07, DT-P08, DT-P11)

Resultados abaixo têm datas próprias no registro de dívidas. Itens resolvidos não devem ser descritos como pendentes.

- **DT-O06 (login de conta bloqueada): executado.** A validação do formulário de login recusa a conta bloqueada com a mensagem do Core ("não foi ativado ou está bloqueado") e aceita a conta ativa. Conta de teste criada e removida. Observação: o serviço `user.auth` do Core devolve o uid sem checar o status; a recusa é do formulário, por desenho do Core.
- **DT-P08 (login com CAPTCHA por HTTP): parcial.** O formulário de login em `/entrar` renderiza o widget Turnstile. A resolução do desafio depende de um humano ou de uma exceção de teste aprovada pelo responsável (ver DT-P11). Adiado.
- **DT-P11 (`validate-final-contact`): adiado com decisão.** O script é local por desenho (recusa qualquer host que não seja localhost) e sai com código 2 (pendente) porque o Turnstile recusa envio por script. Permanece pendente até a decisão de exceção de teste.
- **DT-P04 (PHP 8.5): adiado por ambiente.** O host tem PHP 8.4 e não tem PHP 8.5. A instalação exige privilégio de root neste host.
- **DT-O07 (drift de `smtp.settings` e `system.mail`): resolvida na M2.** O módulo SMTP e sua configuração foram removidos; `system.mail` usa Mailer Plus. **DT-P05 (entrega em produção):** decisão do responsável para o fim do projeto; não é bloqueio de fase. O envio no Runtime de teste está validado.
- **DT-P07 (Mercado Pago com credencial de sandbox): adiado.** Decisão do responsável, a pedido.

### S4 — Decisões do responsável (DT-P10; decisões tomadas em DT-P06, DT-P09 e DT-G01)

- DT-P06 concluído em 0.2.0-dev.22 (decisão do responsável, 2026-10-09): cadastro neutro. DT-P09 resolvida na M1 da migração do e-mail (2026-10-11). DT-G01 tem decisão registrada pelo responsável. Resta a decisão DT-P10 sobre módulos de administração em produção.

- Reabrir a política de enumeração pelo cadastro quando houver revisão de CAPTCHA.
- Decidir sobre os módulos de administração ativos em produção.
- Decidir sobre a mensagem do webform via SMTP (template HTML).
- Critério: cada decisão registrada no registro, com data e responsável.

### S5 — Conta e AJAX (DT-P12, DT-P13)

- Conta: segurança e conexões reimplementadas sobre a `main`, como presenters e sem storage
  paralelo. Escopo a confirmar antes do início (DT-P12).
- Reduzir `js/account-navigation.js` (183 linhas) por fluxo, com fallback de página inteira.
- Busca, engajamento e fórum: só entram como feature (F-fase), com decisão de adoção.

### S6 — Operação e Runtime (DT-O01 a DT-O05)

- Limpeza de branches remotas mescladas: a contagem de 64 vem de inventário anterior e está desatualizada; reconciliar o inventário atual e remover somente branches já mescladas conforme autorização do responsável (DT-O01).
- Política para `estados/`: o snapshot de 2026-10-04 não representa mais o Runtime. Decidir entre
  novo snapshot versionado ou registro de drift.
- Diretório de agregados do Drupal: ajuste de permissão no ambiente (responsabilidade de operação).
- Credenciais de teste: rotação e remoção periódicas, conforme `TEST-DATA.md`.

## Features de produto (depois do saneamento)

Estas fases dependem de S0–S4. Nenhuma tem versão alvo de 1.0.

### F1 — Conta completa
- Segurança, conexões e identidade (ver S5), com presenters e AJAX reduzido.
- Critério: cada fluxo da conta com fallback sem JavaScript.

### F2 — Busca e feedback
- Search API e Views para Wiki e conteúdo, quando houver backend e Runtime.
- Critério: indexação sem alterar índices de produção sem autorização.

### F3 — Participação
- Fórum e Participation Hub, com Domain próprio e Forum/Node/Comment/Taxonomy como fontes.
- Critério: nenhum storage paralelo; access e cache por Domain.

O roadmap específico aprovado em 2026-10-19 reconstrói o ACULTA Forum por versões
`0.1.0-dev.1`–`0.1.0-dev.17`, com Drupal Forum contrib legado apenas até uma fase
de retirada controlada. A fase ativa e seus gates estão em
[`aculta_forum/docs/ROADMAP.md`](../../web/modules/custom/aculta_portal/modules/aculta_forum/docs/ROADMAP.md);
esta linha não substitui nem duplica a sequência do Portal.

### F4 — Engajamento
- Flag para favoritos e follows; Comment Notify para notificações, com opt-in e privacidade.
- Critério: consentimento e envio de e-mail validados em S3.

## Linha LMS + PDF global

O inventário e planejamento da evolução educacional estão em [roadmap do aculta_lms](../modules/ACULTA-LMS-ROADMAP.md). A linha educacional deve seguir fases independentes e não altera a ordem de saneamento deste roadmap. Cada etapa requer decisão e critérios próprios.

As fontes de verdade e o estado exportado de Group/LMS estão em [LMS-GROUP.md](../modules/LMS-GROUP.md) e [ADR-005](../decisions/ADR-005-lms-integration.md). O viewer global e seu contrato estão em [aculta_pdf_viewer](../../web/modules/custom/aculta_portal/modules/aculta_pdf_viewer/README.md) e [PDF-VIEWER-INTEGRATION.md](PDF-VIEWER-INTEGRATION.md). Integração com Messenger, User, Forum, Gallery e apresentação ACULTA420: [LMS-INTEGRATION-CONTRACTS.md](LMS-INTEGRATION-CONTRACTS.md). A matriz de segurança/arquivos está em [SECURITY-ACCESS-FILES-MATRIX.md](SECURITY-ACCESS-FILES-MATRIX.md).

**Restrições técnicas:** PDFPreview 8.x-1.2 declara ImageMagick `^3.7 || ^4.0`; PDF 1.3 carrega `.js` enquanto a distribuição PDF.js fixada 6.4.299 usa `.mjs`. A rota privada Core preserva File/Media access, mas a Config Sync atual concede `view media` a anônimo e autenticado e Group↔Media ainda não está integrado; não usar PDF privado como documento confidencial de turma até validar essa autorização. Ver [matriz de compatibilidade](../modules/LMS-COMPATIBILITY.md) e [contrato PDF](PDF-VIEWER-INTEGRATION.md).

## Critério para encerrar o saneamento

Todos os itens do registro do Portal estão em Resolvida, Decisão registrada ou Aberta com
justificativa aceita pelo responsável. Os gates passam. Não há dívida nova sem registro.

## Primeiro release candidate — preparação do pacote de produção

Ao final da preparação do primeiro RC do ACULTA Portal, iniciar no
`aculta_deployer` uma etapa de **montagem de pacote enxuto de produção**. O
objetivo é evitar que arquivos usados somente para desenvolvimento, testes e
operação de deploy sejam enviados ou mantidos no servidor Hostinger. Esses
arquivos continuam no Git para desenvolvimento e auditoria, mas não entram no
artefato de produção quando comprovadamente não forem necessários ao Drupal em
runtime.

A montagem deve partir de uma lista explícita e revisável de inclusão e exclusão,
e manter tudo que a instalação precisa para executar: Core, módulos e temas
ativos, dependências Composer de produção, configuração aplicável e arquivos
operacionais exigidos pelo ambiente. Testes, fixtures, ferramentas locais de
desenvolvimento, documentação interna e metadados do repositório podem ser
excluídos quando não forem dependências de runtime. Não excluir por extensão ou
nome genérico sem verificar consumidores; por exemplo, arquivos PHP/YAML/JSON
podem ser necessários em produção. O Deployer deve montar o artefato fora do
repositório, gerar manifesto auditável e validar tanto arquivos obrigatórios
quanto ausências esperadas, sem alterar a árvore de trabalho.

Essa etapa define e valida o conteúdo do pacote; não autoriza upload nem deploy
em produção. Ver também o
[planejamento do aculta_deployer](../../web/modules/custom/aculta_portal/modules/aculta_deployer/docs/PLANEJAMENTO.md).
