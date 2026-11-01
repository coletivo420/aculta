# Pendências da próxima rodada

> **Snapshot histórico (arquivado em 2026-10-15).** Não é o estado atual. Estado corrente: [RELATORIO-ESTADO-APOS-PR-238-239.md](../RELATORIO-ESTADO-APOS-PR-238-239.md), ROADMAP e DEBT-REGISTER.


Estado das pendências do checkout, pagamento e Minha conta, atualizado em 2026-10-12. Branch base: `main`, após o merge
do PR #221 (`7a302a7`), que trouxe o commit `682bec2`.

Legenda: **feito** = código no repositório; **verificado** = confirmado no servidor de testes; **pendente** = falta
verificar ou decidir.

## Itens da rodada anterior

1. **Foco no primeiro erro** (Minha conta, AJAX): feito. O foco vai direto ao primeiro campo inválido
   (`acultaPortalAccountFormErrors` em `js/account-navigation.js`). **Pendente:** verificar no navegador.
2. **Alinhamento do S/N**: feito no CSS (`css/components/portal-address-number.css`). **Pendente:** verificar no
   navegador, desktop e mobile.
3. **Pagamento do pedido 7 (payment 183758594246)**: consulta à API do Mercado Pago feita a partir do PHP do runtime, sem
   imprimir o token: status `approved`. **Achado:** o pagamento veio com `live_mode=true`, embora a configuração do
   gateway esteja em modo teste. Isso precisa ser explicado pelo responsável. **Pendente:** notificação (IPN) não recebida;
   a URL de notificação aponta para o Homelab, que pode não ser alcançável pelo Mercado Pago. Recibo por e-mail: não
   verificado (o e-mail de teste não é entregue).
4. **Segurança em abas** (E-mail e Senha, com URL própria): feito no commit `682bec2`. **Pendente:** verificar no
   navegador, desktop e mobile. Ver também `docs/portal/SENHA-CONTA-OAUTH.md` (abaixo).
5. **Commerce Shipping**: feito no runtime e no código. Módulo habilitado; pane "Envio" no checkout padrão; endereço
   único reaproveitado no envio; trait de envio na camiseta; método "Envio" (taxa fixa **R$ 0,00, valor a definir**);
   países de envio = Brasil. Detalhes em `docs/portal/CHECKOUT-BILLING.md`.
   **Pendente:** (a) definir o valor do frete ou a retirada; (b) teste de navegador com pedido novo e camiseta
   (o pane aparece, o frete calcula, o pedido chega à revisão); (c) provisionar loja e método em produção, porque são
   conteúdo do banco e não entram em `config/sync`.

## Itens novos

6. **Criar a primeira senha em conta criada com Google**: feito (`AccountPasswordCreateForm`). Verificado por teste de
   lógica no runtime, com conta temporária. **Pendente:** verificar no navegador, e decidir o tratamento das contas
   criadas antes da marca (ver `docs/portal/SENHA-CONTA-OAUTH.md`, seção "Limitação conhecida").
7. **CHANGELOG do `aculta_portal`**: feito em 0.3.0-dev.20 (itens 4 a 6 e envio no checkout).
8. **Config exportável incompleta**: `config/sync` não tem o tipo de produto `produto_loja`, nem o tipo de item de pedido
   `default`, nem várias configurações exportadas no runtime. Um deploy a partir de `config/sync` não reproduz a loja.
   Antes do RC, comparar runtime e `config/sync` por objeto.
9. **Gate de fronteira** (`scripts/validate-module-theme-boundary.php`): 1 falha, em
   `SocialLinksPagePresenter.php` (classes de design no módulo). Vem da linha de redes sociais, não destas entregas.

## Trabalho de outra linha, ainda sem commit

Há alterações na árvore de trabalho que não fazem parte destas entregas: purpose `links` (redes sociais, versões
0.3.0-dev.17 e dev.18 no `CHANGELOG.md`), carrossel VVJB, administração de redes sociais, submódulo
`aculta_id_cpf` (ainda não rastreado no Git), deployer 0.1.20 e documentos de arquitetura. Não foram commitados. Confirmar
o responsável antes de incluir no mesmo PR.
