import type { Config } from 'tailwindcss';

const config: Config = {
  darkMode: ['class'],
  content: ['./index.html', './src/renderer/**/*.{ts,tsx}'],
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
      fontFamily: {
        sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
      },
    },
  },
  plugins: [],
};

export default config;
