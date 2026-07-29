<?php
return [
  'from_email' => 'reservations@stampstour.com',
  'from_name'  => "Stamp's Tour",

  // Google Workspace SMTP
  'host'       => 'smtp.gmail.com',
  'username'   => 'reservations@stampstour.com',
  'password'   => 'dbpy vqim jono rbmh',   // see steps below
  'port'       => 587,                   // 465 = SSL, or use 587 with 'tls'
  'secure'     => 'tls',

  'reply_to'   => ['email' => 'reservations@stampstour.com', 'name' => "Stamp's Tour"],
];
