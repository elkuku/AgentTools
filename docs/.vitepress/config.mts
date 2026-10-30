import { defineConfig } from 'vitepress'

// Published on GitHub Pages at https://elkuku.github.io/AgentTools/ (with the dev APK and the
// test coverage next to it, see .github/workflows/symfony-pages.yml)
export default defineConfig({
  title: 'AgentTools',
  description: 'Save your Ingress agent stats and medals and see them in one place.',
  base: '/AgentTools/',
  lang: 'en-US',
  cleanUrls: true,
  lastUpdated: true,
  head: [['link', { rel: 'icon', href: '/AgentTools/favicon.ico' }]],
  // Files the Pages workflow adds after the build
  ignoreDeadLinks: [/^\/agenttools-dev\.apk$/, /^\/coverage\//],

  themeConfig: {
    logo: '/logo.svg',

    nav: [
      { text: 'Guide', link: '/guide/', activeMatch: '/guide/' },
      { text: 'Developers', link: '/developers/', activeMatch: '/developers/' },
      { text: 'Downloads', link: '/downloads' },
      { text: 'Website', link: 'https://agent-tools.elkuku.dev' },
    ],

    sidebar: {
      '/guide/': [
        {
          text: 'Guide',
          items: [
            { text: 'Getting started', link: '/guide/' },
            { text: 'The Android app', link: '/guide/app' },
            { text: 'The website', link: '/guide/web' },
            { text: 'Privacy', link: '/guide/privacy' },
          ],
        },
      ],
      '/developers/': [
        {
          text: 'Developers',
          items: [
            { text: 'Developer guide', link: '/developers/' },
            { text: 'Architecture', link: '/developers/architecture' },
            { text: 'API', link: '/developers/api' },
            { text: 'Google Sign-In setup', link: '/developers/google-sign-in' },
          ],
        },
      ],
    },

    socialLinks: [{ icon: 'github', link: 'https://github.com/elkuku/AgentTools' }],

    editLink: {
      pattern: 'https://github.com/elkuku/AgentTools/edit/master/docs/:path',
    },

    search: { provider: 'local' },

    footer: {
      message: 'AgentTools is not affiliated with Niantic Spatial or Ingress.',
    },
  },
})
