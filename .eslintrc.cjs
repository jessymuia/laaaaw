module.exports = {
    root: true,
    env: {
        browser: true,
        es2021: true,
        node: true,
    },
    extends: [
        'eslint:recommended',
        'plugin:vue/vue3-recommended',
    ],
    parserOptions: {
        ecmaVersion: 'latest',
        sourceType: 'module',
    },
    rules: {
        // Pragmatic starting point for a codebase with no prior lint
        // config at all (matches the phpstan.neon comment's reasoning):
        // these catch real bugs without demanding a repo-wide reformat
        // as a side effect of turning linting on for the first time.
        'no-unused-vars': 'warn',
        'vue/multi-word-component-names': 'off',
        'vue/no-v-html': 'off',
    },
};
