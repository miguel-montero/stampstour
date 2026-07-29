<?php
function render_template(string $file, array $vars = []): string {
  if (!is_file($file)) return '';
  extract($vars, EXTR_SKIP);
  ob_start();
  include $file; // file should echo/print HTML using $vars
  return ob_get_clean();
}
