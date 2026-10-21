/***************************************
 * Next-Day Reservations Extractor
 * (Viator + GetYourGuide + Bókun + Civitatis)
 *
 * Tracked copy of the Google Apps Script bound to the
 * "Next-Day Reservations" Google Sheet. The live source of truth is the
 * Apps Script project itself (Extensions > Apps Script in the Sheet) -
 * this file exists so changes are visible in git history. After editing
 * here, paste the change into the Apps Script editor and re-save there too.
 *
 * Triggered two ways:
 *   1. Manually, from the Apps Script editor (Run > updateNextDayBookings)
 *   2. Via HTTP, by admin/refresh-reservations.php in the STAMP admin panel,
 *      which calls the doGet(e) web app endpoint below.
 ***************************************/


/*************************************************
 * CENTRAL TOUR / PRODUCT CONFIGURATION
 *
 * Add new operational tour types here.
 *************************************************/

var TOUR_CATEGORIES_ = {

  MAIPO_4: {
    outputTour: 'Maipo 4 Wineries'
  },

  MAIPO_LUNCH: {
    outputTour: 'Maipo Lunch Included'
  }

};


/*************************************************
 * VIATOR PRODUCT / GRADE MAPPING
 *
 * Priority:
 *
 * 1. Product Code + exact Tour Grade
 * 2. Product-specific title fallback
 * 3. Product-specific legacy Rate fallback
 * 4. Product-specific default
 * 5. Generic legacy text fallback
 *
 * IMPORTANT:
 * TG1 does NOT have a universal meaning.
 * Grades are interpreted per product.
 *************************************************/

var VIATOR_PRODUCT_MAP_ = {

  /***********************************************
   * PRODUCT 20268P25
   *
   * Maipo Valley Wine Tour with
   * 4 vineyards from Santiago
   ***********************************************/
  '20268P25': {

    identifyRegexes: [
      /\bmaipo\s+valley\s+wine\s+tour\s+with\s+4\s+vineyards\b/i
    ],

    grades: {

      // Normal / default tour
      'TG1': {
        category: 'MAIPO_4'
      },

      // Private version of normal tour
      'TG2': {
        category: 'MAIPO_4',
        private: true
      },

      // Lunch version
      'TG3': {
        category: 'MAIPO_LUNCH'
      },

      // Lunch version - alternate historical Viator grade
      'TG4': {
        category: 'MAIPO_LUNCH'
      }
    },


    /*
     * Used only when the grade is missing or unknown.
     *
     * Example:
     *
     * P25 + TG99 + "Classic Maipo Valley"
     * => Lunch Included
     */
    fallbacks: [

      {
        regex: /\bclassic\s+maipo\s+valley\b/i,
        category: 'MAIPO_LUNCH',
        note: 'Classic Maipo fallback'
      }

    ],


    /*
     * Old Bókun Rate names.
     *
     * These are useful when Bókun doesn't expose
     * Product Code / Tour Grade cleanly.
     */
    legacyRateFallbacks: [

      {
        regex: /\bfull[-\s]?day\s+maipo\s*:\s*4\s+wineries\b/i,
        category: 'MAIPO_4',
        note: 'Legacy Bókun 4-wineries rate'
      },

      {
        regex: /\bmaipo\s+(?:valley\s+)?(?:wine\s+)?tour\s*:\s*4\s+wineries\b/i,
        category: 'MAIPO_4',
        note: 'Legacy Bókun 4-wineries rate'
      },

      /*
       * Previous script treated this as the
       * 3-wineries / Classic option.
       *
       * Classic is now operationally:
       * Maipo Lunch Included.
       */
      {
        regex: /\bmaipo\s+wine\s+tour\s*:\s*3\s+wineries\b/i,
        category: 'MAIPO_LUNCH',
        note: 'Legacy Bókun 3-wineries/lunch rate'
      }

    ]

    /*
     * NO defaultRule intentionally.
     *
     * If Viator creates TG5/TG6/etc. for P25,
     * we do not want to silently remove lunch.
     *
     * The reservation remains visible and gets
     * a Mapping warning in Notes.
     */
  },


  /***********************************************
   * PRODUCT 20268P15
   *
   * Wine / Culinary Trail
   ***********************************************/
  '20268P15': {

    identifyRegexes: [

      /\bwine\s*(?:&|and)\s*culinary\s+trail[\s\S]{0,100}\bmaipo\b/i,

      /\bmaipo\b[\s\S]{0,100}\b(?:wine\s*(?:&|and)\s*)?culinary\s+trail\b/i,

      /\bmaipo\s+valley\b[\s\S]{0,100}\blunch\s+(?:experience\s+)?included\b/i

    ],

    grades: {

      // Lunch included
      'TG1': {
        category: 'MAIPO_LUNCH'
      },

      // Plain / no lunch
      'TG2': {
        category: 'MAIPO_4'
      }

    },


    /*
     * IMPORTANT BUSINESS RULE:
     *
     * Any unknown/missing P15 grade defaults
     * to Lunch Included.
     *
     * This deliberately fails toward the
     * paid inclusion instead of dropping lunch.
     */
    defaultRule: {
      category: 'MAIPO_LUNCH',
      note: 'Unknown/missing P15 grade -> lunch'
    }

  }

};


/*************************************************
 * GENERIC LEGACY TEXT FALLBACKS
 *
 * Used only when a configured Product Code
 * cannot be determined.
 *************************************************/

var TOUR_TEXT_FALLBACKS_ = [

  {
    regex: /\bclassic\s+maipo\s+valley\b/i,
    category: 'MAIPO_LUNCH',
    note: 'Classic Maipo text fallback'
  },

  {
    regex: /\bmaipo\s+wine\s+tour\s*:\s*3\s+wineries\b/i,
    category: 'MAIPO_LUNCH',
    note: 'Legacy Maipo 3-wineries fallback'
  },

  {
    regex: /\bfull[-\s]?day\s+maipo\s*:\s*4\s+wineries\b/i,
    category: 'MAIPO_4',
    note: 'Legacy Maipo 4-wineries fallback'
  },

  {
    regex: /\bmaipo[\s\S]{0,80}4\s+wineries\b/i,
    category: 'MAIPO_4',
    note: 'Maipo 4-wineries text fallback'
  },

  {
    regex: /\bwine\s*(?:&|and)\s*culinary\s+trail[\s\S]{0,100}\bmaipo\b/i,
    category: 'MAIPO_LUNCH',
    note: 'Wine & Culinary Trail fallback'
  },

  {
    regex: /\bmaipo[\s\S]{0,100}\blunch\s+(?:experience\s+)?included\b/i,
    category: 'MAIPO_LUNCH',
    note: 'Maipo lunch text fallback'
  }

];



/***************************************
 * WEB APP TRIGGER (for admin panel button)
 *
 * One-time setup in the Apps Script editor:
 *
 *   1. Project Settings (gear icon) > Script Properties > Add property
 *        Key:   REFRESH_SECRET
 *        Value: <a long random string you choose - this is the shared
 *                secret the admin panel must send back>
 *
 *   2. Deploy > New deployment > type: Web app
 *        Execute as:        Me
 *        Who has access:    Anyone
 *      Copy the resulting web app URL.
 *
 *   3. Put that URL and the same secret into
 *      admin/_apps_script_config.php in the STAMP repo (gitignored,
 *      not committed - see that file for the exact format).
 *
 * Anyone who has both the URL AND the secret can trigger a refresh -
 * treat the secret like a password (long, random, not shared elsewhere).
 ***************************************/

function doGet(e) {

  var expected =
    PropertiesService
      .getScriptProperties()
      .getProperty('REFRESH_SECRET');

  var supplied =
    (e && e.parameter && e.parameter.secret) || '';


  if (!expected || supplied !== expected) {

    return ContentService
      .createTextOutput(
        JSON.stringify({
          ok: false,
          error: 'Unauthorized'
        })
      )
      .setMimeType(
        ContentService.MimeType.JSON
      );

  }


  try {

    var result =
      updateNextDayBookings();

    return ContentService
      .createTextOutput(
        JSON.stringify({
          ok: true,
          rowsWritten:
            (result && result.rowsWritten) || 0,
          rows:
            (result && result.rows) || []
        })
      )
      .setMimeType(
        ContentService.MimeType.JSON
      );

  } catch (err) {

    return ContentService
      .createTextOutput(
        JSON.stringify({
          ok: false,
          error: String(err)
        })
      )
      .setMimeType(
        ContentService.MimeType.JSON
      );

  }

}



/***************************************
 * MAIN
 ***************************************/

