<?php

declare(strict_types=1);

return [
    // Header / brand / navigation
    'brand.suffix'                     => 'Inscripcions',
    'header.brand_full'                => 'WeRun Inscripcions',

    // Common
    'common.back_to_list'              => 'Tornar al llistat',
    'common.cancel'                    => 'Cancel·la',
    'common.continue'                  => 'Continuar',
    'common.required'                  => 'Obligatori',
    'common.optional'                  => 'opcional',

    // Home (event listing)
    'home.title'                       => 'Inscripcions obertes',
    'home.subtitle'                    => 'Tria la cursa i inscriu-te en pocs minuts.',
    'home.empty.title'                 => 'No hi ha cap esdeveniment actiu',
    'home.empty.desc'                  => 'Torna més endavant per veure les properes curses.',
    'home.card.from'                   => 'des de',
    'home.card.no_tarifa'              => 'Sense tarifes',
    'home.card.cta'                    => 'Inscriu-te →',

    // Event detail
    'event.breadcrumb_home'            => 'Inici',
    'event.breadcrumb_events'          => 'Esdeveniments',
    'event.info_title'                 => "Informació de l'esdeveniment",
    'event.event_label'                => 'Esdeveniment',
    'event.location_label'             => 'Localització',
    'event.dates_label'                => "Dates d'inscripció",
    'event.dates_end'                  => 'Fi',
    'event.capacity_label'             => 'Aforament',
    'event.capacity_value'             => '{n} places disponibles',
    'event.description_title'          => 'Descripció',
    'event.closed_title'               => 'Inscripcions tancades',
    'event.closed_no_tarifa'           => 'No hi ha tarifes disponibles ara mateix.',
    'event.closed_full'                => "S'ha esgotat l'aforament.",
    'event.closed_generic'             => 'Les inscripcions per a aquest esdeveniment estan tancades.',

    // Form labels
    'form.tarifa.title'                => 'Tria la tarifa',
    'form.tarifa.label'                => 'Tarifa',
    'form.tarifa.placeholder'          => '— Tria una tarifa —',
    'form.personal.title'              => 'Dades personals',
    'form.label.name'                  => 'Nom',
    'form.label.surname'               => 'Cognoms',
    'form.label.dni'                   => 'DNI / NIE',
    'form.label.birth_date'            => 'Data de naixement',
    'form.label.email'                 => 'Correu electrònic',
    'form.label.phone'                 => 'Telèfon',
    'form.label.sex'                   => 'Sexe',
    'form.label.sex.choose'            => '— Tria —',
    'form.label.sex.male'              => 'Home',
    'form.label.sex.female'            => 'Dona',
    'form.label.sex.nonbinary'         => 'No binari',
    'form.label.shirt'                 => 'Talla samarreta',
    'form.label.shirt.none'            => '— Sense —',
    'form.label.city'                  => 'Població',
    'form.label.postal_code'           => 'Codi postal',
    'form.label.club'                  => 'Club',
    'form.label.custom_fields'         => 'Camps addicionals',
    'form.label.discount_question'     => 'Tens un codi de descompte?',
    'form.label.discount'              => 'Codi (lletres i números)',
    'form.label.discount.hint'         => "S'aplicarà el descompte al pagament.",
    'form.submit'                      => "Inscriure'm i pagar",
    'form.submit.note'                 => 'Pagament segur amb targeta o Bizum via Redsys.',
    'form.test.banner'                 => 'Mode prova',
    'form.test.banner_desc'            => 'clica per omplir el formulari amb dades aleatòries vàlides.',
    'form.test.fill'                   => '🧪 Omplir prova',

    // Payment method selector
    'payment.choose_title'             => 'Tria com vols pagar',
    'payment.method.card'              => 'Targeta',
    'payment.method.card.desc'         => 'Visa, Mastercard…',
    'payment.method.bizum'             => 'Bizum',
    'payment.method.bizum.desc'        => 'Pagament des del mòbil',
    'payment.method.note'              => 'Seràs redirigit a la pàgina segura de Redsys per completar el pagament.',

    // Payment redirect
    'payment.redirecting.title'        => 'Et redirigim al pagament segur…',
    'payment.redirecting.desc'         => 'Pagaràs {price} amb {method} per {event}.',
    'payment.redirecting.fallback'     => "Si no et redirigeix automàticament en 3 segons, clica el botó:",
    'payment.redirecting.manual'       => 'Continuar manualment',
    'payment.redirecting.noscript'     => 'Continuar al pagament',
    'payment.method_name.card'         => 'targeta',
    'payment.method_name.bizum'        => 'Bizum',

    // Payment OK page
    'payment.ok.processing.title'      => 'Verificant el pagament…',
    'payment.ok.processing.desc'       => "Hem rebut la confirmació del banc però encara estem actualitzant la teva inscripció. En uns segons hauràs de veure aquí el comprovant.",
    'payment.ok.processing.note'       => 'Si en un minut no es refresca, contacta amb nosaltres amb l\'ID #{id}.',
    'payment.ok.title'                 => 'Pagament completat',
    'payment.ok.lead'                  => 'Gràcies, {name}. La teva inscripció a {event} ha quedat confirmada.',
    'payment.ok.summary.event'         => 'Esdeveniment',
    'payment.ok.summary.tarifa'        => 'Tarifa',
    'payment.ok.summary.reference'     => 'Referència',
    'payment.ok.summary.auth'          => 'Autorització',
    'payment.ok.summary.status'        => 'Estat',
    'payment.ok.summary.confirmed'     => 'Confirmat',
    'payment.ok.email_notice'          => 'Aviat rebràs un correu amb el comprovant i el codi QR per fer el check-in el dia de l\'esdeveniment.',

    // Payment KO page
    'payment.ko.title'                 => "El pagament no s'ha completat",
    'payment.ko.bank_denied'           => 'El banc ha denegat o cancel·lat la transacció (codi {code}).',
    'payment.ko.generic_error'         => "La transacció no s'ha pogut completar.",
    'payment.ko.summary.status_pending'=> 'Pendent',
    'payment.ko.kept'                  => 'La teva inscripció continua reservada. Pots tornar a intentar el pagament.',
    'payment.ko.retry'                 => 'Tornar a intentar el pagament',
    'payment.ko.back_event'            => "Tornar a l'esdeveniment",

    // Inscripció gratuita (exit)
    'success.received_title'           => 'Inscripció rebuda',
    'success.lead'                     => 'Gràcies, {name}. Hem rebut la teva inscripció a {event}.',
    'success.summary.dni'              => 'DNI',
    'success.summary.email'            => 'Correu',
    'success.summary.event'            => 'Esdeveniment',
    'success.summary.tarifa'           => 'Tarifa',
    'success.summary.status'           => 'Estat',
    'success.summary.pending_payment'  => 'Pendent de pagament',
    'success.payment_note'             => "El pagament amb targeta s'activarà ben aviat. Mentrestant, la teva inscripció queda registrada com a pendent. T'enviarem un correu quan puguis completar el pagament.",

    // Email confirmacio
    'email.subject'                    => 'Inscripció confirmada · {event}',
    'email.header_title'               => 'Inscripció confirmada ✓',
    'email.greeting'                   => 'Hola {name},',
    'email.intro'                      => "La teva inscripció a {event} ha estat confirmada correctament. Aquí tens els detalls i el QR que hauràs de mostrar el dia de l'esdeveniment per fer el check-in.",
    'email.field.event'                => 'Esdeveniment',
    'email.field.date'                 => 'Data',
    'email.field.tarifa'               => 'Tarifa',
    'email.field.runner'               => 'Corredor',
    'email.field.payment_ref'          => 'Referència pagament',
    'email.qr.title'                   => 'El teu QR de check-in',
    'email.qr.desc'                    => "Mostra aquest codi a l'organització el dia de l'esdeveniment. Guarda aquest correu o fes una captura.",
    'email.qr.alt'                     => 'QR Check-in',
    'email.footer.contact'             => 'Si tens qualsevol dubte, respon a aquest correu.',

    // Errors / flashes
    'error.session_expired'            => 'La sessió ha expirat. Torna a omplir el formulari.',
    'error.event_closed'               => 'Les inscripcions per a aquest esdeveniment estan tancades.',
    'error.tarifa_invalid'             => 'Tria una tarifa vàlida.',
    'error.tarifa_full'                => 'La tarifa triada està esgotada.',
    'error.discount_invalid'           => 'El codi de descompte no és vàlid per a aquest esdeveniment.',
];
