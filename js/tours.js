
   $('#Img_carousel').sliderPro({
     width: 960,
     height: 500,
     fade: true,
     arrows: true,
     buttons: false,
     fullScreen: false,
     smallSize: 500,
     startSlide: 0,
     mediumSize: 1000,
     largeSize: 3000,
     thumbnailArrows: true,
     autoplay: false
   }).css('visibility', 'visible');
  
  <!-- Date and time pickers -->
 
  // Date and time pickers (DB-driven block dates by cupos=0, robust JSON handling)
$(function () {
  const expName = window.EXP_NAME || 'Maipo';
  const from = new Date().toISOString().slice(0,10);
  const to   = new Date(new Date().setMonth(new Date().getMonth()+6)).toISOString().slice(0,10);

  // IMPORTANT: relative path so it works in subfolders too
  const apiUrl = `api/cupos_status.php?nombre=${encodeURIComponent(expName)}&from=${from}&to=${to}`;

  fetch(apiUrl, { credentials: 'same-origin' })
    .then(async (r) => {
      const text = await r.text();  // read as plain text
      try {
        return JSON.parse(text);    // then try JSON parse
      } catch (e) {
        console.error('cupos_status.php raw response:', text);
        throw new Error('Invalid JSON from API');
      }
    })
    .then((data) => {
      const blocked = new Set((data.unavailable || []));
      $('input.date-pick').daterangepicker({
        singleDatePicker: true,
        autoApply: true,
        autoUpdateInput: true,
        minDate: new Date(),
        showCustomRangeLabel: false,
        parentEl: '.scroll-fix',
        locale: { format: 'MM-DD-YYYY' },
        isInvalidDate: function(date) {
          return blocked.has(date.format('YYYY-MM-DD'));
        }
      });
    })
    .catch(err => {
      console.warn('Availability load failed, initializing calendar without blocks:', err);
      // Fallback calendar so UX isn’t broken
      $('input.date-pick').daterangepicker({
        singleDatePicker: true,
        autoApply: true,
        autoUpdateInput: true,
        minDate: new Date(),
        showCustomRangeLabel: false,
        parentEl: '.scroll-fix',
        locale: { format: 'MM-DD-YYYY' }
      });
    });
});



   document.addEventListener('DOMContentLoaded', () => {
     const PRICE_PICKUP = 30;
     const prices = { adult: 0, child: 0 };
     let couponApplied = false;
     // Fetch tour prices from server
     const exp = window.EXP_NAME || 'Maipo';
     fetch(`get_prices.php?nombre=${encodeURIComponent(exp)}`)
       .then(res => res.json())
       .then(data => {
         prices.adult = parseFloat(data.precio_adulto) || 0;
         prices.child = parseFloat(data.precio_nino) || 0;
         document.getElementById('dynamic_price').textContent = prices.adult;
         document.getElementById('activity_name').value = data.nombre || 'Maipo';
         calcularTotal(); // initialize total with fetched prices
       })
       .catch(err => {
         console.error('Error fetching prices:', err);
         // if error, prices remain 0 (total will be 0, form submit disabled)
       });
     // Calculate total price
     function calcularTotal() {
       const adults   = parseInt(document.getElementById('adults').value)   || 0;
       const children = parseInt(document.getElementById('children').value) || 0;
       const infants  = parseInt(document.getElementById('infants').value)  || 0;
       const totalPassengers = adults + children;
       const pickUpCheckbox = document.getElementById('option_2');
       // Calculate pickup cost (flat $30 if selected, otherwise 0)
       let pickUpCost = pickUpCheckbox.checked ? PRICE_PICKUP : 0;
       // Disable pickup option if no passengers (adults or children)
       if (totalPassengers === 0) {
         pickUpCheckbox.checked = false;
         pickUpCheckbox.disabled = true;
         pickUpCost = 0;
       } else {
         pickUpCheckbox.disabled = false;
       }
       // Total = (adult tickets * price) + (child tickets * price) + pickup (flat)
       const total = (adults * prices.adult) + (children * prices.child) + pickUpCost;
       document.getElementById('total_price').textContent = total;
       // Enable/disable submit button: only allow booking if total > 0 (at least one passenger)
       const submitBtn = document.querySelector('#booking button[type="submit"]');
       if (submitBtn) submitBtn.disabled = (total === 0);
       return total;
     }
     // Recalculate total whenever passenger counts or pickup option change
     document.getElementById('adults').addEventListener('input',  calcularTotal);
     document.getElementById('children').addEventListener('input',  calcularTotal);
     document.getElementById('adults').addEventListener('change', calcularTotal);
     document.getElementById('children').addEventListener('change', calcularTotal);
     document.getElementById('option_2').addEventListener('change', calcularTotal);
     // Also recalc when plus/minus buttons are clicked (they modify input values)
     document.getElementById('booking').addEventListener('click', e => {
       if (e.target.closest('.numbers-row')) {
         setTimeout(calcularTotal, 50);
       }
     });
     // Real-time email validation reset
     const emailInput = document.getElementById('email_booking');
     emailInput.addEventListener('input', () => {
       emailInput.setCustomValidity(''); // clear custom validity on input
     });
     // Apply coupon code logic
     document.getElementById('apply_coupon_valpo').addEventListener('click', () => {
       const code = document.getElementById('coupon_code').value.trim();
       const currentTotal = parseFloat(document.getElementById('total_price').textContent) || 0;
       if (!code) {
         alert('Please enter a coupon code.');
         return;
       }
       // Send coupon code to server for validation
       fetch('api/apply_coupon.php', {
         method: 'POST',
         headers: { 'Content-Type': 'application/json' },
         body: JSON.stringify({ code: code, total: currentTotal })
       })
       .then(response => response.json())
       .then(data => {
         if (data.valid) {
           // Show discounted total
           document.getElementById('new_total_price').textContent = data.discountedTotal;
           document.getElementById('new_total_container').style.display = 'block';
           // Disable form fields except coupon inputs to lock the pricing
           document.querySelectorAll('#booking input, #booking select, #booking textarea').forEach(el => {
             if (el.id !== 'apply_coupon_valpo' && el.id !== 'coupon_code' && el.id !== 'email_booking') {
               el.disabled = true;
             }
           });
           // Disable plus/minus controls
           document.querySelectorAll('.numbers-row').forEach(row => {
             row.querySelectorAll('input, button, span, div').forEach(el => {
               el.style.pointerEvents = 'none';
               el.style.opacity = '0.5';
             });
           });
           // Ensure pickup checkbox is also disabled
           document.getElementById('option_2').disabled = true;
           // Disable coupon fields to prevent re-applying
           document.getElementById('coupon_code').disabled = true;
           document.getElementById('apply_coupon_valpo').disabled = true;
           couponApplied = true;
         } else {
           alert(data.message || 'Invalid coupon.');
         }
       })
       .catch(err => {
         console.error('Coupon error:', err);
         alert('Error validating the coupon.');
       });
     });
     // Handle form submission
     document.getElementById('booking').addEventListener('submit', function(event) {
       // Ensure email is filled (even if HTML5 validation is bypassed)
       const emailVal = emailInput.value.trim();
       if (!emailVal) {
         event.preventDefault();
         emailInput.setCustomValidity('Email field is mandatory');
         emailInput.reportValidity();
         return;
       }
       event.preventDefault(); // prevent default form submission to handle data
       // Prepare booking data
       const pickupValue = document.getElementById('option_2').checked ? 'Yes' : 'No';
       const totalDOM = parseFloat(document.getElementById('total_price').textContent) || 0;
       const discountedTotal = couponApplied 
                                 ? (parseFloat(document.getElementById('new_total_price').textContent) || totalDOM)
                                 : totalDOM;
       const formData = {
         name_booking:      document.getElementById('name_booking').value,
         last_name_booking: document.getElementById('last_name_booking').value,
         email_booking:     document.getElementById('email_booking').value,
         phone_booking:     document.getElementById('phone_booking').value,
         date_booking:      document.getElementById('date_booking').value,
         adults:            document.getElementById('adults').value,
         children:          document.getElementById('children').value,
         infants:           document.getElementById('infants').value,
         airport_pick_up:   pickupValue,
         activity_name:     document.getElementById('activity_name').value,
         coupon_code:       document.getElementById('coupon_code').value.trim(),
         subtotal:          totalDOM,            // original total before discount
         total_price:       discountedTotal      // final total after coupon (if applied)
       };
       // Create a form and submit (or use AJAX) – here we create a hidden form for POST
       const form = document.createElement('form');
       form.action = 'submit.php';
       form.method = 'POST';
       for (const key in formData) {
         const hiddenInput = document.createElement('input');
         hiddenInput.type = 'hidden';
         hiddenInput.name = key;
         hiddenInput.value = formData[key];
         form.appendChild(hiddenInput);
       }
       document.body.appendChild(form);
       form.submit();
     });
     // Initial total calculation on page load (in case default values or fetched prices need to be applied)
     calcularTotal();
   });

  <!-- Optional: "Book Now" floating button on header (unchanged from original) -->
  
   $(function() {
     var $win = $(window),
         $booking = $('#booking'),
         $headerRow = $('header > .container > .row').first().css('position','relative'),
         $hdrWrap = $('<div id="hdr-booking-wrap"></div>').css({
           position: 'absolute',
           top: '50%',
           left: '50%',
           transform: 'translate(-50%, -50%)',
           zIndex: 1100,
           display: 'none'
         }).appendTo($headerRow),
         $hdrBtn = $('<button class="btn_full">Book now</button>').appendTo($hdrWrap),
         $banner = $('.tour-banner, .parallax-window'),
         stickyThreshold = ($banner.outerHeight() || 470) - ($headerRow.outerHeight() || 0);
     function toggleHdrBtn() {
       var scrollTop = $win.scrollTop(),
           winBottom = scrollTop + $win.height(),
           formTop = $booking.offset().top;
       if (scrollTop > stickyThreshold && winBottom < formTop) {
         $hdrWrap.fadeIn(200);
       } else {
         $hdrWrap.fadeOut(200);
       }
     }
     $win.on('scroll resize', toggleHdrBtn);
     toggleHdrBtn();
     $hdrBtn.on('click', function() {
       $('html, body').animate({ scrollTop: $booking.offset().top - 20 }, 500);
     });
   });
  
  <!-- Itinerary toggle function -->
 
   function toggleItinerary() {
     const wrapper = document.getElementById("itinerary-wrapper");
     const button = document.getElementById("toggle-btn");
     const fade   = document.getElementById("fade-effect");
     const isExpanded = wrapper && wrapper.classList.contains("expanded");
     if (!wrapper || !button || !fade) return;
     wrapper.classList.toggle("expanded");
     wrapper.style.maxHeight = isExpanded ? "180px" : "5000px";
     fade.style.opacity      = isExpanded ? "1" : "0";
     button.innerText        = isExpanded ? "See more" : "See less";
   }
  