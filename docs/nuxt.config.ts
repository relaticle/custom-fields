// https://nuxt.com/docs/api/configuration/nuxt-config
const baseURL = process.env.NUXT_APP_BASE_URL || '/'
const docsVersion = process.env.DOCS_VERSION || '1.x'

export default defineNuxtConfig({
    extends: 'docus',
    modules: ['@nuxt/image', '@nuxt/scripts'],
    devtools: { enabled: true },
    site: {
        name: 'Custom Fields',
    },
    runtimeConfig: {
        public: {
            docsVersion,
        },
    },
    appConfig: {
        docus: {
            url: `https://relaticle.github.io${baseURL}`,
            image: `${baseURL}preview.png`,
            header: {
                logo: {
                    light: `${baseURL}logo-light.svg`,
                    dark: `${baseURL}logo-dark.svg`,
                },
            },
        },
        seo: {
            ogImage: `${baseURL}preview.png`,
        },
        github: {
            branch: docsVersion,
        },
    },
    app: {
        baseURL,
        buildAssetsDir: 'assets',
        head: {
            link: [
                {
                    rel: 'icon',
                    type: 'image/svg+xml',
                    href: baseURL + 'favicon.svg',
                },
            ],
        },
    },
    image: {
        provider: 'none',
    },
    content: {
        build: {
            markdown: {
                highlight: {
                    langs: ['php', 'blade', 'bash', 'json', 'css'],
                },
            },
        },
    },
    llms: {
        domain: `https://relaticle.github.io${baseURL.replace(/\/$/, '')}`,
    },
    nitro: {
        preset: 'github_pages',
    },
})
