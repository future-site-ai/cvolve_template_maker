<?php

/**
 * Returns the importmap for this application.
 *
 * - "path" is a path inside the asset mapper system. Use the
 *     "debug:asset-map" command to see the full list of paths.
 *
 * - "entrypoint" (JavaScript only) set to true for any module that will
 *     be used as an "entrypoint" (and passed to the importmap() Twig function).
 *
 * The "importmap:require" command can be used to add new entries to this file.
 */
return [
    'app' => [
        'path' => './assets/app.js',
        'entrypoint' => true,
    ],
    '@hotwired/stimulus' => [
        'version' => '3.2.2',
    ],
    '@symfony/stimulus-bundle' => [
        'path' => './vendor/symfony/stimulus-bundle/assets/dist/loader.js',
    ],
    '@hotwired/turbo' => [
        'version' => '8.0.23',
    ],
    'codemirror' => [
        'version' => '6.0.2',
    ],
    '@codemirror/lang-html' => [
        'version' => '6.4.12',
    ],
    '@codemirror/lang-css' => [
        'version' => '6.3.1',
    ],
    '@codemirror/lang-json' => [
        'version' => '6.0.2',
    ],
    '@codemirror/theme-one-dark' => [
        'version' => '6.1.3',
    ],
    '@codemirror/state' => [
        'version' => '6.7.6',
    ],
    '@codemirror/view' => [
        'version' => '6.43.14',
    ],
    '@codemirror/language' => [
        'version' => '6.11.1',
    ],
    '@codemirror/commands' => [
        'version' => '6.8.1',
    ],
    '@codemirror/search' => [
        'version' => '6.5.11',
    ],
    '@codemirror/autocomplete' => [
        'version' => '6.18.6',
    ],
    '@codemirror/lint' => [
        'version' => '6.8.5',
    ],
    '@lezer/html' => [
        'version' => '1.3.13',
    ],
    '@codemirror/lang-javascript' => [
        'version' => '6.2.5',
    ],
    '@lezer/css' => [
        'version' => '1.1.9',
    ],
    '@lezer/common' => [
        'version' => '1.2.3',
    ],
    '@lezer/json' => [
        'version' => '1.0.3',
    ],
    '@lezer/highlight' => [
        'version' => '1.2.1',
    ],
    '@marijn/find-cluster-break' => [
        'version' => '1.0.4',
    ],
    'style-mod' => [
        'version' => '4.1.4',
    ],
    'w3c-keyname' => [
        'version' => '2.2.8',
    ],
    'crelt' => [
        'version' => '1.0.7',
    ],
    '@lezer/lr' => [
        'version' => '1.4.2',
    ],
    '@lezer/javascript' => [
        'version' => '1.5.4',
    ],
];
