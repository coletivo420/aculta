# ADR-051 — CAPTCHA e anti-bot pertencem ao módulo aculta_portal

**Status:** aceita (2026-11-07)
**Escopo:** `web/modules/custom/aculta_portal`, `web/themes/custom/aculta420_v2`, `scripts/validate-aculta420-design-foundations.php`

## Contexto

Em 2026-11-07 o CSS do widget de CAPTCHA (`captcha-presentation.css`, biblioteca
`aculta_portal/captcha-presentation`, regra do Turnstile) foi movido para o tema
`aculta420_v2` para atender à regra de fronteira módulo x tema. Essa mudança
conflitou com o gate de fundamentos, que proíbe referência a anti-bot no tema.
A mudança foi revertida no mesmo dia, e o vai e volta se repetiu sem registro.

## Decisão

1. O CSS, a biblioteca e o anexo do CAPTCHA (`CaptchaPresentationHooks`) pertencem
   ao módulo `aculta_portal`, que é o dono da integração anti-bot.
2. O tema `aculta420_v2` não carrega estilo nem biblioteca de CAPTCHA ou Turnstile.
3. Não há exceção de "apresentação" para anti-bot no tema. Qualquer estilo novo
   de CAPTCHA entra no módulo, mesmo que seja só CSS.
4. Não se move este CSS de volta para o tema sem novo ADR que substitua este.

## Exceções registradas no gate

- `components/multidomain-bar/multidomain-bar.component.yml` declara a biblioteca
  `aculta_portal/search-preview`. A referência é tolerada apenas nesse arquivo e
  apenas para essa biblioteca; qualquer outro uso de `aculta_portal` no tema falha.

## Consequências

- O gate de fundamentos deve passar sem o CSS do CAPTCHA no tema.
- Quem alterar a fronteira de CAPTCHA deve citar este ADR no commit.
