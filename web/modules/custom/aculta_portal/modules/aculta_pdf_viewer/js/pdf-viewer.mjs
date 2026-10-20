// Keep this value in sync with the mozilla/pdfjs-dist version pinned in
// composer.json. Query versioning prevents a year-long cache from pairing a
// stale API module with a freshly fetched worker (or the reverse).
const PDFJS_VERSION = '6.4.299';
// Keep a single page canvas below common browser surface limits and avoid
// allocating hundreds of megabytes for unusually large PDF page dimensions.
const MAX_CANVAS_PIXELS = 16_000_000;
const MAX_CANVAS_DIMENSION = 16_384;
const MIN_SAFE_SCALE_RATIO = 0.5;

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
  const zoomOut = viewer.querySelector('[data-aculta-pdf-control="zoom-out"]');
  const zoomIn = viewer.querySelector('[data-aculta-pdf-control="zoom-in"]');
  const zoomReset = viewer.querySelector('[data-aculta-pdf-control="zoom-reset"]');
  const zoomStatus = viewer.querySelector('[data-aculta-pdf-control="zoom-status"]');
  const fullscreen = viewer.querySelector('[data-aculta-pdf-control="fullscreen"]');
  const searchInput = viewer.querySelector('[data-aculta-pdf-control="search-input"]');
  const searchSubmit = viewer.querySelector('[data-aculta-pdf-control="search-submit"]');
  const searchPrevious = viewer.querySelector('[data-aculta-pdf-control="search-previous"]');
  const searchNext = viewer.querySelector('[data-aculta-pdf-control="search-next"]');
  const searchStatus = viewer.querySelector('[data-aculta-pdf-control="search-status"]');
  const outline = viewer.querySelector('[data-aculta-pdf-control="outline"]');
  const outlineItems = viewer.querySelector('[data-aculta-pdf-control="outline-items"]');
  const outlineStatus = viewer.querySelector('[data-aculta-pdf-control="outline-status"]');
  if (!url || !url.startsWith('/') || url.startsWith('//') || !canvas || !status) {
    return;
  }

  try {
    const pdfjsLib = await import(`/libraries/pdf.js/build/pdf.mjs?v=${PDFJS_VERSION}`);
    pdfjsLib.GlobalWorkerOptions.workerSrc = `/libraries/pdf.js/build/pdf.worker.mjs?v=${PDFJS_VERSION}`;
    const pdf = await pdfjsLib.getDocument({ url }).promise;
    let pageNumber = 1;
    const fitToWidth = viewer.dataset.acultaPdfDefaultZoom === 'fit-width';
    const initialZoom = fitToWidth ? 1 : viewer.dataset.acultaPdfDefaultZoom === '125' ? 1.25 : 1;
    let zoom = initialZoom;
    let rotation = 0;
    let renderSequence = 0;
    let renderTask;
    let searchSequence = 0;
    let matchingPages = [];
    let matchingPageIndex = -1;

    if (outline && outlineItems && outlineStatus) {
      const entries = await pdf.getOutline();
      if (entries?.length) {
        outlineStatus.textContent = '';
        let entryCount = 0;
        const appendEntries = (parent, items, depth = 0) => {
          if (depth > 20 || entryCount >= 500) return;
          for (const entry of items) {
            if (entryCount >= 500) break;
            entryCount += 1;
            const item = document.createElement('li');
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = entry.title || `Seção ${entryCount}`;
            button.addEventListener('click', async () => {
              try {
                let destination = entry.dest;
                if (typeof destination === 'string') destination = await pdf.getDestination(destination);
                if (!Array.isArray(destination) || !destination[0]) return;
                const pageIndex = await pdf.getPageIndex(destination[0]);
                goToPage(pageIndex + 1);
              }
              catch {
                outlineStatus.textContent = 'Não foi possível abrir esta seção do contorno.';
              }
            });
            item.append(button);
            if (Array.isArray(entry.items) && entry.items.length) {
              const nested = document.createElement('ul');
              item.append(nested);
              appendEntries(nested, entry.items, depth + 1);
            }
            parent.append(item);
          }
        };
        appendEntries(outlineItems, entries);
        if (entryCount >= 500) outlineStatus.textContent = 'Contorno truncado após 500 itens.';
      }
      else {
        outline.hidden = true;
      }
    }

    const render = async () => {
      const sequence = ++renderSequence;
      if (renderTask) {
        const previousTask = renderTask;
        previousTask.cancel();
        try {
          await previousTask.promise;
        }
        catch (error) {
          if (error.name !== 'RenderingCancelledException') {
            throw error;
          }
        }
        if (sequence !== renderSequence) {
          return;
        }
        renderTask = undefined;
      }
      const page = await pdf.getPage(pageNumber);
      if (sequence !== renderSequence) {
        return;
      }
      const baseViewport = page.getViewport({ scale: 1, rotation });
      const availableWidth = Math.max(240, (viewer.clientWidth || 800) - 32);
      const fitScale = Math.min(1.5, availableWidth / baseViewport.width);
      const requestedScale = Math.min(3, fitToWidth ? fitScale * zoom : zoom);
      const maxScaleByArea = Math.sqrt(MAX_CANVAS_PIXELS / (baseViewport.width * baseViewport.height));
      const maxScaleByDimension = (MAX_CANVAS_DIMENSION - 1) / Math.max(baseViewport.width, baseViewport.height);
      let renderScale = Math.min(requestedScale, maxScaleByArea, maxScaleByDimension);
      let viewport = page.getViewport({ scale: renderScale, rotation });
      let canvasWidth = Math.ceil(viewport.width);
      let canvasHeight = Math.ceil(viewport.height);
      // Rounding both sides up can exceed the pixel budget by a few thousand
      // pixels even when the floating-point viewport is exactly at the limit.
      while (canvasWidth * canvasHeight > MAX_CANVAS_PIXELS) {
        renderScale *= Math.sqrt(MAX_CANVAS_PIXELS / (canvasWidth * canvasHeight)) * 0.999;
        viewport = page.getViewport({ scale: renderScale, rotation });
        canvasWidth = Math.ceil(viewport.width);
        canvasHeight = Math.ceil(viewport.height);
      }
      const effectiveZoom = fitToWidth ? renderScale / fitScale : renderScale;
      if (effectiveZoom < zoom * MIN_SAFE_SCALE_RATIO) {
        canvas.hidden = true;
        status.textContent = 'Esta página excede o limite seguro de visualização. Use o link para abrir ou baixar o PDF.';
        previous.disabled = pageNumber <= 1;
        next.disabled = pageNumber >= pdf.numPages;
        zoomStatus.textContent = `${Math.round(effectiveZoom * 100)}%`;
        zoomOut.disabled = zoom <= 0.5;
        zoomIn.disabled = zoom >= 2.5;
        return;
      }
      const context = canvas.getContext('2d');
      canvas.width = canvasWidth;
      canvas.height = canvasHeight;
      const task = page.render({ canvasContext: context, viewport });
      renderTask = task;
      try {
        await task.promise;
      }
      catch (error) {
        if (error.name === 'RenderingCancelledException') {
          return;
        }
        throw error;
      }
      if (sequence !== renderSequence) {
        return;
      }
      renderTask = undefined;
      canvas.hidden = false;
      status.textContent = `Página ${pageNumber} de ${pdf.numPages}`;
      previous.disabled = pageNumber <= 1;
      next.disabled = pageNumber >= pdf.numPages;
      zoomStatus.textContent = `${Math.round(effectiveZoom * 100)}%`;
      zoomOut.disabled = zoom <= 0.5;
      zoomIn.disabled = zoom >= 2.5;
    };

    const goToPage = (target) => {
      pageNumber = Math.max(1, Math.min(pdf.numPages, target));
      render().catch(() => {
        status.textContent = 'Não foi possível renderizar esta página do PDF.';
      });
    };

    previous.addEventListener('click', () => goToPage(pageNumber - 1));
    next.addEventListener('click', () => goToPage(pageNumber + 1));
    zoomOut.addEventListener('click', () => {
      zoom = Math.max(0.5, zoom - 0.25);
      render().catch(() => {
        status.textContent = 'Não foi possível ajustar a visualização do PDF.';
      });
    });
    zoomIn.addEventListener('click', () => {
      zoom = Math.min(2.5, zoom + 0.25);
      render().catch(() => {
        status.textContent = 'Não foi possível ajustar a visualização do PDF.';
      });
    });
    zoomReset.addEventListener('click', () => {
        zoom = initialZoom;
      render().catch(() => {
        status.textContent = 'Não foi possível restaurar o zoom do PDF.';
      });
    });
    viewer.querySelector('[data-aculta-pdf-control="rotate"]')?.addEventListener('click', () => {
      rotation = (rotation + 90) % 360;
      render().catch(() => {
        status.textContent = 'Não foi possível girar a página do PDF.';
      });
    });

    const updateFullscreenLabel = () => {
      const isFullscreen = document.fullscreenElement === viewer;
      fullscreen.setAttribute('aria-pressed', isFullscreen ? 'true' : 'false');
      fullscreen.setAttribute('aria-label', isFullscreen ? 'Sair da tela cheia' : 'Abrir leitor em tela cheia');
      fullscreen.textContent = isFullscreen ? 'Sair da tela cheia' : 'Tela cheia';
      render().catch(() => {});
    };
    fullscreen.addEventListener('click', async () => {
      try {
        if (document.fullscreenElement === viewer) {
          await document.exitFullscreen();
        }
        else if (viewer.requestFullscreen) {
          await viewer.requestFullscreen();
        }
        else {
          status.textContent = 'Tela cheia não está disponível neste navegador.';
        }
      }
      catch {
        status.textContent = 'Não foi possível abrir o leitor em tela cheia.';
      }
    });
    viewer.addEventListener('fullscreenchange', updateFullscreenLabel);

    const selectSearchResult = (index) => {
      if (!matchingPages.length) {
        return;
      }
      matchingPageIndex = (index + matchingPages.length) % matchingPages.length;
      const targetPage = matchingPages[matchingPageIndex];
      searchStatus.textContent = `Resultado ${matchingPageIndex + 1} de ${matchingPages.length} — página ${targetPage}`;
      goToPage(targetPage);
    };
    const search = async () => {
      const sequence = ++searchSequence;
      const query = searchInput.value.trim().toLocaleLowerCase();
      matchingPages = [];
      matchingPageIndex = -1;
      searchPrevious.disabled = true;
      searchNext.disabled = true;
      if (!query) {
        searchStatus.textContent = 'Digite um termo para buscar no PDF.';
        return;
      }

      searchStatus.textContent = 'Buscando texto no PDF…';
      for (let page = 1; page <= pdf.numPages; page += 1) {
        if (sequence !== searchSequence) {
          return;
        }
        const pageData = await pdf.getPage(page);
        const text = await pageData.getTextContent();
        const pageText = text.items
          .filter((item) => typeof item.str === 'string')
          .map((item) => item.str)
          .join(' ')
          .toLocaleLowerCase();
        if (pageText.includes(query)) {
          matchingPages.push(page);
        }
        if (page % 20 === 0 || page === pdf.numPages) {
          searchStatus.textContent = `Buscando página ${page} de ${pdf.numPages}…`;
        }
      }
      if (sequence !== searchSequence) {
        return;
      }
      searchPrevious.disabled = matchingPages.length === 0;
      searchNext.disabled = matchingPages.length === 0;
      if (!matchingPages.length) {
        searchStatus.textContent = 'Nenhum resultado encontrado.';
        return;
      }
      selectSearchResult(0);
    };
    searchSubmit.addEventListener('click', () => {
      search().catch(() => {
        searchStatus.textContent = 'Não foi possível pesquisar o texto deste PDF.';
      });
    });
    searchInput.addEventListener('keydown', (event) => {
      if (event.key === 'Enter') {
        event.preventDefault();
        searchSubmit.click();
      }
    });
    searchPrevious.addEventListener('click', () => selectSearchResult(matchingPageIndex - 1));
    searchNext.addEventListener('click', () => selectSearchResult(matchingPageIndex + 1));
    window.addEventListener('resize', () => {
      render().catch(() => {});
    });

    await render();
  }
  catch {
    status.textContent = 'Não foi possível exibir este PDF. Use o link para abrir o arquivo.';
  }
}

function attach(context = document) {
  const selector = '[data-aculta-pdf-viewer="true"]';
  if (context instanceof Element && context.matches(selector)) {
    initialize(context);
  }
  context.querySelectorAll?.(selector).forEach(initialize);
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
