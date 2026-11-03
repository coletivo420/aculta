# Studio Visual P1 - estruturas pessoais e editoriais

## Implementação P1.1/P1.2 e estado P1.3 - 2026-11-03

P1.1 e a implementação local de P1.2 estão em código: dashboard de Conta, identidade provider-backed, shell Grid dedicado e navegação AJAX própria. Preserva Form API e owners. Focais Studio Unit 65/631 PASS. P1.2 ainda não está homologada no browser/Runtime nem cobre a matriz de formulários após trocas, falha/sessão, modo escuro, zoom e resize. Marley mantém listagem, ação abaixo do título e moderação condicional owner-backed; confirmar visualmente no Runtime.


**Tipo:** plano documental para execução incremental.  
**Base:** branch `dev`; consultar [Plano documental visual geral](ACULTA-STUDIO-VISUAL-PLANNING.md).  
**Escopo desta trilha:** Studio Dashboard/entrada da Conta, Minha Conta, Meu Perfil e ACULTA Studio Marley.  
**Fora desta trilha:** Ganjalab, WE Sativa, Culturas, Green Stash e alterações de grants/dados. Não iniciar implementação dessas experiências com este plano.

## Fontes que governam o trabalho

- [ADR-038 - Suites por domínio](../decisions/ADR-038-aculta-studio-suites-por-dominio.md): host, purpose e paths.
- [Identidade e rotas](ACULTA-STUDIO-IDENTITY.md): Conta/Perfil e nomes.
- [Fronteiras entre experiências](ACULTA-STUDIO-EXPERIENCE-BOUNDARIES.md): ownership e escopo Marley.
- [Conceito visual geral](STUDIO-VISUAL-CONCEPT.md): padrões comuns, após reconciliar o texto histórico de rotas.
- [Roadmap do aculta_studio](../../web/modules/custom/aculta_portal/modules/aculta_studio/docs/ROADMAP.md): fases OCS/THEME e estado de validação.
- [Tokens visuais](../../web/themes/custom/aculta420/css/tokens.css) e [identidade Studio](../../web/themes/custom/aculta420/css/components/studio-identity.css).
- [Roadmap da Revista](STUDIO-MAGAZINE-ROADMAP.md) e [fluxos editoriais](STUDIO-EDITOR-FLOWS.md).

Se o HEAD divergir destes documentos, o agente registra a divergência e atualiza o dono da decisão. Não escolhe uma rota pela imagem.

## Contrato de identidade visual

Usar os tokens semânticos ACULTA420: fundo claro verde suave `#f2f7f0`, superfícies brancas, creme `#fbf4e8`, texto verde escuro `#0c3c29`, verde médio `#689427`, amarelo `#f2ca36` em ação/seleção e vermelho `#d4452d` em semântica apropriada. Tipografia institucional: Oswald nos títulos e Inter na interface. Consultar tokens em claro/escuro; não gravar hex nos componentes.

O shell inclui barra multidomínio global. A interface interna deve apresentar navegação contextual da tela sem duplicar a barra global, nem misturar o menu institucional público com a navegação de trabalho. Manter separação visual e semântica entre Minha Conta (dados privados), Meu Perfil (identidade pública) e Marley (workspace editorial).

## Matriz desta trilha

| Estrutura | Conteúdo/ação | Regra visual e funcional |
|---|---|---|
| Studio Dashboard / raiz da Conta | Identidade curta e cartões para destinos autorizados | Cartões vêm dos providers reais; só mostrar experiências e links autorizados. Sem métricas fictícias. |
| Minha Conta | Dados cadastrais, endereço, documentos, e-mail/segurança, senha, conexões, pedidos, apoio e preferências conforme disponibilidade | Navegação de conta e formulários privados; manter owners/Form API atuais e Conta legada. |
| Meu Perfil | Apelido público, biografia, redes e visibilidade conforme providers ativos | Identidade pública independente. Não exibir documentos/endereço privados. |
| Marley | Revista, moderação do Fórum e Galerias, conforme tabs/providers e permissões | Remover item lateral redundante “Meu trabalho”; tabela/listagem existente é a vista de trabalho. “Criar” como botão abaixo do título; “Comentários e moderação” como aba ao lado das demais, conforme decisão UX registrada. Não inserir Cursos/Wiki geral no Marley. |

**Nota de precisão:** antes de codificar cada cartão/aba, confirmar o destino no registry e o access atual. Uma experiência indisponível ou sem permissão deve ser omitida segundo o contrato existente; não criar URL ou grant para fazê-la aparecer.

## Microfases executáveis

### P1.0 - Reconciliação e baseline

