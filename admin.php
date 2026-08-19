<?php
session_start();
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1">

	<meta name="description" content="Stamp's tour - Premium Tour agency based in Chile.">
	<meta name="author" content="Ansonika">
	<title>Stamp's Tour - Admin</title>

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
	
	<!-- SPECIFIC CSS -->
	<link href="css/admin.css" rel="stylesheet">
	
	<!-- CUSTOM CSS -->
	<link href="css/custom.css" rel="stylesheet">
	<style>
    /* Modal styles */
    .modal-overlay {
      position: fixed; top:0; left:0;
      width:100%; height:100%;
      background: rgba(0,0,0,0.6);
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 1000;
    }
    .modal {
      background: #fff;
      padding: 1rem;
      border-radius: 5px;
      max-width: 90%;
      max-height: 80%;
      overflow-y: auto;
      position: relative;
      box-shadow: 0 2px 10px rgba(0,0,0,0.2);
    }
    .modal .close {
      position: absolute;
      top: 0.5rem;
      right: 0.5rem;
      background: none;
      border: none;
      font-size: 1.5rem;
      cursor: pointer;
    }
    /* Make days look clickable */
    #calendar tbody td { cursor: pointer; }
  </style>

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
                            <li><a href="https://www.instagram.com/stampstour/"><i class="bi bi-instagram"></i></a></li>
                            <li><a href="https://www.facebook.com/stampstour"><i class="bi bi-facebook"></i></a></li>
                            <li><a href="https://api.whatsapp.com/send?phone=56923993146"><i class="bi bi-whatsapp"></i></a></li>
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
       <a href="/">
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
        <img alt="City tours" height="34" src="img/logo_sticky.png" width="160"/>
       </div>
       <a class="open_close" href="#" id="close_in">
        <i class="icon_set_1_icon-77">
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
	</header>
	<!-- End Header -->

	<section class="parallax-window" data-parallax="scroll" data-image-src="img/admin_top.jpg" data-natural-width="1400" data-natural-height="">
		<div class="parallax-content-1 opacity-mask" data-opacity-mask="rgba(0, 0, 0, 0.4)">
			<div class="animated fadeInDowsn">
				<h1>Bienvenido!</h1>
				<p>Panel de Control</p>
			</div>
		</div>
	</section>
	<!-- End section -->

	<main>
		
		<!-- End Position -->

		<div class="margin_60 container">
			<div id="tabs" class="tabs">
				<nav>
					<ul>
						<li><a href="#section-1" class="icon-booking"><span>Calendario</span></a>
						</li>
						<li><a href="#section-2" class="icon-wishlist"><span>Reservas</span></a>
						</li>
						<li><a href="#section-3" class="icon-settings"><span>Reportes</span></a>
						</li>
						<li><a href="#section-4" class="icon-profile"><span>RECO</span></a>
						</li>
						<li><a href="#section-5" class="icon-settings"><span>Admin Tools</span></a>
						</li>
					</ul>
				</nav>
				<div class="content">

					<section id="section-1">
  <style>
    /* === Controls === */
    .calendar-controls { display: flex; gap: 1rem; margin-bottom: 1rem; }
    .calendar-controls select { padding: .25rem; font-size: 1rem; border:1px solid #ccc; border-radius:4px; }
    /* === Calendar === */
    .calendar-container { width: 100%; }
    #calendar { width: 100%; border-collapse: collapse; }
    #calendar th, #calendar td { border:1px solid #ddd; padding:.5rem; vertical-align:top; }
    #calendar th { background:#d9534f; color:#fff; font-weight:normal; }
    #calendar td.outside { background:#eee; color:#888; }
    #calendar tbody td { cursor:pointer; }
    .reserva-badge { display:block; margin:2px 0; padding:2px 4px; font-size:.7rem; border-radius:4px; background:#0275d8; color:#fff; }
  </style>

  <div class="strip_booking">
    <div class="row">
      <div class="col-lg-12">
        <!-- Controls -->
        <div class="calendar-controls">
          <select id="month-select"></select>
          <select id="year-select"></select>
        </div>
        <!-- Calendar Table -->
        <div class="calendar-container">
          <table id="calendar">
            <thead>
              <tr><th>LUN</th><th>MAR</th><th>MIÉ</th><th>JUE</th><th>VIE</th><th>SÁB</th><th>DOM</th></tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- No modal—detail opens in new tab -->

  <script>
  (function(){
    const monthNames = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
    const selM = document.getElementById('month-select');
    const selY = document.getElementById('year-select');
    const tbody = document.querySelector('#calendar tbody');
    const today = new Date(), thisYear = today.getFullYear();

    // Populate month & year selects
    monthNames.forEach((m,i)=>{ const o=new Option(m,i); if(i===today.getMonth()) o.selected=true; selM.add(o); });
    for(let y=thisYear-5; y<=thisYear+5; y++){ const o=new Option(y,y); if(y===thisYear) o.selected=true; selY.add(o); }

    function render(m, y){
      tbody.innerHTML = '';
      const first = new Date(y,m,1).getDay()||7;
      const dim   = new Date(y,m+1,0).getDate();
      const dip   = new Date(y,m,0).getDate();
      let row = document.createElement('tr');

      // Previous month spill
      for(let i=1;i<first;i++){
        const td = document.createElement('td');
        td.textContent = dip - (first - 1) + i;
        td.classList.add('outside');
        row.append(td);
      }
      // Current month days
      for(let d=1; d<=dim; d++){
        if(row.children.length===7){ tbody.append(row); row=document.createElement('tr'); }
        const td = document.createElement('td');
        td.textContent = d;
        row.append(td);
      }
      // Next month spill
      let nd=1;
      while(row.children.length<7){
        const td = document.createElement('td');
        td.textContent = nd++;
        td.classList.add('outside');
        row.append(td);
      }
      tbody.append(row);

      // Paint badges and attach click to open detail in new tab
      tbody.querySelectorAll('td').forEach(td=>{
        const raw = td.textContent.trim();
        td.innerHTML = '';
        if(td.classList.contains('outside')){
          td.textContent = raw;
          return;
        }
        const dayNum = parseInt(raw,10);
        if(!dayNum) return;

        // Day number block
        const dayDiv = document.createElement('div');
        dayDiv.textContent = raw;
        td.append(dayDiv);

        // Spacer
        const sp = document.createElement('div');
        sp.innerHTML = '&nbsp;';
        td.append(sp);

        // Totals per activity
        const mon = parseInt(selM.value,10) + 1;
        const yr  = parseInt(selY.value,10);
        const iso = `${yr}-${String(mon).padStart(2,'0')}-${String(dayNum).padStart(2,'0')}`;
        fetch(`reserva_dia.php?day=${dayNum}&month=${mon}&year=${yr}&ajax=1`)
          .then(r=>r.json())
          .then(list=>{
            list.forEach(item=>{
              const b = document.createElement('div');
              b.classList.add('reserva-badge');
              b.textContent = `${item.actividad}: ${item.total_pasajeros}`;
              td.append(b);
            });
          });

        // Open detail in new tab on click
        td.onclick = () => {
          window.open(`detalle_reservas.php?date=${iso}`, '_blank');
        };
      });
    }

    selM.onchange = () => render(+selM.value, +selY.value);
    selY.onchange = () => render(+selM.value, +selY.value);
    render(today.getMonth(), thisYear);
  })();
  </script>

</section>

					<!-- End section 1 -->

					<section id="section-2">
						  <div class="row mb-4">
    <div class="col-12">
      <form id="search-unificado" class="d-flex flex-wrap align-items-end">

        <!-- Selector de tipo de búsqueda -->
        <div class="form-group mr-3 mb-2">
          <label for="search-type" class="font-weight-bold">Buscar por:</label>
          <select id="search-type" class="form-control">
            <option value="">-- Seleccione --</option>
            <option value="date">Rango de fechas</option>
            <option value="name">Titular</option>
            <option value="code">Código</option>
          </select>
        </div>

        <!-- Rango de fechas -->
        <div id="group-date" class="form-group mr-3 mb-2" style="display:none;">
          <label for="start-date" class="font-weight-bold">Desde:</label>
          <input type="date" id="start-date" class="form-control">
        </div>
        <div id="group-end-date" class="form-group mr-3 mb-2" style="display:none;">
          <label for="end-date" class="font-weight-bold">Hasta:</label>
          <input type="date" id="end-date" class="form-control">
        </div>
        <button type="button" id="btn-search-date" class="btn_1 green mb-2 mr-2" style="display:none;">Buscar</button>

        <!-- Búsqueda por nombre -->
        <div id="group-name" class="form-group mr-3 mb-2" style="display:none;">
          <label for="search-term" class="font-weight-bold">Titular:</label>
          <input type="text" id="search-term" placeholder="Ingrese nombre…" class="form-control">
        </div>

        <!-- Búsqueda por código -->
        <div id="group-code" class="form-group mr-3 mb-2" style="display:none;">
          <label for="search-code" class="font-weight-bold">Código:</label>
          <input type="text" id="search-code" placeholder="Ingrese código…" class="form-control">
        </div>
        <button type="button" id="btn-search-code" class="btn_1 green mb-2 mr-2" style="display:none;">Buscar</button>

      </form>
    </div>
  </div>

  <!-- Aquí se volcarán los resultados -->
  <div id="search-results" class="row mb-4"></div>
					</section>
					<!-- End section 2 -->

					<section id="section-3">
						Brinda acceso a reportes para la gestión y toma de decisiones, tales como días trabajados por conductor o guía, cálculo de comisiones, entre otros indicadores de desempeño. 

					</section>
					<!-- End section 3 -->

					<section id="section-4">
					<h1>Registro de Control y Operaciones</h1>
					
					En esta sección se visualiza el consolidado de rutas diarias, además de permitir la asignación de guías y conductores de forma organizada. El objetivo es automatizar al máximo la generación de planillas operativas, de modo que el rol del coordinador se limite únicamente a validar y asignar los recursos humanos necesarios.

					Para lograr este nivel de automatización, se desarrollará un algoritmo que ordene los hoteles según su ubicación geográfica (de oriete a poniente) y calcule el tiempo de traslado entre puntos, generando un itinerario optimizado. Además, el sistema contemplará reglas dinámicas: por ejemplo, si se excede un número determinado de pasajeros (como 14 pax por actividad), el itinerario se segmentará automáticamente en subgrupos, considerando variables como zona y idioma.

					Una alternativa de alto valor agregado es la implementación de algoritmos de aprendizaje automático, aprovechando la base de datos histórica de los últimos tres años. Esto permitirá afinar la optimización de rutas y asignaciones, generando itinerarios que se ajusten a patrones reales de operación. El resultado final se presentará de forma clara al usuario, quien podrá revisarlo y realizar ajustes manuales si lo considera necesario.

					En conjunto, estos módulos permiten integrar reservas, planificación operativa y análisis de resultados en una misma plataforma, garantizando una operación eficiente, escalable y con alto potencial de automatización.	
					</section>
					<!-- End section 4 -->

					<section id="section-5">
					<h1>Admin Tools</h1>
					<p>Quick links to the operations &amp; reporting tools.</p>
					<div class="row">
						<div class="col-md-4 col-sm-6 mb-4">
							<div class="card h-100 shadow-sm">
								<div class="card-body">
									<h5 class="card-title">Dashboard</h5>
									<p class="card-text">Daily operations overview by tour instance.</p>
									<a href="/admin/dashboard.php" class="btn btn-primary btn-sm">Open</a>
								</div>
							</div>
						</div>
						<div class="col-md-4 col-sm-6 mb-4">
							<div class="card h-100 shadow-sm">
								<div class="card-body">
									<h5 class="card-title">Check Reservations</h5>
									<p class="card-text">Reconcile planilla, OTA files, and web reservations.</p>
									<a href="/admin/check.php" class="btn btn-primary btn-sm">Open</a>
								</div>
							</div>
						</div>
						<div class="col-md-4 col-sm-6 mb-4">
							<div class="card h-100 shadow-sm">
								<div class="card-body">
									<h5 class="card-title">Consolidate Day</h5>
									<p class="card-text">Merge OTA and planilla data for a single day.</p>
									<a href="/admin/consolidate-day.php" class="btn btn-primary btn-sm">Open</a>
								</div>
							</div>
						</div>
						<div class="col-md-4 col-sm-6 mb-4">
							<div class="card h-100 shadow-sm">
								<div class="card-body">
									<h5 class="card-title">Consolidate Month</h5>
									<p class="card-text">Multi-day booking consolidation report.</p>
									<a href="/admin/consolidate-month.php" class="btn btn-primary btn-sm">Open</a>
								</div>
							</div>
						</div>
						<div class="col-md-4 col-sm-6 mb-4">
							<div class="card h-100 shadow-sm">
								<div class="card-body">
									<h5 class="card-title">Closing</h5>
									<p class="card-text">Daily operational closing with driver/guide assignment.</p>
									<a href="/admin/closing.php" class="btn btn-primary btn-sm">Open</a>
								</div>
							</div>
						</div>
						<div class="col-md-4 col-sm-6 mb-4">
							<div class="card h-100 shadow-sm">
								<div class="card-body">
									<h5 class="card-title">Preferentials</h5>
									<p class="card-text">Travel-agent preferential pricing booking page.</p>
									<a href="/admin/preferentials.php" class="btn btn-primary btn-sm">Open</a>
								</div>
							</div>
						</div>
						<div class="col-md-4 col-sm-6 mb-4">
							<div class="card h-100 shadow-sm">
								<div class="card-body">
									<h5 class="card-title">Private Booking</h5>
									<p class="card-text">Custom one-off booking page for a specific reservation.</p>
									<a href="/admin/private-booking.php" class="btn btn-primary btn-sm">Open</a>
								</div>
							</div>
						</div>
						<div class="col-md-4 col-sm-6 mb-4">
							<div class="card h-100 shadow-sm">
								<div class="card-body">
									<h5 class="card-title">Blog</h5>
									<p class="card-text">Create and manage blog posts.</p>
									<a href="/admin/blog.php" class="btn btn-primary btn-sm">Open</a>
								</div>
							</div>
						</div>
						<div class="col-md-4 col-sm-6 mb-4">
							<div class="card h-100 shadow-sm">
								<div class="card-body">
									<h5 class="card-title">Gallery Upload</h5>
									<p class="card-text">Upload and manage tour gallery photos.</p>
									<a href="/admin/gallery-upload.php" class="btn btn-primary btn-sm">Open</a>
								</div>
							</div>
						</div>
						<div class="col-md-4 col-sm-6 mb-4">
							<div class="card h-100 shadow-sm">
								<div class="card-body">
									<h5 class="card-title">Getnet Reconciliation</h5>
									<p class="card-text">Reconcile Getnet payment events against reservations.</p>
									<a href="/admin/getnet-reconcile.php" class="btn btn-primary btn-sm">Open</a>
								</div>
							</div>
						</div>
						<div class="col-md-4 col-sm-6 mb-4">
							<div class="card h-100 shadow-sm">
								<div class="card-body">
									<h5 class="card-title">PayPal Reprocessing</h5>
									<p class="card-text">Recover stuck PayPal webhook events.</p>
									<a href="/admin/paypal-reprocess.php" class="btn btn-primary btn-sm">Open</a>
								</div>
							</div>
						</div>
						<div class="col-md-4 col-sm-6 mb-4">
							<div class="card h-100 shadow-sm">
								<div class="card-body">
									<h5 class="card-title">Refund by Reference</h5>
									<p class="card-text">Look up a reservation by STAMP code and issue a PayPal refund.</p>
									<a href="/admin/refund-by-reference.php" class="btn btn-primary btn-sm">Open</a>
								</div>
							</div>
						</div>
					</div>
					</section>
					<!-- End section 5 -->

					</div>
					<!-- End content -->
				</div>
				<!-- End tabs -->
			</div>
			<!-- end container -->
	</main>
	<!-- End main -->

	<footer class="revealed">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <h3>Need help?</h3>
                    <a href="tel://56923993146" id="phone">+56 923993146</a>
                    <a href="mailto:help@citytours.com" id="email_footer">reservations@stamptour.com</a>
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
                            <li><a href="https://www.instagram.com/stampstour/"><i class="bi bi-instagram"></i></a></li>
                            <li><a href="https://api.whatsapp.com/send?phone=56923993146"><i class="bi bi-whatsapp"></i></a></li>
                            <li><a href="https://www.facebook.com/stampstour"><i class="bi bi-facebook"></i></a></li>
                        </ul>
                        <p>© Stamp's Tour 2025</p>
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

	<!-- Specific scripts -->
	<script src="js/tabs.js"></script>
	<script>
		new CBPFWTabs(document.getElementById('tabs'));
	</script>
	<script>
		$('.wishlist_close_admin').on('click', function (c) {
			$(this).parent().parent().parent().fadeOut('slow', function (c) {});
		});
	</script>
	<script>
  function escapeHtml(str) {
    return String(str ?? '').replace(/[&<>"']/g, c => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[c]));
  }

  $(function(){
    const groups = {
      date: ['#group-date','#group-end-date','#btn-search-date'],
      name: ['#group-name'],
      code: ['#group-code','#btn-search-code']
    };

    $('#search-type').on('change', function(){
      $('#search-results').empty();
      Object.values(groups).flat().forEach(sel => $(sel).hide());
      const sel = $(this).val();
      if (groups[sel]) groups[sel].forEach(s => $(s).show());
    });

    // Fecha
    $('#btn-search-date').on('click', function(){
      const start = $('#start-date').val(),
            end   = $('#end-date').val();
      if(!start||!end){ alert('Completa ambas fechas.'); return; }
      $.getJSON('search_by_date.php',{start,end})
        .done(renderList)
        .fail(()=>alert('Error al buscar por fechas.'));
    });

    // Código
    $('#btn-search-code').on('click', function(){
      const codigo = $('#search-code').val().trim();
      if(!codigo){ alert('Ingresa un código.'); return; }
      $.getJSON('search_by_codigo.php',{codigo})
        .done(r => {
          if(!r.id_reserva){ alert('No se encontró la reserva.'); return; }
          renderSingle(r);
        })
        .fail(()=>alert('Error al buscar por código.'));
    });

    // Nombre
    let nameTimer;
    $('#search-term').on('input', function(){
      clearTimeout(nameTimer);
      const term = $(this).val().trim();
      if(!term){ $('#search-results').empty(); return; }
      nameTimer = setTimeout(()=>{
        $.getJSON('search_reservas.php',{term})
          .done(renderList)
          .fail(()=>alert('Error al buscar por nombre.'));
      }, 300);
    });

   function renderList(data){
      const $c = $('#search-results').empty();
      if(!data.length){
        return $c.append('<div class="col-12"><p>No hay reservas.</p></div>');
      }
      data.forEach(r => {
        const listHtml = `
          <ul class="list-group mb-4">
            <li class="list-group-item" style="background-color:#000; color:#fff;"><strong>Titular:</strong> ${escapeHtml(r.nombre_titular)}</li>
            <li class="list-group-item"><strong>Experiencia:</strong> ${escapeHtml(r.experiencia)}</li>
            <li class="list-group-item"><strong>Fecha Reserva:</strong> ${escapeHtml(r.fecha_reserva)}</li>
            <li class="list-group-item"><strong>Fecha Actividad:</strong> ${escapeHtml(r.fecha_actividad)}</li>
            <li class="list-group-item"><strong>Adultos:</strong> ${escapeHtml(r.adultos)}</li>
            <li class="list-group-item"><strong>Niños:</strong> ${escapeHtml(r.ninos)}</li>
            <li class="list-group-item"><strong>Infantes:</strong> ${escapeHtml(r.infantes)}</li>
            <li class="list-group-item"><strong>Pickup Aeropuerto:</strong> ${escapeHtml(r.airport_pickup)}</li>
            <li class="list-group-item"><strong>Hotel:</strong> ${escapeHtml(r.hotel)}</li>
            <li class="list-group-item"><strong>Email:</strong> ${escapeHtml(r.correo_electronico)}</li>
            <li class="list-group-item"><strong>Teléfono:</strong> ${escapeHtml(r.telefono)}</li>
            <li class="list-group-item"><strong>Total Pagado:</strong> $${escapeHtml(r.total_pagado)}</li>
            <li class="list-group-item"><strong>Vendedor:</strong> ${escapeHtml(r.nombre_vendedor)}</li>
            ${r.codigo_externo ? `<li class="list-group-item"><strong>Código:</strong> ${escapeHtml(r.codigo_externo)}</li>` : ''}
          </ul>`;
        $c.append(`<div class="col-12">${listHtml}</div>`);
      });
    }

 function renderSingle(r){
      const $c = $('#search-results').empty();
      const listHtml = `
        <ul class="list-group mb-4">
          <li class="list-group-item" style="background-color:#000; color:#fff;"><strong>Titular:</strong> ${escapeHtml(r.nombre_titular)}</li>
          <li class="list-group-item"><strong>Experiencia:</strong> ${escapeHtml(r.experiencia)}</li>
          <li class="list-group-item"><strong>Fecha Reserva:</strong> ${escapeHtml(r.fecha_reserva)}</li>
          <li class="list-group-item"><strong>Fecha Actividad:</strong> ${escapeHtml(r.fecha_actividad)}</li>
          <li class="list-group-item"><strong>Adultos:</strong> ${escapeHtml(r.adultos)}</li>
          <li class="list-group-item"><strong>Niños:</strong> ${escapeHtml(r.ninos)}</li>
          <li class="list-group-item"><strong>Infantes:</strong> ${escapeHtml(r.infantes)}</li>
          <li class="list-group-item"><strong>Pickup Aeropuerto:</strong> ${escapeHtml(r.airport_pickup)}</li>
          <li class="list-group-item"><strong>Hotel:</strong> ${escapeHtml(r.hotel)}</li>
          <li class="list-group-item"><strong>Email:</strong> ${escapeHtml(r.correo_electronico)}</li>
          <li class="list-group-item"><strong>Teléfono:</strong> ${escapeHtml(r.telefono)}</li>
          <li class="list-group-item"><strong>Total Pagado:</strong> $${escapeHtml(r.total_pagado)}</li>
          <li class="list-group-item"><strong>Vendedor:</strong> ${escapeHtml(r.nombre_vendedor)}</li>
          ${r.codigo_externo ? `<li class="list-group-item"><strong>Código:</strong> ${escapeHtml(r.codigo_externo)}</li>` : ''}
        </ul>`;
      $c.append(`<div class="col-12">${listHtml}</div>`);
    }
  });
</script>


</body>

</html>