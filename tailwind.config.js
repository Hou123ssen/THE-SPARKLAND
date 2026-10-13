/** @type {import('tailwindcss').Config} */
export default {
  content: ["./index.html", "./src/**/*.{js,jsx}"],
  theme: {
    extend: {
      colors: {
        spark: {
          offwhite: "#F7F7F5",
          ink: "#101010",
          dark: "#080808",
          blue: "#0057C8",
        },
      },
      fontFamily: {
        sans: ["Manrope Variable", "ui-sans-serif", "system-ui", "sans-serif"],
        headline: ["Helvetica Neue", "Arial Black", "Arial", "sans-serif"],
      },
    },
  },
  plugins: [],
};
