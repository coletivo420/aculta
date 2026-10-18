/** @type { import('@storybook/server').Preview } */
const preview = {
  parameters: {
    server: {
      // Storybook fetches rendered HTML from Drupal in the preview iframe.
      // Include the administrator session cookie; Drupal still enforces the
      // `render storybook stories` permission on every request.
      fetchStoryHtml: async (url, path, params) => {
        let renderEndpoint = url;
        if (!renderEndpoint) {
          const localDrupalUrl = window.localStorage.getItem('aculta420-storybook-drupal-url');
          if (!localDrupalUrl) {
            throw new Error('Set aculta420-storybook-drupal-url in localStorage to your Drupal development URL.');
          }
          renderEndpoint = `${new URL(localDrupalUrl).origin}/storybook/stories/render`;
        }
        const storyUrl = new URL(`${renderEndpoint.replace(/\/$/, '')}/${path}`);
        storyUrl.search = new URLSearchParams(params).toString();
        const response = await fetch(storyUrl, {
          credentials: 'include',
          headers: { Accept: 'text/html' },
        });
        if (!response.ok) {
          throw new Error(`Drupal Storybook render failed (${response.status}).`);
        }
        return response.text();
      },
    },
    docs: {
      toc: true,
    },
    options: {
      storySort: {
        order: [
          'Comece aqui',
          'Sistema visual',
          'Arquitetura',
          'Acessibilidade',
          'Desenvolvimento',
          'IA e agentes',
          'Componentes',
        ],
      },
    },
  },
};

export default preview;
