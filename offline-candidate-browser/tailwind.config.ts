import type { Config } from 'tailwindcss';

export default {
    content: ['./index.html', './src/**/*.{ts,tsx}'],
    theme: {
        extend: {
            colors: {
                primary: '#0F7A3A',
                darkGreen: '#064E3B',
                accentOrange: '#F59E0B',
                slateDark: '#0F172A',
                lightBackground: '#F8FAFC',
                border: '#E2E8F0',
                success: '#16A34A',
                danger: '#DC2626',
                info: '#2563EB',
            },
        },
    },
    plugins: [],
} satisfies Config;
