/** @type { import('@storybook/server').StorybookConfig } */
const config = {
  stories: [
    '../docs/**/*.mdx',
    '../../web/themes/custom/aculta420/components/**/*.stories.json',
  ],
  addons: ['@storybook/addon-docs'],
  framework: {
    name: '@storybook/server',
    options: {},
  },
  core: {
    builder: '@storybook/builder-vite',
  },
  docs: {
    autodocs: 'tag',
  },
};

export default config;
