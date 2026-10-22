# Regras específicas — consulta sob demanda

Estas regras foram realocadas do arquivo histórico de instruções; aplique somente no escopo correspondente. O `AGENTS.md` raiz continua obrigatório.

## Front-end

Tema público atual:

ACULTA420

Machine name:

aculta420

Provider funcional:

web/themes/custom/aculta420/

Não recriar provider, alias, shim ou camada de compatibilidade de tema com machine name diferente de `aculta420`.

Prioridades:

- mobile first
- responsividade
- acessibilidade
- HTML semântico
- boa performance
- CSS organizado
- JavaScript mínimo
- compatibilidade com Drupal
- SEO técnico
- facilidade de manutenção

Evitar page builders pesados.

Evitar dependências JavaScript desnecessárias.

### Orientações mobile-first

- Trate mobile-first como estratégia de implementação: a base CSS deve ser útil
  na menor largura suportada, e regras para mais espaço devem ser acrescentadas
  progressivamente quando o conteúdo justificar. Não basta reduzir ou empilhar
  automaticamente uma interface desktop.
- Use os breakpoints existentes do Bootstrap 5 como referências compartilhadas
  (`sm` 576 px, `md` 768 px, `lg` 992 px, `xl` 1200 px, `xxl` 1400 px). Eles não
  representam modelos específicos de aparelho; escolha a faixa pelo momento em
  que o conteúdo ou a tarefa pede uma mudança. CSS Grid, Flexbox e container
  queries podem responder ao espaço real do componente quando forem adequados.
- Não converta mecanicamente `max-width` em `min-width` nem substitua regras
  locais por um arquivo responsivo genérico. Audite seletores, cascata, wrappers,
  bibliotecas carregadas, templates, estados, conteúdo real e consumidores antes
  de editar; mantenha cada regra no arquivo responsável documentado em
  `web/themes/custom/aculta420/docs/development.md`.
- Em páginas comuns, priorize reflow sem perda de conteúdo ou operação em 320 px
  CSS, inclusive com zoom. Rolagem bidimensional só pode ficar confinada a
  conteúdo cuja estrutura realmente a exija (por exemplo, uma tabela comparativa),
  sem causar rolagem horizontal da página. Preserve zoom e redimensionamento de
  texto; mire WCAG 2.2 AA e, para controles frequentes, alvos de toque de 44 × 44
  px sempre que o layout permitir.
- Preserve todas as operações, permissões e estados disponíveis em telas largas.
  Simplifique a navegação por apresentação progressiva, sem esconder destinos ou
  inventar telas/funções. Links continuam destinos reais; aprimoramentos AJAX
  mantêm URL, histórico, teclado, foco, anúncio de estado, erros recuperáveis e
  fallback funcional sem JavaScript. Não descarte formulários ou dados não salvos
  ao trocar de contexto.
- Formulários, editores, diálogos, menus e barras de ação devem continuar
  utilizáveis com teclado virtual, zoom, teclado físico e leitor de tela. Ações
  persistentes não podem cobrir conteúdo ou foco; movimento respeita
  `prefers-reduced-motion`; cor não é o único indicador de estado.
- Claro e escuro mantêm a mesma geometria, hierarquia, navegação e comportamento;
  somente tokens visuais podem variar. O tema apresenta o contrato neutro e não
  passa a decidir acesso, domínio ou regra de negócio para adaptar a interface.
- Ao alterar componente responsivo, registre evidência por viewport e interação
  aplicáveis. Use 320, 375/390, 768, 1024 e 1440 px como matriz inicial, não como
  declaração automática de cobertura. Confira ausência de overflow da página,
  foco, estados, zoom, modos claro/escuro e consumidores afetados; diferencie
  validação local de Runtime de teste e de dispositivo real.
- No ACULTA Studio, consulte primeiro o contrato e o roadmap do Studio. Preserve
  seus três níveis, URLs reais, acesso concedido pelo dono e fallback sem JS.
  Mobile pode mostrar uma escolha contextual por vez; desktop pode manter
  navegações simultâneas quando aumentarem a produtividade. Não criar área,
  ação ou estado que o módulo proprietário ainda não oferece.

Referências normativas e de implementação: [Bootstrap 5.3 — breakpoints e
abordagem mobile-first](https://getbootstrap.com/docs/5.3/layout/breakpoints/),
[Bootstrap 5.3 — grid](https://getbootstrap.com/docs/5.3/layout/grid/) e [W3C —
WCAG 2.2, critério 1.4.10 Reflow](https://www.w3.org/WAI/WCAG22/Understanding/reflow.html).

## Direção de arte para imagens criadas por agentes

- Toda imagem criada por um agente para este projeto deve considerar a identidade da ACULTA como associação cultural canábica e antiproibicionista; quando a imagem representar a comunidade, a conquista ou a participação no Portal, a relação com cannabis, cultura, direitos ou superação da proibição deve ser reconhecível.
- Usar enquadramento cultural, comunitário, educativo e de direitos. Não representar o Portal como dispensário, loja de cannabis ou campanha de consumo.
- Antes de criar, inspecionar os assets próximos e preservar as imagens históricas ou fornecidas pelo responsável. Registrar origem, intenção e dimensões das novas artes na documentação do componente.
- Para ilustração raster nova, usar a ferramenta imagegen; redimensionar e otimizar a saída para o uso real. Preferir arte original sem texto incorporado, salvo quando o texto exato fizer parte do pedido.
- Consultar `docs/portal/IMAGE-ART-DIRECTION.md` para a paleta e a linguagem visual das conquistas.

## Imagens e arquivos de imagem (regra permanente)

- Toda imagem e todo arquivo de imagem do site passa pelo submódulo `aculta_image`
  (`web/modules/custom/aculta_portal/modules/aculta_image/`). Vale para código novo e antigo; código existente
  fora dele deve ser refatorado.
- Não usar `image_style` (renderização, referência ou configuração) fora do catálogo `ImageCatalog`. Pedir uma
  finalidade, não um estilo.
- Não criar limite de upload de imagem próprio: usar `UploadPolicy`. A exceção aprovada da conquista (aculta_game)
  fica só no formulário da conquista.
- Não processar imagem com `ImageFactory` nem excluir arquivos por fora do `aculta_image`. Imagens candidatas à limpeza do acervo seguem a quarentena; no ciclo normal, `aculta_image` pode excluir arquivo substituído/removido de entidade somente após revalidar que `file_usage` e `entity_usage` não apontam para ele. Temporários de imagem sem uso vencem segundo `system.file:temporary_maximum_age`.
- Campo novo de imagem nasce com a política do `aculta_image` em `third_party_settings`.
- Importação de qualquer origem externa (Wayback, LMS, arquivo recebido) passa pela ingestão do `aculta_image`.
- Gate: `php scripts/validate-aculta-image.php` (tolerância zero). Plano e fases: `web/modules/custom/aculta_portal/modules/aculta_image/docs/PLANO.md`.

## Ícones de interface: Bootstrap Icons por padrão (regra permanente)

- Ícones de interface usam os **Bootstrap Icons** por padrão (`<i class="bi bi-…">`, já carregados pelo bootstrap5 do tema).
- Outro pacote de ícones ou SVG de interface só com exceção registrada no próprio componente, com o motivo e a origem documentados.
- Logos de marcas de terceiros (ex.: Mercado Pago) ficam em `web/themes/custom/aculta420/assets/brands/`, com `alt` do nome da marca.
