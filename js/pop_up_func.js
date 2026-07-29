 $(window).on('load',function() {
                new $.popup({                
                    title       : '',
                    content         : '<a href="#tours"><img src="img/offer.jpg" alt="Image" class="pop_img"><h3 id="pop_msg">Special offer <strong>20% OFF</strong> on selected Tours!</h3></a><small>Autoclose delay in 3 seconds.</small>',
					html: true,
					autoclose   : true,
					closeOverlay:true,
					closeEsc: true,
					buttons     : false,
                    timeout     : 3000 
                });
            });