# Baque Sativa — kit de imagens para ACULTA420

Artes oficiais recebidas: texto **amarelo** e texto **vermelho**. Ambas são verticais e transparentes; o desenho original (percussão, tambor, correntes, lettering) foi preservado.

## Organização
- `source/`: originais em 1024×1024 px; não alterar.
- `web/*-master.png|webp`: originais recortados com margem de segurança de ~2,5%.
- `web/*-hN.png|webp`: altura N px, largura proporcional, sem distorção.
- `web/*-square-N.png|webp`: composição inteira centralizada em canvas quadrado N×N com transparência; **não** corta o tambor ou texto.
- `manifest.json`: dimensões, tamanhos e hashes SHA-256 dos arquivos.

## Recomendações
- Uso editorial, projeto, cards, grids de projetos, perfis, galeria, material de divulgação; **não utilizar como logo do cabeçalho global**.
- Amarelo e vermelho são **duas variantes de marca**, não modificações light/dark automáticas. Não escolher a variante por domínio, hostname ou modo de cor sem decisão de design aprovada.
- Para cards quadrados usar `square-256` ou `square-512`. Para layout vertical usar `h384`, `h512`, `h768` ou master, conforme o espaço. A marca vertical não deve ser esticada para ocupar formato horizontal.
- PNG preserva transparência; WebP lossless é alternativa otimizada. Dimensionar o elemento responsivamente (`max-width:100%`, `height:auto`) e indicar width/height no HTML ou render array.
- Usar alt contextual, por exemplo `Logomarca do Grupo de Percussão Baque Sativa`, quando transmitir identidade; alt vazio apenas se estritamente decorativa e houver texto redundante.
- Capas, fotografias e imagens editoriais dinâmicas devem ser geridas por Drupal Media/Image Styles, não copiadas para o tema.

## Publicação
Kit instalado em `web/themes/custom/aculta420/assets/branding/baque-sativa/` (`source/`, `web/`, `README.md`, `manifest.json`). Documentação de uso em `web/themes/custom/aculta420/docs/branding-baque-sativa.md`; verificação com `python3 scripts/verify-baque-sativa-assets.py`.
