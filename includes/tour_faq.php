<?php
// includes/tour_faq.php
// Renders a Bootstrap accordion FAQ block plus matching FAQPage JSON-LD
// structured data for a tour page. $faqs is a list of ['q' => ..., 'a' => ...].
// Bootstrap 5 JS (collapse) is already loaded on these pages for #collapseMap.

function render_tour_faq(array $faqs): void
{
    if (empty($faqs)) {
        return;
    }
    ?>
      <hr/>
      <hr/>
      <div class="row">
       <div class="col-lg-3">
        <h3>FAQ</h3>
       </div>
       <div class="col-lg-9">
        <div class="accordion" id="tourFaqAccordion">
         <?php foreach ($faqs as $i => $faq): ?>
          <?php $itemId = 'tourFaq' . $i; ?>
          <div class="accordion-item">
           <h4 class="accordion-header" id="<?= $itemId ?>-heading">
            <button class="accordion-button<?= $i === 0 ? '' : ' collapsed' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $itemId ?>-collapse" aria-expanded="<?= $i === 0 ? 'true' : 'false' ?>" aria-controls="<?= $itemId ?>-collapse">
             <?= htmlspecialchars($faq['q'], ENT_QUOTES, 'UTF-8') ?>
            </button>
           </h4>
           <div id="<?= $itemId ?>-collapse" class="accordion-collapse collapse<?= $i === 0 ? ' show' : '' ?>" aria-labelledby="<?= $itemId ?>-heading" data-bs-parent="#tourFaqAccordion">
            <div class="accordion-body">
             <?= htmlspecialchars($faq['a'], ENT_QUOTES, 'UTF-8') ?>
            </div>
           </div>
          </div>
         <?php endforeach; ?>
        </div>
       </div>
      </div>
    <?php
    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => array_map(static function (array $faq): array {
            return [
                '@type' => 'Question',
                'name' => $faq['q'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $faq['a'],
                ],
            ];
        }, $faqs),
    ];
    ?>
    <script type="application/ld+json"><?= json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
    <?php
}
