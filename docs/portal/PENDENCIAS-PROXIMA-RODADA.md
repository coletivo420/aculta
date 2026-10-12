# Pendências para a próxima rodada

Registro das pendências ao fechar a rodada de checkout, pagamento e Minha conta (branch `feat/slugs-checkout-pt`).

1. **Foco no primeiro erro:** em erro de formulário via AJAX na Minha conta, o foco fica no botão "op". O campo
   inválido recebe `aria-invalid`, mas não o foco. Ver `acultaPortalAccountFormErrors` em `js/account-navigation.js`.
2. **Alinhamento do S/N:** a marca S/N fica cerca de 30 px abaixo da linha Número/Complemento. Alinhar no tema
   (`css/components/portal-address-number.css`).
3. **Pagamento do pedido 7 (payment 183758594246):** confirmar o status na API do Mercado Pago e o e-mail de recibo
   com o token carregado no servidor (`MERCADOPAGO_TEST_ACCESS_TOKEN`). Nesta rodada a consulta não foi feita (token
   ausente na sessão) e não há registro de notificação (IPN) nem de envio de recibo no log.
4. **Segurança em abas:** abas "E-mail" e "Senha" (com URL própria e navegação dentro do shell), como Meus Dados. Renomear
   "E-mail de acesso" para "E-mail". Alinhar o título "Segurança da conta" com o item do menu "Segurança".
5. **Commerce Shipping:** instalar o módulo, definir o endereço único também para o envio, e o Bairro obrigatório nos
   formulários de envio. Ver `docs/portal/CHECKOUT-BILLING.md`.
