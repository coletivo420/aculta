---
name: htmx
description: Use para escrever ou revisar interações HTMX no ACULTA (hx-post, hx-get, hx-select, hx-target, hx-swap, eventos, cabeçalhos de resposta, rotas que devolvem fragmento). Indica a página exata do htmx.org antes de responder de memória.
---

# HTMX no ACULTA

Índice de referência do htmx 2.x. Antes de responder de memória sobre atributo, modificador de swap ou cabeçalho, abra a página exata em https://htmx.org (ela muda entre versões).

## Atributos mais usados
- Requisição: hx-get, hx-post, hx-put, hx-patch, hx-delete — https://htmx.org/attributes/hx-post/
- Destino e troca: hx-target, hx-swap, hx-select, hx-select-oob, hx-swap-oob — https://htmx.org/attributes/hx-swap/
- Disparo e controle: hx-trigger, hx-sync, hx-indicator, hx-disabled-elt — https://htmx.org/attributes/hx-trigger/
- Dados: hx-include, hx-vals, hx-params, hx-headers — https://htmx.org/attributes/hx-include/
- Histórico e URL: hx-push-url, hx-replace-url, hx-history — https://htmx.org/attributes/hx-push-url/
- Eventos inline: hx-on — https://htmx.org/attributes/hx-on/

## Cabeçalhos de resposta
- HX-Redirect, HX-Location, HX-Push-Url, HX-Replace-Url, HX-Trigger — https://htmx.org/headers/

## Eventos e API
- https://htmx.org/events/ e https://htmx.org/api/

## Convenções deste projeto
- No Drupal, o HTMX é gerado por `Drupal\Core\Htmx\Htmx` (->post(url), ->select(), ->target(), ->swap('outerHTML'), ->onlyMainContent(), ->applyTo($elemento)). Use esse helper, não escreva `hx-*` à mão.
- A rota devolve o mesmo formulário ou bloco. O `hx-select` escolhe o fragmento pelo id estável.
- Envio de formulário continua submit do Form API: `name`, `value`, `id`, `#submit` e `formnovalidate` preservados.
- Para fechar um diálogo ou atualizar mais de um alvo, use cabeçalho de resposta (HX-Trigger) e um listener mínimo no tema. Não use jQuery.
- Toda rota HTMX nova tem teste que verifique os atributos `data-hx-*` e a resposta do fragmento.
- Drupal AJAX (`#ajax`, `use-ajax`) é legado; ver AGENTS.md, seção "Interações no front-end: HTMX antes de AJAX".
