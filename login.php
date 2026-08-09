<?php
session_start();

if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    header('Location: /login.php');
    exit;
}

$error = false;

// load DB credentials from parent folder
require_once __DIR__ . '/../db_config.php';

// now $db_host, $db_name, $db_user and $db_pass are available
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = trim($_POST['username'] ?? '');
    $p = $_POST['password'] ?? '';

    $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    $stmt = $pdo->prepare('SELECT password FROM users WHERE username = ?');
    $stmt->execute([$u]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row && password_verify($p, $row['password'])) {
        $_SESSION['username'] = $u;
        header('Location: admin.php');
        exit;
    }

    $error = true;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Stamps tour - Premium Tour agency based in Chile">
    <meta name="author" content="Miguel">
    <title>Stamps Tour - Log In</title>

    <!-- Favicons-->
    <link rel="shortcut icon" href="img/favicon.ico" type="image/x-icon">
    <link rel="apple-touch-icon" type="image/x-icon" href="img/apple-touch-icon-57x57-precomposed.png">
    <link rel="apple-touch-icon" type="image/x-icon" sizes="72x72" href="img/apple-touch-icon-72x72-precomposed.png">
    <link rel="apple-touch-icon" type="image/x-icon" sizes="114x114" href="img/apple-touch-icon-114x114-precomposed.png">
    <link rel="apple-touch-icon" type="image/x-icon" sizes="144x144" href="img/apple-touch-icon-144x144-precomposed.png">

    <!-- GOOGLE WEB FONT -->
    <link href="https://fonts.googleapis.com/css2?family=Gochi+Hand&family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- COMMON CSS -->
	<link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
	<link href="css/vendors.css" rel="stylesheet">
	
	<!-- CUSTOM CSS -->
	<link href="css/custom.css" rel="stylesheet">
        
