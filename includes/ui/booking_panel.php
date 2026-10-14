<?php
// includes/ui/booking_panel.php
// The booking form, restyled. Every id/name/class below is load-bearing:
//   js/tours.js       #booking (submit + action), #activity_name, #name_booking,
//                     #last_name_booking, #email_booking, #phone_booking,
//                     #date_booking (.date-pick -> daterangepicker), #adults,
//                     #children, #infants, #option_2 (pickup checkbox, kept
//                     hidden + unchecked), #coupon_code, #apply_coupon_valpo,
//                     #total_price, #new_total_container (inline display:none,
//                     shown by tours.js), #new_total_price, button[type=submit]
//   js/functions.js   .numbers-row (appends the +/- buttons), input.qty2
//   js/maipo-options.js  #activity_name + [data-tour-options] radios
// Don't rename any of them without updating those scripts.

function ui_booking_panel(array $options, array $prices, string $selected, string $action = 'book_tour.php'): void
{
    $exp = $options[$selected]['exp_name'] ?? '';
    ?>
    <div class="tw:rounded-2xl tw:border tw:border-teal/50 tw:bg-[linear-gradient(135deg,rgba(0,132,137,.25),rgba(224,79,103,.12))] tw:p-5 tw:md:p-7">
     <form action="<?= ui_h($action) ?>" method="POST" id="booking" class="tw:grid tw:gap-6 tw:lg:grid-cols-[1.2fr_1fr] tw:lg:gap-7">
      <input id="activity_name" name="activity_name" type="hidden" value="<?= ui_h($exp) ?>">
      <input id="option_2" name="option_2" type="checkbox" value="Yes" hidden>
      <div class="tw:space-y-3">
       <?php ui_option_cards($options, $prices, $selected, 'tour_option_book', 'Route', 'segmented'); ?>
       <div class="tw:grid tw:gap-3 tw:sm:grid-cols-2">
        <div><label class="lab-label" for="name_booking">Name</label><input class="lab-input required" id="name_booking" name="name_booking" type="text" autocomplete="given-name"></div>
        <div><label class="lab-label" for="last_name_booking">Last name</label><input class="lab-input required" id="last_name_booking" name="last_name_booking" type="text" autocomplete="family-name"></div>
       </div>
       <div class="tw:grid tw:gap-3 tw:sm:grid-cols-2">
        <div><label class="lab-label" for="email_booking">Email</label><input class="lab-input required" id="email_booking" name="email_booking" type="email" autocomplete="email"></div>
        <div><label class="lab-label" for="phone_booking">Phone (WhatsApp)</label><input class="lab-input required" id="phone_booking" name="phone_booking" type="tel" autocomplete="tel"></div>
       </div>
       <div><label class="lab-label" for="date_booking">Date</label><input class="lab-input date-pick required" id="date_booking" name="date_booking" type="text" autocomplete="off" placeholder="Select a date"></div>
      </div>
      <div class="tw:space-y-3">
       <div class="tw:grid tw:grid-cols-3 tw:gap-2 tw:sm:gap-3">
        <div><label class="lab-label" for="adults">Adults</label><div class="numbers-row"><input class="qty2" id="adults" name="adults" type="text" inputmode="numeric" value="1"></div></div>
        <div><label class="lab-label" for="children">Children</label><div class="numbers-row"><input class="qty2" id="children" name="children" type="text" inputmode="numeric" value="0"></div><span class="tw:mt-1 tw:block tw:text-[11px] tw:text-muted">Under 12</span></div>
        <div><label class="lab-label" for="infants">Infants</label><div class="numbers-row"><input class="qty2" id="infants" name="infants" type="text" inputmode="numeric" value="0"></div><span class="tw:mt-1 tw:block tw:text-[11px] tw:text-muted">Under 2</span></div>
       </div>
       <div>
        <label class="lab-label" for="coupon_code">Coupon</label>
        <div class="tw:flex tw:gap-2">
         <input class="lab-input required" id="coupon_code" name="coupon_code" type="text" placeholder="I have a coupon!">
         <button class="lab-btn tw:shrink-0 tw:bg-white/10 tw:px-4 tw:hover:bg-white/20" id="apply_coupon_valpo" type="button">Apply</button>
        </div>
       </div>
       <div class="tw:flex tw:items-baseline tw:justify-between tw:border-t tw:border-line tw:pt-3.5">
        <span class="tw:text-muted">Total</span>
        <strong class="tw:text-[28px] tw:font-extrabold tw:text-coral">$<span id="total_price">0</span> <span class="tw:text-sm tw:font-semibold tw:text-muted">USD</span></strong>
       </div>
       <p id="new_total_container" style="display:none;" class="tw:text-sm tw:text-white"><strong>New total:</strong> $<span id="new_total_price"></span> USD</p>
       <button class="lab-btn tw:w-full" type="submit">Book now</button>
       <?php ui_award('compact', 'Free cancellation up to 24 h before · Secure payment'); ?>
      </div>
     </form>
    </div>
    <?php
}
