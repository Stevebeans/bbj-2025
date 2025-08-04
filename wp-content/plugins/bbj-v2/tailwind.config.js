// tailwind.config.js
module.exports = {
  content: [
    "./src/**/*.js", // your React/JS entrypoints
    "./**/*.php" // any PHP templates that use Tailwind classes
  ],
  theme: {
    extend: {
      colors: {
        primary500: "#35546e",
        primarySoft: "#4D6D88",
        primaryHard: "#2D4B65",
        second500: "#FFBF0F",
        secondSoft: "#ffd970",
        secondHard: "#FA910A",
        thirdColor: "#E55C41"
      }
    }
  },
  plugins: []
};
