import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.jsx',
        './resources/js/**/*.tsx',
        './resources/js/**/*.ts',
    ],

    theme: {
        extend: {
            colors: {
                primary: '#0F7A3A',
                primaryDark: '#064E3B',
                accent: '#F59E0B',
                brown: '#7C4A21',
                slateDark: '#0F172A',
                surface: '#F8FAFC',
                border: '#E2E8F0',
                success: '#16A34A',
                danger: '#DC2626',
                info: '#2563EB',
                warning: '#F59E0B',
            },
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};
