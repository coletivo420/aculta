# Pendências e Decisões do Responsável — ACULTA LMS

Este arquivo registra pontos que dependem de decisão explícita do responsável pelo projeto para que a implementação do LMS prossiga ou seja concluída em suas respectivas frentes.

---

## 1. Tour de Primeiro Acesso (`opigno_tour`)
- **Documentação de referência:** `docs/modules/OPIGNO-ACULTA-INDEPENDENCE-PLAN.md` (§61).
- **Contexto:** Na auditoria do Opigno e no plano de independência, o tour de primeiro acesso foi identificado como uma capacidade a ser decidida antes de qualquer implementação.
- **Opções:**
  1. *(Recomendado)* Não implementar no `aculta_lms` e delegar onboarding para o shell do ACULTA Studio ou documentação guiada estática (evitando dependências de bibliotecas de tour, JS pesado e manutenção de seletores).
  2. Implementar tour nativo e leve via HTML/dialog nativo no tema V2 sem bibliotecas externas.
  3. Adotar módulo ou biblioteca específica de tour (ex.: Shepherd.js / driver.js).
- **Decisão aguardada:** Qual caminho seguir para o tour de primeiro acesso do LMS?

---

## 2. Formulário Wizard Multietapa no LMS (ADR-044 / ADR-045)
- **Documentação de referência:** `docs/decisions/ADR-044-ctools-ampliacao-tema-v2.md` (§2 item 4, §3.3) e `docs/decisions/ADR-045-compatibilidade-drupal-12-tema-v2.md` (§2 item 3).
- **Contexto:** O ADR-044 prevê a possibilidade de form wizard para formulário multietapa no LMS, mas não definiu qual formulário deve adotá-lo. O ADR-045 recomenda preferir páginas completas quando não houver ganho real com AJAX.
- **Opções:**
  1. *(Recomendado)* Manter o fluxo de autoria do Course Builder e criação de turmas em formulários padrão/páginas completas sem CTools Form Wizard, reduzindo a complexidade de sessão e estado temporário.
  2. Definir o Course Builder ou a criação de cursos/turmas como um fluxo wizard multietapa via CTools ou Form API nativa.
- **Decisão aguardada:** Há algum formulário específico do LMS que deve ser multietapa (wizard), ou confirma-se a preferência por páginas completas?

---

## 3. Homologação de Pagamentos e Checkout Real (F7 / Mercado Pago)
- **Documentação de referência:** `docs/modules/ACULTA-LMS-ROADMAP.md` (F7) e `web/modules/custom/aculta_portal/modules/aculta_lms/TODO.md`.
- **Contexto:** A cobertura Kernel com Commerce interno passou (inscrição gratuita e item de carrinho com turma selecionada). Checkout pago real depende de credenciais de sandbox do Mercado Pago (`aculta_pagamentos_mercadopago`) e produto comercial configurado no ambiente de testes.
- **Opções:**
  1. *(Recomendado)* Conduzir a homologação HTTP com credenciais sandbox autorizadas pelo responsável no servidor de testes Homelab.
  2. Manter a cobertura no nível Kernel sem gateway remoto ativo.
- **Decisão aguardada:** Autorização e fornecimento do ambiente de sandbox para o teste de ponta a ponta do checkout de curso pago.

---

## 4. Integração H5P em Produção e Corpus de Teste
- **Documentação de referência:** `docs/modules/OPIGNO-ACULTA-INDEPENDENCE-PLAN.md` (§59) e `docs/operations/status/aculta_lms_h5p.yml`.
- **Contexto:** O pacote de teste H5P do Opigno é exclusivo do Homelab e não deve entrar em produção. A integração `aculta_lms_h5p` está em Dev.176 aguardando finalização dos gates PHPCS e homologação de storage privado `private://h5p`.
- **Decisão aguardada:** Aprovação do plano de submissão do H5P para RC após fechamento dos gates e configuração do storage privado.
