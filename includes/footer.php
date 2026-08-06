<?php
/* /includes/footer.php
   Shared footer for all tour pages – matches MAPIO style.
   No JS here (keep scripts in page or a separate include to avoid duplicates).
*/
?>
<footer>
   <div class="container">

    <!-- local styles for separator + logo sizing/whitespace -->
    <style>
      /* Desktop-only subtle separator to the left of the PayPal column */
      @media (min-width: 768px){
        .footer-sep { border-left: 1px solid rgba(0,0,0,.06); }
      }
      /* Enforce clear space + size guidance for the acceptance mark */
      .pp-acceptance {
        width: 160px;         /* recommended desktop width */
        min-width: 150px;     /* don't go smaller than ~150px */
        max-width: 100%;      /* responsive on mobile */
        height: auto;         /* preserve aspect ratio */
        padding: 12px;        /* whitespace/clear space around logo */
        background: #fff;     /* light background per guidelines */
        border: 0;
      }
    </style>

    <div class="row">
     <div class="col-md-4">
      <h3>Need help?</h3>
     <a href="https://api.whatsapp.com/send?phone=56923993146" id="phone">+56 9 2399 3146</a>
      <a href="mailto:reservations@stampstour.com" id="email_footer">reservations@stampstour.com</a>
     </div>
     <div class="col-md-3">
      <h3>About/Legal</h3>
      <ul>
       <li><a href="refunds-cancellations.php">Refunds & Cancellations</a></li>
       <li><a href="privacy.php">Privacy</a></li>
       <li><a href="#" id="cookie-consent-manage">Manage cookies</a></li>
       <li><a href="#">FAQ</a></li>
      </ul>
     </div>
     <div class="col-md-3">
      <h3>Discover</h3>
      <ul>
       <li><a href="/blog">Blog</a></li>
       <li><a href="/gallery.php">Gallery</a></li>
      </ul>
     </div>

    <!-- PayPal Acceptance Mark column (rightmost) -->
      <div class="col-md-2 text-md-end text-start mt-3 mt-md-0 footer-sep">
        <!-- PayPal Logo (official acceptance mark + popup) -->
        <a href="https://www.paypal.com/webapps/mpp/paypal-popup"
           title="How PayPal Works"
           onclick="javascript:window.open('https://www.paypal.com/webapps/mpp/paypal-popup','WIPaypal','toolbar=no, location=no, directories=no, status=no, menubar=no, scrollbars=yes, resizable=yes, width=1060, height=700'); return false;">
          <img src="https://www.paypalobjects.com/webstatic/mktg/logo/AM_mc_vs_dc_ae.jpg"
               alt="PayPal Acceptance Mark"
               class="pp-acceptance">
        </a>
        <!-- End PayPal Logo -->
      </div>

    </div>
    <!-- End row -->

    <div class="row">
     <div class="col-md-12">
      <div id="social_footer">
       <ul>
        <li>
         <a href="https://www.instagram.com/stampstour/">
          <i class="bi bi-instagram"></i>
         </a>
        </li>
        <li>
         <a href="https://api.whatsapp.com/send?phone=56923993146">
          <i class="bi bi-whatsapp"></i>
         </a>
        </li>
        <li>
         <a href="https://www.facebook.com/stampstour">
          <i class="bi bi-facebook"></i>
         </a>
        </li>
       </ul>
       <p>&copy; Stampstour 2025</p>
      </div>
     </div>
    </div>
    <!-- End row -->
   </div>
   <!-- End container -->
   <div id="toTop"><i class="icon-up-open"></i></div>
</footer>
