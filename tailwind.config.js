import defaultTheme from "tailwindcss/defaultTheme";
import forms from "@tailwindcss/forms";

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        "./vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php",
        "./storage/framework/views/*.php",
        "./resources/views/**/*.blade.php",
    ],
    safelist: ["-rotate-90"],
    theme: {
        extend: {
            fontFamily: {
                sans: ["Figtree", ...defaultTheme.fontFamily.sans],
            },
            // tailwind.config.js
            colors: {
                    primary: "#3f001f",
                    gold: "#d4af37",
                    cream: "#f8f1e7",
                    charcoal: "#1a1a1a",
            },
        },
    },

    plugins: [forms],
};
