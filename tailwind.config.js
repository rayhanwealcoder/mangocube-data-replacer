/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./src/**/*.{js,jsx,ts,tsx}",
    "./templates/**/*.php",
  ],
  prefix: 'wcfdr-',
  theme: {
    extend: {
      colors: {
        // WordPress admin palette
        'wp-blue': '#0073aa',
        'wp-blue-dark': '#005177',
        'wp-blue-light': '#00a0d2',
        'wp-gray': '#f1f1f1',
        'wp-gray-dark': '#666',
        'wp-gray-light': '#f9f9f9',
        'wp-red': '#dc3232',
        'wp-green': '#46b450',
        'wp-orange': '#ffb900',
        'wp-yellow': '#ffb900',
        
        // Semantic colors
        'success': '#46b450',
        'warning': '#ffb900',
        'error': '#dc3232',
        'info': '#00a0d2',
      },
      borderRadius: {
        'wp': '3px',
      },
      boxShadow: {
        'wp': '0 1px 1px rgba(0,0,0,.04)',
        'wp-focus': '0 0 0 1px #0073aa, 0 0 2px 1px rgba(0,115,170,.3)',
      },
      fontFamily: {
        'wp': ['-apple-system', 'BlinkMacSystemFont', '"Segoe UI"', 'Roboto', 'Oxygen-Sans', 'Ubuntu', 'Cantarell', '"Helvetica Neue"', 'sans-serif'],
      },
      fontSize: {
        'wp': '13px',
        'wp-lg': '14px',
        'wp-xl': '16px',
      },
      spacing: {
        'wp': '12px',
        'wp-lg': '16px',
        'wp-xl': '24px',
      },
      zIndex: {
        'wp-modal': '100050',
        'wp-toolbar': '100000',
        'wp-adminbar': '99999',
      },
    },
  },
  plugins: [
    require('@tailwindcss/forms'),
    require('@tailwindcss/typography'),
  ],
  corePlugins: {
    preflight: false,
  },
};
