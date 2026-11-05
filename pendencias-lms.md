# Pendências e Decisões do Responsável — ACULTA LMS

Este arquivo registra o histórico de deliberações e o estado das decisões técnicas do LMS.

---

## 1. Tour de Primeiro Acesso (`opigno_tour`)
- **Status:** **Decidido pelo responsável em 2026-11-05**.
- **Decisão:** Implementar tour leve nativo (`<dialog>` HTML) no tema ACULTA420 / ACULTA420 V2, sem bibliotecas externas pesadas (sem driver.js / shepherd.js / Angular do Opigno).
- **Próximos passos:** Criar componente SDC no tema V2 para apresentação e behavior acessível acoplado ao primeiro acesso do estudante.

---

## 2. Formulário Wizard Multietapa no LMS (ADR-044 / ADR-045)
- **Status:** **Decidido pelo responsável em 2026-11-05**.
- **Decisão:** Adotar CTools Form Wizard multietapa no **Course Builder** (criação e estruturação de curso: metadados/capa, módulos/aulas e atividades).
- **Próximos passos:**
  1. Definir plugin ou classe base derivada de `\Drupal\ctools\Wizard\EntityFormWizardBase` / `FormWizardBase` no `aculta_lms`.
  2. Implementar as etapas: dados do curso, estrutura de aulas e revisão/publicação.
  3. Cobrir com testes Unit e Kernel.

---

## 3. Homologação de Pagamentos e Checkout Real (F7 / Mercado Pago)
- **Status:** **Esclarecido pelo responsável em 2026-11-05**.
- **Contexto:** As credenciais e o gateway do Mercado Pago (`aculta_pagamentos_mercadopago`) já foram homologados no portal e loja.
- **Próximos passos:**
  1. Verificar o gateway ativo em `commerce_payment.commerce_payment_gateway.mercado_pago`.
  2. Conectar os testes e validações de checkout do curso com os parâmetros já consolidados no módulo próprio.
