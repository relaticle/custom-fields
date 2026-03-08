export default defineAppConfig({
    docus: {
        title: 'Custom Fields',
        description: 'Add dynamic custom fields to your Filament admin panels without writing database migrations.',
        header: {
            logo: {
                alt: 'Custom Fields Logo',
            }
        }
    },
    seo: {
        title: 'Custom Fields',
        description: 'Add dynamic custom fields to your Filament admin panels without writing database migrations.',
    },
    socials: {
        discord: 'https://discord.gg/b9WxzUce4Q'
    },
    ui: {
        colors: {
            primary: 'violet',
            neutral: 'zinc'
        }
    },
    uiPro: {
        pageHero: {
            slots: {
                container: 'flex flex-col lg:grid py-16 sm:py-20 lg:py-24 gap-16 sm:gap-y-2'
            }
        }
    },
    toc: {
        title: 'On this page',
        bottom: {
            title: 'Ecosystem',
            links: [
                {
                    icon: 'i-simple-icons-laravel',
                    label: 'FilaForms',
                    to: 'https://filaforms.app',
                    target: '_blank'
                },
                {
                    icon: 'i-lucide-layout-kanban',
                    label: 'Flowforge',
                    to: 'https://relaticle.github.io/flowforge/',
                    target: '_blank'
                }
            ]
        }
    }
})
