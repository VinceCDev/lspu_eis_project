const globals = require('globals');

/**
 * Ported from the original lspu_eis project's eslint.config.js — this app
 * ships JS the same way (plain <script> tags, no bundler), just relocated
 * to public/assets/js instead of frontend/assets/js.
 */
module.exports = [
    {
        ignores: [
            'public/assets/vendor/**',
            'node_modules/**',
            'vendor/**',
        ],
    },
    {
        files: ['public/assets/js/**/*.js'],
        languageOptions: {
            sourceType: 'script',
            ecmaVersion: 2022,
            globals: {
                ...globals.browser,
                Vue: 'readonly',
                Chart: 'readonly',
                L: 'readonly',
                Quill: 'readonly',
                jspdf: 'readonly',
                XLSX: 'readonly',
                ExcelJS: 'readonly',
                html2canvas: 'readonly',
                saveAs: 'readonly',
                module: 'writable',
                require: 'readonly',
            },
        },
        rules: {
            'no-unused-vars': ['warn', { args: 'none', varsIgnorePattern: '^_' }],
            'no-undef': 'error',
            'no-redeclare': 'error',
            'no-var': 'error',
            eqeqeq: ['warn', 'smart'],
        },
    },
    {
        files: ['tests/js/**/*.js'],
        languageOptions: {
            sourceType: 'module',
            ecmaVersion: 2022,
            globals: {
                ...globals.node,
            },
        },
        rules: {
            'no-unused-vars': ['warn', { args: 'none' }],
        },
    },
];
