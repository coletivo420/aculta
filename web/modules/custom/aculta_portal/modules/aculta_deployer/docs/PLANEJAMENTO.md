# Planejamento de construção do ACULTA Deployer

Este documento define as fases de construção do submódulo `aculta_deployer`
(versão atual: 0.1.22, conforme `VERSION`; estado de testes e de `check` em
`docs/portal/historico/RELATORIO-ESTADO-2026-10-12.md` e DT-O12). Regra geral: cada fase é curta, termina com testes
determinísticos e entra em PR própria. Fases longas terminam com uma **verificação
de segurança** obrigatória (seção "Gates de segurança"). Nenhuma fase começa com a
anterior com pendência de segurança aberta.

## Premissas

- A ferramenta é standalone: sem Drupal, Drush ou vendor (ver `ARQUITETURA.md`).
- **Produção (decisão do responsável):** a entrega em produção não é requisito de fase, PR, release, RC nem pendência do agente. O responsável decide quando o projeto vai para produção, no fim de todo o projeto. Merge em `main` atualiza o código do repositório e não publica nada no Hostinger. As verificações de produção (`robots --env=production`, `sitemap --env=production`) são ensaios, não ações de deploy.
- O build de produção nunca grava dentro do repositório.
- Correções que o código não resolve são registradas em `registry/deploy-registry.json`.
- Segredos não entram no build nem no registro (ver `GUARDRAILS.md`).

## Estado atual (fase 0, concluída)

- CLI com `check`, `boundaries`, `list`, `register`, `build`, `version`.
- Substituição de host com fronteira de rótulo; remoção de aliases de teste.
- Registro com validação de campos e ids sequenciais.
- Fronteiras verificadas para tema, Portal (fora do submódulo) e a própria ferramenta.
- Testes em `tests/run.php`; documentação completa da versão 0.1.0.
- Pendência herdada: o favicon do validador de navegador ainda espera `aculta_favicon.ico`,
  e o site serve `aculta420-favicon.ico`.

## Fase 1 — Endurecimento da CLI e do registro (curta)

Objetivo: tornar as operações de escrita seguras e os erros previsíveis.

- Escrita atômica do registro (arquivo temporário e renomeação), com bloqueio contra
  gravações concorrentes.
- Códigos de saída documentados para todos os comandos; mensagens sem caminhos absolutos.
- Testes de erro: registro corrompido, JSON inválido, campos vazios, id duplicado.
- Corrigir o favicon no validador de navegador (`aculta420-favicon.ico`), pendência herdada.

Critério de saída: `tests/run.php` com novos casos de erro passando; `check` e
`boundaries` passando; PR própria.

## Fase 2 — Cobertura do escopo do build (longa)

**Status: concluída (0.1.x em desenvolvimento).** Implementado: saída nunca sobrescrita; saída via link ou dentro do repositório recusada; binários preservados e reprovados se tiverem host de teste; limite de tamanho por arquivo (`max_bytes`); hashes SHA-256 no relatório; autoverificação que remove a saída se restar host de teste; idempotência testada.

Objetivo: garantir que nenhum host de teste escape para produção.

- Varredura completa do escopo com relatório por arquivo (substituições, remoções).
- Idempotência: rodar `build` duas vezes produz a mesma árvore.
- Tratamento de arquivos binários e de tamanho grande (sem carregar tudo em memória).
- Escopo configurável por arquivo `config/deploy.json` validado (padrões inválidos são erro).
- Testes com árvore de fixtures: aliases, prefixos, rótulos extras, arquivos binários.

Gate de segurança ao fim da fase 2: ver seção "Gate S2".

## Fase 3 — Integração com os validadores (curta)

**Status: concluída.** Implementado: `register --from-json=ARQUIVO` com importação tudo ou nada, limite de 256 KiB, campos extras descartados e recusa de achado sem `page`, `current` ou `expected_production`.

Objetivo: transformar achados do validador em entradas do registro, sem escrita direta.

- `register --from-json=ARQUIVO`: importa achados de um JSON gerado pelo validador de
  navegador (canonical, sitemap, favicon), com validação completa antes de gravar.
