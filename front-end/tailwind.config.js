/** @type {import('tailwindcss').Config} */
export default {
  darkMode: 'class',
  content: [
    "./index.html",
    "./src/**/*.{vue,js,ts,jsx,tsx}",
  ],
  safelist: [
    {
      pattern: /(bg|border|border-t|text|ring|shadow|from|to)-(violet|indigo|blue|sky|emerald|amber|orange|rose|pink|slate|purple|green)-(50|100|200|300|400|500|600|700)/,
      variants: ['hover', 'focus', 'group-hover', 'active'],
    },
    // Safelist opacity modifiers used in Group cards and previews
    ...['violet', 'indigo', 'blue', 'sky', 'emerald', 'amber', 'orange', 'rose', 'pink', 'slate', 'purple', 'green'].flatMap(c => [
      `bg-${c}-50/40`,
      `bg-${c}-50/50`,
      `bg-${c}-500/15`,
      `bg-${c}-500/10`,
      `from-${c}-500/15`,
      `from-${c}-500/20`,
      `to-${c}-500/5`,
      `border-${c}-200/80`,
      `shadow-${c}-500/10`,
      `hover:shadow-${c}-500/10`,
      `hover:bg-${c}-50`,
      `hover:bg-${c}-50/50`,
    ]),
  ],
  theme: {
    extend: {
      colors: {
        primary: {
          50: '#f5f3ff',
          100: '#ede9fe',
          500: '#8b5cf6',
          600: '#7c3aed',
          900: '#4c1d95',
        }
      },
      fontFamily: {
        sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
        display: ['Outfit', 'ui-sans-serif', 'system-ui', 'sans-serif'],
      },
      boxShadow: {
        'soft': '0 4px 20px -2px rgba(0, 0, 0, 0.05)',
        'premium': '0 10px 40px -10px rgba(139, 92, 246, 0.15)',
      }
    },
  },
  plugins: [],
}

