import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
                mono: ['"JetBrains Mono"', ...defaultTheme.fontFamily.mono],
            },

            colors: {
                /* Chrome de l'application : rail latéral, surfaces sombres */
                night: {
                    50: '#EEF1F8',
                    100: '#D6DCEC',
                    200: '#AEB9D6',
                    300: '#7D8CB4',
                    400: '#526292',
                    500: '#33436F',
                    600: '#24325C',
                    700: '#1A2647',
                    800: '#101A33',
                    900: '#0B1220',
                },

                /* Accent principal : actions, sélection, focus */
                brand: {
                    50: '#EFF0FF',
                    100: '#E1E3FF',
                    200: '#C7CAFF',
                    300: '#A5A9FB',
                    400: '#8286F0',
                    500: '#6366E0',
                    600: '#4F4CC7',
                    700: '#403C9F',
                    800: '#35337F',
                    900: '#2E2D66',
                },

                /* Surface de travail */
                canvas: {
                    DEFAULT: '#F5F7FA',
                    raised: '#FFFFFF',
                    sunken: '#EDF0F6',
                },

                line: {
                    DEFAULT: '#E3E7F0',
                    strong: '#CBD3E3',
                },
            },

            boxShadow: {
                rail: '1px 0 0 0 rgba(11, 18, 32, 0.08)',
                lift: '0 1px 2px rgba(16, 26, 51, 0.06), 0 8px 24px -12px rgba(16, 26, 51, 0.24)',
                pop: '0 12px 40px -12px rgba(16, 26, 51, 0.35)',
            },

            keyframes: {
                'rise-in': {
                    from: { opacity: '0', transform: 'translateY(6px)' },
                    to: { opacity: '1', transform: 'translateY(0)' },
                },
                'fade-in': {
                    from: { opacity: '0' },
                    to: { opacity: '1' },
                },
                'slide-in-right': {
                    from: { opacity: '0', transform: 'translateX(16px)' },
                    to: { opacity: '1', transform: 'translateX(0)' },
                },
                'pulse-ring': {
                    '0%': { transform: 'scale(0.85)', opacity: '0.7' },
                    '70%': { transform: 'scale(2.2)', opacity: '0' },
                    '100%': { transform: 'scale(2.2)', opacity: '0' },
                },
                shimmer: {
                    '100%': { transform: 'translateX(100%)' },
                },
                'draw-line': {
                    from: { strokeDashoffset: '1000' },
                    to: { strokeDashoffset: '0' },
                },
            },

            animation: {
                'rise-in': 'rise-in 260ms cubic-bezier(0.16, 1, 0.3, 1) both',
                'fade-in': 'fade-in 200ms ease-out both',
                'slide-in-right': 'slide-in-right 260ms cubic-bezier(0.16, 1, 0.3, 1) both',
                'pulse-ring': 'pulse-ring 2s cubic-bezier(0.24, 0, 0.38, 1) infinite',
                shimmer: 'shimmer 1.6s infinite',
                'draw-line': 'draw-line 900ms ease-out both',
            },
        },
    },

    plugins: [forms],
};
