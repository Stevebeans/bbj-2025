/** @type {import('tailwindcss').Config} */
module.exports = {
  darkMode: "class",
  content: [
    "./**/*.php",
    "./src/**/*.{js,css}",
    "./template-parts/**/*.php",
  ],
  theme: {
    extend: {
      fontFamily: {
        sans: ["Roboto", "sans-serif"],
        osw: ["Oswald", "sans-serif"],
        display: ["Yanone Kaffeesatz", "sans-serif"],
        mainHead: ["Yanone Kaffeesatz", "sans-serif"],
        hand: ["Caveat", "cursive"],
      },
      colors: {
        primary: {
          400: "#4D6D88",
          500: "#35546e",
          600: "#2D4B65",
        },
        secondary: {
          400: "#ffd970",
          500: "#FFBF0F",
          600: "#FA910A",
        },
        accent: {
          red: "#E55C41",
        },
      },
    },
  },
  plugins: [require("@tailwindcss/typography")],
};
