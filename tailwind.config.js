/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './*.php',
    './dashboard/**/*.php',
    './includes/**/*.php',
    './assets/js/**/*.js',
    './script.js',
    './lang/**/*.php'
  ],
  theme: {
    extend: {
      colors: {
        cream: {
          DEFAULT: '#FAF3E3',
          50: '#FDFBF7',
          100: '#FAF3E3',
          200: '#F5E7C8',
        },
        teak: {
          DEFAULT: '#3B2416',
          900: '#26170E',
          800: '#3B2416',
          700: '#523420',
        },
        wood: {
          DEFAULT: '#6B4A2F',
          light: '#8C6240',
          dark: '#523420',
        },
        sand: {
          DEFAULT: '#EFE0C0',
          light: '#F8F1E4',
          dark: '#D9C49A',
        },
        gold: {
          DEFAULT: '#E3A600',
          dark: '#B88400',
          light: '#F3C445',
        },
        'green-dark': {
          DEFAULT: '#2F4F3E',
          light: '#3D6650',
          dark: '#1F3429',
        }
      },
      fontFamily: {
        display: ['"Playfair Display"', 'Georgia', 'serif'],
        body: ['Lato', '-apple-system', 'BlinkMacSystemFont', '"Segoe UI"', 'Roboto', 'sans-serif'],
        script: ['"Kaushan Script"', 'cursive'],
      },
      boxShadow: {
        'joglo': '0 10px 30px -5px rgba(59, 36, 22, 0.08), 0 4px 6px -2px rgba(59, 36, 22, 0.04)',
        'joglo-lg': '0 20px 40px -10px rgba(59, 36, 22, 0.15)',
        'joglo-glow': '0 0 25px rgba(227, 166, 0, 0.25)',
      },
      borderRadius: {
        'joglo': '12px',
        'joglo-lg': '16px',
        'joglo-xl': '24px',
      }
    },
  },
  plugins: [],
}

