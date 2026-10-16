import * as pdfjsLib from '/libraries/pdf.js/build/pdf.mjs';

pdfjsLib.GlobalWorkerOptions.workerSrc = '/libraries/pdf.js/build/pdf.worker.mjs';

const initialized = new WeakSet();

async function initialize(viewer) {
  if (initialized.has(viewer)) {
    return;
  }
  initialized.add(viewer);

  const url = viewer.dataset.acultaPdfUrl;
  const canvas = viewer.querySelector('[data-aculta-pdf-control="page"]');
  const status = viewer.querySelector('[data-aculta-pdf-control="status"]');
  const previous = viewer.querySelector('[data-aculta-pdf-control="previous"]');
  const next = viewer.querySelector('[data-aculta-pdf-control="next"]');
  if (!url || !url.startsWith('/') || url.startsWith('//') || !canvas || !status) {
    return;
  }

  try {
    const pdf = await pdfjsLib.getDocument({ url }).promise;
    let pageNumber = 1;
    const render = async () => {
      const page = await pdf.getPage(pageNumber);
      const baseViewport = page.getViewport({ scale: 1 });
      const scale = Math.max(0.5, Math.min(1.5, (viewer.clientWidth || 800) / baseViewport.width));
      const viewport = page.getViewport({ scale });
      const context = canvas.getContext('2d');
      canvas.width = Math.ceil(viewport.width);
      canvas.height = Math.ceil(viewport.height);
      await page.render({ canvasContext: context, viewport }).promise;
      status.textContent = `Página ${pageNumber} de ${pdf.numPages}`;
      previous.disabled = pageNumber <= 1;
      next.disabled = pageNumber >= pdf.numPages;
    };
    previous.addEventListener('click', () => {
      if (pageNumber > 1) {
        pageNumber -= 1;
        render();
      }
    });
    next.addEventListener('click', () => {
      if (pageNumber < pdf.numPages) {
        pageNumber += 1;
        render();
      }
    });
    await render();
  }
  catch {
    status.textContent = 'Não foi possível exibir este PDF. Use o link para abrir o arquivo.';
  }
}

function attach(context = document) {
  context.querySelectorAll('[data-aculta-pdf-viewer="true"]').forEach(initialize);
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => attach(), { once: true });
}
else {
  attach();
}

if (window.Drupal?.behaviors) {
  window.Drupal.behaviors.acultaPdfViewer = {
    attach,
  };
}
