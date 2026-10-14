<?php
// includes/ui/ui.php
// Loads the "lab" UI components: _helpers.php first, then every
// includes/ui/[a-z]*.php (one component per file). Pages using these must
// also load css/lab.css (built by bin/build-css) and wrap the content in
// <main class="lab">. See docs/superpowers/specs/2026-10-14-maipo-lab-redesign-design.md

require_once __DIR__ . '/_helpers.php';
foreach (glob(__DIR__ . '/[a-z]*.php') as $ui_component_file) {
    if (basename($ui_component_file) !== 'ui.php') {
        require_once $ui_component_file;
    }
}
unset($ui_component_file);
