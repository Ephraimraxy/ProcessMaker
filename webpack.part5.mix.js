const mix = require("laravel-mix");
const path = require("path");
const fs = require("fs");
const manifestPath = path.resolve(__dirname, "public/mix-manifest.json");
let existingContent = {};

mix.webpackConfig({
    externals: ["SharedComponents", "ModelerInspector"],
    resolve: {
        extensions: [".*", ".js", ".ts", ".mjs", ".vue", ".json"],
        symlinks: false,
        alias: {
            "vue-monaco": path.resolve(__dirname, "resources/js/vue-monaco-amd.js"),
            styles: path.resolve(__dirname, "resources/sass"),
        },
    },
});

mix.options({
    legacyNodePolyfills: false,
    terser: {
        parallel: false,
    },
});

mix.before(() => {
    if (fs.existsSync(manifestPath)) {
        try {
            existingContent = JSON.parse(fs.readFileSync(manifestPath, "utf8"));
        } catch (e) {
            existingContent = {};
        }
    }
});

mix.then(() => {
    if (fs.existsSync(manifestPath)) {
        const newContent = JSON.parse(fs.readFileSync(manifestPath, "utf8"));
        const mergedContent = { ...existingContent, ...newContent };
        fs.writeFileSync(manifestPath, JSON.stringify(mergedContent, null, 4));
    }
});

// Monaco AMD modules. Copy only the files we need to make the build faster.
const monacoSource = "node_modules/monaco-editor/min/vs/";
const monacoDestination = "public/vendor/monaco-editor/min/vs/";
const monacoLanguages = ["php", "css", "lua", "javascript", "csharp", "java", "python", "r", "html", "xml", "typescript", "sql"];
const monacoFiles = [
    "loader.js",
    "editor/editor.main.js",
    "editor/editor.main.css",
    "editor/editor.main.nls.js",
    "base/browser/ui/codicons/codicon/codicon.ttf",
    "base/worker/workerMain.js",
    "base/common/worker/simpleWorker.nls.js",
];
monacoFiles.forEach((file) => {
    mix.copy(monacoSource + file, monacoDestination + file);
});
monacoLanguages.forEach((lang) => {
    const path = `basic-languages/${lang}/${lang}.js`;
    mix.copy(monacoSource + path, monacoDestination + path);
});
mix.copyDirectory(`${monacoSource}language`, `${monacoDestination}language`);

mix
    .sass("resources/sass/sidebar/sidebar.scss", "public/css")
    .sass("resources/sass/collapseDetails.scss", "public/css")
    .sass("resources/sass/app.scss", "public/css")
    .sass("resources/sass/admin/queues.scss", "public/css/admin")
    .postCss("resources/sass/tailwind.css", "public/css", [
        require("tailwindcss"),
    ])
    .version();
