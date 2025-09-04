const defaultConfig = require("@wordpress/scripts/config/webpack.config");
const path = require("path");

module.exports = {
  ...defaultConfig,
  externals: {
    react: "React",
    "react-dom": "ReactDOM",
  },
  entry: {
    admin: "./src/admin.tsx",
  },
  output: {
    path: path.resolve(__dirname, "assets"),
    filename: "js/[name].js",
    clean: true,
  },
  resolve: {
    ...defaultConfig.resolve,
    alias: {
      ...defaultConfig.resolve.alias,
      "@": path.resolve(__dirname, "src"),
    },
  },
};
