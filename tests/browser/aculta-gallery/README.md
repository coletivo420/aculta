# Galeria ACULTA

Revisão Firefox ESR visível no servidor de teste https://aculta.toca.net.br.
O script usa UID 1 por link único, abre um rascunho descartável da Revista
através do host principal, aciona o diálogo Gallery sem enviar formulário e
mede álbuns públicos nos hosts principal e do Fórum. Screenshots e resumo ficam fora do repositório em
SUITE_OUT.

Execute com:

    SUITE_OUT=/caminho/fora-do-repositorio/aculta-gallery \
    ACULTA_PROJECT_ROOT=/home/piradopirata/projetos/aculta \
    SUITE_NODE_ID=<nid-descartavel> DISPLAY=:0 \
    python3 -I tests/browser/aculta-gallery/visual.py

O script não salva a galeria nem edita o rascunho. O chamador deve remover o
rascunho temporário ao terminar. Os álbuns públicos usados são fixtures já
existentes no Runtime; o teste não altera Fórum, Revista ou galerias.


O teste também valida que a barra administrativa top-bar mantém suas ações
visíveis em cada viewport; essa parte é regressão da integração mobile-first do
tema ACULTA420 no contexto de uma galeria.
