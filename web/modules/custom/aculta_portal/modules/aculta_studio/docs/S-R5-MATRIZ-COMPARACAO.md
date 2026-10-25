# ACULTA Studio — S-R5: matriz de comparação Conta / Perfil
**Data:** 2026-10-25 · **Estado:** execução preparatória; **NÃO homologada**.
**PR única:** [#297](https://github.com/coletivo420/aculta/pull/297), branch `docs/aculta-forum-0.1.0-dev.1`.
**Versões preservadas:** Studio `0.1.0-dev.22`, ACULTA420 `0.7.0-dev.2`.

## Evidências da rodada de qualidade (2026-10-25, dev.23)

Verificação executada no Runtime de teste, sem alterar dados:

- `drush php:script` confirmou as 16 rotas `aculta_studio.*` de Conta e Perfil (`/studio/conta` … `/studio/conta/preferencias`, `/studio/perfil` … `/studio/perfil/redes/{rede}`).
- Confirmou os caminhos legados da matriz: `/conta-legada`, `/dados`, `/dados/endereco`, `/dados/outros-dados`, `/seguranca`, `/seguranca/senha`, `/conexoes`, `/meu-apoio`, `/configuracoes`. Pedidos segue no owner Commerce (`view.commerce_user_orders.order_page` em `/pedidos`).
- `/pagamentos` não tem rota, conforme `e75437129`.
- Testes Unit de rotas, navegação, delegação e Form API passaram (60/60 em `aculta_studio`).

Não cobre: formulários, dados reais, duas contas, sessão anônima, CSRF, no-JS, zoom e capturas no Firefox ESR. Esses itens permanecem pendentes e são a etapa de navegador.

## Regra de passagem

A ordem de aceite continua **S-R4 homologada → S-R5 funcional/visual validada → decisão RC**.
A autorização para avançar S-R5 permite preparar instrumento de comparação,
auditar ownership e corrigir defeitos provados; **não transforma os SKIPs
da S-R4 em PASS**. Nesta sessão, o repositório foi inspecionado via GitHub,
mas não existe checkout Drupal/Firefox ESR/Drush do Homelab disponível
para homologação. Script S-R5 foi criado e analisado sintaticamente, **não
executado no navegador**.

Contrato: `docs/architecture/ACULTA-STUDIO-CONTA-PERFIL-SEPARADOS.md`.
Handoff: `HANDOFF-S-R4-S-R5-2026-10-25.md`.
Suite: `tests/browser/aculta-studio/parity-s-r5.mjs` e README na mesma pasta.

## Fontes de verdade verificadas no código da PR

- `aculta_studio.routing.yml`: rotas de Conta e Perfil no purpose ACCOUNT.
- `StudioAccountSectionController`: usa **a mesma instância de Form API
  ou os mesmos métodos** de `PortalController`/ `SupportController`;
  a UI de navegação é do Studio, sem `AccountShellBuilder` adicional.
- `AccountExperienceProvider`: oferece `/conta-legada` como referência
  permanente e suprime Documentos quando a capacidade opcional falta.
- `aculta_portal.routing.yml`: `/conta-legada`, `/dados`,
  `/dados/endereco`, `/dados/outros-dados`, `/seguranca`,
  `/seguranca/senha`, `/conexoes`, `/meu-apoio`,
  `/configuracoes`. **Não há rota /pedidos no roteamento do Portal**;
  não inferir uma URL homóloga. `PortalController::orders()` é chamado
  pelo Studio; comparar o dashboard legado somente como referência visual,
  e pedidos por fixtures/owner Commerce no Runtime.
- `PublicProfileController` + `PublicProfilePolicy`: pública somente
  no purpose MAIN `/u/{apelido}`. Perfil **privado** com apelido ainda
  expõe somente **apelido e foto**, não bio, redes ou e-mail; endereço,
  CPF, WhatsApp e nome cadastral nunca são públicos. Em perfil público,
  **e-mail pode ser opt-in explícito** e não deve ser rejeitado sem ler
  `field_exibir_email`. `PublicProfileLink` pode omitir link para o
  perfil privado, independentemente de acesso direto à URL.
- `StudioProfileController`: Meu Perfil do titular contém identidade
  comunitária, avatar próprio e Form API User; fallback sem apelido é
  "Apelido não informado", jamais nome/contato privado do cadastro.

## Matriz obrigatória de paridade

| ID | Studio ACCOUNT | Owner / referência | Operações e estados a provar | Diferença deliberada / aceite |
| --- | --- | --- | --- | --- |
| S5-00 | `/` | `/conta-legada` | hub, navegação, sessão, acesso anônimo, nenhuma duplicação de shell | Raiz ACCOUNT é hub; `/studio` direto 404; legado permanente |
| S5-01 | `/studio/conta` | `/conta-legada` | atalhos, seção ativa, links, back/forward, sem JS | Não reproduzir shell legado dentro do Studio |
| S5-02 | `/studio/conta/dados` | `/dados` | nome, WhatsApp, form ID/POST/CSRF, erros e retorno | Form Studio **não** tem `field_nickname`; legado preserva campo |
| S5-03 | `/studio/conta/endereco` | `/dados/endereco` | mesmo owner CEP, número, S/N, UF/cidade, validação e checkout | Persistência na mesma entidade/endereço, sem segundo formulário |
| S5-04 | `/studio/conta/documentos` | `/dados/outros-dados` | CPF/CNPJ opcional, máscara, readonly/imodificabilidade, serviço ausente, checkout | Sem `aculta_id_cpf`: rota Studio 404 e link oculto; **não** migrar documento para Perfil |
| S5-05 | `/studio/conta/seguranca` | `/seguranca` | e-mail atual, alteração/confirmador, indisponibilidade SMTP, CSRF | E-mail cadastral privado; nunca no editor Meu Perfil |
| S5-06 | `/studio/conta/seguranca/senha` | `/seguranca/senha` | alterar senha, primeira senha OAuth, erros, proteção | Fluxos nativos Portal/Auth preservados |
| S5-07 | `/studio/conta/conexoes` | `/conexoes` | OAuth Google conectado/ausente, remover/vincular, autorização | Owner Social Auth e seus callbacks, sem cópia |
| S5-08 | `/studio/conta/pedidos` | `PortalController::orders()`, dashboard legado | UID, carrinho rascunho excluído, números/status, detalhes, pagamento | **Sem `/pedidos` legado**; não fazer comparação funcional artificial por rota |
| S5-09 | `/studio/conta/apoio` | `/meu-apoio` | histórico, assinatura, pausar/reativar/cancelar, CSRF, doações | Reusar `SupportController::mySupport()`; ações originais |
| S5-10 | `/studio/conta/preferencias` | `/configuracoes` | mesmo `AppearanceForm`, alterações claro/escuro, persistência e cache | Rotas Studio e legado distintas, armazenamento único |
| S5-11 | `/studio/perfil` | `/u/{apelido}` em MAIN | foto própria privada, apelido, bio, redes, visibilidade, link condicional | Perfil Studio pode editar, público só mostra campos autorizados |
| S5-12 | `/studio/perfil/apelido` | `/dados` (legado) | apelido vazio/duplicado/reservado, retorno, slug, concorrência | Edição isolada no User, não duplicar campo `Profile.field_nickname` |
| S5-13 | `/studio/perfil/bio`, `/privacidade`, `/redes/{rede}` | formulários do User e MAIN | JS, sem JS, validação, privacidade por rede, retorno | Studio não substitui Form API/proteção original |
| S5-14 | modal de foto no Perfil | editor proprietário `aculta_user.foto` | upload, recorte, avatar sorteado, fallback, limpar foto, privacidade | Apenas `aculta_image` gera/persiste imagens; não criar storage |
| S5-15 | todas as seções | duas contas sintéticas e anônimo | cache, autorização, dados de A nunca aparecem em B, Domain ACCOUNT/MAIN | Sessão e identidade não podem contaminar outra conta |

## Matriz de visibilidade e identidade — não generalizar regras

| Fixture | Studio Perfil (titular) | MAIN `/u/{apelido}` | Link `PublicProfileLink` |
| --- | --- | --- | --- |
| Apelido ausente | "Apelido não informado" | 404/sem recurso público válido | Ausente |
| Perfil público com opt-in bio/redes/foto | Mostrar os próprios dados e editores | Mostrar apenas itens marcados na policy | Presente com slug canônico |
| Perfil público com campos sem opt-in | Mostrar ao próprio titular | Omitir dados sem opt-in | Presente quando slug válido |
| Perfil privado com apelido | Mostrar ao próprio titular | **Apenas apelido e foto**, conforme policy vigente | Ausente |
| Foto não publicada no editor | Titular pode ver pelo `aculta_image` | Não presumir mesma foto; conferir policy/avatar fallback | Sem influência no link |
| A vê perfil de B | Nunca carregar o editor de B no `/studio/perfil` de A | Somente dados públicos de B na policy | Independe da conta solicitante |

A matriz pressupõe papéis de teste comuns. O papel `content_editor`
está na lista `PAPEIS_PUBLICO_OBRIGATORIO`; escolher as fixtures
considerando isso (caso contrário o perfil "privado" pode tornar-se
público por obrigação de papel).

## Contratos de evidência e execução

1. **S-R4**: primeiro executar lint/PHPCS, Unit/Kernel/Functional,
   discovery real SDC, cache e duas contas, `aculta_image`, Firefox ESR/X11,
   teste no-JS/AJAX e Olivero. Sem isso, S-R5 continua **pré-homologação**.
2. **Acesso**: verificar host, URI, DB e branch no Homelab; usar somente
   fixtures sintéticas. As operações de escrita/checkout nunca devem usar
   dados pessoais. Executar suite `parity-s-r5.mjs` conforme seu README.
3. **Fluxos funcionais**: para cada ID acima, registrar GET, POST/CSRF,
   validação negativa, sucesso/erro, persistência (consulta owner autorizada),
   voltar/cancelar, permissões anônimo/A/B, com/sem JavaScript.
4. **Visual**: comparar Studio x legado em
   **320, 375, 390, 768, 1024 e 1440 px**, claro/escuro, zoom 200–400%,
   foco/teclado, nome acessível, contraste WCAG 2.2 AA, overflow e header
   multidomínio. Evidência PNG + notas por caso e ambiente.
   **Layouts podem ser diferentes**; a paridade é de recursos e direitos,
   não igualdade de pixels.
5. **Relato**: classificação **PASS / FAIL / SKIP** por caso e por
   suboperação, versão/SHA, data, host, finalidade de domínio, log de
   testes, screenshot não publicado e correção/rollback. Nunca transformar
   ausência de fixture ou falta de execução em PASS.
6. **Correções**: somente Studio/ACULTA420 conforme ownership,
   commits pequenos na mesma PR #297; sem merge/deploy.
   Se o defeito for do Portal/User/Commerce, corrigir com owner autorizado,
   sem afetar outros agentes ou restaurar rotas transitórias.

## Limites atuais e pendências anteriores ao RC

- **PASS estático:** rotas e reuso de owners conferidos no código da PR;
  políticas de perfil público/privado inspecionadas.
- **PASS de preparação:** script `parity-s-r5.mjs` adicionado; sintaxe
  submetida ao parser JavaScript (sem executar o navegador).
- **SKIP real:** PHP lint, PHPCS, PHPUnit, validação Drupal, status gate,
  descoberta runtime SDC, HTTP/CSRF, testes de duas contas e Firefox ESR/X11:
  checkout/runtime Homelab inacessíveis nesta sessão.
- **Pendência owner externa:** a verificação por fingerprint HMAC de
  CPF/CNPJ não assegura unicidade transacional concorrente; não alterar
  owner `aculta_id_cpf` sem escopo/decisão próprios.
- **S-R5 funcional/visual:** não aceita nem pronta para RC enquanto
  existir SKIP obrigatório ou FAIL não corrigido.

Este documento é matriz e inventário de fonte, **não certificado de
homologação**. Atualizar a cada execução real, sem modificar
`/conta-legada`, sem alias e sem mistura Conta/Perfil.
