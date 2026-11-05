# Configuração administrativa do tema (V2)

Opções que o administrador vê em **Aparência → ACULTA420 V2 → Configurações**.
Documenta o que existe, o que não é do tema e o que falta.

## 1. Estado

**B1 implementado.** Existem:

- `theme-settings.php`: formulário com a seção "Superfícies".
- `src/Hook/SurfaceSettingsHooks.php`: aplica classes neutras no `<body>` sem
  lógica de domínio.
- `config/schema/aculta420_v2.schema.yml`: schema de `aculta420_v2.settings`.
- `config/install/aculta420_v2.settings.yml`: valores padrão.

As opções são salvas em `aculta420_v2.settings` e ligadas a classes neutras no
`<body>`. A migração dos consumidores CSS e a captura visual continuam
pendentes.

## 2. Opções atuais

| Chave | Tipo | Padrão | Valores | Aplica a |
| --- | --- | --- | --- | --- |
| `aculta_radius_button` | select | `lg` | `base` (8px), `lg` (16px), `full` (pastilha) | botões |
| `aculta_radius_card` | select | `lg` | `base` (8px), `lg` (16px) | cartões e blocos |
| `aculta_radius_field` | select | `base` | `sm` (4px), `base` (8px) | campos de formulário |
| `aculta_accent_override` | select | `mapping` | `mapping` (padrão: pelo tipo de conteúdo), `green`, `yellow`, `red` (forçam uma cor para todos) | cor da faixa de acento |
| `aculta_show_accent_stripe` | boolean | `true` | sim, não | faixa de acento dos cartões de área |

`aculta_accent_override` só fica ativo com a faixa visível. Com `mapping`, o tema
usa o valor `accent` que o Portal envia, por tipo (`docs/superficies.md`, seção 8.3).
Com uma cor fixa, o tema ignora o `accent` do Portal. Essa escolha desfaz a
diferença entre níveis vizinhos e deve ser usada só por pedido do responsável.
Não há opção de "sem faixa" aqui: para isso, use `aculta_show_accent_stripe`.

Os valores seguem `docs/superficies.md`, seção 1.

## 3. O que não é do tema

Pela regra de fronteiras (AGENTS, seção 2 e 3), estas opções **não ficam no tema**:

- **Modo de cor (claro e escuro):** é preferência do Portal (`aculta_color_mode`,
  tratada em `ColorModeHooks`). Um padrão do site, se for pedido, vem do Portal.
- **Domínio, purpose e regra de acesso:** pertencem ao Portal.
- **Cor da faixa de acento por área:** o mapeamento purpose → cor (ver
  `docs/superficies.md`, seção 8.3) deve vir do Portal como valor neutro
  (`accent: green|yellow|red`). O tema só traduz esse valor em token.
  Isso ainda não existe no contrato `domain_presentation`.
- **Credenciais e integrações:** nunca em configuração de tema (AGENTS, seção 2).

## 4. Próximos passos

1. Classes neutras `aculta-radius--*` e `aculta-accent--*` são aplicadas no
   `<body>` por `SurfaceSettingsHooks::preprocessHtml`, sem lógica de domínio.
2. Criar as variáveis `--radius-control-button`, `--radius-control-card` e
   `--radius-control-field` em `src/styles/tokens.css`, com um valor por classe.
3. Trocar o uso de `--radius-base` nos componentes (botão, cartão, campo) pelas
   novas variáveis.
4. Incluir `--accent-*-stripe` (ver `docs/superficies.md`, seção 8.2).
5. Capturas nos dois modos e gates (`docs/gates.md`), no Homelab.
6. Exportar a configuração com `drush cex` para `config/sync` antes do RC.
   `config:status` está limpo; nenhuma alteração no Runtime ou export foi
   necessária nesta entrega.

## 5. Outras definições que faltam

Além das opções acima, estas definições ainda não existem na estrutura do tema:

- **Tokens de faixa de acento** (`--accent-green-stripe`, `--accent-yellow-stripe`,
  `--accent-red-stripe`), com os valores de `docs/superficies.md` seção 8.2.
- **Tokens de bloco de destaque** (`--color-surface-highlight`, `--color-border-highlight`),
  com os valores de `docs/superficies.md` seção 3.3.
- **Contrato `accent`** no `domain_presentation`, a pedir ao Portal.
- **Escala de espaçamento** (`--spacing-*`): os nomes são usados, mas a escala não está
  documentada em um lugar só.
- **Componente de faixa de acento** (propriedade `accent` no SDC do cartão), ainda não criado.
- **Padrão de cartão editorial** (imagem no topo, kicker, título, subtítulo e texto),
  pela referência de 2026-11-05.
- **Página de estilos de referência** (inventário de botões, cartões e blocos nos dois modos),
  útil para captura e revisão.

## 6. Versão

Nenhuma versão foi marcada para este scaffold. A marcação (`info.yml`, `CHANGELOG.md`)
só vem na entrega validada (AGENTS, seção 5).
