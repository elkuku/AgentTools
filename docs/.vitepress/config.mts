import { defineConfig, type DefaultTheme } from 'vitepress'

/** The nav and sidebars of a language; `prefix` is its folder, e.g. '/de'. */
function themeConfig(prefix: string, t: {
  guide: string
  developers: string
  downloads: string
  website: string
  gettingStarted: string
  app: string
  web: string
  privacy: string
  developerGuide: string
  architecture: string
  googleSignIn: string
}): DefaultTheme.Config {
  return {
    nav: [
      { text: t.guide, link: `${prefix}/guide/`, activeMatch: `${prefix}/guide/` },
      { text: t.developers, link: `${prefix}/developers/`, activeMatch: `${prefix}/developers/` },
      { text: t.downloads, link: `${prefix}/downloads` },
      { text: t.website, link: 'https://agent-tools.elkuku.dev' },
    ],

    sidebar: {
      [`${prefix}/guide/`]: [
        {
          text: t.guide,
          items: [
            { text: t.gettingStarted, link: `${prefix}/guide/` },
            { text: t.app, link: `${prefix}/guide/app` },
            { text: t.web, link: `${prefix}/guide/web` },
            { text: t.privacy, link: `${prefix}/guide/privacy` },
          ],
        },
      ],
      [`${prefix}/developers/`]: [
        {
          text: t.developers,
          items: [
            { text: t.developerGuide, link: `${prefix}/developers/` },
            { text: t.architecture, link: `${prefix}/developers/architecture` },
            { text: 'API', link: `${prefix}/developers/api` },
            { text: t.googleSignIn, link: `${prefix}/developers/google-sign-in` },
          ],
        },
      ],
    },
  }
}

