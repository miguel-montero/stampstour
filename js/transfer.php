<?php
/* transfer.php */
require __DIR__ . '/db_config.php';          // opens $conn

/* 1.  grab prices for the four transfer experiences */
$expNames = ['CRUISE.SA_STGO','CRUISE.VLP_STGO','DROP_CRUISE.SA','DROP_CRUISE.VLPO'];
$placeholders = implode(',', array_fill(0,count($expNames),'?'));
$stmt = $conn->prepare(
    "SELECT nombre, precio_adulto, precio_nino, precio_infante
    FROM experiencias
    WHERE nombre IN ($placeholders)"
);
$stmt->bind_param(str_repeat('s',count($expNames)), ...$expNames);
$stmt->execute();
$res = $stmt->get_result();
$prices = [];
while($row=$res->fetch_assoc()){
    $prices[$row['nombre']] = [
        'adult'=>$row['precio_adulto'],
        'child'=>$row['precio_nino'],
        'infant'=>$row['precio_infante']
    ];
}
$stmt->close();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Private Transfers – Booking</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://code.jquery.com/ui/1.13.3/themes/base/jquery-ui.css" rel="stylesheet">
</head>
<body class="container py-4">
<h1 class="h3 mb-4">Private transfer booking</h1>
<form id="bookingForm" autocomplete="off">

        <!-- STEP 1 – Pick-up (visible from start) -->
        <div class="mb-3">
            <label for="pickup" class="form-label">Pick-up location</label>
            <select id="pickup" name="pickup" class="form-control" required>
                <option value="">Select…</option>
                <option value="SA">San Antonio (Cruise Port)</option>
                <option value="VLP">Valparaíso (Cruise Port)</option>
                <option value="STGO_HOTEL">Hotel in Santiago</option>
                <option value="STGO_AIRPORT">Santiago Airport (SCL)</option>
            </select>
        </div>

        <!-- Hotel autocomplete (only if pick-up is STGO_HOTEL) -->
        <div id="pickupHotelGroup" class="collapse">
            <label for="hotelPickup" class="form-label">Choose your hotel</label>
            <input id="hotelPickup" name="hotelPickup" class="form-control" placeholder="Start typing…">
            <div class="d-flex align-items-center mt-2">
                <div class="form-check me-3">
                    <input class="form-check-input" type="radio" name="hotelPickupOption"
                           id="notListedPickup" value="not_listed">
                    <label class="form-check-label" for="notListedPickup">Not on the list</label>
                </div>
                <div id="customHotelPickupWrapper" style="display:none; flex:1 1 auto;">
                    <input id="customHotelPickup" name="customHotelPickup"
                           class="form-control" placeholder="Hotel / address" readonly>
                </div>
                <div class="form-check ms-3">
                    <input class="form-check-input" type="radio" name="hotelPickupOption"
                           id="decideLaterPickup" value="decide_later">
                    <label class="form-check-label" for="decideLaterPickup">Later</label>
                </div>
            </div>
        </div>

        <!-- STEP 2 – Drop-off -->
        <div id="dropoff-wrapper" class="collapse">
            <div class="mb-3 mt-3">
                <label for="dropoff" class="form-label">Drop-off location</label>
                <select id="dropoff" name="dropoff" class="form-control" disabled required></select>
            </div>
        </div>

        <!-- Hotel autocomplete for Drop-off (only if drop-off is STGO_HOTEL) -->
        <div id="dropoffHotelGroup" class="collapse">
            <label for="hotelDropoff" class="form-label">Choose your hotel</label>
            <input id="hotelDropoff" name="hotelDropoff" class="form-control" placeholder="Start typing…">
            <div class="d-flex align-items-center mt-2">
                <div class="form-check me-3">
                    <input class="form-check-input" type="radio" name="hotelDropoffOption"
                           id="notListedDropoff" value="not_listed">
                    <label class="form-check-label" for="notListedDropoff">Not on the list</label>
                </div>
                <div id="customHotelDropoffWrapper" style="display:none; flex:1 1 auto;">
                    <input id="customHotelDropoff" name="customHotelDropoff"
                           class="form-control" placeholder="Hotel / address" readonly>
                </div>
                <div class="form-check ms-3">
                    <input class="form-check-input" type="radio" name="hotelDropoffOption"
                           id="decideLaterDropoff" value="decide_later">
                    <label class="form-check-label" for="decideLaterDropoff">Later</label>
                </div>
            </div>
        </div>

        <!-- STEP 3 – Date -->
        <div id="date-wrapper" class="collapse">
            <div class="mb-3">
                <label for="transfer_date" class="form-label">Date</label>
                <input type="date" id="transfer_date" name="transfer_date"
                       class="form-control" disabled required>
            </div>
        </div>

        <!-- STEP 4 – Passengers -->
        <div id="passengers-wrapper" class="collapse">
            <div class="row g-3">
                <div class="col-md-4">
                    <label for="adults" class="form-label">Adults (12+)</label>
                    <input type="number" id="adults" name="adults" class="form-control" min="1" value="1" disabled required>
                </div>
                <div class="col-md-4">
                    <label for="children" class="form-label">Children (3‑11)</label>
                    <input type="number" id="children" name="children" class="form-control" min="0" value="0" disabled>
                </div>
                <div class="col-md-4">
                    <label for="infants" class="form-label">Infants (0‑2)</label>
                    <input type="number" id="infants" name="infants"
                           class="form-control" min="0" value="0" disabled>
                </div>
            </div>
        </div>

        <!-- Total & submit -->
        <p class="fw-bold">Total US$ <span id="total_price">0.00</span></p>
        <button type="submit" class="btn btn-primary" disabled>Book now</button>
