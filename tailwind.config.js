/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./resources/**/*.blade.php",
    "./resources/**/*.js",
    "./resources/**/*.vue",
  ],
  theme: {
    extend: {
      colors: {
        brand: {
          DEFAULT: '#00a81c',
          700: '#008517',
        },
        accent: {
          DEFAULT: '#0b4ea2',
          700: '#093f82',
        },
      }
    },
  },
  plugins: [],
}