- Confirmar HEAD de `dev`, árvore de trabalho remota e rotas/provider no código.
- Corrigir/documentar conflitos de rota e narrativa no conceito visual, identidade e roadmap. Preservar decisões históricas com rótulo explícito.
- Capturar baseline Runtime para Dashboard/Conta, Perfil e Marley em desktop e mobile, com role autorizada; salvar URLs, viewports e estado de dados.
- Registrar quais tokens/SDCs/cascata realmente renderizam e se `aculta420.studio_identity` está ativa.
- Saída: matriz de rotas/owners/roles e pacote de screenshots baseline.

### P1.1 - Studio Dashboard

- Desenhar/ajustar cards a partir dos providers de experiência. Cada card inclui nome e chamada objetiva; acessibilidade de link inteiro; estados indisponíveis conforme regra do provider.
- Identidade resumida é opcional e só deve usar dados já fornecidos com cache correto. “Meu Perfil” e “Minha Conta” têm destinos distintos.
- Não adicionar “atividade recente”, calendário, KPIs, cursos ativos ou contadores sem fonte e API confirmadas.
- Testar barra multidomínio em desktop/mobile e links absolutos cross-purpose.
- Atualizar contrato de provider/tema se a composição exigir novo dado; não criar consulta paralela no controller.

### P1.2 - Minha Conta e Meu Perfil

- Tratar Conta e Perfil como jornadas distintas e manter Conta legada para comparação/retorno onde o projeto define.
- Aplicar apresentação do tema aos formulários Drupal existentes, preservando validação, AJAX e Form API.
- Confirmar que campos privados permanecem privados e links para Perfil público não revelam dados sensíveis.
- Verificar estados de erro, pendências de confirmação, foto/biografia e ausência de módulos opcionais.
- Atualizar docs de Conta/Perfil e roadmap somente com evidência correspondente.

### P1.3 - Marley editorial

- Comparar tela atual com o pedido UX já registrado: retirar duplicação “Meu trabalho” da navegação lateral; manter a listagem/tabela como destino; mover Criar para botão abaixo do título; converter Comentários e moderação em aba.
- Não alterar grants nem ownership. A aba de moderação permanece condicionada à permissão do owner Fórum/UGC; Revista e Galerias permanecem em seus owners.
- Manter distinção dos estados de conteúdo e controles de revisão/publicação fornecidos pelo módulo Magazine.
- Preparar desktop tabela e mobile cards, sem cortar colunas nem perder ações acessíveis.
- Registrar separadamente itens ainda ausentes, especialmente painel administrativo da Revista, para não insinuar que já estão implementados.

### P1.4 - Implementação e validação

- Implementar em mudanças pequenas e isoladas, reaproveitando tokens/SDCs e os atributos JS existentes.
- Para navegação AJAX, confirmar URL, pushState/popstate, título, menu ativo, foco e anúncio; fallback sem JS; comportamento depois de anexar behaviors; falha/retry.
- Validar roles autorizada e não autorizada, purposes certos/errados, formulários sem submit destrutivo, estados vazios e dados longos.
- Cobrir desktop/mobile, claro/escuro, teclado, foco, zoom/reflow e ausência de overflow segundo os gates existentes.
- Atualizar screenshots de destino, roadmap e relatório de validação; separar teste automatizado de homologação Runtime.

## Critérios de aceite

A trilha pessoal/editorial só fecha quando:

1. Rotas, owners e destinos estiverem confirmados no HEAD.
2. A barra multidomínio real permanecer consistente, inclusive navegação entre purposes.
3. Conta privada, Perfil público e Conta legada não forem fundidos.
4. Dashboard não exibir blocos inventados.
5. Marley refletir a decisão UX sem duplicação de “Meu trabalho” e com aba de comentários/moderação autorizada.
6. Cores vierem dos tokens ACULTA420, com temas claro/escuro e foco legível.
7. AJAX, Form API, fallback, teclado e mobile estiverem verificados no Runtime apropriado.
8. Documentação e roadmap indicarem exatamente o que foi implementado, testado e homologado.

## Referências visuais

As imagens geradas anteriormente ajudam a discutir composição, mas contêm conteúdo fictício e erros de texto. A prancha pessoal/editorial tem erro de marca “ACULTA Drupal Studio”; não reutilizar essa marca. Gerar/atualizar capturas de referência com logo, rótulos e conteúdo corretos antes de tratá-las como alvo de implementação. Referências conceituais: [prancha Dashboard/Conta/Perfil/Marley](https://chatgpt.com/library?file_id=libfile_60aecd549e64819190d243cb41ea9b87&account_id=personal) e [prancha geral com barra multidomínio](https://chatgpt.com/library?file_id=libfile_7735ab10eae88191b49c9ba339cca5b0&account_id=personal).
