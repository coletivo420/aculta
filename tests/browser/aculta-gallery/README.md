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

Para avaliar uma regra CSS ainda não publicada no Runtime, SUITE_THEME_CSS pode
apontar para a folha local. O script injeta o conteúdo somente no documento
atual do Firefox, sem alterar o servidor; esse resultado é prototipagem e não
substitui repetir o teste após o cache rebuild do candidato.

`SUITE_SKIP_EDITOR=1` omite somente o fluxo de diálogo e mantém login e álbum
público, para isolar regressões da top-bar sem depender de uma fixture editorial
editável. `SUITE_LOGIN_URL` pode receber um link único gerado pelo Drush do
checkout do servidor de teste; o script captura esse valor sem imprimi-lo.
