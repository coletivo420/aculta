# Módulos — Conta, AJAX e Views interativas

Data da revisão: 2026-10-07.

## Arquitetura da Minha Conta

Visão geral (Ver Perfil, Bio, Redes sociais), perfil público e cartão de autor da Revista: `docs/modules/PERFIL-PUBLICO-E-CARTAO-DE-AUTOR.md`.


A área ACCOUNT usa rotas Drupal, access, Domain, controllers, Form API e progressive enhancement. O AJAX é otimização de UX; links e rotas normais continuam sendo o fallback full-page.

## VVJT — decisão arquitetural

`drupal/vvjt` não está instalado atualmente e **não deve substituir a navegação principal da Minha Conta**.

VVJT é um style plugin de Views: ele transforma linhas/campos de uma View em tabs. As seções da Conta são responsabilidades heterogêneas e rotas reais, com Form API, dados privados, OAuth, LMS/Group e Commerce.

Usar VVJT como engine do painel exigiria adaptar rotas e formulários ao modelo de linhas de Views, invertendo a arquitetura.

### Uso permitido

VVJT pode ser avaliado dentro de uma seção quando:

- o conteúdo já for naturalmente uma View;
- tabs forem a melhor apresentação dos registros;
- não houver Form API crítico dentro das tabs;
- access/cache já tiverem sido resolvidos antes da apresentação.

Não usar VVJT para substituir Dashboard, Dados, Segurança, Conexões, OAuth, checkout, troca de senha/e-mail ou fallback full-page.

## VVJ atualmente instalado

- `vvj_core`: fundação compartilhada da família VVJ 2.x;
- `vvjb`: carousel como formato de Views.

VVJ Core é infraestrutura da família VVJ, não framework geral do Portal.

## Decisão atual

**Manter a navegação AJAX atual da Conta.**

A direção futura permanece reduzir infraestrutura custom por fluxo, usando Drupal AJAX / HTMX / Views AJAX / Form API quando fizer sentido. Não trocar o painel inteiro por VVJT.
