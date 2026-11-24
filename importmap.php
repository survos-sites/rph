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
 *
 * @return array<string, array{    // Import name as key, description of the imported file as value
 *     path: string,               // Logical, relative or absolute path to the file
 *     type?: 'js'|'css'|'json',   // Type of the file, defaults to 'js'
 *     entrypoint?: bool,          // Whether the file is an entrypoint, for 'js' only
 * }|array{
 *     version: string,            // Version of the remote package
 *     package_specifier?: string, // Remote "package-name/path" specifier, defaults to the import name
 *     type?: 'js'|'css'|'json',
 *     entrypoint?: bool,
 *     esm?: bool,                 // Whether jsDelivr's ESM build is used, defaults to true
 * }>
 */
return [
    '@algolia/events' => ['version' => '4.0.1'],
    '@andypf/json-viewer' => ['version' => '2.8.1'],
    '@floating-ui/core' => ['version' => '1.8.0'],
    '@floating-ui/dom' => ['version' => '1.8.0'],
    '@floating-ui/utils' => ['version' => '0.2.12'],
    '@floating-ui/utils/dom' => ['version' => '0.2.12'],
    '@hotwired/stimulus' => ['version' => '3.2.2'],
    '@hotwired/turbo' => ['version' => '8.0.23'],
    '@hpcc-js/wasm/graphviz' => ['version' => '2.20.0'],
    '@pixiv/three-vrm' => ['version' => '3.5.5'],
    '@survos/otio-bundle/player' => ['path' => './vendor/survos/otio-bundle/assets/dist/player.js'],
    '@swc/helpers/esm/_call_super.js' => ['version' => '0.5.18'],
    '@swc/helpers/esm/_class_call_check.js' => ['version' => '0.5.18'],
    '@swc/helpers/esm/_create_class.js' => ['version' => '0.5.18'],
    '@swc/helpers/esm/_define_property.js' => ['version' => '0.5.18'],
    '@swc/helpers/esm/_extends.js' => ['version' => '0.5.18'],
    '@swc/helpers/esm/_get.js' => ['version' => '0.5.18'],
    '@swc/helpers/esm/_get_prototype_of.js' => ['version' => '0.5.18'],
    '@swc/helpers/esm/_inherits.js' => ['version' => '0.5.18'],
    '@swc/helpers/esm/_instanceof.js' => ['version' => '0.5.18'],
    '@swc/helpers/esm/_object_destructuring_empty.js' => ['version' => '0.5.18'],
    '@swc/helpers/esm/_object_spread.js' => ['version' => '0.5.18'],
    '@swc/helpers/esm/_object_spread_props.js' => ['version' => '0.5.18'],
    '@swc/helpers/esm/_object_without_properties.js' => ['version' => '0.5.18'],
    '@swc/helpers/esm/_sliced_to_array.js' => ['version' => '0.5.18'],
    '@swc/helpers/esm/_to_consumable_array.js' => ['version' => '0.5.18'],
    '@swc/helpers/esm/_type_of.js' => ['version' => '0.5.18'],
    '@symfony/stimulus-bundle' => ['path' => './vendor/symfony/stimulus-bundle/assets/dist/loader.js'],
    '@symfony/ux-live-component' => ['path' => './vendor/symfony/ux-live-component/assets/dist/live_controller.js'],
    '@symfony/ux-router' => ['path' => './vendor/symfony/ux-router/assets/dist/router_controller.js'],
    '@tabler/core' => ['version' => '1.6.1'],
    '@tabler/core/dist/css/tabler.min.css' => ['version' => '1.6.1', 'type' => 'css'],
    '@tacman1123/twig-browser' => ['version' => '1.0.0'],
    '@tacman1123/twig-browser/adapters/symfony' => ['version' => '1.0.0'],
    '@tacman1123/twig-browser/src/compat/compileTwigBlocks.js' => ['version' => '1.0.0'],
    'algoliasearch-helper' => ['version' => '3.30.3'],
    'algoliasearch-helper/types/algoliasearch.js' => ['version' => '3.30.3'],
    'app' => ['path' => './assets/app.js', 'entrypoint' => true],
    'call-bind' => ['version' => '1.0.8'],
    'call-bind-apply-helpers' => ['version' => '1.0.0'],
    'call-bind-apply-helpers/applyBind' => ['version' => '1.0.0'],
    'call-bind-apply-helpers/functionApply' => ['version' => '1.0.0'],
    'call-bind-apply-helpers/functionCall' => ['version' => '1.0.0'],
    'call-bound' => ['version' => '1.0.2'],
    'd3-color' => ['version' => '3.0.1'],
    'd3-dispatch' => ['version' => '3.0.1'],
    'd3-drag' => ['version' => '3.0.0'],
    'd3-ease' => ['version' => '3.0.1'],
    'd3-format' => ['version' => '3.1.0'],
    'd3-graphviz' => ['version' => '5.6.0'],
    'd3-interpolate' => ['version' => '3.0.1'],
    'd3-path' => ['version' => '3.1.0'],
    'd3-selection' => ['version' => '3.0.0'],
    'd3-timer' => ['version' => '3.0.1'],
    'd3-transition' => ['version' => '3.0.1'],
    'd3-zoom' => ['version' => '3.0.0'],
    'define-data-property' => ['version' => '1.1.4'],
    'dunder-proto/get' => ['version' => '1.0.0'],
    'es-define-property' => ['version' => '1.0.1'],
    'es-errors' => ['version' => '1.3.0'],
    'es-errors/eval' => ['version' => '1.3.0'],
    'es-errors/range' => ['version' => '1.3.0'],
    'es-errors/ref' => ['version' => '1.3.0'],
    'es-errors/syntax' => ['version' => '1.3.0'],
    'es-errors/type' => ['version' => '1.3.0'],
    'es-errors/uri' => ['version' => '1.3.0'],
    'flag-icons/css/flag-icons.min.css' => ['version' => '7.5.0', 'type' => 'css'],
    'function-bind' => ['version' => '1.1.2'],
    'get-intrinsic' => ['version' => '1.2.5'],
    'gopd' => ['version' => '1.2.0'],
    'has-property-descriptors' => ['version' => '1.0.2'],
    'has-symbols' => ['version' => '1.1.0'],
    'hasown' => ['version' => '2.0.2'],
    'hogan.js' => ['version' => '3.0.2'],
    'htm' => ['version' => '3.1.1'],
    'htm/preact' => ['version' => '3.1.1'],
    'instantsearch-ui-components' => ['version' => '0.46.1'],
    'instantsearch.js' => ['version' => '4.123.1'],
    'instantsearch.js/es/widgets' => ['version' => '4.123.1'],
    'marked' => ['version' => '18.0.14'],
    'object-inspect' => ['version' => '1.13.4'],
    'preact' => ['version' => '10.29.8'],
    'preact/hooks' => ['version' => '10.29.8'],
    'qs' => ['version' => '6.17.1'],
    'set-function-length' => ['version' => '1.2.2'],
    'side-channel' => ['version' => '1.1.1'],
    'side-channel-list' => ['version' => '1.0.1'],
    'side-channel-map' => ['version' => '1.0.1'],
    'side-channel-weakmap' => ['version' => '1.0.2'],
    'three' => ['version' => '0.187.0'],
    'three/addons/loaders/GLTFLoader.js' => ['version' => '0.187.0'],
];
