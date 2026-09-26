/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './app/**/*.{js,ts,jsx,tsx,mdx}',
    './components/**/*.{js,ts,jsx,tsx,mdx}',
    './features/**/*.{js,ts,jsx,tsx,mdx}',
  ],
  theme: {
    extend: {
      colors: {
        brand: {
          50: '#ebfbee',
          100: '#d3f9d8',
          200: '#b2f2bb',
          300: '#8ce99a',
          400: '#69db7c',
          500: '#2b8a3e',
          600: '#237032',
          700: '#1b5627',
          800: '#133c1b',
          900: '#0b2210',
        },
        primary: {
          DEFAULT: '#2b8a3e',
          hover: '#237032',
        }
      },
      fontFamily: {
        sans: ['Inter', 'sans-serif'],
      }
    },
  },
  plugins: [],
}
