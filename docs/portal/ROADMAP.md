# Roadmap do aculta_portal

Atualizado em 2026-10-12 (relatório de estado: [RELATORIO-ESTADO-2026-10-12.md](RELATORIO-ESTADO-2026-10-12.md)). Substitui o roadmap de modernização
P0–P10 e a lista antiga de prioridades de produto.

Dívidas e pendências, com evidência, estão em [`docs/operations/DEBT-REGISTER.md`](../operations/DEBT-REGISTER.md).
Este roadmap planeja as fases a partir desse registro. Não planeja lançamento 1.0.

Referências: [DRUPAL-11-STANDARDS.md](DRUPAL-11-STANDARDS.md) (norma),
[SOURCE-OF-TRUTH.md](SOURCE-OF-TRUTH.md) (fontes de verdade) e
[RELEASES.md](../operations/RELEASES.md) (gates de release).

## Estado atual

- Versão: **0.3.0-dev.28** (`aculta_portal.info.yml`; linha 0.3.x em desenvolvimento, com leitura de documentos existentes separada dos requisitos de gravação). Sem tag: tags só por pedido explícito do responsável. A linha 0.2.0 foi fechada antes desta revisão.
- Submódulos: `aculta_deployer` 0.1.20 (com falhas de teste e de `check`, DT-O12), `aculta_id_cpf` 0.2.0-dev.2 (Runtime DEFERRED), `aculta_portal_sitemap` 0.1.1.
- Tema: `aculta420` 0.6.0-dev.13. O gate Foundation reprova durações literais (DT-T19).
- Estado detalhado, com o que funciona, o que está DEFERRED e o que falta: [RELATORIO-ESTADO-2026-10-12.md](RELATORIO-ESTADO-2026-10-12.md).
- Modernização Drupal 11+ (P0–P10) e auditoria P10-R concluídas e mescladas (PRs #90 e #91).
- Migração do envio de e-mail para Mailer Plus (M0 a M2) concluída no código e validada no runtime de teste. Entrega em produção: decisão do responsável no fim do projeto.
- Gates: Drupal 11+ do Portal PASS (542 checks); comércio e configuração PASS; linha de base DT-O10 em `scripts/institution/CONFIG-DRIFT-BASELINE.json`.
- Pendências abertas: ver DEBT-REGISTER (estado vivo; este roadmap não repete a contagem).

## Ordem de execução

1. **Saneamento (S0–S6)**: resolver as dívidas antes de qualquer feature.
2. **Features de produto (F1–F4)**: somente depois de S0–S4 estarem fechadas, ou de a fase
   ser explicitamente priorizada pelo responsável.

Cada fase segue a política de `docs/versioning.md`: classificar antes de codar, registrar a
versão alvo, validar e só então taguear.

## Saneamento

### S0 — Higiene documental (em andamento)

- Corrigir referências a PRs e estados obsoletos (DT-P16, DT-P17, DT-P18).
- Critério: busca pelos padrões de DT-P16 e DT-P18 (PR obsoleta citada como integração em curso; auditoria P10-R apontada como etapa seguinte) vazia nos documentos atuais, excluindo este roadmap e o registro de dívidas, que guardam a evidência das dívidas resolvidas.

### S1 — Fronteira de apresentação (DT-P01) — concluída em 0.2.0-dev.20

- As duas páginas de template do módulo (`aculta-portal-shell`, `aculta-portal-photo-editor`)
  usam classes `aculta-*` do tema. Mover a apresentação para o tema, ou trocar essas classes por
  nomes neutros de contrato.
- Critério: nenhuma classe `aculta-*` em `web/modules/custom/aculta_portal/templates/`; o gate
  do Portal passa a impedir o retorno.

### S2 — Tipagem e cobertura (DT-P02, DT-P03) — parcial: strict_types concluído e 1 teste de Kernel (login); domínio e pagamento pendentes

- `declare(strict_types=1)` em `PortalHooks.php`; remover o teto correspondente do gate.
- Testes de Kernel para os serviços P5–P7, em banco MariaDB de teste. Comece pelos três de maior
  risco: login (validação), domínio (política de rotas) e pagamento (fail-closed).
- Critério: teto de `strictTypesDebt` vazio; ao menos um teste de Kernel por serviço de risco alto.

### S3 — Validação em ambiente real (DT-P04, DT-P05, DT-P07, DT-P08, DT-P11, DT-O06, DT-O07)

Resultado da rodada de 2026-10-09. Cada item tem resultado ou decisão de adiar; nada fica implícito.

- **DT-O06 (login de conta bloqueada): executado.** A validação do formulário de login recusa a conta bloqueada com a mensagem do Core ("não foi ativado ou está bloqueado") e aceita a conta ativa. Conta de teste criada e removida. Observação: o serviço `user.auth` do Core devolve o uid sem checar o status; a recusa é do formulário, por desenho do Core.
- **DT-P08 (login com CAPTCHA por HTTP): parcial.** O formulário de login em `/entrar` renderiza o widget Turnstile. A resolução do desafio depende de um humano ou de uma exceção de teste aprovada pelo responsável (ver DT-P11). Adiado.
- **DT-P11 (`validate-final-contact`): adiado com decisão.** O script é local por desenho (recusa qualquer host que não seja localhost) e sai com código 2 (pendente) porque o Turnstile recusa envio por script. Permanece pendente até a decisão de exceção de teste.
- **DT-P04 (PHP 8.5): adiado por ambiente.** O host tem PHP 8.4 e não tem PHP 8.5. A instalação exige privilégio de root neste host.
- **DT-P05 (entrega de e-mail) e DT-O07 (drift de `smtp.settings` e `system.mail`): adiados por dependência.** Importar a configuração de SMTP exige credenciais e o deploy. Hoje o drift é classificado: ambos diferem de `config/sync`, e não serão importados até a decisão de credenciais.
- **DT-P07 (Mercado Pago com credencial de sandbox): adiado.** Decisão do responsável, a pedido.

### S4 — Decisões do responsável (DT-P06, DT-P09, DT-P10, DT-G01)

- DT-P06 concluído em 0.2.0-dev.22 (decisão do responsável, 2026-10-09): cadastro neutro. DT-P09 resolvida na M1 da migração do e-mail (2026-10-11). Restam DT-P10 e DT-G01.

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

- Limpeza de branches remotas mescladas (64): listar, confirmar e apagar só com autorização.
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

### F4 — Engajamento
- Flag para favoritos e follows; Comment Notify para notificações, com opt-in e privacidade.
- Critério: consentimento e envio de e-mail validados em S3.

## Critério para encerrar o saneamento

Todos os itens do registro do Portal estão em Resolvida, Decisão registrada ou Aberta com
justificativa aceita pelo responsável. Os gates passam. Não há dívida nova sem registro.