function updateNextDayBookings() {

  var LOOKBACK_DAYS = 0;
  var SHEET_NAME = 'Hoja 1';
  var LABEL_NAME = null;
  var TO_ADDRESS = null;
  var TZ = 'America/Santiago';

  var win = computeWindow_(TZ);

  var searchStart = addDays_(win.start, -LOOKBACK_DAYS);
  var searchEnd = win.end;

  var targetTomorrowMidnight =
    dateOnly_(addDays_(win.start, 1), TZ);

  var afterQuery = formatGmailDate(searchStart);

  var queryParts = [

    'after:' + afterQuery,

    '(' +
      'from:viator.com OR from:t1.viator.com OR from:tripadvisor.com ' +
      'OR from:getyourguide.com OR from:notification.getyourguide.com ' +
      'OR from:bokun.io OR from:no-reply@bokun.io OR from:pm-bounces.bokun.io ' +
      'OR from:civitatis.com' +
    ')'

  ];

  if (LABEL_NAME) {
    queryParts.push('label:' + LABEL_NAME);
  }

  if (TO_ADDRESS) {
    queryParts.push('to:' + TO_ADDRESS);
  }


  var threads =
    GmailApp.search(queryParts.join(' '));

  var messages = [];

  threads.forEach(function(th) {

    messages =
      messages.concat(th.getMessages());

  });


  var rows = [];

  rows.push([
    'Tour',
    'Nombre',
    'Pax',
    'Pickup',
    '',
    'Phone',
    'Lang',
    'Origin',
    'Email',
    'Code',
    'Type',
    'Subject',
    'Notes'
  ]);


  messages.forEach(function(msg) {

    var received = msg.getDate();

    if (
      !(received >= searchStart &&
        received < searchEnd)
    ) {
      return;
    }


    var from =
      (msg.getFrom() || '').toLowerCase();

    var subject =
      (msg.getSubject() || '').trim();

    var originKind =
      detectOrigin(from, subject);

    if (!originKind) return;

    if (
      isSkippableNotification_(
        subject,
        originKind
      )
    ) {
      return;
    }


    var rawHtml =
      msg.getBody() || '';

    var plain =
      normalizeBody_(
        msg.getPlainBody() || ''
      );

    var htmlText =
      normalizeBody_(
        stripHtmlHtml_KeepBreaks_(rawHtml)
      );

    var body =
      (plain ? plain + '\n' : '') +
      htmlText;


    var type =
      detectType(subject, originKind);


    /***************************************
     * TRAVEL DATE
     ***************************************/

    var travelDate;

    if (originKind === 'BOKUN') {

      var bokunDateCell =
        extractHtmlFieldRaw_(
          rawHtml,
          'Date'
        );

      var bokunDateText =
        stripHtml(bokunDateCell || '');

      travelDate =
        parseBokunDisplayDate_(
          bokunDateText
        ) ||
        parseBokunDateFromSubject_(
          subject
        );

    }

    else if (originKind === 'CIVITATIS') {

      travelDate =
        parseSpanishDate_(
          getMatch(
            body,
            /^FECHA:\s*([^\n]+)$/mi
          )
        );

    }

    else {

      travelDate =
        extractTravelDateStrict(
          body,
          originKind
        );

    }


    if (!travelDate) return;

    travelDate.setHours(0,0,0,0);

    if (
      travelDate.getTime() !==
      targetTomorrowMidnight.getTime()
    ) {
      return;
    }


    /***************************************
     * COMMON OUTPUT VARIABLES
     ***************************************/

    var nombre = '';
    var pax = '';
    var pickup = '';
    var phone = '';
    var lang = '';
    var code = '';
    var tour = '';
    var originOut = '';
    var email = '';

    var notes = [];



    /***************************************
     * VIATOR DIRECT EMAIL
     ***************************************/

    if (originKind === 'Viator') {

      nombre = pickFirst([

        getMatch(
          body,
          /^Lead\s*Traveler\s*Name:\s*([^\n]+)$/mi
        ),

        getMatch(
          body,
          /^Lead\s*traveler\s*name:\s*([^\n]+)$/mi
        )

      ]);


      var trav =
        getMatch(
          body,
          /^Travelers:\s*([^\n]+)$/mi
        );

      pax =
        travelersCountFromViator_(trav) ||

        getNumber(
          getMatch(
            body,
            /^Number\s*of\s*travelers:\s*([^\n]+)$/mi
          )
        ) ||

        '';


      pickup = pickFirst([

        cleanLine(
          getMatch(
            body,
            /^Hotel\s*Pickup:\s*([^\n]+)$/mi
          )
        ),

        cleanLine(
          getMatch(
            body,
            /^Pickup\s*location:\s*([^\n]+)$/mi
          )
        )

      ]);


      phone =
        normalizePhoneDigitsOnly_(

          pickFirst([

            getMatch(
              body,
              /^Phone:\s*([^\n]+)$/mi
            ),

            getMatch(
              body,
              /^\(Alternate Phone\)\s*([^\n]+)$/mi
            ),

            getMatch(
              body,
              /^Alternate Phone:\s*([^\n]+)$/mi
            )

          ])

        );


      lang =
        normalizeLangCode_(

          pickFirst([

            getMatch(
              body,
              /^Tour\s*Language:\s*([^\n]+)$/mi
            ),

            getMatch(
              body,
              /^Language:\s*([^\n]+)$/mi
            )

          ])

        );


      code =
        extractViatorExtBookingRef_(
          body,
          subject
        );


      tour =
        extractTourName(
          body,
          originKind,
          subject,
          rawHtml
        );


      /***************************************
       * VIATOR PRODUCT / GRADE MAPPING
       ***************************************/

      var viatorProductCode =
        extractViatorProductCodeFromText_(
          body,
          subject,
          rawHtml
        );


      var viatorGradeCode =
        extractViatorTourGradeCode_(
          body,
          rawHtml,
          ''
        );


      var viatorGradeName =
        extractViatorTourGradeName_(
          body,
          rawHtml,
          ''
        );


      var viatorMap =
        resolveConfiguredTourMapping_({

          productCode:
            viatorProductCode,

          gradeCode:
            viatorGradeCode,

          gradeName:
            viatorGradeName,

          tour:
            tour,

          rate:
            '',

          productName:
            tour,

          subject:
            subject,

          rawText:
            body

        });


      if (viatorMap.matched) {

        if (viatorMap.tour) {
          tour = viatorMap.tour;
        }


        if (viatorMap.private) {

          tour =
            applyPrivatePrefix_(tour);

          pushUniqueNote_(
            notes,
            'Private'
          );

        }


        if (viatorMap.note) {

          pushUniqueNote_(
            notes,
            viatorMap.note
          );

        }

      }

      else if (viatorMap.warning) {

        pushUniqueNote_(
          notes,
          viatorMap.warning
        );

      }


      originOut = 'TRIP';

    }



    /***************************************
     * GETYOURGUIDE
     ***************************************/

    else if (originKind === 'GYG') {

      var rawHtmlGYG = rawHtml;


      nombre = (function() {

        var same =
          getMatch(
            body,
            /^Main\s*customer:\s*([^\n]+)/mi
          );

        if (same) {
          return cleanMainCustomer(same);
        }


        var blk =
          blockValueUntilBlankOrNextLabel_(

            body,

            /^Main\s*customer$/i,

            [

              /^Tour\s*language/i,
              /^Pickup/i,
              /^Price/i,
              /^Reference\s*number/i,
              /^Date$/i,
              /^Number\s*of\s*participants/i

            ],

            8
          );


        return blk
          ? cleanMainCustomer(blk)
          : '';

      })();


      var pblock =
        extractGYGParticipantsBlock_(
          body,
          rawHtmlGYG
        );


      pax =
        participantsCountFromGYG_(
          pblock
        ) ||

        getNumber(pblock) ||

        '';


      var isPrivateGYG =
        detectPrivateGYG_(
          body,
          pblock,
          rawHtmlGYG
        );


      pickup = pickFirst([

        cleanLine(
          getMatch(
            body,
            /^Pickup\s*location:\s*([^\n]+)$/mi
          )
        ),

        cleanLine(
          getMatch(
            body,
            /^Pickup:\s*([^\n]+)$/mi
          )
        )

      ]);


      if (!pickup) {

        var pickBlk =
          blockValueUntilBlankOrNextLabel_(

            body,

            /^Pickup$/i,

            [

              /^Price/i,
              /^Reference\s*number/i,
              /^Date$/i,
              /^Number\s*of\s*participants/i,
              /^Main\s*customer/i,
              /^Tour\s*language/i

            ],

            8
          );


        if (pickBlk) {
          pickup =
            cleanLine(pickBlk);
        }

      }


      if (!pickup) {

        pickup =
          cleanLine(
            getMatch(
              body,
              /^Customer\s*hotel:\s*([^\n]+)$/mi
            )
          ) ||

          cleanLine(
            blockValue_(
              body,
              /^Customer\s*hotel$/i
            )
          );

      }


      phone =
        normalizePhoneDigitsOnly_(

          pickFirst([

            getMatch(
              body,
              /^Phone:\s*([^\n]+)$/mi
            ),

            (function() {

              var m =
                body.match(
                  /Main\s*customer:[\s\S]*?Phone:\s*([^\n]+)/i
                );

              return m
                ? m[1].trim()
                : '';

            })(),

            (function() {

              var m =
                body.match(
                  /Phone:\s*([+\d][^\n]+)/i
                );

              return m
                ? m[1].trim()
                : '';

            })()

          ])

        );


      lang =
        normalizeLangCode_(

          pickFirst([

            (function() {

              var t =
                getMatch(
                  body,
                  /^Tour\s*language:\s*([^\n]+)/mi
                );

              return t
                ? t.split('(')[0].trim()
                : '';

            })(),

            (function() {

              var t =
                blockValue_(
                  body,
                  /^Tour\s*language/i,
                  3
                );

              return t
                ? t.split('(')[0].trim()
                : '';

            })()

          ])

        );


      email = (function() {

        var m =
          body.match(
            /customer-[A-Z0-9._%+-]+@reply\.getyourguide\.com/i
          );

        if (m) {
          return m[0].trim();
        }


        var blkMatch =
          body.match(
            /Main\s*customer:?[\s\S]{0,500}/i
          );

        if (blkMatch) {

          var e2 =
            blkMatch[0].match(
              /[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i
            );

          if (e2) {
            return e2[0].trim();
          }

        }


        return '';

      })();


      code =
        extractGYGReferenceNumber_(
          body,
          subject
        );


      tour =
        extractTourName(
          body,
          originKind,
          subject,
          rawHtmlGYG
        );


      if (isPrivateGYG) {

        tour =
          applyPrivatePrefix_(tour);

        pushUniqueNote_(
          notes,
          'Private'
        );

      }


      originOut = 'GET';

    }



    /***************************************
     * CIVITATIS
     ***************************************/

    else if (originKind === 'CIVITATIS') {

      var nombreCivi =
        extractCivitatisField_(
          body,
          'NOMBRE'
        );


      var apellidosCivi =
        cleanLine(
          extractCivitatisField_(
            body,
            'APELLIDOS'
          )
        );


      nombre =
        (
          nombreCivi +
          ' ' +
          apellidosCivi
        ).trim();


      pax =
        personsCountFromCivitatis_(
          body
        );


      pickup =
        cleanLine(
          extractCivitatisField_(
            body,
            'PUNTO\\s*DE\\s*RECOGIDA'
          )
        );


      // Contact details live behind the
      // "Datos de contacto" link.
      phone = '';
      email = '';


      lang =
        normalizeLangCode_(
          extractCivitatisField_(
            body,
            'IDIOMA'
          )
        );


      code = pickFirst([

        extractCivitatisField_(
          body,
          'N.MERO\\s*DE\\s*RESERVA'
        ),

        getMatch(
          subject,
          /Nueva\s*reserva\s*A?(\d+)/i
        )

      ]);


      tour =
        cleanupCivitatisTour_(

          extractCivitatisField_(
            body,
            'ACTIVIDAD'
          )

        );


      var codigoInternoCivi =
        extractCivitatisField_(
          body,
          'C.DIGO\\s*INTERNO'
        );


      if (codigoInternoCivi) {

        notes.push(
          'Código interno: ' +
          codigoInternoCivi
        );

      }


      originOut = 'CIVI';

    }



    /***************************************
     * BÓKUN
     ***************************************/

    else {

      var html = rawHtml;

      var f = {};


      f.Code =

        stripHtml(
          extractHtmlFieldRaw_(
            html,
            'Ext\\. booking ref'
          )
        ) ||

        extractBokunExtBookingRefFromSubject_(
          subject
        ) ||

        stripHtml(
          extractHtmlFieldRaw_(
            html,
            'Booking ref\\.'
          )
        );


      f.Product =

        stripHtml(
          extractHtmlFieldRaw_(
            html,
            'Product'
          )
        ) ||

        stripHtml(
          extractHtmlFieldRaw_(
            html,
            'Rate'
          )
        );


      f.Rate =
        stripHtml(
          extractHtmlFieldRaw_(
            html,
            'Rate'
          )
        );


      /*
       * These fields may or may not exist
       * depending on Bókun notification format.
       */
      f.ProductCode =
        stripHtml(
          extractHtmlFieldRaw_(
            html,
            'Product\\s*Code'
          )
        );


      f.TourGradeCode =
        stripHtml(
          extractHtmlFieldRaw_(
            html,
            'Tour\\s*Grade\\s*Code'
          )
        );


      f.TourGradeName =
        stripHtml(
          extractHtmlFieldRaw_(
            html,
            'Tour\\s*Grade(?:\\s*Name)?'
          )
        );


      f.Customer =
        stripHtml(
          extractHtmlFieldRaw_(
            html,
            'Customer'
          )
        );


      f.Pax =
        stripHtml(
          extractHtmlFieldRaw_(
            html,
            'PAX'
          )
        );


      f.Pickup =
        stripHtml(
          extractHtmlFieldRaw_(
            html,
            'Pick-up'
          )
        );


      f.Phone =
        stripHtml(
          extractHtmlFieldRaw_(
            html,
            'Customer phone'
          )
        );


      f.Lang =
        stripHtml(
          extractHtmlFieldRaw_(
            html,
            'Guided languages'
          )
        );


      f.Channel =

        stripHtml(
          extractHtmlFieldRaw_(
            html,
            'Booking channel'
          )
        ) ||

        stripHtml(
          extractHtmlFieldRaw_(
            html,
            'Sold by'
          )
        );


      f.Email =
        stripHtml(
          extractHtmlFieldRaw_(
            html,
            'Customer email'
          )
        );


      tour =
        (f.Product || '').trim();


      var dashIdx =
        tour.indexOf(' - ');


      if (dashIdx !== -1) {

        tour =
          tour.substring(
            dashIdx + 3
          ).trim();

      }



      /***************************************
       * SHARED VIATOR / BÓKUN MAPPING
       ***************************************/

      var bokunProductCode =

        normalizeViatorProductCode_(
          f.ProductCode
        ) ||

        extractViatorProductCodeFromText_(
          body,
          subject,
          html
        );


      var bokunGradeCode =

        normalizeViatorTourGradeCode_(
          f.TourGradeCode
        ) ||

        extractViatorTourGradeCode_(
          body,
          html,
          f.Rate
        );


      var bokunGradeName =

        f.TourGradeName ||

        extractViatorTourGradeName_(
          body,
          html,
          f.Rate
        );


      var bokunMap =
        resolveConfiguredTourMapping_({

          productCode:
            bokunProductCode,

          gradeCode:
            bokunGradeCode,

          gradeName:
            bokunGradeName,

          tour:
            tour,

          rate:
            f.Rate,

          productName:
            f.Product,

          subject:
            subject,

          rawText:
            body

        });


      if (bokunMap.matched) {

        if (bokunMap.tour) {

          tour =
            bokunMap.tour;

        }


        if (bokunMap.note) {

          pushUniqueNote_(
            notes,
            bokunMap.note
          );

        }

      }

      else if (bokunMap.warning) {

        pushUniqueNote_(
          notes,
          bokunMap.warning
        );

      }



      /***************************************
       * BÓKUN PRIVATE DETECTION
       ***************************************/

      var isPrivateBokun =

        !!bokunMap.private ||

        /\bprivate\b|\bpvt\b/i.test(

          String(f.Rate || '') +
          ' ' +
          String(f.Product || '') +
          ' ' +
          String(f.TourGradeName || '')

        );


      if (isPrivateBokun) {

        tour =
          applyPrivatePrefix_(tour);

        pushUniqueNote_(
          notes,
          'Private'
        );

      }



      /***************************************
       * BÓKUN CUSTOMER DATA
       ***************************************/

      nombre = (function(s) {

        s = (s || '').trim();

        var parts =
          s.split(',');

        if (parts.length === 2) {

          return (
            parts[1].trim() +
            ' ' +
            parts[0].trim()
          ).trim();

        }

        return s;

      })(f.Customer);


      pax =
        paxFromBokun_(f.Pax);


      pickup =
        (f.Pickup || '')
          .replace(
            /\s+\(.*?\)\s*$/,
            ''
          )
          .trim();


      phone =
        normalizePhoneDigitsOnly_(
          f.Phone || ''
        );


      lang =
        normalizeLangCode_(
          f.Lang || ''
        );


      email =
        (f.Email || '').trim();


      originOut = (function(ch) {

        var s =
          (ch || '').toLowerCase();


        if (
          s.indexOf('viator.com') !== -1 ||
          s.indexOf('tripadvisor') !== -1
        ) {
          return 'TRIP';
        }


        if (
          s.indexOf('getyourguide') !== -1
        ) {
          return 'GET';
        }


        if (
          s.indexOf('bokun') !== -1 ||
          s.indexOf('online') !== -1 ||
          s.indexOf('website') !== -1
        ) {
          return 'WEB';
        }


        return (
          ch &&
          ch.length > 4
        )
          ? ch.substring(0,4).toUpperCase()
          : (ch || '').toUpperCase();

      })(f.Channel);


      code =
        (f.Code || '').trim();

    }



    /***************************************
     * NORMALIZE BOOKING CODE
     ***************************************/

    if (code) {

      code =
        normalizeExtractedCode_(
          code,
          originOut
        );

    }



    /***************************************
     * MISSING DATA NOTES
     ***************************************/

    var missing = [];


    if (!nombre) missing.push('Nombre');
    if (!pax) missing.push('Pax');
    if (!phone) missing.push('Phone');
    if (!code) missing.push('Code');
    if (!tour) missing.push('Tour');


    if (missing.length) {

      notes.push(
        'Missing: ' +
        missing.join(', ')
      );

    }



    /***************************************
     * OUTPUT ROW
     ***************************************/

    rows.push([

      tour || '',
      nombre || '',
      pax || '',
      pickup || '',
      '',
      phone || '',
      lang || '',
      originOut || '',
      email || '',
      code || '',
      type,
      subject,
      notes.join(' | ')

    ]);

  });



  /***************************************
   * WRITE SHEET
   ***************************************/

  var ss =
    SpreadsheetApp.getActiveSpreadsheet();


  var sh =
    ss.getSheetByName(
      SHEET_NAME
    );


  if (!sh) {

    throw new Error(
      'Sheet not found: ' +
      SHEET_NAME
    );

  }


  sh.clearContents();


  sh.getRange(
    1,
    1,
    rows.length,
    rows[0].length
  ).setValues(rows);


  return {
    rowsWritten: rows.length - 1,
    rows: rows
  };

}



/*************************************************
 * PRODUCT MAPPING HELPERS
 *************************************************/


function normalizeViatorProductCode_(raw) {

  var s =
    normalizeWhitespace_(
      stripHtml(
        String(raw || '')
      )
    ).toUpperCase();


  if (!s) return '';


  var m =
    s.match(
      /\b\d+P\d+\b/i
    );


  return m
    ? m[0].toUpperCase()
    : s;

}



function normalizeViatorTourGradeCode_(raw) {

  var s =
    normalizeWhitespace_(
      stripHtml(
        String(raw || '')
      )
    ).toUpperCase();


  if (!s) return '';


  /*
   * Handles values such as:
   *
   * TG1
   * TG1~09:30
   * TG12~14:00
   */
  var tg =
    s.match(
      /\bTG\d+\b/i
    );


  if (tg) {

    return tg[0].toUpperCase();

  }


  return '';

}



/*
 * Attempts to identify one of our configured
 * products from any text.
 */
function identifyConfiguredViatorProductCode_(text) {

  var raw =
    String(text || '');

  var upper =
    raw.toUpperCase();

  var codes =
    Object.keys(
      VIATOR_PRODUCT_MAP_
    );


  var i;
  var j;


  /*
   * Literal product code has priority.
   */
  for (
    i = 0;
    i < codes.length;
    i++
  ) {

    if (
      upper.indexOf(
        codes[i].toUpperCase()
      ) !== -1
    ) {

      return codes[i];

    }

  }


  /*
   * Product title fallback.
   */
  for (
    i = 0;
    i < codes.length;
    i++
  ) {

    var product =
      VIATOR_PRODUCT_MAP_[
        codes[i]
      ];


    var regexes =
      product.identifyRegexes || [];


    for (
      j = 0;
      j < regexes.length;
      j++
    ) {

      if (
        regexes[j].test(raw)
      ) {

        return codes[i];

      }

    }

  }


  return '';

}



/*
 * Extract Viator Product Code from direct
 * Viator text or Bókun HTML.
 */
function extractViatorProductCodeFromText_(
  body,
  subject,
  html
) {

  var text =
    String(body || '');


  var direct =
    getMatch(
      text,
      /^Product\s*Code\s*:?\s*([A-Za-z0-9_-]+)/mi
    );


  if (!direct && html) {

    direct =
      stripHtml(
        extractHtmlFieldRaw_(
          html,
          'Product\\s*Code'
        )
      );

  }


  if (direct) {

    return normalizeViatorProductCode_(
      direct
    );

  }


  return identifyConfiguredViatorProductCode_(

    text +
    '\n' +
    String(subject || '') +
    '\n' +
    stripHtml(html || '')

  );

}



/*
 * Extract Tour Grade Code.
 *
 * Rate is also checked because some Bókun
 * configurations may expose the TG value there.
 */
function extractViatorTourGradeCode_(
  body,
  html,
  rate
) {

  var raw =
    getMatch(
      String(body || ''),
      /^Tour\s*Grade\s*Code\s*:?\s*([^\n]+)/mi
    );


  if (!raw && html) {

    raw =
      stripHtml(
        extractHtmlFieldRaw_(
          html,
          'Tour\\s*Grade\\s*Code'
        )
      );

  }


  var normalized =
    normalizeViatorTourGradeCode_(
      raw
    );


  if (normalized) {

    return normalized;

  }


  return normalizeViatorTourGradeCode_(
    rate || ''
  );

}



/*
 * Extract human-readable option name.
 */
function extractViatorTourGradeName_(
  body,
  html,
  rate
) {

  var text =
    String(body || '');


  var value =
    pickFirst([

      getMatch(
        text,
        /^Tour\s*Grade\s*Name\s*:?\s*([^\n]+)/mi
      ),

      getMatch(
        text,
        /^Tour\s*Grade\s*:\s*([^\n]+)/mi
      )

    ]);


  if (!value && html) {

    value =
      stripHtml(
        extractHtmlFieldRaw_(
          html,
          'Tour\\s*Grade\\s*Name'
        )
      ) ||

      stripHtml(
        extractHtmlFieldRaw_(
          html,
          'Tour\\s*Grade'
        )
      );

  }


  if (value) {
    return normalizeWhitespace_(value);
  }


  return normalizeWhitespace_(
    rate || ''
  );

}



/*
 * Converts one config rule into a result.
 */
function buildTourMappingResult_(
  productCode,
  gradeCode,
  rule,
  source
) {

  rule = rule || {};


  var categoryKey =
    rule.category || '';


  var category =
    categoryKey
      ? TOUR_CATEGORIES_[categoryKey]
      : null;


  var outputTour =
    rule.outputTour || '';


  if (
    !outputTour &&
    category
  ) {

    outputTour =
      category.outputTour || '';

  }


  var mapText =
    productCode
      ? productCode
      : 'text';


  if (gradeCode) {

    mapText +=
      '/' +
      gradeCode;

  }


  var note =
    'Map: ' +
    mapText;


  if (source) {

    note +=
      ' [' +
      source +
      ']';

  }


  if (rule.note) {

    note +=
      ' - ' +
      rule.note;

  }


  return {

    matched: !!outputTour,

    tour: outputTour,

    private:
      !!rule.private,

    productCode:
      productCode || '',

    gradeCode:
      gradeCode || '',

    category:
      categoryKey,

    note:
      note,

    warning:
      ''

  };

}



/*
 * Main mapping engine.
 */
function resolveConfiguredTourMapping_(ctx) {

  ctx =
    ctx || {};


  var productCode =
    normalizeViatorProductCode_(
      ctx.productCode || ''
    );


  var gradeCode =
    normalizeViatorTourGradeCode_(
      ctx.gradeCode || ''
    );


  var titleText =
    normalizeWhitespace_([

      ctx.tour || '',
      ctx.gradeName || '',
      ctx.rate || '',
      ctx.productName || '',
      ctx.subject || ''

    ].join(' '));


  var rawText = [

    ctx.rawText || '',
    titleText

  ].join('\n');


  /*
   * Try to identify product if explicit
   * code was unavailable.
   */
  if (!productCode) {

    productCode =
      identifyConfiguredViatorProductCode_(
        rawText
      );

  }


  var productMap =
    productCode
      ? VIATOR_PRODUCT_MAP_[productCode]
      : null;



  /****************************************
   * CONFIGURED PRODUCT
   ****************************************/

  if (productMap) {


    /*
     * 1. Exact Product + exact Grade
     */
    if (
      gradeCode &&
      productMap.grades &&
      productMap.grades[gradeCode]
    ) {

      return buildTourMappingResult_(

        productCode,
        gradeCode,
        productMap.grades[gradeCode],
        'product+grade'

      );

    }



    /*
     * 2. Product-specific fallback.
     *
     * Example:
     * Classic Maipo Valley => Lunch
     */
    var fallbacks =
      productMap.fallbacks || [];


    for (
      var i = 0;
      i < fallbacks.length;
      i++
    ) {

      if (
        fallbacks[i].regex.test(
          titleText
        )
      ) {

        return buildTourMappingResult_(

          productCode,
          gradeCode,
          fallbacks[i],
          'title-fallback'

        );

      }

    }



    /*
     * 3. Legacy Bókun Rate fallback.
     */
    var legacy =
      productMap.legacyRateFallbacks || [];


    for (
      var l = 0;
      l < legacy.length;
      l++
    ) {

      if (
        legacy[l].regex.test(
          titleText
        )
      ) {

        return buildTourMappingResult_(

          productCode,
          gradeCode,
          legacy[l],
          'legacy-rate'

        );

      }

    }



    /*
     * 4. Product-specific default.
     *
     * Currently used by P15:
     * unknown grade => Lunch Included.
     */
    if (
      productMap.defaultRule
    ) {

      return buildTourMappingResult_(

        productCode,
        gradeCode,
        productMap.defaultRule,
        'product-default'

      );

    }



    /*
     * Known product but unknown grade.
     *
     * P25 intentionally lands here instead
     * of guessing.
     */
    return {

      matched: false,

      tour: '',

      private: false,

      productCode:
        productCode,

      gradeCode:
        gradeCode,

      category: '',

      note: '',

      warning:
        'Mapping warning: ' +
        productCode +
        '/' +
        (
          gradeCode ||
          '(no recognizable grade)'
        )

    };

  }



  /****************************************
   * 5. GENERIC LEGACY FALLBACK
   ****************************************/

  for (
    var j = 0;
    j < TOUR_TEXT_FALLBACKS_.length;
    j++
  ) {

    var fallback =
      TOUR_TEXT_FALLBACKS_[j];


    if (
      fallback.regex.test(
        titleText
      )
    ) {

      return buildTourMappingResult_(

        '',
        gradeCode,
        fallback,
        'text-fallback'

      );

    }

  }



  return {

    matched: false,

    tour: '',

    private: false,

    productCode:
      productCode,

    gradeCode:
      gradeCode,

    category: '',

    note: '',

    warning: ''

  };

}



function pushUniqueNote_(notes, value) {

  if (!value) return;

  if (
    notes.indexOf(value) === -1
  ) {

    notes.push(value);

  }

}



/*************************************************
 * GYG HELPERS
 *************************************************/

function extractGYGParticipantsBlock_(
  body,
  rawHtml
) {

  var htmlBlock =
    extractGYGParticipantsFromHtml_(
      rawHtml
    );


  if (htmlBlock) {
    return htmlBlock;
  }


  var inline =
    getMatch(
      body,
      /^Number\s*of\s*participants:\s*([^\n]+)$/mi
    );


  if (inline) {
    return inline;
  }


  return blockValueUntilBlankOrNextLabel_(

    body,

    /^Number\s*of\s*participants$/i,

    [

      /^Main\s*customer/i,
      /^Tour\s*language/i,
      /^Pickup/i,
      /^Price/i,
      /^Reference\s*number/i,
      /^Date$/i,
      /^Phone/i,
      /^Language/i

    ],

    8
  );

}



function extractGYGParticipantsFromHtml_(
  html
) {

  if (!html) return '';


  var text =
    String(html);


  var idx =
    text.search(
      /Number\s*of\s*participants/i
    );


  if (idx === -1) {
    return '';
  }


  var chunk =
    text.substring(
      idx,
      idx + 3000
    );


  var right =
    chunk.match(
      /<div[^>]*class=["'][^"']*right-content[^"']*["'][^>]*>([\s\S]*?)<\/div>/i
    );


  var candidate =
    right
      ? right[1]
      : chunk;


  var clean =
    stripHtml(candidate);


  clean =
    normalizeWhitespace_(clean);


  clean =
    clean.replace(
      /\bMain\s*customer\b[\s\S]*$/i,
      ''
    ).trim();


  clean =
    clean.replace(
      /\bTour\s*language\b[\s\S]*$/i,
      ''
    ).trim();


  clean =
    clean.replace(
      /\bPickup\b[\s\S]*$/i,
      ''
    ).trim();


  clean =
    clean.replace(
      /\bPrice\b[\s\S]*$/i,
      ''
    ).trim();


  clean =
    clean.replace(
      /\bReference\s*number\b[\s\S]*$/i,
      ''
    ).trim();


  clean =
    clean.replace(
      /\bDate\b[\s\S]*$/i,
      ''
    ).trim();


  return clean;

}



function blockValueUntilBlankOrNextLabel_(
  body,
  labelRegex,
  stopRegexList,
  maxLines
) {

  var lines =
    String(body || '').split('\n');


  var maxLook =
    Math.max(
      1,
      maxLines || 6
    );


  var stops =
    stopRegexList || [];


  for (
    var i = 0;
    i < lines.length;
    i++
  ) {

    var current =
      (lines[i] || '').trim();


    if (
      labelRegex.test(current)
    ) {

      var collected = [];


      for (
        var k = 1;
        k <= maxLook &&
        i + k < lines.length;
        k++
      ) {

        var cand =
          (lines[i + k] || '').trim();


        if (!cand) {

          if (collected.length) {
            break;
          }

          continue;

        }


        var isStop = false;


        for (
          var s = 0;
          s < stops.length;
          s++
        ) {

          if (
            stops[s].test(cand)
          ) {

            isStop = true;
            break;

          }

        }


        if (isStop) break;


        collected.push(cand);

      }


      return collected.join(' ');

    }

  }


  return '';

}



function extractGYGReferenceNumber_(
  body,
  subject
) {

  var text =
    body || '';


  var direct =
    getMatch(
      text,
      /^Reference\s*number\s*:?\s*([A-Za-z0-9\-]+)/mi
    );


  if (direct) {
    return direct;
  }


  var block =
    blockValue_(
      text,
      /^Reference\s*number$/i,
      4
    );


  if (block) {

    var m =
      block.match(
        /\b(GYG[A-Z0-9\-]{5,})\b/i
      );


    if (m) {
      return m[1].toUpperCase();
    }


    var m2 =
      block.match(
        /\b([A-Z0-9\-]{6,})\b/i
      );


    if (m2) {
      return m2[1];
    }

  }


  var subj =
    subject || '';


  var sm =
    subj.match(
      /\b(GYG[A-Z0-9\-]{5,})\b/i
    );


  if (sm) {
    return sm[1].toUpperCase();
  }


  return '';

}



/*************************************************
 * VIATOR BOOKING CODE HELPERS
 *************************************************/

function extractViatorExtBookingRef_(
  body,
  subject
) {

  var text =
    body || '';


  var direct =
    getMatch(

      text,

      /^Ext\.?\s*booking\s*ref(?:erence)?\s*:?\s*(?:BR[-:\s]*)?([A-Za-z0-9\-]+)/mi

    );


  if (direct) {
    return direct;
  }


  var block =
    blockValue_(

      text,

      /^Ext\.?\s*booking\s*ref(?:erence)?$/i,

      4

    );


  if (block) {

    var m =
      block.match(
        /(?:BR[-:\s]*)?([A-Za-z0-9\-]{6,})/i
      );


    if (m) {
      return m[1];
    }

  }


  var fromSubject =
    extractBokunExtBookingRefFromSubject_(
      subject
    );


  if (fromSubject) {
    return fromSubject;
  }


  return '';

}



function extractBokunExtBookingRefFromSubject_(
  subject
) {

  var s =
    subject || '';


  var m =
    s.match(
      /Ext\.?\s*booking\s*ref\s*:?\s*([A-Za-z0-9\-]+)/i
    );


  return m
    ? m[1].trim()
    : '';

}



function normalizeExtractedCode_(
  code,
  originOut
) {

  var c =
    normalizeWhitespace_(
      stripHtml(
        String(code || '')
      )
    );


  if (originOut === 'GET') {

    return c;

  }


  if (originOut === 'TRIP') {

    c =
      c.replace(
        /^VIA[-:\s]*/i,
        ''
      );


    c =
      c.replace(
        /^BR[-:\s]*/i,
        ''
      );


    return c
      ? 'BR-' + c
      : '';

  }


  if (originOut === 'CIVI') {

    c =
      c.replace(
        /^A/i,
        ''
      );


    return c
      ? 'A' + c
      : '';

  }


  return c;

}



/*************************************************
 * GYG PARTICIPANT / PRIVATE HELPERS
 *************************************************/

function participantsCountFromGYG_(s) {

  if (!s) return '';


  var text =
    normalizeWhitespace_(
      stripHtml(
        String(s)
      )
    );


  var groupPersons =
    matchInt_(
      text,
      /\(\s*(\d+)\s*(?:Persons?|People|Pax)\s*\)/i
    );


  if (groupPersons) {
    return String(groupPersons);
  }


  var groupUpTo =
    matchInt_(
      text,
      /Group\s*up\s*to\s*(\d+)/i
    );


  if (groupUpTo) {
    return String(groupUpTo);
  }


  var total = 0;
  var m;


  var rx =
    /(\d+)\s*(?:x\s*)?(Adults?|Children|Child|Kids?|Youth|Infants?|People|Persons?|Pax)\b/gi;


  while (
    (m = rx.exec(text)) !== null
  ) {

    total +=
      parseInt(
        m[1],
        10
      );

  }


  if (total) {
    return String(total);
  }


  var bare =
    matchInt_(
      text,
      /^\s*(\d+)\s*$/
    );


  if (bare) {
    return String(bare);
  }


  return '';

}



function detectPrivateGYG_(
  body,
  pblock,
  rawHtml
) {

  var titleInfo =
    extractGYGTitleInfo_(
      body,
      rawHtml
    );


  if (titleInfo.isPrivate) {
    return true;
  }


  var participantsText =
    normalizeWhitespace_(

      stripHtml(
        String(pblock || '')
      )

    );


  if (
    /\bgroup\s*up\s*to\b/i.test(
      participantsText
    )
  ) {

    return true;

  }


  return false;

}



/*************************************************
 * TOUR NAME HELPERS
 *************************************************/

function extractTourName(
  body,
  origin,
  subject,
  rawHtml
) {

  if (origin === 'Viator') {

    return getMatch(
      body,
      /^Tour\s*Name:\s*([^\n]+)$/mi
    );

  }


  if (origin === 'GYG') {

    var titleInfo =
      extractGYGTitleInfo_(
        body,
        rawHtml
      );


    var tour =
      titleInfo.baseTitle || '';


    if (
      !tour &&
      titleInfo.optionTitle
    ) {

      tour =
        titleInfo.optionTitle.replace(

          /^\s*private\s*[:\-]?\s*/i,

          ''

        );

    }


    if (!tour) {

      var lines =
        body
          .split('\n')
          .map(function(l) {

            return cleanLine(
              l.trim()
            );

          })
          .filter(Boolean);


      for (
        var i = 0;
        i < Math.min(
          lines.length,
          30
        );
        i++
      ) {

        if (

          lines[i].length > 10 &&

          !/^(Reference number|Date|Number of participants|Main customer|Phone|Language|Tour language|Pickup|Price)$/i
            .test(
              lines[i]
            )

        ) {

          return cleanupGYGTitle_(
            lines[i]
          );

        }

      }

    }


    return cleanupGYGTitle_(tour);

  }


  return '';

}



function extractGYGTitleInfo_(
  body,
  rawHtml
) {

  var info = {

    baseTitle: '',
    optionTitle: '',
    isPrivate: false

  };


  info.baseTitle =
    extractHtmlClassText_(
      rawHtml,
      'activity-title'
    );


  info.optionTitle =
    extractHtmlClassText_(
      rawHtml,
      'activity-option-title'
    );


  if (

    /\bprivate\b/i.test(
      info.optionTitle
    ) ||

    /^private\b/i.test(
      info.baseTitle
    )

  ) {

    info.isPrivate = true;

  }


  info.baseTitle =
    cleanupGYGTitle_(
      info.baseTitle
    );


  info.optionTitle =
    cleanupGYGTitle_(
      info.optionTitle
    );


  return info;

}



function applyPrivatePrefix_(tour) {

  var clean =
    cleanupGYGTitle_(
      tour || ''
    );


  clean =
    clean.replace(
      /^\s*private\s*[:\-]?\s*/i,
      ''
    );


  return clean
    ? 'Private: ' + clean
    : 'Private';

}



function extractHtmlClassText_(
  html,
  className
) {

  if (
    !html ||
    !className
  ) {
    return '';
  }


  var re =
    new RegExp(

      '<[^>]*class=["\'][^"\']*' +
      className +
      '[^"\']*["\'][^>]*>' +
      '([\\s\\S]*?)' +
      '<\\/[^>]+>',

      'i'
    );


  var m =
    String(html).match(re);


  return m
    ? cleanupGYGTitle_(
        m[1]
      )
    : '';

}



/*************************************************
 * BÓKUN PAX
 *************************************************/

function paxFromBokun_(s) {

  if (!s) return '';


  var total = 0;
  var m;


  var rx =
    /(\d+)\s*(Adult|Child|Infant|Kid|Youth|Niñ|Menor|Bebe|Bebé)/gi;


  while (
    (m = rx.exec(s))
  ) {

    total +=
      parseInt(
        m[1],
        10
      );

  }


  if (!total) {

    var only =
      s.match(
        /\b(\d+)\b/
      );


    if (only) {

      total =
        parseInt(
          only[1],
          10
        );

    }

  }


  return total
    ? String(total)
    : '';

}



/*************************************************
 * CIVITATIS HELPERS
 *************************************************/

function extractCivitatisField_(
  body,
  labelPattern
) {

  var lines =
    String(body || '')
      .split('\n');


  var labelRe =
    new RegExp(

      '^\\s*' +
      labelPattern +
      '\\s*:\\s*(.*)$',

      'i'

    );


  var nextLabelRe =
    /^[A-Za-zÁÉÍÓÚÑ.]{3,}(\s+[A-Za-zÁÉÍÓÚÑ.]{2,}){0,3}\s*:/;


  var linkRe =
    /^\[?https?:\/\//i;


  for (
    var i = 0;
    i < lines.length;
    i++
  ) {

    var raw =
      lines[i] || '';


    var m =
      raw.match(labelRe);


    if (!m) continue;


    var collected = [];


    if (
      m[1] &&
      m[1].trim()
    ) {

      collected.push(
        m[1].trim()
      );

    }


    for (
      var k = i + 1;
      k < lines.length;
      k++
    ) {

      var cand =
        (lines[k] || '').trim();


      if (!cand) break;

      if (
        nextLabelRe.test(cand)
      ) {
        break;
      }

      if (
        linkRe.test(cand)
      ) {
        break;
      }


      collected.push(cand);

    }


    var joined =
      collected
        .join(' ')
        .trim();


    var contactIdx =
      joined.search(
        /\(Datos de contacto\)/i
      );


    if (contactIdx !== -1) {

      joined =
        joined.substring(
          0,
          contactIdx
        ).trim();

    }


    return joined;

  }


  return '';

}



function personsCountFromCivitatis_(body) {

  var lines =
    String(body || '')
      .split('\n');


  var total = 0;
  var inBlock = false;


  for (
    var i = 0;
    i < lines.length;
    i++
  ) {

    var line =
      (lines[i] || '').trim();


    if (
      /^PERSONAS\b/i.test(line)
    ) {

      inBlock = true;
      continue;

    }


    if (!inBlock) continue;


    if (

      /^PRECIO\s*DE\s*VENTA/i.test(
        line
      ) ||

      /^PRECIO\s*NETO/i.test(
        line
      )

    ) {

      break;

    }


    var m =
      line.match(
        /(\d+)\s*(adultos?|ni[ñn]os?|infantes?|beb[ée]s?)/i
      );


    if (m) {

      total +=
        parseInt(
          m[1],
          10
        );

    }

  }


  return total
    ? String(total)
    : '';

}



function cleanupCivitatisTour_(s) {

  var t =
    normalizeWhitespace_(
      String(s || '')
    );


  t =
    t.replace(

      /\s*-\s*Tour\s+en\s+[A-Za-zÁÉÍÓÚáéíóúñÑ]+\s*$/i,

      ''

    );


  return t.trim();

}



function parseSpanishDate_(s) {

  if (!s) return null;


  var m =
    String(s).match(

      /(\d{1,2})\s+de\s+([A-Za-zÁÉÍÓÚáéíóúñÑ]+)\s+de\s+(\d{4})/i

    );


  if (!m) return null;


  var monthIdx =
    monthIndexEs_(m[2]);


  if (monthIdx === -1) {
    return null;
  }


  return new Date(

    parseInt(
      m[3],
      10
    ),

    monthIdx,

    parseInt(
      m[1],
      10
    )

  );

}



function monthIndexEs_(name) {

  var m =
    (name || '')
      .toLowerCase()
      .replace(/á/g, 'a')
      .replace(/é/g, 'e')
      .replace(/í/g, 'i')
      .replace(/ó/g, 'o')
      .replace(/ú/g, 'u');


  var months = [

    'enero',
    'febrero',
    'marzo',
    'abril',
    'mayo',
    'junio',
    'julio',
    'agosto',
    'septiembre',
    'octubre',
    'noviembre',
    'diciembre'

  ];


  for (
    var i = 0;
    i < months.length;
    i++
  ) {

    if (
      months[i].indexOf(m) === 0
    ) {

      return i;

    }

  }


  return -1;

}



/*************************************************
 * DATE / WINDOW HELPERS
 *************************************************/

function computeWindow_(tz) {

  var now =
    new Date();


  var today0730 =
    setTimeTz_(
      now,
      7,
      30,
      0,
      0,
      tz
    );


  if (
    now >= today0730
  ) {

    return {

      start: today0730,

      end:
        addDays_(
          today0730,
          1
        )

    };

  }


  return {

    start:
      addDays_(
        today0730,
        -1
      ),

    end:
      today0730

  };

}



function setTimeTz_(
  baseDate,
  hh,
  mm,
  ss,
  ms,
  tz
) {

  var y =
    parseInt(
      Utilities.formatDate(
        baseDate,
        tz,
        'yyyy'
      ),
      10
    );


  var m =
    parseInt(
      Utilities.formatDate(
        baseDate,
        tz,
        'M'
      ),
      10
    ) - 1;


  var d =
    parseInt(
      Utilities.formatDate(
        baseDate,
        tz,
        'd'
      ),
      10
    );


  return new Date(
    y,
    m,
    d,
    hh,
    mm,
    ss,
    ms
  );

}



function addDays_(date, n) {

  var d =
    new Date(
      date.getTime()
    );


  d.setDate(
    d.getDate() + n
  );


  return d;

}



function dateOnly_(date, tz) {

  var y =
    Utilities.formatDate(
      date,
      tz,
      'yyyy'
    );


  var m =
    Utilities.formatDate(
      date,
      tz,
      'MM'
    );


  var d =
    Utilities.formatDate(
      date,
      tz,
      'dd'
    );


  return new Date(

    y +
    '-' +
    m +
    '-' +
    d +
    'T00:00:00'

  );

}



/*************************************************
 * ORIGIN / TYPE
 *************************************************/

function detectOrigin(
  from,
  subject
) {

  if (
    from.indexOf(
      'getyourguide'
    ) !== -1
  ) {
    return 'GYG';
  }


  if (

    from.indexOf(
      'viator'
    ) !== -1 ||

    from.indexOf(
      'tripadvisor'
    ) !== -1 ||

    from.indexOf(
      't1.viator.com'
    ) !== -1

  ) {

    return 'Viator';

  }


  if (
    from.indexOf(
      'bokun'
    ) !== -1
  ) {
    return 'BOKUN';
  }


  if (
    from.indexOf(
      'civitatis'
    ) !== -1
  ) {
    return 'CIVITATIS';
  }


  var s =
    (subject || '')
      .toLowerCase();


  if (

    s.indexOf(
      'getyourguide'
    ) !== -1 ||

    s.indexOf(
      'gyg'
    ) !== -1

  ) {
    return 'GYG';
  }


  if (
    s.indexOf(
      'viator'
    ) !== -1
  ) {
    return 'Viator';
  }


  if (

    s.indexOf(
      'booking ref'
    ) !== -1 &&

    s.indexOf(
      'new booking'
    ) !== -1

  ) {
    return 'BOKUN';
  }


  if (
    s.indexOf(
      'civitatis'
    ) !== -1
  ) {
    return 'CIVITATIS';
  }


  return '';

}



function detectType(
  subject,
  origin
) {

  var s =
    (subject || '')
      .toLowerCase();


  if (
    s.includes('cancel') ||
    s.includes('cancela')
  ) {

    return 'Cancellation';

  }


  if (

    s.includes('amend') ||

    s.includes('change') ||

    s.includes('update') ||

    s.includes('modific') ||

    s.includes('cambio')

  ) {

    return 'Amendment';

  }


  return 'New Booking';

}



function isSkippableNotification_(
  subject,
  originKind
) {

  var s =
    (subject || '')
      .toLowerCase();


  if (
    originKind === 'CIVITATIS'
  ) {

    if (
      /mensaje\s+de\s+cliente/i.test(s)
    ) {
      return true;
    }


    if (
      /mensaje\s+de\s+agencia/i.test(s)
    ) {
      return true;
    }


    if (
      /nueva\s+pregunta/i.test(s)
    ) {
      return true;
    }


    if (
      /nueva\s+valoraci[oó]n/i.test(s)
    ) {
      return true;
    }


    if (
      /ha\s+respondido/i.test(s)
    ) {
      return true;
    }

  }


  return false;

}



/*************************************************
 * TRAVEL DATE PARSING
 *************************************************/

function extractTravelDateStrict(
  body,
  origin
) {

  var m;


  if (origin === 'Viator') {

    m =
      body.match(

        /^Travel\s*Date:\s*(?:[A-Za-z]{3},\s*)?([A-Za-z]+\s+\d{1,2},\s*\d{4})(?:[^\n]*)$/mi

      );


    if (m) {
      return parseLooseDate(
        m[1]
      );
    }

  }

  else {

    m =

      body.match(

        /^Date:\s*([A-Za-z]+\s+\d{1,2},\s*\d{4}(?:\s+\d{1,2}:\d{2}\s*(AM|PM))?)/mi

      ) ||

      body.match(

        /^Date:\s*([0-3]?\d\s+[A-Za-z]+\s+\d{4})/mi

      );


    if (m) {

      return parseLooseDate(
        m[1]
      );

    }

  }


  var g =

    body.match(

      /([A-Za-z]{3,9}\s+\d{1,2},?\s+\d{4})/i

    ) ||

    body.match(

      /([0-3]?\d\s+[A-Za-z]{3,9}\s+\d{4})/i

    );


  return g
    ? parseLooseDate(g[1])
    : null;

}



function parseBokunDisplayDate_(s) {

  if (!s) return null;


  var m =
    s.match(

      /(\d{1,2})\.(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\s*'(\d{2})/i

    );


  if (!m) {

    var alt =
      s
        .replace(/\./g,' ')
        .replace(/@.*/,'')
        .replace(/\s+/g,' ')
        .trim();


    var d =
      new Date(alt);


    return isNaN(d)
      ? null
      : d;

  }


  return new Date(

    2000 +
    parseInt(
      m[3],
      10
    ),

    monthIndex(
      m[2]
    ),

    parseInt(
      m[1],
      10
    )

  );

}



function parseBokunDateFromSubject_(subject) {

  if (!subject) {
    return null;
  }


  var m =
    subject.match(

      /(\d{1,2})\.(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\s*'(\d{2})/i

    );


  if (!m) {
    return null;
  }


  return new Date(

    2000 +
    parseInt(
      m[3],
      10
    ),

    monthIndex(
      m[2]
    ),

    parseInt(
      m[1],
      10
    )

  );

}



/*************************************************
 * HTML HELPERS
 *************************************************/

function extractHtmlFieldRaw_(
  html,
  label
) {

  var re =
    new RegExp(

      '<strong>' +
      label +
      '<\\/strong>\\s*' +
      '<\\/td>\\s*' +
      '<td[^>]*>' +
      '([\\s\\S]*?)' +
      '<\\/td>',

      'i'

    );


  var m =
    String(html || '')
      .match(re);


  return m
    ? m[1]
    : null;

}



function stripHtml(s) {

  if (!s) return '';


  return String(s)

    .replace(
      /<br\s*\/?>/gi,
      '\n'
    )

    .replace(
      /<\/div>/gi,
      '\n'
    )

    .replace(
      /<\/p>/gi,
      '\n'
    )

    .replace(
      /<[^>]+>/g,
      ''
    )

    .replace(
      /&nbsp;/g,
      ' '
    )

    .replace(
      /&amp;/g,
      '&'
    )

    .replace(
      /&lt;/g,
      '<'
    )

    .replace(
      /&gt;/g,
      '>'
    )

    .replace(
      /&quot;/g,
      '"'
    )

    .replace(
      /&#x27;|&#39;/g,
      "'"
    )

    .replace(
      /&#(\d+);/g,
      function(_, n) {

        return String.fromCharCode(
          parseInt(
            n,
            10
          )
        );

      }
    )

    .trim();

}



function stripHtmlHtml_KeepBreaks_(html) {

  return String(html || '')

    .replace(
      /<br\s*\/?>/gi,
      '\n'
    )

    .replace(
      /<\/p>/gi,
      '\n'
    )

    .replace(
      /<\/div>/gi,
      '\n'
    )

    .replace(
      /<[^>]+>/g,
      ' '
    )

    .replace(
      / /g,
      ' '
    )

    .replace(
      /\s+\n/g,
      '\n'
    )

    .replace(
      /\n\s+/g,
      '\n'
    )

    .trim();

}



/*************************************************
 * PHONE / LANGUAGE / GENERAL HELPERS
 *************************************************/

function normalizePhoneDigitsOnly_(raw) {

  if (!raw) {
    return '';
  }


  var m =
    String(raw).match(

      /\+?\d[\d\s\-()A-Za-z+]*/

    );


  if (!m) {
    return '';
  }


  return m[0].replace(
    /[^\d]/g,
    ''
  );

}



function travelersCountFromViator_(s) {

  if (!s) return '';


  var a =
    matchInt_(
      s,
      /(\d+)\s*Adult/i
    );


  var c =
    matchInt_(
      s,
      /(\d+)\s*Child/i
    );


  var i =
    matchInt_(
      s,
      /(\d+)\s*Infant/i
    );


  var total =
    (a || 0) +
    (c || 0) +
    (i || 0);


  return total
    ? String(total)
    : '';

}



function normalizeLangCode_(val) {

  if (!val) {
    return '';
  }


  var s =
    String(val)
      .toLowerCase();


  if (

    s.includes('spanish') ||
    s.includes('español') ||
    s.includes('espanol')

  ) {

    return 'SPA';

  }


  if (

    s.includes('portuguese') ||
    s.includes('portugués') ||
    s.includes('portugues')

  ) {

    return 'BRA';

  }


  if (

    s.includes('english') ||
    s.includes('inglés') ||
    s.includes('ingles')

  ) {

    return 'ENG';

  }


  return 'ENG';

}



function matchInt_(
  text,
  regex
) {

  var m =
    regex.exec(
      text || ''
    );


  return m
    ? parseInt(
        m[1],
        10
      )
    : 0;

}



function normalizeBody_(s) {

  return (s || '')

    .replace(
      / /g,
      ' '
    )

    .replace(
      /\r/g,
      ''
    );

}



function blockValue_(
  body,
  labelRegex,
  lookaheadLines
) {

  var lines =
    body.split('\n');


  var maxLook =
    Math.max(
      1,
      lookaheadLines || 2
    );


  for (
    var i = 0;
    i < lines.length;
    i++
  ) {

    if (
      labelRegex.test(
        (lines[i] || '').trim()
      )
    ) {

      for (

        var k = 1;

        k <= maxLook &&
        i + k < lines.length;

        k++

      ) {

        var cand =
          (lines[i + k] || '')
            .trim();


        if (cand) {
          return cand;
        }

      }

    }

  }


  return '';

}



function cleanMainCustomer(s) {

  var txt =
    normalizeWhitespace_(
      s || ''
    );


  txt =
    txt.replace(

      /\s+customer-[A-Z0-9._%+-]+@reply\.getyourguide\.com.*$/i,

      ''

    );


  txt =
    txt.replace(

      /\s+Phone:\s*.*$/i,

      ''

    );


  txt =
    txt.replace(

      /\s+Language:\s*.*$/i,

      ''

    );


  var idx =
    txt
      .toLowerCase()
      .indexOf(
        ' from '
      );


  return idx !== -1

    ? txt
        .substring(
          0,
          idx
        )
        .trim()

    : txt.trim();

}



function getMatch(
  text,
  regex
) {

  var m =
    (text || '')
      .match(regex);


  return m
    ? (m[1] || '').trim()
    : '';

}



function pickFirst(list) {

  for (
    var i = 0;
    i < list.length;
    i++
  ) {

    if (list[i]) {
      return list[i];
    }

  }


  return '';

}



function getNumber(s) {

  if (!s) {
    return '';
  }


  var m =
    s.match(
      /\d+/
    );


  return m
    ? m[0]
    : '';

}



function cleanLine(s) {

  if (!s) {
    return '';
  }


  return s
    .replace(
      /\s+\(.*?\)\s*$/,
      ''
    )
    .trim();

}



function normalizeWhitespace_(s) {

  return String(s || '')
    .replace(
      /\s+/g,
      ' '
    )
    .trim();

}



function cleanupGYGTitle_(s) {

  return normalizeWhitespace_(

    stripHtml(
      String(s || '')
    )

  );

}



/*************************************************
 * GENERIC DATE HELPERS
 *************************************************/

function parseLooseDate(s) {

  var d =
    new Date(s);


  if (!isNaN(d)) {
    return d;
  }


  var m1 =
    s.match(

      /^\s*([0-3]?\d)\s+([A-Za-z]+)\s+(\d{4})\s*$/

    );


  if (m1) {

    return new Date(

      parseInt(
        m1[3],
        10
      ),

      monthIndex(
        m1[2]
      ),

      parseInt(
        m1[1],
        10
      )

    );

  }


  var m2 =
    s.match(

      /^\s*([A-Za-z]+)\s+([0-3]?\d),\s*(\d{4})\s*$/

    );


  if (m2) {

    return new Date(

      parseInt(
        m2[3],
        10
      ),

      monthIndex(
        m2[1]
      ),

      parseInt(
        m2[2],
        10
      )

    );

  }


  return null;

}



function monthIndex(name) {

  var m =
    (name || '')
      .toLowerCase();


  var months = [

    'january',
    'february',
    'march',
    'april',
    'may',
    'june',
    'july',
    'august',
    'september',
    'october',
    'november',
    'december'

  ];


  for (
    var i = 0;
    i < months.length;
    i++
  ) {

    if (
      months[i].indexOf(m) === 0
    ) {

      return i;

    }

  }


  return -1;

}



function formatGmailDate(d) {

  return (

    d.getFullYear() +
    '/' +

    (
      '0' +
      (
        d.getMonth() + 1
      )
    ).slice(-2) +

    '/' +

    (
      '0' +
      d.getDate()
    ).slice(-2)

  );

}



/*************************************************
 * OPTIONAL TEST
 *
 * Run testMaipoMappings_ manually in Apps Script.
 * It does NOT modify Gmail or Sheets.
 *************************************************/

function testMaipoMappings_() {

  var tests = [

    {
      name: 'P25 TG1',
      ctx: {
        productCode: '20268P25',
        gradeCode: 'TG1'
      },
      tour: 'Maipo 4 Wineries',
      private: false
    },

    {
      name: 'P25 TG2',
      ctx: {
        productCode: '20268P25',
        gradeCode: 'TG2'
      },
      tour: 'Maipo 4 Wineries',
      private: true
    },

    {
      name: 'P25 TG3',
      ctx: {
        productCode: '20268P25',
        gradeCode: 'TG3'
      },
      tour: 'Maipo Lunch Included',
      private: false
    },

    {
      name: 'P25 TG4',
      ctx: {
        productCode: '20268P25',
        gradeCode: 'TG4'
      },
      tour: 'Maipo Lunch Included',
      private: false
    },

    {
      name: 'P25 TG99 Classic',
      ctx: {
        productCode: '20268P25',
        gradeCode: 'TG99',
        gradeName:
          'Classic Maipo Valley Wine Tour'
      },
      tour: 'Maipo Lunch Included',
      private: false
    },

    {
      name: 'P25 TG1 with Classic text - TG1 wins',
      ctx: {
        productCode: '20268P25',
        gradeCode: 'TG1',
        gradeName:
          'Classic Maipo Valley Wine Tour'
      },
      tour: 'Maipo 4 Wineries',
      private: false
    },

    {
      name: 'P15 TG1',
      ctx: {
        productCode: '20268P15',
        gradeCode: 'TG1'
      },
      tour: 'Maipo Lunch Included',
      private: false
    },

    {
      name: 'P15 TG2',
      ctx: {
        productCode: '20268P15',
        gradeCode: 'TG2'
      },
      tour: 'Maipo 4 Wineries',
      private: false
    },

    {
      name: 'P15 unknown',
      ctx: {
        productCode: '20268P15',
        gradeCode: 'TG99'
      },
      tour: 'Maipo Lunch Included',
      private: false
    },

    {
      name: 'P15 missing grade',
      ctx: {
        productCode: '20268P15'
      },
      tour: 'Maipo Lunch Included',
      private: false
    },

    {
      name: 'Legacy 4 wineries',
      ctx: {
        rate:
          'Full-Day Maipo: 4 Wineries'
      },
      tour: 'Maipo 4 Wineries',
      private: false
    },

    {
      name: 'Legacy 3 wineries',
      ctx: {
        rate:
          'Maipo Wine Tour: 3 Wineries'
      },
      tour: 'Maipo Lunch Included',
      private: false
    }

  ];


  var errors = [];


  tests.forEach(
    function(test) {

      var result =
        resolveConfiguredTourMapping_(
          test.ctx
        );


      var ok =

        result.matched &&

        result.tour ===
          test.tour &&

        result.private ===
          test.private;


      Logger.log(

        '%s | %s | tour=%s | private=%s | note=%s | warning=%s',

        ok
          ? 'PASS'
          : 'FAIL',

        test.name,

        result.tour,

        result.private,

        result.note,

        result.warning

      );


      if (!ok) {

        errors.push(

          test.name +
          ' => got "' +
          result.tour +
          '", private=' +
          result.private

        );

      }

    }
  );


  if (errors.length) {

    throw new Error(

      'Maipo mapping failures:\n' +
      errors.join('\n')

    );

  }


  Logger.log(
    'All Maipo mapping tests passed.'
  );

}
