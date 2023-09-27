module.exports = {
    plugins: [
        require("postcss-import"),
        require("postcss-preset-env")({ stage: 1 }), // stage 1 should work on modern navigators
        require("cssnano")({
            preset: "default",
        }),
    ],
};