</form>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.3/jquery-ui.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const prices = <?php echo json_encode($prices,JSON_NUMERIC_CHECK) ?>;
const routeToExp = {
    'SA_STGO'  : 'CRUISE.SA_STGO',
    'VLP_STGO' : 'CRUISE.VLP_STGO',
    'STGO_SA'  : 'DROP_CRUISE.SA',
    'STGO_VLP' : 'DROP_CRUISE.VLPO'
};

/* ---------- helpers ---------- */
function reveal(id){
    const el = document.getElementById(id);
    bootstrap.Collapse.getOrCreateInstance(el,{toggle:false}).show();
    el.querySelectorAll('[disabled]').forEach(e=>e.disabled=false);
}
function hide(id){
    const el = document.getElementById(id);
    bootstrap.Collapse.getOrCreateInstance(el,{toggle:false}).hide();
    el.querySelectorAll('input,select').forEach(e=>e.disabled=true);
}
function resetSelect(sel){
    sel.innerHTML='<option value="">Select…</option>';
    sel.value='';
}

/* ---------- DOM ready ---------- */
$(function(){

    const pickup   = $('#pickup');
    const pickupHotelGroup = $('#pickupHotelGroup');
    const dropSel  = $('#dropoff');
    const dropoffHotelGroup = $('#dropoffHotelGroup');
    const dateIn   = $('#transfer_date');
    const totalLbl = $('#total_price');
    const btnSubmit= $('#bookingForm button[type="submit"]');

    /* hotel autocomplete (pickup) */
    $('#hotelPickup').autocomplete({
        source:'get_hotels.php', minLength:1
    }).autocomplete('instance')._renderItem=function(ul,item){
        return $('<li>')
            .append('<div><strong>'+item.label+
                    '</strong><br><small class="text-muted">'+item.desc+'</small></div>')
            .appendTo(ul);
    };

    // radio toggles for manual hotel input (pickup)
    $('input[name="hotelPickupOption"]').on('change',function(){
        if(this.value==='not_listed'){
            $('#hotelPickup').prop({readonly:true,value:''});
            $('#customHotelPickupWrapper').show();
            $('#customHotelPickup').prop({readonly:false,value:''}).focus();
        } else {
            $('#customHotelPickupWrapper').hide();
            $('#customHotelPickup').prop({readonly:true,value:''});
            $('#hotelPickup').prop('readonly',this.value==='decide_later').val('');
        }
    });

    /* hotel autocomplete (drop-off) */
    $('#hotelDropoff').autocomplete({
        source:'get_hotels.php', minLength:1
    }).autocomplete('instance')._renderItem=function(ul,item){
        return $('<li>')
            .append('<div><strong>'+item.label+
                    '</strong><br><small class="text-muted">'+item.desc+'</small></div>')
            .appendTo(ul);
    };

    // radio toggles for manual hotel input (drop-off)
    $('input[name="hotelDropoffOption"]').on('change',function(){
        if(this.value==='not_listed'){
            $('#hotelDropoff').prop({readonly:true,value:''});
            $('#customHotelDropoffWrapper').show();
            $('#customHotelDropoff').prop({readonly:false,value:''}).focus();
        } else {
            $('#customHotelDropoffWrapper').hide();
            $('#customHotelDropoff').prop({readonly:true,value:''});
            $('#hotelDropoff').prop('readonly',this.value==='decide_later').val('');
        }
    });

    /* STEP 1 → 2 + hotel group */
    pickup.on('change', function(){
        const val = this.value;

        // show / hide hotel picker
        if(val==='STGO_HOTEL') reveal('pickupHotelGroup'); else hide('pickupHotelGroup');

        /* build valid drop-offs (4 combos total) */
        resetSelect(dropSel[0]);
        if(!val){
            hide('dropoff-wrapper'); hide('date-wrapper'); hide('passengers-wrapper');
            updateTotal(); return;
        }
        if(val==='SA'||val==='VLP'){
            dropSel.append('<option value="STGO_HOTEL">Hotel in Santiago</option>');
            dropSel.append('<option value="STGO_AIRPORT">Santiago Airport (SCL)</option>');
        }else{
            dropSel.append('<option value="SA">San Antonio (Cruise Port)</option>');
            dropSel.append('<option value="VLP">Valparaíso (Cruise Port)</option>');
        }
        reveal('dropoff-wrapper');
        updateTotal();
    });

    /* STEP 2 → 3 */
    dropSel.on('change', function(){
        if(this.value){
            if(this.value==='STGO_HOTEL') reveal('dropoffHotelGroup');
            else hide('dropoffHotelGroup');
            reveal('date-wrapper');
        } else {
            hide('date-wrapper'); hide('passengers-wrapper'); hide('dropoffHotelGroup');
        }
        updateTotal();
    });

    /* STEP 3 → 4 */
    dateIn.on('change', function(){
        if(this.value){
            reveal('passengers-wrapper');
        } else {
            hide('passengers-wrapper');
        }
        updateTotal();
    })

    /* passenger inputs */
    $('#adults,#children,#infants').on('input change', updateTotal);

    function calcTotal(){
        const pu = pickup.val(), dof = dropSel.val();
        const ad = +$('#adults').val()   || 0;
        const ch = +$('#children').val() || 0;
        const inft=+$('#infants').val()  || 0;
        if(!pu||!dof) return 0;
        const expKey = routeToExp[pu.split('_')[0]+'_'+dof.split('_')[0]];
        if(!prices[expKey]) return 0;
        const p = prices[expKey];
        let total = ad*p.adult + ch*p.child + inft*p.infant;
        if(pu==='STGO_AIRPORT' || dof==='STGO_AIRPORT') total += 30; // flat surcharge for airport
        return total;
    }
    function updateTotal(){
        const t = calcTotal();
        totalLbl.text(t.toFixed(2));
        btnSubmit.prop('disabled',
            !t || !dateIn.val() || !pickup.val() || !dropSel.val()
        );
    }
});
</script>
</body>
</html>
