export default {
    content: [
        './app/Filament/**/*.php',
        './resources/views/filament/**/*.blade.php',
        './resources/views/components/filament/**/*.blade.php',
        './vendor/filament/**/*.blade.php',
    ],
    theme: {
        extend: {
            colors: {
                brand: {
                    50: '#f0fdf4',
                    100: '#dcfce7',
                    200: '#bbf7d0',
                    300: '#86efac',
                    400: '#4ade80',
                    500: '#166534',
                    600: '#14532d',
                    700: '#0f3f23',
                    800: '#0b2d19',
                    900: '#071d10',
                },
                secondary: {
                    100: '#dcfce7',
                    200: '#bbf7d0',
                    300: '#86efac',
                },
                accent: {
                    teal: '#14b8a6',
                    gold: '#d97706',
                },
                surface: {
                    page: '#f8fafc',
                    card: '#ffffff',
                },
                semantic: {
                    danger: '#ef4444',
                    warning: '#f59e0b',
                    info: '#64748b',
                    success: '#15803d',
                },
            },
            borderRadius: {
                xl: '0.75rem',
            },
            boxShadow: {
                card: '0 8px 24px rgba(15, 23, 42, 0.06)',
                widget: '0 8px 24px rgba(15, 23, 42, 0.08)',
            },
            fontSize: {
                'body-sm': ['0.875rem', { lineHeight: '1.5' }],
                'label-xs': ['0.75rem', { lineHeight: '1rem', letterSpacing: '0.04em' }],
            },
            transitionTimingFunction: {
                smooth: 'cubic-bezier(0.4, 0, 0.2, 1)',
            },
            fontFamily: {
                sans: ['Inter', 'ui-sans-serif', 'system-ui', '-apple-system', 'Segoe UI', 'Roboto', 'sans-serif'],
            },
        },
    },
};
