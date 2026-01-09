/** @type {import('tailwindcss').Config} */
module.exports = {
    prefix: 'bbjd-',
    content: [
        './src/**/*.php',
        './templates/**/*.php'
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
                thirdColor: "#E55C41",
            },
            fontFamily: {
                sans: ['Roboto', 'sans-serif'],
                osw: ['Oswald', 'sans-serif'],
                mainHead: ['Yanone Kaffeesatz', 'sans-serif'],
                hand: ['Caveat', 'cursive'],
            },
        },
    },
    plugins: [],
}
