import type { Config } from 'tailwindcss';

const config: Config = {
    content: [
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
        './app/View/Components/**/*.php',
    ],
    theme: {
        extend: {
            colors: {
                laporin: {
                    green: '#00A651',
                    'green-700': '#04783E',
                    'green-900': '#064225',
                    gold: '#F6C23E',
                    ink: '#10281B',
                    muted: '#647067',
                    soft: '#F4FFF8',
                    surface: '#FFFFFF',
                    line: '#DFEEE5',
                    danger: '#DC3545',
                },
            },
            borderRadius: {
                laporin: '1.1rem',
                'laporin-sm': '0.85rem',
                'laporin-lg': '1.45rem',
            },
            boxShadow: {
                laporin: '0 18px 45px rgb(16 40 27 / 0.10)',
                'laporin-soft': '0 10px 26px rgb(16 40 27 / 0.07)',
            },
            screens: {
                xs: '480px',
            },
        },
    },
    plugins: [],
};

export default config;
