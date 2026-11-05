# UI Styles do tema V2 (Fase 4)

Estilos que o administrador Drupal pode aplicar pelo módulo `ui_styles`, sem
código. Cada opção aponta para uma variante que já existe no componente, com
tokens e contraste medidos. Nenhum estilo novo é criado nesta fase.

## 1. Decisões do responsável (2026-11-05)

- **Escopo:** botões (4 variantes), faixa de acento de cartão e cores por
  purpose.
- **Quem aplica:** somente o administrador Drupal. Editores comuns não
  escolhem estilo.
- **Momento:** a F4 entra antes da RC. A RC depende dela.

## 2. Pré-requisitos

Todos são bloqueantes para a RC:

1. Botões sincronizados no servidor de testes, com o CSS legado de
   `src/styles/buttons.css` removido (botão com `--color-brand-highlight` ainda
   existe ali).
2. Escolha de esquema por purpose ligada ao runtime. Hoje
   `aculta_purpose_palette` é salva, mas não é lida por nenhum código de
   renderização. Sem isso, a opção "cores por purpose" não tem efeito.
3. Servidor de testes na versão atual de `origin/dev`, confirmada por hash.

## 3. Inventário das opções

| Opção | Componente | Variantes existentes | Situação |
| --- | --- | --- | --- |
| Botão primário | `button` | `primary` | Tokens `--button-*` migrados; pendente de sincronização |
| Botão secundário | `button` | `secondary` | Idem |
| Botão contorno | `button` | `outline` | Idem |
| Botão fantasma | `button` | `ghost` | Idem |
| Faixa de acento verde | `card-cultural` | `accent: green` | Enum do componente; mapeamento para classe pendente |
| Faixa de acento amarela | `card-cultural` | `accent: yellow` | Idem |
| Faixa de acento vermelha | `card-cultural` | `accent: red` | Idem |
| Cores por purpose | tema (faixa `--purpose-*`) | 10 esquemas | Escolha salva, não aplicada; bloqueada por pré-requisito 2 |

O `accent` do cartão é resolvido pelo Portal. O tema só traduz o valor em
classe ou token. A escolha do administrador por UI Styles não deve sobrepor o
`accent` vindo do Portal sem regra escrita: esse ponto precisa de decisão antes
da implementação.

## 4. Regras

- Cada opção de UI Styles aponta para uma classe que já existe no mapa de
  `docs/classes-sem-css-excecoes.md`. Classe nova exige entrada no mapa.
- UI Styles não altera a barra institucional, o foco, os neutros nem a lógica de
  purpose. `validate-module-theme-boundary.php` continua obrigatório.
- Botões usam só os tokens `--button-*`. Nunca `--purpose-*` nem
  `--color-brand-highlight`.
- As opções ficam disponíveis apenas para o papel de administrador Drupal.

## 5. Etapas

1. Registrar os plugins de UI Styles no tema, um por opção, com rótulo em
   português.
2. Gate que falha se uma opção apontar para classe sem variante no componente.
3. Captura no Firefox ESR, nos dois modos, em 320 px e 1440 px, de uma página
   de exemplo com cada opção aplicada.
4. Atualizar `CHANGELOG.md` e `roadmap.md`. A versão segue `docs/versioning.md`.

## 6. Pendências

- Decidir a regra de precedência entre o `accent` do Portal e a escolha do
  administrador na faixa de cartão.
- Confirmar o número de versão (provavelmente MINOR) em `docs/versioning.md`.
- Medir a faixa de cores por purpose sobre botões, quando a fiação estiver
  pronta (ver `docs/botoes.md`, seção 5).
