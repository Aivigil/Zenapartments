import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.vue',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', 'ui-sans-serif', 'system-ui', '-apple-system', 'BlinkMacSystemFont', '"Segoe UI"', 'Roboto', 'Helvetica', 'Arial', 'sans-serif'],
            },
            colors: {
                brand: {
                    DEFAULT: '#1B3A2F',
                    dark: '#13291F',
                    light: '#5B8C7B',
                    50:  '#F0F4F2',
                    100: '#DCE6E1',
                    200: '#B7CCC1',
                    300: '#8FB0A0',
                    400: '#5B8C7B',
                    500: '#3F6E5D',
                    600: '#2C5547',
                    700: '#1F4135',
                    800: '#1B3A2F',
                    900: '#13291F',
                },
            },
        },
    },
    plugins: [forms, typography],
};