</head>
<body>

    <div id="preloader">
        <div class="sk-spinner sk-spinner-wave">
            <div class="sk-rect1"></div>
            <div class="sk-rect2"></div>
            <div class="sk-rect3"></div>
            <div class="sk-rect4"></div>
            <div class="sk-rect5"></div>
        </div>
    </div>
    <!-- End Preload -->

    <div class="layer"></div>
    <!-- Mobile menu overlay mask -->

    <!-- Header================================================== -->
    <header>
        <div id="top_line">
            <div class="container">
                <div class="row">
                    <div class="col-6"><i class="icon-phone"></i><strong>56 923993146</strong></div>
                    <div class="col-6">
                        <ul id="top_links">
                            <!-- li><a href="#sign-in-dialog" id="access_link">Sign in</a></li>
                            <li><a href="wishlist.html" id="wishlist_link">Wishlist</a></li>
                            template</a></li> -->
                            <li><a href="https://www.instagram.com/stampstour/" aria-label="Instagram"><i class="bi bi-instagram" aria-hidden="true"></i></a></li>
                            <li><a href="https://www.facebook.com/stampstour" aria-label="Facebook"><i class="bi bi-facebook" aria-hidden="true"></i></a></li>
                            <li><a href="https://api.whatsapp.com/send?phone=56923993146" aria-label="WhatsApp"><i class="bi bi-whatsapp" aria-hidden="true"></i></a></li>
                        </ul>
                    </div>
                </div><!-- End row -->
            </div><!-- End container-->
        </div><!-- End top line-->
   <div class="container">
    <div class="row">
     <div class="col-3">
      <div id="logo">
       <a href="/">
        <img alt="City tours" class="logo_normal" height="47" width="132" src="img/logolargo.png"/>
       </a>
       <a href="/" aria-label="Stamps Tour">
        <img alt="City tours" class="logo_sticky" height="34" width="147" src="img/logo_sticky.png"/>
       </a>
      </div>
     </div>
     <nav class="col-9">
      <a class="cmn-toggle-switch cmn-toggle-switch__htx open_close" href="javascript:void(0);">
       <span>
        Menu mobile
       </span>
      </a>
      <div class="main-menu">
       <div id="header_menu">
        <img alt="StampsTour" height="34" src="img/logo_sticky.png" width="160"/>
       </div>
       <a class="open_close" href="#" id="close_in" aria-label="Close menu">
        <i class="icon_set_1_icon-77" aria-hidden="true">
        </i>
       </a>
       <ul>
        <li>
         <a href="/">
          Home
         </a>
        </li>
        <li class="submenu">
         <a class="show-submenu" href="javascript:void(0);">
          Tours
          <i class="icon-down-open-mini">
          </i>
         </a>
         <ul>
          <li>
           <a href="Valparaiso.html">
            Valparaíso
           </a>
          </li>
          <li>
           <a href="Maipo.html">
            Isla de Maipo
           </a>
          </li>
          <li>
           <a href="Andes.html">
            Andes Tour
           </a>
          </li>
          <li>
           <a href="Santiago.html">
            Santiago City Tour
           </a>
          </li>
         </ul>
        </li>
        <li>
         <a href="gallery.html">
          Gallery
         </a>
        </li>
        <li>
         <a href="contact_us.html">
          Contact us
         </a>
        </li>
       </ul>
      </div>
      <!-- End main-menu -->
     </nav>
    </div>
   </div>
        </div><!-- container -->
    </header><!-- End Header -->
    
	<main>
    <section id="hero" class="login">
    	<div class="container">
        	<div class="row justify-content-center">
            	<div class="col-xl-4 col-lg-5 col-md-6 col-sm-8">
                	<div id="login">
                    		<div class="text-center"><img src="img/logo_sticky.png" alt="Image" width="160" height="34"></div>
                            <hr>
                            <form method="post" action="login.php">
							<div class="divider"><span>LOG IN</span></div>
                                <div class="form-group">
                                    <label>Username</label>
                                    <input name="username" type="text" class="form-control" placeholder="Username">
                                </div>
                                <div class="form-group">
                                    <label>Password</label>
                                    <input name="password" type="password" class="form-control" placeholder="Password">
                                </div>
                                <p class="small">
                                    <a href="#">Forgot Password?</a>
                                </p>
                                <?php if ($error): ?>
                                  <div class="text-danger small mt-2">
                                    Your username or password is incorrect.
                                  </div>
                                <?php endif; ?>
                                <button type="submit" class="btn_full">Sign in</button>
                            </form>
                        </div>
                </div>
            </div>
        </div>
    </section>
	</main><!-- End main -->
	
	<footer>
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <h3>Need help?</h3>
                    <a href="tel://56923993146" id="phone">+56 923993146</a>
                    <a href="mailto:help@citytours.com" id="email_footer">reservations@stampstour.com</a>
                </div>
                <div class="col-md-3">
                    <h3>About</h3>
                    <ul>
                        <li><a href="#">About us</a></li>
                        <li><a href="#">FAQ</a></li>
                        <li><a href="#">Terms and condition</a></li>
                    </ul>
                </div>
                <div class="col-md-3">
                    <h3>Discover</h3>
                    <ul>
                        <li><a href="#">Community blog</a></li>
                        <li><a href="#">Gallery</a></li>
                    </ul>
                </div>
               
            </div><!-- End row -->
            <div class="row">
                <div class="col-md-12">
                    <div id="social_footer">
                        <ul>
                            <li><a href="https://www.instagram.com/stampstour/" aria-label="Instagram"><i class="bi bi-instagram" aria-hidden="true"></i></a></li>
                            <li><a href="https://api.whatsapp.com/send?phone=56923993146" aria-label="WhatsApp"><i class="bi bi-whatsapp" aria-hidden="true"></i></a></li>
                            <li><a href="https://www.facebook.com/stampstour" aria-label="Facebook"><i class="bi bi-facebook" aria-hidden="true"></i></a></li>
                        </ul>
                        <p>© Stampstour 2025</p>
                    </div>
                </div>
            </div><!-- End row -->
        </div><!-- End container -->
    </footer><!-- End footer -->

	<div id="toTop"></div><!-- Back to top button -->
	
	<!-- Search Menu -->
	<div class="search-overlay-menu">
		<span class="search-overlay-close"><i class="icon_set_1_icon-77"></i></span>
		<form role="search" id="searchform" method="get">
			<input value="" name="q" type="text" placeholder="Search..." />
			<button type="submit"><i class="icon_set_1_icon-78"></i>
			</button>
		</form>
	</div><!-- End Search Menu -->
	

	<!-- Common scripts -->
	<script src="js/jquery-3.7.1.min.js"></script>
	<script src="js/common_scripts_min.js"></script>
	<script src="js/functions.js"></script>
	


  </body>
</html>