- O validador continua sem escrever no registro; a importação é um passo explícito.
- Testes com achados de exemplo, inclusive achados inválidos.

Critério de saída: importação recusa achados sem `page`, `current` ou `expected_production`.

## Fase 4 — Sitemap por host de apoio (longa, depende de decisão)

Objetivo: resolver DT-P23 (sitemap sem o host de apoio). A decisão entre módulo por
domínio e rota técnica própria é do responsável. Esta fase não começa sem ela.

- Se módulo: avaliação de compatibilidade com Drupal 11.3, teste em branch, justificativa
  de dependência Composer (exigência do `AGENTS.md`).
- Se rota própria: controlador no Portal com purpose SUPPORT, sem conflito com a rota
  `/sitemap.xml` do simple_sitemap, com teste de precedência de rotas.
- Atualizar DEP-0002 para `resolved` após verificação no servidor de testes.

Gate de segurança ao fim da fase 4: ver seção "Gate S4".

## Fase 5 — Pipeline de deploy e verificação pós-deploy (longa)

**Status: concluída.** Implementado: `verify` somente leitura (GET, sem redirecionamentos, somente HTTPS, sem credenciais na URL, tempo limite de 10 s e limite de 1 MiB); entradas do registro com `probe` e `expect`; builds nunca sobrescrevem, então o build anterior permanece para rollback.

Objetivo: fechar o ciclo entre build, deploy e verificação.

- Comando `verify --url=PRODUÇÃO`: somente leitura, confere canonical e sitemap das
  páginas registradas; atualiza nada automaticamente.
- Relatório de deploy com hashes dos arquivos gerados.
- Procedimento de rollback documentado (o build anterior é preservado, não sobrescrito).

Gate de segurança ao fim da fase 5: ver seção "Gate S5".

## Fase 7 — Descoberta e sitemaps por ambiente (0.1.0-H, 0.1.3)

**Status: implementada no código e nos testes; verificação em produção pendente.**

- `sitemap --env=production|test`: índice central, base de cada filho (problema 1) e hosts
  de conteúdo, que devem pertencer à política de produção e responder (problema 2, cross-host).
- `robots --env=production`: diretiva `Sitemap:` com o índice de produção e ausência de
  `Disallow: /` em cada host.
- `web/robots.txt` anuncia `Sitemap: https://aculta.org/sitemap.xml`.
- Provisionamento do servidor de testes: `simple_sitemap.settings:base_url` = host de teste no
  runtime (`drush config:set`, não versionado). A produção usa `https://aculta.org` em `config/sync`.

Pendências desta fase (não fechadas):
1. Deploy no RC (todos os módulos, temas e subtemas). Só então: `robots --env=production` e `sitemap --env=production` como verificação pós-deploy. Até lá, o deploy não é executado.
2. Cross-host: `apoio.aculta.org`, `wiki420`, `coletivo420` e `cursos` precisam responder em produção
   (DEP-0001, DEP-0002 e DEP-0003 no registro). Verificação de propriedade no Search Console é
   manual e fica fora do deployer (ADR-009).
3. Decidir a `base_url` de teste de forma permanente (runtime hoje, `settings.local.php` como alternativa).
4. ~~Revisão do gate S5 para a verificação de descoberta.~~ Feita em 0.1.3 (itens S4 e S5 acima).

## Fase 8 — Painel do Portal e complemento (0.1.6)

**Status: implementada no código e nos testes.**

- Comando `report` e relatório neutro `var/deployer/status.json` (esquema 1, sem valores).
- Página somente leitura no painel do `aculta_portal` e opções de sitemap na visão geral.
- Declarado como complemento opcional do `aculta_portal`; o Portal não depende dele.

## Fase 10 — Ambiente por função (0.1.8)

**Status: implementada para as funções com comportamento por ambiente.** `environment`, `robots`, `sitemap`, `report` e
`verify` respeitam o ambiente; `build` recusa `--env=test`. Pendências:
1. Testes de `robots` e `sitemap` nos dois estados com servidor local (hoje exigem rede).
2. Entradas do registro com expectativa própria por ambiente, para que o canonical de teste (divergente) tenha um valor esperado.

