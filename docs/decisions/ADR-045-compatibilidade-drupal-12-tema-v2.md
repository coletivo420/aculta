# ADR-045 — Compatibilidade do tema ACULTA420 V2 com Drupal 12

- **Status:** proposto. Premissas e checklist definidos em 2026-11-04. A declaração "12 ready" só vale depois da validação da seção 4.
- **Data:** 2026-11-04.
- **Relaciona-se com:** ADR-044 (CTools e AJAX), `web/themes/custom/aculta420_v2/docs/roadmap.md`, `docs/operations/DEBT-REGISTER.md`, AGENTS §5.10 (Drupal 11 e PHP 8.5) e a regra de não chamar algo de "12 ready" sem verificação.

## 1. Contexto

- O desenvolvimento segue no **Drupal 11.5 instalado no servidor de desenvolvimento** (`drupal/core` 11.5.0-beta1 no `composer.json`). A migração para 12 fica para depois.
- O tema deve ser **compatível com Drupal 12** desde já, para não exigir retrabalho na migração.
- O `info.yml` do tema declara `core_version_requirement: ^11.5 || ^12`. Essa declaração ainda não foi validada no Core 12.
- Fontes secundárias (não confirmadas em drupal.org): Drupal 12.0.0 e 11.5.0 agendados para a semana de 7/12/2026, com release candidates na semana de 9/11/2026.
- Não há plano publicado para remover jQuery de `core/drupal.ajax` no Drupal 12. `#ajax` não está deprecado, e a migração para HTMX do core não tem versão definida (issue #3404409).

## 2. Decisões

1. **Desenvolvimento no 11.5; compatibilidade declarada como meta.** O tema não é chamado de "12 ready" até passar na validação da seção 4. Até lá, a declaração no `info.yml` continua `^11.5 || ^12`, sem qualquer afirmação de suporte validado.
2. **AJAX isolado em um único módulo JavaScript.** As interações do tema passam por um ponto único. Trocar Fetch por HTMX do core ou por `core/drupal.ajax` depois fica restrito a esse módulo.
3. **Preferir páginas completas quando não houver ganho real com AJAX.** Isso vale, em especial, para o wizard do LMS (ADR-044, caminho A).
4. **Nenhum seletor, API ou biblioteca deprecada no 11.5.** Antes da validação, o tema passa por análise estática de deprecações (`drupal-check` ou `phpstan-drupal`), tanto no tema quanto nos módulos do Portal que o usam.
5. **Toolbar.** O tema não depende do Toolbar. Antes do 12, verificar se o painel administrativo depende dele; se depender, a substituição é planejada antes da migração.
6. **Dívida de jQuery com prazo.** A remoção do jQuery do `core/drupal.dialog.ajax` nas páginas do Portal é registrada no `DEBT-REGISTER`, com responsável, e acompanhada nos change records do 12.0.0 e do 11.5.0.
7. **CTools fora do tema.** A lógica do CTools fica no `aculta_portal` (ADR-044). O tema apenas apresenta.
8. **UI Styles, se adotado,** precisa ter a compatibilidade com o 12 confirmada no `composer.json` antes da adoção (a página do módulo cita Drupal 12 na versão 1.22).

## 3. Consequências

- O tema continua funcional no 11.5, sem depender de mudanças do 12.
- Parte do trabalho de compatibilidade é feita agora (itens 2, 3, 4 e 7). A outra parte (itens 1, 5 e 6) depende de validação no RC e dos change records.
- O jQuery permanece nas páginas do Portal que dependem de `core/drupal.dialog.ajax` até a migração ou a troca dessas dependências. Isso é dívida registrada, não solução.

## 4. Checklist de validação (quando o RC estiver disponível)

- [ ] Instalar o tema em um ambiente de teste com Core 12 RC, no Homelab (nunca no servidor de produção).
- [ ] Rodar os gates do tema: `validate-aculta420-sdc-schemas.php`, `validate-aculta420-design-foundations.php`, `validate-browser-validators.php`, e via Drush `validate-aculta420-foundation.php`, `validate-aculta420-shell-contract.php` e `validate-aculta420-v2-sdc.php`.
- [ ] Rodar a análise de deprecações no Core 12 (tema e módulos do Portal que o usam).
- [ ] Testar login pela barra, busca, carrinho, mensagens, alertas e o menu de conta, em claro e escuro.
- [ ] Testar o painel administrativo (Navigation e, se houver, Toolbar).
- [ ] Testar as páginas do Portal com modais (eventos, galerias, mensagens, editor de foto) e registrar o estado do jQuery.
- [ ] Validar o Portal no tema ACULTA ativo; comparação com outros temas não é requisito.
- [ ] Atualizar `core_version_requirement`, o `roadmap.md` e o `CHANGELOG.md` do tema conforme o resultado.
- [ ] Registrar o resultado neste ADR, com a versão testada do Core 12.

## 5. Não-decisões

- Não define a data da migração para o Drupal 12.
- Não define se o AJAX futuro será o do core ou o HTMX do core; isso depende dos change records.
- Não altera a dependência de jQuery das páginas do Portal.

## 6. Pendências

- Verificar nos change records do 12.0.0 e do 11.5.0 (drupal.org) se houve remoção do jQuery do `core/drupal.ajax`.
- Confirmar a data do RC do 12, que hoje vem de fonte secundária.
- Registrar a dívida de jQuery no `DEBT-REGISTER`.
