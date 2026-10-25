# ADR-0002 — Portar trechos de código GPL com atribuição, sem instalar contrib

Status: aceita (fase 0.1.0).

## Contexto

Vários projetos do drupal.org resolvem partes do problema (contador de visitas,
janela do Search Console, variação entre períodos). Instalar cada um traz
dependências, credenciais no módulo Key e superfície de manutenção que o projeto
não quer. Os projetos são GPL-2.0 ou GPL-2.0-or-later, compatíveis com o ACULTA.

## Decisão

- **Portar** somente trechos pequenos e puros, adaptados ao ACULTA: namespace
  `Drupal\aculta_statistics`, injeção de dependência, tipos PHP, sem `\Drupal::`,
  sem Key e sem tokens em `state`.
- **Atribuir** no cabeçalho de cada arquivo: projeto, licença e commit de origem.
- **Não instalar** módulos contrib só para estudar código; a leitura é feita em
  diretório temporário.
- **Não portar** clientes que exijam biblioteca não aprovada ou credenciais
  fora do `aculta_portal.secrets_manager`.

## Consequências

- O ACULTA mantém o código portado, sem dependência de atualização do upstream.
- Correções de segurança no upstream precisam ser acompanhadas manualmente;
  registrar o commit de origem permite comparar.
- A lista do que foi portado e do que ficou como referência está em `PORTABILITY-MATRIX.md`.

## Exceções já decididas

- `google/apiclient` não entra sem justificativa escrita (AGENTS, regra 14).
- Módulos Key e `state` para credenciais não são usados.
