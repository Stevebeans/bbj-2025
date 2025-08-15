// webpack.config.js
const defaultConfig = require("@wordpress/scripts/config/webpack.config");
const path = require("path");

module.exports = {
  ...defaultConfig,

  entry: async (env, argv) => {
    // 1) Resolve the default entry (string | array | object)
    const baseEntry = typeof defaultConfig.entry === "function" ? await defaultConfig.entry(env, argv) : defaultConfig.entry;

    // 2) Normalize to an object keyed by "index"
    const entries = typeof baseEntry === "string" || Array.isArray(baseEntry) ? { index: baseEntry } : { ...baseEntry };

    // 3) Add your player-admin bundle
    entries["player-admin"] = path.resolve(__dirname, "src/js/player-admin.js");

    return entries;
  },

  output: {
    ...defaultConfig.output,
    // spit out build/index.js and build/player-admin.js
    filename: "[name].js"
  },

  watchOptions: {
    ignored: /build/
  }
};
