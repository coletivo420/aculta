# Relatório de estado — Portal, submódulos e tema (2026-10-12)

Data de referência: 2026-10-12. Branch base: `main` (após o merge da PR #232, `4de1645`).

Este relatório separa o que **funciona** (verificado por gate ou teste executado nesta data), o que está
**implementado mas não validado no Runtime** (`RUNTIME STATUS: DEFERRED`), o que está **em andamento** e o que está
**só planejado**. Dívidas com evidência continuam no registro único
[`docs/operations/DEBT-REGISTER.md`](../operations/DEBT-REGISTER.md); aqui estão referenciadas pelo ID, sem repetir o
texto. Não há versão de lançamento 1.0 neste documento.

## 1. Versões vigentes

| Componente | Versão marcada | Fonte | Observação |
| --- | --- | --- | --- |
| `aculta_portal` | 0.3.0-dev.25 | `aculta_portal.info.yml` | Linha 0.3.x em desenvolvimento. A `docs/portal/ROADMAP.md` dizia 0.2.0; corrigido nesta revisão. |
| `aculta_deployer` | 0.1.20 | `VERSION` e `CHANGELOG.md` | `PLANEJAMENTO.md` dizia 0.1.0; corrigido nesta revisão. |
| `aculta_id_cpf` | 0.2.0-dev.2 | `aculta_id_cpf.info.yml` | Submódulo opt-in. Runtime DEFERRED. |
| `aculta_portal_sitemap` | 0.1.1 | `aculta_portal_sitemap.info.yml` | Submódulo opt-in para índice de sitemap. |
| `aculta420` (tema) | 0.6.0-dev.13 | `aculta420.info.yml` | Linha 0.6.x em desenvolvimento. |
| Drupal Core | 11.4.8 | `drush status` no Homelab | PHP do host: **8.4.26** (baseline do projeto é PHP 8.5; ver DT-P04). |

Tags Git: nenhuma criada nesta revisão (tags só por pedido explícito do responsável).

## 2. Gates executados nesta data

Execução local no Homelab, PHP 8.4.26. "Drush" indica execução por `drush scr` (o gate precisa do Drupal bootstrap).

| Gate | Resultado | Comentário |
| --- | --- | --- |
| `scripts/validate-aculta-portal-drupal11.php` | PASS (659 checks) | Baseline progressivo Drupal 11+. |
| `scripts/validate-module-theme-boundary.php` | PASS (683 verificações; classes de design no módulo: 0) | Fronteira módulo/tema. |
| `scripts/validate-public-slugs.php` | PASS (338 rotas; 20 violações de contrib na linha de base DT-P20) | A linha de base só pode diminuir. |
| `scripts/validate-environment-addresses.php` | PASS (1315 arquivos) | Endereços de ambiente só no mapeamento do deployer. |
| `scripts/validate-lms-skin.php` | PASS (10 checks; LMS 1.2.3) | Gate fixa a versão do LMS. |
| `scripts/validate-aculta420-sdc-schemas.php` | PASS (12 componentes; 185 checks) | — |
| `scripts/validate-aculta420-design-foundations.php` | PASS (exit 0) | Contraste claro/escuro medido. |
| `drush scr scripts/validate-aculta420-shell-contract.php` | PASS (9 checks) | Precisa do bootstrap; rodar via Drush. |
| `drush scr scripts/validate-aculta420-foundation.php` | **FAIL** | "Motion durations remain centralized in tokens.css": durações literais (`180ms`, `150ms`) em `css/components/portal-account.css`. Ver DT-T19. |
| `aculta_id_cpf/tests/run.php` | PASS (248 verificações de domínio) | — |
| `aculta_deployer/tests/run.php` | **FAIL** (27 PASS, 2 FAIL, exit 255) | "fronteiras atuais passam" e "build de ensaio gera saída em dois diretórios novos". Ver DT-O12. |
| `aculta_deployer check` | **FAIL** (exit 1) | 1 falha de fronteira e 52 avisos de perfil de produção. Ver DT-O12. |
| Testes de Kernel do Portal | Não executados nesta revisão | Os 5 arquivos em `tests/src/Kernel` dependem de MariaDB de teste (DT-P03). |

## 3. `aculta_portal` (módulo principal)

### 3.1 Funciona (verificado)

- **Arquitetura e padrão Drupal 11+:** gate PASS; `strict_types` em `src/` (DT-P02 resolvida); hooks OOP `#[Hook]`; sem novo `\Drupal::` em `src/` além dos já mapeados.
- **Fronteira módulo/tema:** o módulo não emite classe de design; contrato `data-portal-ui` e `domain_presentation`.
- **Domínios e purposes** (`DomainPurposeManager`, política administrativa, rotas de pagamento no MAIN): testes de Kernel de domínio (7 testes, DT-P03 parcial).
- **Confirmação de e-mail e cadastro neutro** (DT-P06 resolvida em 0.2.0-dev.22); teste de Kernel de login.
- **Login de conta bloqueada** (DT-O06 resolvida em 2026-10-09).
- **E-mail:** migração para Mailer Plus concluída (M0–M2); envio validado no Runtime de teste; remetente confirmado; HTML do Webform recebido e confirmado pelo responsável (DT-P09, DT-O07 resolvidas). Ver [EMAIL-TRANSPORT.md](EMAIL-TRANSPORT.md).
- **URLs de LMS em português** (`/curso/{curso}/{lição}/{atividade}`, 0.2.0-dev.1; DT-P21 resolvida). Caminhos numéricos antigos respondem 404 por política (DT-P22).
- **Busca pelo banco** (`/busca`, 0.2.0-dev.19; índice com `content_access`, 0.2.0-dev.40).
- **Fórum** adotado e implementado (0.2.0-dev.57). Denúncia de comentários pendente (DT-P13).
- **Endereço único** no checkout e na Minha conta; Commerce Shipping instalado no runtime com pane "Envio" (ver `CHECKOUT-BILLING.md`).
- **Mercado Pago:** pagamento processado no gateway; sem cartões salvos nem extrato na conta (regra permanente). Tokens de teste removidos do banco do runtime em 2026-10-11 (DT-O11, parcial).
- **Redes sociais e página LINKS** (0.3.0-dev.22 a dev.25): purpose `links`, canais administráveis, CTA para a raiz MAIN. Verificado por HTTP 200 e estrutura no servidor de testes.
- **Ambiente de endereços:** `aculta.org` e `aculta.toca.net.br` só no mapeamento do deployer (gate PASS).

### 3.2 Implementado, mas não validado no Runtime (DEFERRED)

| Item | Versão | O que falta |
| --- | --- | --- |
| Aba "Outros Dados" (CPF/CNPJ em Meus dados) | 0.3.0-dev.21 | Chave `aculta_document_encryption_key` no arquivo de segredos do Homelab; `drush en aculta_id_cpf`; teste de formulário e gravação; provisionamento em produção. |
| Administração de redes sociais e carrossel VVJB em LINKS | 0.3.0-dev.23 a dev.25 | Envio autenticado e salvamento pelo navegador (dev.25); inspeção visual com a configuração real. |
| Senha para conta criada com Google | `AccountPasswordCreateForm` | Teste no navegador; decisão sobre contas criadas antes da marca (`SENHA-CONTA-OAUTH.md`). |
| Segurança em abas (E-mail e Senha), foco no primeiro erro, S/N alinhado | 682bec2 | Verificação no navegador, desktop e mobile ([PENDENCIAS-PROXIMA-RODADA.md](PENDENCIAS-PROXIMA-RODADA.md), itens 1, 2, 4). |
| Recibo por e-mail do pedido | — | Não verificado (e-mail de teste não é entregue). |

### 3.3 Em aberto por decisão ou dependência externa

- **Pagamento real:** credencial de sandbox pendente a pedido do responsável (DT-P07). O pagamento do pedido 7 veio com `live_mode=true` com gateway em modo teste; precisa de explicação do responsável. A notificação (IPN) não chegou; a URL aponta para o Homelab (ver `PENDENCIAS-PROXIMA-RODADA.md`, item 3).
- **Rotação dos tokens do Mercado Pago** no painel (DT-O11).
- **Frete:** método "Envio" com taxa R$ 0,00, valor a definir (`PENDENCIAS-PROXIMA-RODADA.md`, item 5a).
- **CAPTCHA:** login com Turnstile não exercitado por HTTP; depende de exceção de teste (DT-P08, DT-P11).
- **Módulos de administração ativos em produção** (DT-P10).
- **Escopo da conta** (segurança/conexões) e escopo de AJAX (DT-P12).
- **Adoção de busca, engajamento e fórum** (DT-P13).
- **Sitemap do host de apoio** em produção: DEP-0001 a DEP-0003 (DT-P23).

### 3.4 Em aberto por implementação

- **Testes de Kernel:** só 5 arquivos em `tests/src/Kernel`. Pagamento sem teste de Kernel (DT-P03, aguarda decisão de instalar Commerce).
- **Config exportável incompleta:** `config/sync` não tem o tipo de produto `produto_loja`, nem o tipo de item de pedido `default`, nem várias configurações do runtime. Um deploy a partir de `config/sync` não reproduz a loja (`PENDENCIAS-PROXIMA-RODADA.md`, item 8; DT-O10: 63 itens de drift).
- **Classes `aculta-*` em templates do módulo:** DT-P01 resolvida em 0.2.0-dev.20. Mas a rodada de 2026-10-12 registrou uma falha de fronteira em `SocialLinksPagePresenter.php` (ver `PENDENCIAS-PROXIMA-RODADA.md`, item 9). Nesta revisão, o gate `validate-module-theme-boundary.php` passou com 0 classes de design no módulo; a falha informada não se repete no `main` atual.
- **Comentário que cita o submódulo do deployer:** `DomainRouteSubscriber.php` (linha 205) cita `aculta_deployer` em comentário; o `aculta_deployer boundaries` reprova (DT-O12).
- **Protocolo de versão:** a versão 0.3.0-dev.25 tem CHANGELOG, mas o marcador `aculta_portal.info.yml` e o `CHANGELOG` do módulo devem ser revisados a cada entrega.

### 3.5 Planejado (ainda não implementado)

Conforme [ROADMAP.md](ROADMAP.md) (revisado nesta data):

- **S2:** testes de Kernel para pagamento (DT-P03).
- **S3:** validação em ambiente real: CAPTCHA, Mercado Pago com sandbox, PHP 8.5 (DT-P04).
- **S4:** decisões do responsável (DT-P10, DT-G01).
- **S5:** conta (segurança/conexões) e AJAX reduzido (DT-P12, DT-P13).
- **S6:** operação e Runtime: 100 branches remotas (64 mescladas, DT-O01), snapshot de `estados/` (DT-O02), permissões de agregados (DT-O04), rotação de credenciais de teste (DT-O05).
- **F1 Conta completa; F2 Busca e feedback; F3 Participação (fórum com Domain próprio); F4 Engajamento** (favoritos e follows, notificações com consentimento). Dependem de S0–S4.

## 4. `aculta_deployer` (0.1.20, complemento opcional)

### 4.1 Funciona

- CLI com `check`, `boundaries`, `list`, `register`, `build`, `version`, `robots`, `sitemap`, `domains`, `transition`, `report`, `status`, `verify`.
- `robots --env=test` e `sitemap --env=test` verificados no servidor de testes (0.1.1 do sitemap; DEP-0002 resolvida no teste pela microfase 0.1.0-G).
- Relatório neutro `var/deployer/status.json` lido pelo painel do Portal, sem executar a CLI.
- Configuração de testes por host: `links`, `loja`, `forum` e demais hosts de teste declarados (0.1.16 a 0.1.20).

### 4.2 Falhas atuais (bloqueiam o fechamento da linha)

- `tests/run.php`: 27 PASS, 2 FAIL, exit 255 (DT-O12).
- `check`: falha de fronteira em `DomainRouteSubscriber.php` e **52 avisos de perfil** "production sem regra para arquivo dependente de ambiente" nos arquivos de `config/sync` de domínio/alias e `metatag` por domínio. Um build de produção a partir do estado atual não é confiável até essas regras existirem (DT-O12).
- Bloqueante `DEP-0003` (noindex nos VirtualHosts de teste) permanece aberto até `robots --env=production` passar em todos os hosts.

### 4.3 Planejado

Conforme `docs/PLANEJAMENTO.md` do submódulo:

- Fase 9 (credenciais em produção no banco criptografado): **não iniciada**; depende de escolha de criptografia e chave com o responsável.
- Fase 10 (ambiente por função): implementada, com 2 pendências (testes com servidor local; entradas com expectativa por ambiente).
- Fase 11 (migração de domínios teste → produção): preparação concluída; execução no RC; host da revista pendente de decisão.
- Fase 6 (release 0.2.0 do deployer): é o gate de RC do deployer; pendente.

## 5. `aculta_portal_sitemap` (0.1.1)

- **Funciona no servidor de testes:** índice com 5 filhos; `aculta-deployer sitemap --env=test` PASS; `robots --env=test` PASS; homologação `url.site` PASS sem vazamento de cache entre hosts.
- **Pendente:** indexar `/wiki/verbetes` (decisão do responsável); DEP-0002 (apoio cross-host) e verificação no Search Console em produção (RC).
- **Limite registrado:** no servidor de testes o canonical de alguns hosts aponta ao host de teste e de outros aponta à produção; estável entre ciclos, mas a revisar no RC.

## 6. `aculta_id_cpf` (0.2.0-dev.2, opt-in)

### 6.1 Funciona

- Validadores de CPF/CNPJ numérico e alfanumérico, normalização estrita e formatação (domínio: 248 verificações PASS).
- Criptografia simétrica com chave de Drupal Key (`aculta_document_encryption_key`), sem gravação em claro quando a chave falta.
- Unicidade por fingerprint (`field_documento_indice`), com mensagem genérica que não revela existência do documento (0.2.0-dev.2).

### 6.2 Limites conhecidos

- **Unicidade não é transacional** (registrado no README do submódulo); teste de Kernel do store pendente.
- **Elementos Form API, widgets e máscaras JavaScript foram retirados** por decisão de escopo. Os testes de UI daquela etapa não representam funcionalidade atual.
- **Campos PF/PJ e máscara** devem ser implementados manualmente no `aculta_portal`, consumindo os serviços do submódulo. Não iniciado.
- Runtime DEFERRED: sem teste de formulário e gravação no servidor de testes; chave ainda não provisionada no Homelab.

## 7. Tema `aculta420` (0.6.0-dev.13)

### 7.1 Funciona

- Gates PASS: SDC (12 componentes), design foundations (contraste claro e escuro), shell contract, LMS skin.
- Shell multidomínio, cabeçalho e barra institucional por purpose (0.2.0 e 0.3.x concluídas).
- Modo de cor claro, escuro e automático (F1, 0.5.0, validado no Runtime em 2026-10-09).
- Ícones Bootstrap Icons sem SVG inline nos templates (F2, 0.5.1).
- Biblioteca de componentes UI Patterns com 9 stories (F4, 0.6.0-dev.1).
- Página de produto da loja em SDC (0.6.0-dev.7): medição de captura de tela DEFERRED.
- Página LINKS com canais administráveis e carrossel VVJB (0.6.0-dev.10 a dev.13): HTTP e visual mobile claro/escuro PASS em 0.6.0-dev.11; validação dos botões adicionais (dev.13) pendente.

### 7.2 Falha atual

- **Gate Foundation FAIL** (DT-T19): durações literais fora de `tokens.css`, em `portal-account.css` (`180ms`, `150ms`). O 0.6.0-dev.11 já registrava "Foundation gate bloqueado" por essa causa. Correção: mover as durações para tokens em `tokens.css`. Não corrigida nesta revisão (é código do tema, fora do escopo desta entrega documental).

### 7.3 Em aberto

- Runtime DEFERRED de 0.6.0-dev.5 (popup e teclado de `/busca`) e dos botões de LINKS (dev.12 e dev.13).
- DT-T18: favicon oficial e ativação da gateway de apoio (independente do tema).
- DT-T10 / DT-T05 / DT-O03: reconstrução de ambiente novo a partir de `config/sync` falha por dependência de `cep_autocomplete` (decisão do responsável; recomendação registrada em DEBT-REGISTER).
- Kit Bloco Sativa 420 (0.4.5-dev.1) e identidades Baque Sativa e Podplant420: sem consumidor de template; autoria e licença pendentes.
- Linha 0.4.x fechada em 0.4.5 sem tag (decisão registrada).

### 7.4 Planejado

- Roadmap do tema (`web/themes/custom/aculta420/docs/roadmap.md`): T5 (reconstrução em ambiente novo, DEFERRED), F3 (busca com QA de popup) e a adoção de componentes UI Patterns por uso real.

## 8. O que falta, em resumo

| Prioridade | Falta | Ref. |
| --- | --- | --- |
| Alta | Corrigir falhas do `aculta_deployer` (testes, fronteira, 52 avisos de perfil) antes de tratar qualquer build de produção como confiável. | DT-O12 |
| Alta | Corrigir o gate Foundation do tema (durações literais). | DT-T19 |
| Alta | Config exportável incompleta (loja, tipos de pedido, drift de 63 itens). | DT-O10, PENDENCIAS item 8 |
| Alta | Rotação dos tokens do Mercado Pago e explicação do `live_mode=true` no pedido 7. | DT-O11 |
| Média | Validar Runtime de 0.3.0-dev.21 a dev.25 e 0.6.0-dev.5, dev.12, dev.13. | seções 3.2 e 7.3 |
| Média | PHP 8.5 não instalado (host 8.4.26). | DT-P04 |
| Média | Testes de Kernel de pagamento e store do id_cpf. | DT-P03, seção 6.2 |
| Média | Campos PF/PJ manuais no Portal (id_cpf). | seção 6.2 |
| Baixa | Frete (valor), recibo por e-mail, IPN. | seção 3.3 |

## 9. Decisões pendentes do responsável

1. Frete: taxa fixa ou retirada.
2. Sandbox do Mercado Pago (DT-P07) e autorização para rotação dos tokens (DT-O11).
3. Exceção de teste do CAPTCHA (DT-P08, DT-P11).
4. Escolha de `cep_autocomplete` (DT-T10 / DT-O03).
5. Host da revista em produção (Fase 11 do deployer).
6. Escolha entre módulo de sitemap por domínio ou rota técnica própria para o apoio (DT-P23).
7. Escopo da conta (DT-P12) e adoção de busca, engajamento e fórum (DT-P13).
8. Módulos de administração em produção (DT-P10).

## 10. Fontes

- `web/modules/custom/aculta_portal/CHANGELOG.md`, `web/modules/custom/aculta_portal/modules/*/CHANGELOG.md`, `web/themes/custom/aculta420/CHANGELOG.md`.
- `docs/portal/ROADMAP.md`, `docs/portal/PENDENCIAS-PROXIMA-RODADA.md`, `docs/operations/DEBT-REGISTER.md`.
- `web/modules/custom/aculta_portal/modules/aculta_deployer/docs/PLANEJAMENTO.md`.
- `web/themes/custom/aculta420/docs/roadmap.md`, `docs/versioning.md`.
- Saída dos gates da seção 2, executados em 2026-10-12.
