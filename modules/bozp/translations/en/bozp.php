<?php

/**
 * English translations for the BOZP module.
 * Source language is Slovak — keys here are the Slovak strings.
 */

return [
    // Module / nav / permissions
    'BOZP Permity' => 'BOZP Permits',
    'Vytvárať permity' => 'Create permits',
    'Zobraziť schvaľovaciu frontu HSE' => 'View HSE approval queue',
    'Schvaľovať / zamietať permity' => 'Approve / reject permits',
    'Zobraziť všetky permity' => 'View all permits',
    'Spravovať zóny' => 'Manage zones',

    // CP subnav
    'Schvaľovacia fronta' => 'Approval queue',
    'Všetky permity' => 'All permits',
    'Filtrovať' => 'Filter',
    'Všetky stavy' => 'All statuses',
    'Žiadne permity.' => 'No permits.',

    // Queue page
    'Schvaľovacia fronta HSE' => 'HSE Approval Queue',
    'Permity čakajúce na schválenie' => 'Permits awaiting approval',
    'Žiadne permity nečakajú na schválenie.' => 'No permits awaiting approval.',
    'Číslo' => 'Number',
    'Dodávateľ' => 'Contractor',
    'Miesto výkonu' => 'Work location',
    'Odoslané' => 'Submitted',
    'Akcie' => 'Actions',
    'Otvoriť' => 'Open',

    // Front-end dashboard + layout
    'Moje permity' => 'My permits',
    'Nový permit' => 'New permit',
    'Odhlásiť sa' => 'Log out',
    'Prihlásiť sa' => 'Log in',
    'Stav' => 'Status',
    'Vytvorené' => 'Created',
    'Zatiaľ ste nevytvorili žiadny permit.' => 'You haven\'t created any permits yet.',

    // Login screen
    'Prihlásenie' => 'Sign in',
    'Prihláste sa vašimi firemnými prístupovými údajmi.' =>
        'Sign in with your company credentials.',
    'Prihlasovacie meno alebo e-mail' => 'Username or email',
    'Heslo' => 'Password',
    'Zostať prihlásený na tomto zariadení' => 'Stay signed in on this device',
    'Problém s prihlásením? Kontaktujte HSE oddelenie.' =>
        'Trouble signing in? Contact the HSE department.',
    'Zadajte prihlasovacie meno alebo e-mail.' => 'Enter your username or email.',
    'Zadajte heslo.' => 'Enter your password.',
    'Nesprávne prihlasovacie údaje.' => 'Incorrect sign-in credentials.',
    'Účet nie je aktívny. Kontaktujte HSE.' => 'Account is not active. Please contact HSE.',
    'Prihlásenie zlyhalo. Skúste znova.' => 'Sign-in failed. Please try again.',
    'Prihlásenie bolo úspešné.' => 'You are signed in.',
    'Boli ste odhlásení.' => 'You have been signed out.',

    // Status labels
    'Koncept' => 'Draft',
    'Schválené' => 'Approved',
    'Zamietnuté' => 'Rejected',
    'Podpísané' => 'Signed',
    'Aktívne' => 'Active',
    'Čaká na uzavretie' => 'Pending closure',
    'Uzavreté' => 'Closed',
    'Zrušené' => 'Cancelled',
    'Vypršané' => 'Expired',

    // Permit form
    'Vyplňte údaje a odošlite na schválenie HSE, alebo uložte ako koncept a pokračujte neskôr.' =>
        'Fill in the details and submit for HSE approval, or save as a draft to continue later.',
    'Názov dodávateľskej firmy' => 'Contractor company name',
    'Kontaktná osoba' => 'Contact person',
    'E-mail' => 'Email',
    'Práca' => 'Work',
    'Popis prác' => 'Work description',
    'Platnosť od' => 'Valid from',
    'Platnosť do' => 'Valid to',
    'Zóny' => 'Zones',
    'Vyberte jednu alebo viac zón, ktorých sa práca týka.' =>
        'Select one or more zones this work applies to.',
    'Platnosť permitu je 7 dní od dátumu schválenia HSE.' =>
        'The permit is valid for 7 days from the date of HSE approval.',
    'Uložiť ako koncept' => 'Save as draft',
    'Odoslať na schválenie' => 'Submit for approval',
    'Zrušiť' => 'Cancel',

    // Form validation + flash messages
    'Skontrolujte chyby vo formulári.' => 'Please review the errors in the form.',
    'Názov dodávateľa je povinný.' => 'Contractor name is required.',
    'Miesto výkonu je povinné.' => 'Work location is required.',
    'Popis prác je povinný.' => 'Work description is required.',
    'Neplatná e-mailová adresa.' => 'Invalid email address.',
    'Plánovaný začiatok je povinný pri odoslaní.' => 'Start time is required on submission.',
    'Plánovaný koniec je povinný pri odoslaní.' => 'End time is required on submission.',
    'Koniec musí byť po začiatku.' => 'End time must be after start time.',
    'Permit sa nepodarilo uložiť. Skúste znova.' => 'The permit could not be saved. Please try again.',
    'Permit {n} bol odoslaný na schválenie HSE.' => 'Permit {n} was submitted for HSE approval.',
    'Permit {n} bol uložený ako koncept.' => 'Permit {n} was saved as a draft.',

    // Front-end permit detail
    'Permit bol zamietnutý' => 'Permit was rejected',
    'Permit bol schválený' => 'Permit was approved',
    'História' => 'History',

    // Permit detail view (CP)
    'Permit {n}' => 'Permit {n}',
    'Vydavateľ a dodávateľ' => 'Issuer and contractor',
    'Vydavateľ' => 'Issuer',
    'Komentár HSE' => 'HSE comment',
    'Rozhodnutie HSE' => 'HSE decision',
    'Komentár (nepovinné)' => 'Comment (optional)',
    'Dôvod zamietnutia' => 'Reason for rejection',
    'Povinné pri zamietnutí.' => 'Required when rejecting.',
    'Schváliť' => 'Approve',
    'Zamietnuť' => 'Reject',
    'Audit' => 'Audit',
    'Kedy' => 'When',
    'Akcia' => 'Action',
    'Zmena stavu' => 'Status change',
    'Poznámka' => 'Note',
    'Žiadne záznamy.' => 'No entries.',

    // Approve / reject flash messages
    'Permit nie je v stave na schválenie.' => 'This permit is not in a state that can be approved.',
    'Permit nie je v stave na zamietnutie.' => 'This permit is not in a state that can be rejected.',
    'Pri zamietnutí je komentár povinný.' => 'A comment is required when rejecting.',
    'Permit sa nepodarilo schváliť. Skúste znova.' => 'The permit could not be approved. Please try again.',
    'Permit sa nepodarilo zamietnuť. Skúste znova.' => 'The permit could not be rejected. Please try again.',
    'Permit {n} bol schválený.' => 'Permit {n} was approved.',
    'Permit {n} bol zamietnutý.' => 'Permit {n} was rejected.',

    // --- Phase 2C.2: hazard matrix + preparation ---

    // Generic yes/no
    'Áno' => 'Yes',
    'Nie' => 'No',

    // Hazards section
    'Riziká a OOPP' => 'Hazards and PPE',
    'Pri každom riziku vyznačte, či je pracovník exponovaný, uveďte ochranné opatrenie a spôsob kontroly počas činnosti.' =>
        'For each hazard, indicate whether the worker is exposed, specify the protective measure and the form of control during the activity.',
    'Riziko' => 'Hazard',
    'Exponovaný?' => 'Exposed?',
    'Opatrenie / OOPP' => 'Measure / PPE',
    'Kontrola počas činnosti' => 'Control during activity',
    'Použité' => 'In use',
    'Nepoužité' => 'Not in use',
    'Iné' => 'Other',
    'Iné — upresnite' => 'Other — please specify',
    'Neboli uvedené žiadne riziká.' => 'No hazards were declared.',

    // Hazard category labels (must match HazardCategory::label())
    'Hluk' => 'Noise',
    'Koža' => 'Skin',
    'Oči' => 'Eyes',
    'Náraz do hlavy' => 'Head impact',
    'Bod vtiahnutia alebo pomliaždenia' => 'Pinch / crush point',
    'Porezanie' => 'Cuts',
    'Ergonomický rizikový faktor' => 'Ergonomic risk factor',
    'Pošmyknutie, zakopnutie, pád' => 'Slip, trip, fall',
    'Priemyselné vozíky/plošiny' => 'Industrial trucks / platforms',
    'Horúci povrch' => 'Hot surface',
    'Respiračné riziko' => 'Respiratory hazard',
    'Nebezpečná energia (LOTO)' => 'Hazardous energy (LOTO)',
    'Telo' => 'Body',
    'Vyžaduje sa pohotovostný režim' => 'Standby required',
    'Ochrana v pohotovostnom režime' => 'Standby protection',
    'Iné riziko' => 'Other hazard',

    // Hazard default measures (prefilled in the textarea)
    'Štuple do uší' => 'Earplugs',
    'Kombinézy/Špeciálny oblek' => 'Coveralls / special suit',
    'Ochranné okuliare/Ochranný štít/Šilt' => 'Safety glasses / face shield / visor',
    'Nárazová čiapka/Prilba' => 'Bump cap / hard hat',
    'Kryt/Uzamknutie-Lockout/PLC/PLD' => 'Guard / lockout / PLC / PLD',
    'Kryt/Rukavice/Uzamknutie-Lockout' => 'Guard / gloves / lockout',
    'Vybavenie/Postupy/Spoločná práca' => 'Equipment / procedures / team work',
    'Protišmyková obuv/Systém zadržiavania pádu' => 'Anti-slip footwear / fall arrest system',
    'Bariéra/Notifikácia/Vysoká viditeľnosť' => 'Barrier / notification / high visibility',
    'Ochranné rukavice/vychladnutie' => 'Protective gloves / cool-down',
    'Maska proti prachu/Kazeta/Celá tvár' => 'Dust mask / cartridge / full face',
    'Označenie/Zámok(y)/Haspra(y)' => 'Tags / locks / hasps',
    'Zástera/Oblečenie spomaľujúce horenie' => 'Apron / flame-retardant clothing',
    'Dozorujúci pripravený a vstupujúci je pripojený k monitorovaciemu systému' =>
        'Attendant on standby, entrant connected to monitoring system',
    'OOPP/Procedúra' => 'PPE / procedure',

    // Preparation checks
    'Príprava pracoviska' => 'Workplace preparation',
    'Potvrďte stav pracoviska a vybavenia pred začiatkom prác.' =>
        'Confirm the state of the workplace and equipment before starting work.',
    'Sú pracovné podmienky vhodné na vykonávanie prác?' =>
        'Are the working conditions suitable for performing the work?',
    'Je náradie a vybavenie v dobrom technickom stave?' =>
        'Are tools and equipment in good technical condition?',
    'Existujú podmienky, pri ktorých je nutné práce zastaviť?' =>
        'Are there conditions under which the work must be stopped?',
    'Popis podmienok pre zastavenie prác' => 'Description of stop-work conditions',
    'Je zavedený LOTO (uzamknutie / označenie)?' => 'Is LOTO (lockout / tagout) in place?',
    'Núdzový plán / postup v prípade mimoriadnej udalosti' =>
        'Emergency plan / procedure in case of an incident',
];
