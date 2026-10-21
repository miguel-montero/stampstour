<?php
/**
 * Local-only config for admin/refresh-reservations.php.
 *
 * Fill these in after deploying scripts/google-apps-script/next-day-reservations.gs
 * as a Web App (Deploy > New deployment > Web app, Execute as: Me,
 * Who has access: Anyone) - see the comment above doGet() in that file
 * for the exact steps, including setting the matching REFRESH_SECRET
 * Script Property.
 *
 * Gitignored on purpose - this secret lets anyone who has it trigger a
 * Gmail scan, same as tests/test_db_config.php keeps real DB credentials
 * out of git.
 */
return [
    'webapp_url' => 'https://script.google.com/macros/s/AKfycbz_Za57O7ip0L3LzrTGAyWytsNp8vnrqQ5f3hjzWKKjadJyTNyCRfSegYhEbcizgu_Y/exec',
    'secret' => 'D0ctorC4t',
];
