<?php
use Dompdf\Dompdf;
use Dompdf\Options;

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/render_template.php';

function generate_ticket_pdf(array $vars, string $saveDir = __DIR__ . '/../tickets'): array {
  if (!is_dir($saveDir)) mkdir($saveDir, 0755, true);

  $html = render_template(__DIR__ . '/ticket_template.html', $vars);

  $options = new Options();
  $options->set('isRemoteEnabled', true); // allow remote images if you add logos
  $dompdf = new Dompdf($options);
  $dompdf->loadHtml($html, 'UTF-8');
  $dompdf->setPaper('A4', 'portrait');
  $dompdf->render();

  $filename = 'TICKET_' . preg_replace('/[^A-Za-z0-9\-_]/', '_', $vars['codigo_externo'] ?? uniqid()) . '.pdf';
  $path = rtrim(realpath($saveDir) ?: $saveDir, '/') . '/' . $filename;
  file_put_contents($path, $dompdf->output());

  return ['path' => $path, 'filename' => $filename];
}
