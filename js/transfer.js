/* ------------------------------------------------------------------
 *  transfer.js – sidebar booking logic (2-step, fixed)
 * ------------------------------------------------------------------ */

/* ---------- constants ---------- */
const routeToExp = {
  SA_STGO  : 'CRUISE.SA_STGO',
  VLP_STGO : 'CRUISE.VLP_STGO',
  STGO_SA  : 'DROP_CRUISE.SA',
  STGO_VLP : 'DROP_CRUISE.VLPO'
};

/* ---------- helpers ---------- */
function reveal(id) {
  const el = document.getElementById(id);
  bootstrap.Collapse.getOrCreateInstance(el, { toggle: false }).show();
  el.querySelectorAll('[disabled]').forEach(i => (i.disabled = false));
}
function hide(id) {
  const el = document.getElementById(id);
  bootstrap.Collapse.getOrCreateInstance(el, { toggle: false }).hide();
  el.querySelectorAll('input,select').forEach(i => (i.disabled = true));
}
function resetSelect(sel) {
  sel.innerHTML = '<option value="">Select…</option>';
  sel.value = '';
}

/* ---------- DOM-ready ---------- */
$(function () {

  /* base elements */
  const pickup   = $('#pickup');
  const dropSel  = $('#dropoff');
  const dateIn   = $('#transfer_date');
  const totalLbl = $('#total_price');

  /* buttons */
  const btnCheck = $('#btnCheck');                  // step-1 trigger

  const timeline = $('#timelineContainer');

  /* ---------- 1. Hotel autocomplete ---------- */
  $('#hotelPickup,#hotelDropoff').autocomplete({
    source: 'get_hotels.php',
    minLength: 1
  }).autocomplete('instance')._renderItem = (ul, item) =>
    $('<li>')
      .append(`<div><strong>${item.label}</strong><br><small class="text-muted">${item.desc}</small></div>`)
      .appendTo(ul);

  /* ---------- 2. Hotel radio / focus logic ---------- */
  initHotelGroup('Pickup');
  initHotelGroup('Dropoff');

  function initHotelGroup(group) {
    const listInp    = $(`#hotel${group}`);
    const notRadio   = $(`#notListed${group}`);
    const laterRadio = $(`#decideLater${group}`);
    const customWrap = $(`#customHotel${group}Wrapper`);
    const customInp  = $(`#customHotel${group}`);

    /* radios */
    $(`input[name="hotel${group}Option"]`).on('change', function () {
      if (this.value === 'not_listed') {
        listInp.prop({ readOnly: true, value: '' });
        customWrap.collapse('show');
        customInp.prop({ readOnly: false, required: true }).focus();
      } else { // decide_later
        listInp.prop({ readOnly: true, value: '' });
        customWrap.collapse('hide');
        customInp.prop({ readOnly: true, value: '', required: false });
      }
      updateTotal();
    });

    /* click list to return to normal mode */
    listInp.on('focus', function () {
      if (listInp.prop('readOnly')) {
        listInp.prop({ readOnly: false });
        notRadio.prop('checked', false);
        laterRadio.prop('checked', false);
        customWrap.collapse('hide');
        customInp.prop({ readOnly: true, value: '', required: false });
        updateTotal();
      }
    });
  }

  /* ---------- 3. Timeline loader ---------- */
  function loadTimeline() {
    const pu = pickup.val(), dof = dropSel.val(); let file = '';
    if (pu && dof) {
      if (pu === 'SA'  && ['STGO_HOTEL','STGO_AIRPORT'].includes(dof)) file = 'timeline_sanantonio_santiago.php';
      else if (['STGO_HOTEL','STGO_AIRPORT'].includes(pu) && dof === 'SA')  file = 'timeline_santiago_sanantonio.php';
      else if (pu === 'VLP' && ['STGO_HOTEL','STGO_AIRPORT'].includes(dof)) file = 'timeline_valparaiso_santiago.php';
      else if (['STGO_HOTEL','STGO_AIRPORT'].includes(pu) && dof === 'VLP') file = 'timeline_santiago_valparaiso.php';
    }
    if (!file) { timeline.empty(); return; }
    fetch('includes/' + file)
      .then(r => r.text())
      .then(html => timeline.html(html))
      .catch(() => timeline.empty());
  }

  /* ---------- 4. Field chain ---------- */
  pickup.on('change', function () {
    const val = this.value;
    (val === 'STGO_HOTEL') ? reveal('pickupHotelGroup') : hide('pickupHotelGroup');

    resetSelect(dropSel[0]);
    if (!val) {
      hide('dropoff-wrapper'); hide('date-wrapper'); hide('passengers-wrapper');
      updateTotal(); loadTimeline(); return;
    }
    if (val === 'SA' || val === 'VLP') {
      dropSel.append('<option value="STGO_HOTEL">Hotel in Santiago</option>')
             .append('<option value="STGO_AIRPORT">Santiago Airport (SCL)</option>');
    } else {
      dropSel.append('<option value="SA">San Antonio (Cruise Port)</option>')
             .append('<option value="VLP">Valparaíso (Cruise Port)</option>');
    }
    reveal('dropoff-wrapper');
    updateTotal(); loadTimeline();
  });

  dropSel.on('change', function () {
    (this.value === 'STGO_HOTEL') ? reveal('dropoffHotelGroup') : hide('dropoffHotelGroup');
    this.value ? reveal('date-wrapper') : (hide('date-wrapper'), hide('passengers-wrapper'));
    updateTotal(); loadTimeline();
  });

  dateIn.on('change', function () {
    if (this.value) reveal('passengers-wrapper');
    else            hide('passengers-wrapper');
    updateTotal();
  });

  $('#adults,#children,#infants').on('input change', updateTotal);

  /* ---------- 5. Price calculator ---------- */
  function calcTotal() {
    const pu  = pickup.val(), dof = dropSel.val();
    const ad  = +$('#adults').val()   || 0;
    const ch  = +$('#children').val() || 0;
    const inf = +$('#infants').val()  || 0;
    if (!pu || !dof) return 0;

    const expKey = routeToExp[pu.split('_')[0] + '_' + dof.split('_')[0]];
    if (!prices[expKey]) return 0;

    const p = prices[expKey];
    let t = ad * p.adult + ch * p.child + inf * p.infant;
    // if (pu === 'STGO_AIRPORT' || dof === 'STGO_AIRPORT') t += 30;
    return t;
  }

  /* ---------- 6. Validation & button toggle ---------- */
  function updateTotal() {
    const total = calcTotal();
    totalLbl.text(total.toFixed(2));

    const pu = pickup.val(), dof = dropSel.val(), date = dateIn.val();

    const pickupNot   = $('#notListedPickup').prop('checked');
    const pickupLater = $('#decideLaterPickup').prop('checked');
    const dropNot     = $('#notListedDropoff').prop('checked');
    const dropLater   = $('#decideLaterDropoff').prop('checked');

    const pickupOK = pu !== 'STGO_HOTEL' ||
      (!pickupNot && !pickupLater && $('#hotelPickup').val().trim()) ||
      (pickupNot   && $('#customHotelPickup').val().trim()) ||
      pickupLater;

    const dropOK = dof !== 'STGO_HOTEL' ||
      (!dropNot && !dropLater && $('#hotelDropoff').val().trim()) ||
      (dropNot   && $('#customHotelDropoff').val().trim()) ||
      dropLater;

    const step1valid = total && date && pu && dof && pickupOK && dropOK;
    btnCheck.prop('disabled', !step1valid);
  }

  updateTotal();    // initialise

  /* ---------- 7. Step switch ---------- */
  btnCheck.on('click', function () {
    if ($(this).prop('disabled')) return;

    // hide open collapses
    $('.collapse.show').collapse('hide');

    // slide button away then show contact wrapper
    $(this).slideUp(150, () => {
      bootstrap.Collapse.getOrCreateInstance(document.getElementById('contact-wrapper')).show();
      $('#cust_name').focus();
    });
  });

  $('#contact-back').on('click', function (e) {
    e.preventDefault();
    bootstrap.Collapse.getInstance(document.getElementById('contact-wrapper')).hide();
    btnCheck.slideDown();

    // reopen relevant groups
    if (pickup.val()) reveal('dropoff-wrapper');
    if (pickup.val() === 'STGO_HOTEL') reveal('pickupHotelGroup');
    if (dropSel.val()) reveal('date-wrapper');
    if (dropSel.val() === 'STGO_HOTEL') reveal('dropoffHotelGroup');
    if (dateIn.val()) reveal('passengers-wrapper');
  });

  /* ---------- 8. Populate hidden fields before submit ---------- */
  $('#bookingForm').on('submit', function () {

    const form = this;
    const pu   = pickup.val();
    const dof  = dropSel.val();

    // activity code (CRUISE.SA_STGO etc.)
    const expKey = routeToExp[pu.split('_')[0] + '_' + dof.split('_')[0]];
    form.activity_name.value = expKey || '';

    // date MM-DD-YYYY
    const d = $('#transfer_date').val();        // YYYY-MM-DD
    if (d) {
      const [y, m, day] = d.split('-');
      form.date_booking.value = `${m}-${day}-${y}`;
    }

    // airport flag
    form.airport_pick_up.value =
      (pu === 'STGO_AIRPORT' || dof === 'STGO_AIRPORT') ? 'Yes' : 'No';

    // subtotal / total
    const tot = $('#total_price').text();
    form.subtotal.value    = tot;
    form.total_price.value = tot;

    // coupon (blank for now)
    form.coupon_code.value = '';
  });

});