## Fase 9 — Provisionamento de credenciais em produção (pendente)

**Status: não iniciada.** Decisão do responsável (2026-10-09): em produção as credenciais ficam no banco de dados,
criptografadas, e o `aculta_deployer` as provisiona após o deploy. A ferramenta não grava valores hoje (0.1.7).

Pendências desta fase:
1. Escolher a criptografia (chave, local da chave e rotação) com o responsável; a chave não fica no Git.
2. Gravação e leitura da camada criptografada no banco, pelo caminho do Drupal Key (provider compatível).
3. Passo pós-deploy com verificação de presença (nomes), sem exibir valores, e bloqueio se obrigatórias faltarem.
4. Gate S5 e registro no CHANGELOG da versão.

## Fase 11 — Migração de domínios teste → produção (0.1.15)

**Status: preparação concluída; execução no RC.** Tabela `domain_transition` em `config/deploy.json`, comando `domains` (somente leitura) e guia em `docs/MIGRACAO-DOMINIOS.md`.

Pendências:
1. Decidir o host de produção da revista (`revista420.aculta.org` ou manter `revista420.aculta.org`).
2. No RC: `domains` sem pendências, `transition --target=production`, importação parcial com backup, DNS e certificados de `*.aculta.org` (fora do repositório), e verificação pós-deploy.

## Fase 6 — Release 0.2.0 (curta)

- Atualizar CHANGELOG, VERSION e `info.yml`; a tag só é criada sob pedido do responsável.
- Esta fase é o gate de RC do deployer. A entrega em produção é decisão do responsável no fim do projeto.
- Revisão final da documentação e das fronteiras.

## Gates de segurança

Os gates são obrigatórios e devem ser registrados no CHANGELOG da versão.

### Gate S2 (cobertura do build)

- [x] `build` recusa saída dentro do repositório, inclusive por link simbólico (testado).
- [x] Nenhum arquivo gerado contém `toca.net.br` (autoverificação do build e teste).
- [x] Aliases de teste não aparecem no build de produção (testado).
- [x] Padrões de escopo inválidos são rejeitados antes de qualquer escrita (validação antes de `mkdir`).
- [x] Idempotência confirmada por comparação de hashes entre duas execuções (testado).

### Gate S4 (sitemap)

- [x] Nenhuma rota nova sobrepõe `/sitemap.xml` (evidência 0.1.0-G: a única rota é `simple_sitemap.sitemap_default`, que entrega o índice; `/sitemaps/{variant}/sitemap.xml` é a rota de variantes). Precedência confirmada por GET no servidor de testes.
- [x] O host de apoio aparece somente com URL do próprio host no sitemap de SUPPORT (`https://apoio.aculta.org/`). Verificado no servidor de testes; a verificação em produção depende de DEP-0001/0002.
- [x] Nenhuma dependência nova: a G e a H usam `simple_sitemap`, `domain` e `domain_config` já presentes no projeto.
- [x] Nenhum vazamento de dados pessoais: as 18 URLs são de nós publicados com acesso anônimo, raízes de purpose e `/contato` (webform público). Nenhuma rota de conta, admin, carrinho ou checkout aparece.

### Gate S5 (deploy)

- [x] `verify` é somente leitura: só GET, nenhum arquivo ou registro é alterado (testado por hash).
- [x] Relatório de deploy não contém segredos, tokens nem caminhos de servidor (caminhos relativos ao repositório; saída do build não é registrada).
- [x] Build anterior preservado para rollback (builds nunca sobrescrevem).
- [x] Verificação pós-deploy compara canonical e sitemap com o registro (`expect`).
- [x] Comandos `sitemap` e `robots` (0.1.3) são somente leitura: hashes de `registry/` e `config/` iguais antes e depois da execução (verificado em 0.1.3).

## Riscos abertos

- Sem fase 1, escritas concorrentes podem corromper o registro.
- Sem fase 2, um host de teste pode escapar para produção sem aviso.
- A fase 4 depende de decisão de arquitetura; sem ela, DEP-0002 permanece bloqueante.
