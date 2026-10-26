import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                inacap: {
                    50: '#fdf2f2',
                    100: '#fde6e6',
                    200: '#fbd0d0',
                    300: '#f8a5a5',
                    400: '#f06a6a',
                    500: '#e52323',
                    600: '#cc0000', // Rojo Oficial Institucional INACAP
                    700: '#b30000', // Rojo Base Botones / Acentos
                    800: '#8b0000', // Hover
                    900: '#660000', // Active
                },
            },
        },
    },

    plugins: [forms],
};
