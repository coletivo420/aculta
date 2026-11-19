# ACULTA420 V2 — biblioteca de componentes

`audit-contraste.js` é um snippet de auditoria de contraste WCAG injetado no
navegador com sessão autenticada. Ele mede pares de texto e fundo computados
no catálogo real da UI Patterns Library
(`/painel-administrativo/aparencia/ui/components`) e devolve as razões de
contraste para revisão do modo claro e escuro.

O snippet não abre sessão nem navega sozinho: use uma sessão de teste obtida
por `drush uli` no host de conta e injete o arquivo no console ou via
WebDriver. A rota administrativa exige administrador e responde 403 para
visitantes anônimos.