// Published on GitHub Pages at https://elkuku.github.io/AgentTools/ (with the dev APK and the
// test coverage next to it, see .github/workflows/symfony-pages.yml)
export default defineConfig({
  title: 'AgentTools',
  base: '/AgentTools/',
  cleanUrls: true,
  lastUpdated: true,
  head: [['link', { rel: 'icon', href: '/AgentTools/favicon.ico' }]],
  // Files the Pages workflow adds after the build
  ignoreDeadLinks: [/^\/agenttools-dev\.apk$/, /^\/coverage\//],

  // English at the root, German in de/ and Spanish in es/, with the same pages in each
  locales: {
    root: {
      label: 'English',
      lang: 'en-US',
      description: 'Save your Ingress agent stats and medals and see them in one place.',
      themeConfig: {
        ...themeConfig('', {
          guide: 'Guide',
          developers: 'Developers',
          downloads: 'Downloads',
          website: 'Website',
          gettingStarted: 'Getting started',
          app: 'The Android app',
          web: 'The website',
          privacy: 'Privacy',
          developerGuide: 'Developer guide',
          architecture: 'Architecture',
          googleSignIn: 'Google Sign-In setup',
        }),
        footer: { message: 'AgentTools is not affiliated with Niantic Spatial or Ingress.' },
      },
    },
    de: {
      label: 'Deutsch',
      lang: 'de-DE',
      description: 'Speichere deine Ingress-Agentenstatistiken und Medaillen und sieh sie an einem Ort.',
      themeConfig: {
        ...themeConfig('/de', {
          guide: 'Anleitung',
          developers: 'Entwickler',
          downloads: 'Downloads',
          website: 'Website',
          gettingStarted: 'Erste Schritte',
          app: 'Die Android-App',
          web: 'Die Website',
          privacy: 'Datenschutz',
          developerGuide: 'Entwicklerhandbuch',
          architecture: 'Architektur',
          googleSignIn: 'Google-Anmeldung einrichten',
        }),
        footer: { message: 'AgentTools steht in keiner Verbindung zu Niantic Spatial oder Ingress.' },
        editLink: {
          pattern: 'https://github.com/elkuku/AgentTools/edit/master/docs/:path',
          text: 'Diese Seite auf GitHub bearbeiten',
        },
        outline: { label: 'Auf dieser Seite' },
        docFooter: { prev: 'Vorherige Seite', next: 'Nächste Seite' },
        lastUpdated: { text: 'Zuletzt aktualisiert' },
        langMenuLabel: 'Sprache ändern',
        returnToTopLabel: 'Zurück nach oben',
        sidebarMenuLabel: 'Menü',
        darkModeSwitchLabel: 'Design',
        lightModeSwitchTitle: 'Zum hellen Design wechseln',
        darkModeSwitchTitle: 'Zum dunklen Design wechseln',
        skipToContentLabel: 'Zum Inhalt springen',
        notFound: {
          title: 'SEITE NICHT GEFUNDEN',
          quote: 'Diese Seite gibt es nicht.',
          linkLabel: 'Zur Startseite',
          linkText: 'Zur Startseite',
        },
      },
    },
    es: {
      label: 'Español',
      lang: 'es-ES',
      description: 'Guarda tus estadísticas y medallas de agente de Ingress y míralas en un solo lugar.',
      themeConfig: {
        ...themeConfig('/es', {
          guide: 'Guía',
          developers: 'Desarrolladores',
          downloads: 'Descargas',
          website: 'Sitio web',
          gettingStarted: 'Primeros pasos',
          app: 'La app para Android',
          web: 'El sitio web',
          privacy: 'Privacidad',
          developerGuide: 'Guía para desarrolladores',
          architecture: 'Arquitectura',
          googleSignIn: 'Configurar el inicio de sesión con Google',
        }),
        footer: { message: 'AgentTools no está afiliado a Niantic Spatial ni a Ingress.' },
        editLink: {
          pattern: 'https://github.com/elkuku/AgentTools/edit/master/docs/:path',
          text: 'Editar esta página en GitHub',
        },
        outline: { label: 'En esta página' },
        docFooter: { prev: 'Página anterior', next: 'Página siguiente' },
        lastUpdated: { text: 'Última actualización' },
        langMenuLabel: 'Cambiar idioma',
        returnToTopLabel: 'Volver arriba',
        sidebarMenuLabel: 'Menú',
        darkModeSwitchLabel: 'Tema',
        lightModeSwitchTitle: 'Cambiar al tema claro',
        darkModeSwitchTitle: 'Cambiar al tema oscuro',
        skipToContentLabel: 'Saltar al contenido',
        notFound: {
          title: 'PÁGINA NO ENCONTRADA',
          quote: 'Esta página no existe.',
          linkLabel: 'Ir al inicio',
          linkText: 'Ir al inicio',
        },
      },
    },
  },

  themeConfig: {
    logo: '/logo.svg',

    socialLinks: [{ icon: 'github', link: 'https://github.com/elkuku/AgentTools' }],

    editLink: {
      pattern: 'https://github.com/elkuku/AgentTools/edit/master/docs/:path',
    },

    search: {
      provider: 'local',
      options: {
        locales: {
          de: {
            translations: {
              button: { buttonText: 'Suchen', buttonAriaLabel: 'Suchen' },
              modal: {
                displayDetails: 'Details anzeigen',
                resetButtonTitle: 'Suche zurücksetzen',
                backButtonTitle: 'Suche schließen',
                noResultsText: 'Keine Ergebnisse für',
                footer: { selectText: 'auswählen', navigateText: 'navigieren', closeText: 'schließen' },
              },
            },
          },
          es: {
            translations: {
              button: { buttonText: 'Buscar', buttonAriaLabel: 'Buscar' },
              modal: {
                displayDetails: 'Mostrar detalles',
                resetButtonTitle: 'Borrar la búsqueda',
                backButtonTitle: 'Cerrar la búsqueda',
                noResultsText: 'No hay resultados para',
                footer: { selectText: 'elegir', navigateText: 'navegar', closeText: 'cerrar' },
              },
            },
          },
        },
      },
    },
  },
})
