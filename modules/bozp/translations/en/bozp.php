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

    // ----- Email notifications --------------------------------------------
    'E-mail dodávateľa je povinný pri odoslaní.' => 'Contractor email is required at submit.',
    'Dobrý deň,' => 'Hello,',
    'Toto je automatická správa zo systému BOZP Permity.' =>
        'This is an automated message from the BOZP Permits system.',

    // submitted-hse
    'Nový permit čaká na schválenie' => 'A new permit is waiting for approval',
    'Nový permit čaká na schválenie: {n}' => 'A new permit is waiting for approval: {n}',
    'Bol odoslaný nový permit {n} a čaká na vaše schválenie.' =>
        'Permit {n} has been submitted and is waiting for your approval.',
    'Otvoriť permit' => 'Open permit',

    // approved
    'Permit bol schválený' => 'Permit approved',
    'Permit {n} bol schválený' => 'Permit {n} approved',
    'Permit {n} bol schválený oddelením HSE.' => 'Permit {n} has been approved by HSE.',
    'Zobraziť permit' => 'View permit',

    // rejected
    'Permit bol zamietnutý' => 'Permit rejected',
    'Permit {n} bol zamietnutý' => 'Permit {n} rejected',
    'Permit {n} bol zamietnutý oddelením HSE.' => 'Permit {n} has been rejected by HSE.',
    'Dôvod' => 'Reason',

    // ----- Contractor email (QR + password) ------------------------------
    'Permit {n} bol schválený. Pre prístup k detailu permitu použite nasledujúci odkaz alebo naskenujte QR kód.' =>
        'Permit {n} has been approved. Use the link below or scan the QR code to view the permit.',
    'Heslo pre prístup' => 'Access password',
    'Odkaz a heslo platia do dátumu skončenia platnosti permitu.' =>
        'The link and password are valid until the permit expires.',

    // ----- Contractor pages ----------------------------------------------
    'Permit' => 'Permit',
    'Permit {n}' => 'Permit {n}',
    'Prístup k permitu' => 'Permit access',
    'Zadajte heslo, ktoré ste dostali e-mailom, pre zobrazenie permitu {n}.' =>
        'Enter the password you received by email to view permit {n}.',
    'Heslo' => 'Password',
    'Pokračovať' => 'Continue',
    'Nesprávne heslo.' => 'Incorrect password.',
    'Platnosť odkazu vypršala' => 'Link has expired',
    'Tento permit už nie je platný a odkaz nie je možné použiť. V prípade otázok kontaktujte HSE oddelenie.' =>
        'This permit is no longer valid and the link cannot be used. Contact HSE for assistance.',

    // ----- Contractor detail page ----------------------------------------
    'Stav' => 'Status',
    'Dodávateľ' => 'Contractor',
    'Miesto výkonu' => 'Work location',
    'Popis prác' => 'Work description',
    'Zóny' => 'Zones',
    'Príprava pracoviska' => 'Workplace preparation',
    'Riziká a OOPP' => 'Hazards & PPE',
    'Kategória' => 'Category',
    'Vystavený' => 'Exposed',
    'Opatrenie / OOPP' => 'Measure / PPE',
    'Kontrola počas činnosti' => 'Control during activity',
    'Áno' => 'Yes',
    'Nie' => 'No',
    'V používaní' => 'In use',
    'Nepoužíva sa' => 'Not in use',
    'Iné' => 'Other',

    // ----- Attachment upload ---------------------------------------------
    'Prílohy dodávateľa' => 'Contractor attachments',
    'Zatiaľ neboli nahrané žiadne prílohy.' => 'No attachments have been uploaded yet.',
    'Súbor nie je dostupný' => 'File is not available',
    'Nahrať súbor (PDF, DOCX, JPG, PNG, max 10 MB)' =>
        'Upload a file (PDF, DOCX, JPG, PNG, max 10 MB)',
    'Nahrať' => 'Upload',
    'Súbor bol nahraný.' => 'File uploaded.',
    'Nepodarilo sa nahrať súbor. Skúste znova.' => 'Could not upload the file. Please try again.',
    'Nahrávanie súboru zlyhalo. Skúste znova.' => 'File upload failed. Please try again.',
    'Súbor je príliš veľký. Maximálna veľkosť je 10 MB.' =>
        'File is too large. Maximum size is 10 MB.',
    'Nepodporovaný typ súboru. Povolené: PDF, DOCX, JPG, PNG.' =>
        'Unsupported file type. Allowed: PDF, DOCX, JPG, PNG.',
    'Úložisko súborov nie je nastavené. Kontaktujte HSE.' =>
        'File storage is not configured. Please contact HSE.',

    // ----- CP attachments / actions --------------------------------------
    'Prílohy' => 'Attachments',
    'Žiadne prílohy.' => 'No attachments.',
    'Súbor' => 'File',
    'Typ' => 'Type',
    'Nahral' => 'Uploaded by',
    'Akcie' => 'Actions',
    'Mazať permity' => 'Delete permits',
    'Zmazať permit' => 'Delete permit',
    'Naozaj zmazať tento permit? Operáciu nie je možné vrátiť.' =>
        'Really delete this permit? This cannot be undone.',
    'Permit {n} bol zmazaný.' => 'Permit {n} has been deleted.',
    'Permit sa nepodarilo zmazať.' => 'Could not delete the permit.',
    'Znova odoslať schválenie (nové heslo)' => 'Resend approval (new password)',
    'Znova odoslať e-mail o zamietnutí' => 'Resend rejection email',
    'Notifikácia o schválení bola znova odoslaná. Vygenerované nové prístupové údaje pre dodávateľa.' =>
        'Approval notification resent. New access credentials generated for the contractor.',
    'Notifikácia o zamietnutí bola znova odoslaná.' => 'Rejection notification resent.',
    'Notifikáciu možno znova odoslať len pre schválené alebo zamietnuté permity.' =>
        'Notification can only be resent for approved or rejected permits.',
    'Notifikáciu sa nepodarilo odoslať.' => 'Could not resend the notification.',

    // ----- Login language switcher ---------------------------------------
    'Jazyk' => 'Language',

    // ----- Contractor signature ------------------------------------------
    'Podpis prijímateľa' => 'Recipient signature',
    'Meno podpisujúceho' => 'Signer name',
    'Zamestnávateľ' => 'Employer',
    'Dátum podpisu' => 'Signature date',
    'Podpis' => 'Signature',
    'Vyčistiť' => 'Clear',
    'Podpísať a potvrdiť' => 'Sign and confirm',
    'Podpísal' => 'Signed by',
    'Zaznamenané' => 'Recorded at',
    'Permit nie je v stave, v ktorom je možné podpísať.' =>
        'The permit is not in a state where it can be signed.',
    'Permit už bol podpísaný.' => 'The permit has already been signed.',
    'Meno podpisujúceho je povinné.' => 'Signer name is required.',
    'Dátum podpisu je povinný.' => 'Signature date is required.',
    'Podpis je povinný.' => 'Signature is required.',
    'Permit bol úspešne podpísaný.' => 'Permit signed successfully.',
    'Podpis sa nepodarilo uložiť. Skúste znova.' => 'Could not save the signature. Please try again.',

    // ----- Sign dialog ---------------------------------------------------
    'Permit je pripravený na podpis dodávateľom.' => 'The permit is ready for the contractor to sign.',
    'Podpísať permit' => 'Sign permit',
    'Pred podpisom' => 'Before signing',
    'Podpisom potvrdzujete, že ste sa oboznámili s podmienkami permitu a so všetkými uvedenými rizikami a opatreniami, a zaväzujete sa ich pri vykonávaní prác dodržiavať.' =>
        'By signing, you confirm that you have reviewed the permit conditions and all listed hazards and controls, and you commit to following them while the work is performed.',
    'Upozornenie' => 'Warning',
    'Po podpise sa permit uzamkne a údaje nie je možné meniť.' =>
        'After signing, the permit will be locked and the data cannot be changed.',
    'Pokračovať k podpisu' => 'Continue to signing',
    'Zrušiť' => 'Cancel',
    'Späť' => 'Back',

    // ----- Closure (recipient + issuer) ---------------------------------
    'Dokončenie permitu' => 'Permit closure',
    'Dokončenie / zrušenie' => 'Closure / cancellation',
    'Dokončiť práce' => 'Mark work as done',
    'Označte aktuálny stav po dokončení prác a podpíšte uzavretie.' =>
        'Mark the current state after the work is done and sign the closure.',
    'Po podpise dokončenia sa už údaje nedajú meniť.' =>
        'After signing the closure the data can no longer be changed.',
    'Stav po dokončení' => 'Closure status',
    'Vyberte aspoň jednu možnosť.' => 'Pick at least one option.',
    'Práce boli dokončené' => 'Work has been completed',
    'Zariadenie je prevádzkyschopné' => 'Equipment is operational',
    'Zariadenie nie je prevádzkyschopné' => 'Equipment is not operational',
    'Všetky osoby opustili oblasť a materiály a zariadenia boli z oblasti odstránené' =>
        'All persons have left the area and materials and equipment have been removed',
    'Práce boli pozastavené' => 'Work has been suspended',
    'Permit nie je v stave, v ktorom je možné dokončiť.' =>
        'The permit is not in a state where it can be closed.',
    'Dokončenie už bolo podpísané.' => 'Closure has already been signed.',
    'Dokončenie bolo zaznamenané.' => 'Closure recorded.',
    'Dokončenie sa nepodarilo uložiť. Skúste znova.' => 'Could not save the closure. Please try again.',
    'Dokončenie zatiaľ nie je možné.' => 'Closure is not yet possible.',
    'Dokončenie dodávateľa' => 'Contractor closure',

    // ----- Issuer cancel / close ----------------------------------------
    'Uzavretie permitu' => 'Permit completion',
    'Zrušiť permit' => 'Cancel permit',
    'Uzavrieť permit' => 'Close permit',
    'Dôvod zrušenia' => 'Reason for cancellation',
    'Dôvod zrušenia je povinný.' => 'Reason for cancellation is required.',
    'Vyžaduje sa skúšobná prevádzka?' => 'Trial operation required?',
    'Vyžaduje sa skúšobná prevádzka' => 'Trial operation required',
    'Práca zrušená / pozastavená' => 'Work canceled / suspended',
    'Práce dokončené, LOTO odstránené' => 'Work completed, LOTO removed',
    'Zrušenie permitu' => 'Permit cancellation',
    'Permit bude označený ako zrušený. Práca, na ktorú sa vzťahuje, je zrušená alebo pozastavená a zariadenie zostáva izolované.' =>
        'The permit will be marked as cancelled. The work covered is cancelled or suspended and equipment stays isolated.',
    'Po podpise sa permit uzamkne ako zrušený a údaje nie je možné meniť.' =>
        'After signing the permit will be locked as cancelled and the data cannot be changed.',
    'Zrušiť a podpísať' => 'Cancel and sign',
    'Práce, na ktoré sa vzťahuje toto povolenie, sú dokončené. Všetky LOTO zámky a štítky, izolácie atď. boli odstránené a zariadenie je vhodné na návrat do prevádzky.' =>
        'The work covered by this permit is completed. All LOTO locks/tags, isolations etc. have been removed and the equipment is fit to return to service.',
    'Po podpise sa permit uzamkne ako uzavretý a údaje nie je možné meniť.' =>
        'After signing the permit will be locked as closed and the data cannot be changed.',
    'Uzavrieť a podpísať' => 'Close and sign',
    'Permit nie je v stave, v ktorom je možné dokončiť. Dodávateľ ho musí najprv podpísať.' =>
        'The permit is not in a state where it can be closed. The contractor must sign it first.',
    'Permit bol zrušený.' => 'Permit cancelled.',
    'Permit bol uzavretý.' => 'Permit closed.',
    'Permit sa nepodarilo zrušiť. Skúste znova.' => 'Could not cancel the permit. Please try again.',
    'Permit sa nepodarilo uzavrieť. Skúste znova.' => 'Could not close the permit. Please try again.',
    'Dodávateľ ešte nepodpísal dokončenie. Permit bude možné uzavrieť po jeho podpise.' =>
        'The contractor has not signed closure yet. The permit can be closed after that signature.',

    // ----- Contractor: done vs cancel split ------------------------------
    'Vyberte jednu z možností: dokončenie alebo zrušenie prác.' =>
        'Choose one option: complete the work or cancel it.',
    'Práce dokončené' => 'Work completed',
    'Práce zrušené' => 'Work cancelled',
    'Potvrdzujete, že práce na tomto permite boli ukončené. Označte aktuálny stav pracoviska.' =>
        'You confirm the work on this permit has been completed. Mark the current state of the workplace.',
    'Zrušenie prác' => 'Cancellation of work',
    'Potvrdzujete, že práce nemôžu byť vykonané za týchto podmienok a permit má byť zrušený.' =>
        'You confirm the work cannot be performed under these conditions and the permit is to be cancelled.',
    'Po podpise zrušenia sa permit uzamkne ako zrušený a nedá sa znova otvoriť.' =>
        'After signing the cancellation the permit is locked as cancelled and cannot be reopened.',
    'Krátko popíšte, prečo nie je možné práce vykonať.' =>
        'Briefly describe why the work cannot be performed.',
    'Permit nie je v stave, v ktorom je možné zrušiť.' =>
        'The permit is not in a state where it can be cancelled.',
    'Permit bol zrušený dodávateľom.' => 'Permit was cancelled by the contractor.',
    'Zrušenie sa nepodarilo uložiť. Skúste znova.' => 'Could not save the cancellation. Please try again.',
    'Permit je uzamknutý — ďalšie prílohy už nie je možné pridávať.' =>
        'The permit is locked — no further attachments can be added.',

    // ----- Subpermit types --------------------------------------------------
    'Zvýšené nebezpečenstvo požiaru'          => 'Increased fire risk (hot work)',
    'Vstup do stiesnených priestorov'         => 'Confined space entry',
    'Práce vo výškach'                        => 'Work at heights',
    'Príkaz „B"'                              => 'Command "B"',
    'Vysokorizikové elektrické práce'         => 'High-risk electrical work',
    'Výkopové práce'                          => 'Excavation work',
    'Zdvíhacie práce a práce so žeriavom'     => 'Lifting and crane work',
    'Práce v prostredí ATEX'                  => 'Work in ATEX environments',

    // ----- Subpermit statuses -----------------------------------------------
    'Čaká na schválenie'  => 'Pending approval',
    'Expirované'          => 'Expired',

    // ----- Subpermit UI strings ---------------------------------------------
    'Subpermity'                              => 'Subpermits',
    'Pridať subpermit'                        => 'Add subpermit',
    'Vybrať typ subpermitu'                   => 'Select subpermit type',
    'Príloha č. {n}'                          => 'Appendix no. {n}',
    'Platný do'                               => 'Valid until',
    'Platnosť subpermitu je 8 hodín od schválenia HSE.' =>
        'The subpermit is valid for 8 hours from HSE approval.',
    'Schváliť subpermit'                      => 'Approve subpermit',
    'Zamietnuť subpermit'                     => 'Reject subpermit',
    'Subpermit bol schválený.'                => 'Subpermit approved.',
    'Subpermit bol zamietnutý.'               => 'Subpermit rejected.',
    'Subpermit sa nepodarilo schváliť. Skúste znova.' =>
        'Could not approve the subpermit. Please try again.',
    'Subpermit sa nepodarilo zamietnuť. Skúste znova.' =>
        'Could not reject the subpermit. Please try again.',
    'Subpermit sa nepodarilo uložiť. Skúste znova.' =>
        'Could not save the subpermit. Please try again.',
    'Subpermit bol uložený.'                  => 'Subpermit saved.',
    'Subpermit bol zrušený.'                  => 'Subpermit cancelled.',
    'Žiadne subpermity.'                      => 'No subpermits.',
    'Vyžaduje subpermit(y) zvýšeného rizika'  => 'Requires high-risk subpermit(s)',
    'Chýbajúce subpermity'                    => 'Missing subpermits',
    'Chýba subpermit'                         => 'Missing subpermit',
    'Tento permit vyžaduje nasledujúce subpermity, ktoré ešte neboli vytvorené: {types}.' =>
        'This permit requires the following subpermits that have not been created yet: {types}.',

    // ----- Permit form — subpermit section ----------------------------------
    'Subpermity zvýšeného rizika'             => 'High-risk subpermits',
    'Označte typy subpermitov, ktoré budú potrebné pre tento permit. Subpermity sa vytvárajú a schvaľujú samostatne po vytvorení permitu.' =>
        'Mark the subpermit types that will be required for this permit. Subpermits are created and approved separately after the permit is created.',
    'Príloha č. {n}'                          => 'Appendix no. {n}',

    // ============================================================
    // Bulk translation pass — covers every Slovak source string used
    // in templates + PHP that was previously missing from this file.
    // ============================================================

    // Severity / general UI
    'Kritická'                                => 'Critical',
    'Vysoká'                                  => 'High',
    'Stredná'                                 => 'Medium',
    'Nízka'                                   => 'Low',
    'Závažnosť'                               => 'Severity',
    'Pridať'                                  => 'Add',
    'Upraviť'                                 => 'Edit',
    'Odoslať'                                 => 'Send',
    'Detail'                                  => 'Details',
    'Popis'                                   => 'Description',
    'Komentár / poznámka'                     => 'Comment / note',
    'Uložiť zmeny'                            => 'Save changes',
    'Opravte nasledujúce chyby'               => 'Please correct the following errors',
    'Otvoriť v administrácii'                 => 'Open in admin',
    'Pridať'                                  => 'Add',
    'Vaše meno a priezvisko'                  => 'Your name and surname',
    'Meno a priezvisko'                       => 'Name and surname',
    'Meno je povinné.'                        => 'Name is required.',
    'Vymazať podpis'                          => 'Clear signature',
    'Pridajte svoj podpis.'                   => 'Add your signature.',
    'Chýba podpis.'                           => 'Signature missing.',
    'Zadajte meno podpisujúceho.'             => 'Enter the signer name.',
    'Podpis a meno sú povinné.'               => 'Signature and name are required.',
    'Funkcia / pracovné zaradenie'            => 'Job title / role',
    'Váša rola'                               => 'Your role',
    'Firma'                                   => 'Company',
    'Oddelenie'                               => 'Department',
    'Meno'                                    => 'Name',
    'Meno čitateľne'                          => 'Legible name',

    // Severity / bug report
    'Nahlásiť chybu'                          => 'Report a bug',
    'Nahlásiť chybu (vytvorí úlohu v ClickUp)' => 'Report a bug (creates a ClickUp task)',
    'Nahlásenie chyby zlyhalo. Skúste znova alebo kontaktujte správcu.' =>
        'Bug report failed. Please try again or contact the administrator.',
    'Chyba bola nahlásená.'                   => 'Bug reported.',
    'Chyba bola nahlásená. Sledovať môžete na: {url}' =>
        'Bug reported. You can track it at: {url}',
    'Názov chyby'                             => 'Bug title',
    'Názov chyby je povinný.'                 => 'Bug title is required.',
    'Názov je príliš dlhý (max. 200 znakov).' => 'Title is too long (max. 200 characters).',
    'Popis chyby je povinný.'                 => 'Bug description is required.',
    'Neplatná závažnosť.'                     => 'Invalid severity.',
    'Po odoslaní sa automaticky vytvorí úloha v ClickUp s vašimi kontextovými údajmi (čas, URL, prehliadač).' =>
        'On submit a ClickUp task is created automatically with your context (time, URL, browser).',
    'Čo robíte, čo ste očakávali, čo sa stalo. Pripojte kroky na zopakovanie chyby.' =>
        'What you do, what you expected, what happened. Include steps to reproduce.',

    // Generic placeholders / boolean labels
    '(zložené dáta)'                          => '(complex data)',
    'Vyžadovaný'                              => 'Required',
    'Voliteľné / podmienené'                  => 'Optional / conditional',
    '— Vyberte zónu —'                        => '— Select zone —',
    '— Žiadna —'                              => '— None —',

    // Map / dashboard
    'Mapa'                                    => 'Map',
    'Mapa zón'                                => 'Zone map',
    'Zobraziť mapu zón'                       => 'View zone map',
    'Späť na mapu'                            => 'Back to map',
    'Späť na permit'                          => 'Back to permit',
    'Sem vložte mapu závodu (SVG)'            => 'Insert plant map (SVG) here',
    'Kliknite na zónu pre zobrazenie aktívnych permitov.' =>
        'Click on a zone to view active permits.',
    'V tejto zóne nie sú aktuálne žiadne aktívne permity.' =>
        'There are currently no active permits in this zone.',
    'Aktívne permity'                         => 'Active permits',
    'Zóna'                                    => 'Zone',
    'Zóna bez aktívnych permitov'             => 'Zone without active permits',
    'Zóna s aktívnymi permitmi'               => 'Zone with active permits',

    // File picker / attachments
    'Vybrať prílohu'                          => 'Select attachment',
    'Aktuálna príloha'                        => 'Current attachment',
    'Dodatočná príloha'                       => 'Additional attachment',
    'Príloha'                                 => 'Attachment',
    'Voliteľná dodatočná príloha k permitu (napr. plán pracoviska, certifikáty, fotografie).' =>
        'Optional additional attachment to the permit (e.g. workplace plan, certificates, photos).',
    'Nahrať prílohu (PDF, DOCX, JPG, PNG, max 10 MB)' =>
        'Upload attachment (PDF, DOCX, JPG, PNG, max 10 MB)',
    'Nahrať prílohu (PDF, DOCX, XLSX, max 10 MB)' =>
        'Upload attachment (PDF, DOCX, XLSX, max 10 MB)',
    'Súbor SSoW (PDF, DOCX, XLSX, max. 10 MB)' => 'SSoW file (PDF, DOCX, XLSX, max. 10 MB)',
    'Súbor hodnotenia rizík'                  => 'Risk assessment file',
    'Hodnotenie rizík'                        => 'Risk assessment',
    'Pripojte dokument s hodnotením rizík. Pri odoslaní na schválenie je príloha povinná.' =>
        'Attach a risk assessment document. The attachment is required when submitting for approval.',
    'Príloha s hodnotením rizík je povinná pri odoslaní.' =>
        'Risk assessment attachment is required on submission.',
    'Nepodporovaný typ súboru. Povolené: PDF, DOCX, XLSX.' =>
        'Unsupported file type. Allowed: PDF, DOCX, XLSX.',
    'Uloženie súboru zlyhalo. Skúste znova.' => 'Could not save the file. Please try again.',
    'Nahrajte súbor SSoW alebo zrušte výber prílohy a vyplňte popis.' =>
        'Upload the SSoW file or uncheck the attachment option and fill in the description.',
    'Popis SSoW je povinný, ak nie je priložený samostatný súbor.' =>
        'SSoW description is required if no separate file is attached.',

    // Permit / subpermit common
    'Číslo permitu'                           => 'Permit number',
    'Číslo povolenia'                         => 'Permit number',
    'Prehľad povolenia'                       => 'Permit overview',
    'Hlavička povolenia'                      => 'Permit header',
    'Základné informácie'                     => 'Basic information',
    'Špecifické podmienky'                    => 'Specific conditions',
    'Zúčastnení dodávatelia / osoby'          => 'Participating contractors / persons',
    'Zodpovedná osoba MDLZ'                   => 'MDLZ responsible person',
    'Zodpovedná osoba je povinná.'            => 'Responsible person is required.',
    'Popis vykonávanej práce'                 => 'Description of the work being performed',
    'Popis práce'                             => 'Work description',
    'Hlavné kroky'                            => 'Main steps',
    'Krok {n}'                                => 'Step {n}',
    'Platnosť'                                => 'Validity',
    'Platnosť 8 hodín od schválenia'          => 'Valid 8 hours from approval',
    'Platnosť tohto permitu vypršala'         => 'This permit has expired',
    'Práce nesmú pokračovať.'                 => 'Work must not continue.',
    'Permit je platný do'                     => 'Permit is valid until',
    'Otvoriť permit v CP'                     => 'Open permit in CP',
    'Otvoriť subpermit'                       => 'Open subpermit',
    'Otvoriť portál dodávateľa'               => 'Open contractor portal',
    'Otvoriť portál a podpísať'               => 'Open the portal and sign',
    'Zobraziť PDF'                            => 'View PDF',
    'Zobraziť PDF subpermitu'                 => 'View subpermit PDF',
    'Podpísaný permit (PDF)'                  => 'Signed permit (PDF)',
    'Žiadne prílohy.'                         => 'No attachments.',
    'Vyberte zónu, v ktorej sa práca vykonáva.' => 'Select the zone in which the work is performed.',

    // Subpermit listing
    'Vyžadované subpermity (prílohy)'         => 'Required subpermits (appendices)',
    'Príloha č. {n} — {type}'                 => 'Appendix no. {n} — {type}',
    'Priložené subpermity'                    => 'Attached subpermits',
    'Subpermit'                               => 'Subpermit',
    'Zatiaľ neboli pridané žiadne subpermity.' => 'No subpermits have been added yet.',
    'Permit je uzavretý — pridanie subpermitu nie je možné.' =>
        'Permit is closed — adding a subpermit is not possible.',
    'Výber typu'                              => 'Type selection',
    'Vyberte typ subpermitu, ktorý chcete vytvoriť.' => 'Select the subpermit type to create.',
    'Naozaj chcete zrušiť tento subpermit?'   => 'Really cancel this subpermit?',
    'Schválený subpermit nie je možné zrušiť.' => 'An approved subpermit cannot be cancelled.',
    'Zrušiť subpermit'                        => 'Cancel subpermit',
    'Upraviť subpermit'                       => 'Edit subpermit',
    'Upraviť údaje subpermitu'                => 'Edit subpermit data',
    'Upraviť permit'                          => 'Edit permit',
    'Upraviť permit {n}'                      => 'Edit permit {n}',
    'Upraviť prílohu č. {n}'                  => 'Edit appendix no. {n}',
    'Pred uzavretím tohto subpermitu je potrebné nahrať aspoň jednu prílohu.' =>
        'At least one attachment must be uploaded before closing this subpermit.',

    // Subpermit statuses / sign flow
    'Subpermit bol podpísaný.'                => 'Subpermit signed.',
    'Subpermit bol uložený a podpísaný.'      => 'Subpermit saved and signed.',
    'Subpermit bol uložený. Pozvania na podpis boli odoslané e-mailom.' =>
        'Subpermit saved. Signing invitations have been sent by email.',
    'Subpermit bol už podpísaný dodávateľom.' => 'Subpermit has already been signed by the contractor.',
    'Subpermit nie je v stave na schválenie.' => 'Subpermit is not in a state to be approved.',
    'Subpermit nie je v stave na zamietnutie.' => 'Subpermit is not in a state to be rejected.',
    'Subpermit musí byť schválený pred podpísom.' => 'Subpermit must be approved before signing.',
    'Subpermit bol schválený. Platnosť 8 hodín.' => 'Subpermit approved. Valid for 8 hours.',
    'Subpermit schválený'                     => 'Subpermit approved',
    'Subpermit sa nepodarilo schváliť.'       => 'Could not approve the subpermit.',
    'Subpermit sa nepodarilo zamietnuť.'      => 'Could not reject the subpermit.',
    'Subpermit sa nepodarilo zrušiť. Skúste znova.' =>
        'Could not cancel the subpermit. Please try again.',
    'Subpermit expiruje do 1 hodiny'          => 'Subpermit expires within 1 hour',
    'Schvaľovacie podpisy (voliteľné)'        => 'Approval signatures (optional)',
    'Schválenie HSE'                          => 'HSE approval',
    'Zamietnutie HSE'                         => 'HSE rejection',
    'Schváliť subpermit'                      => 'Approve subpermit',
    'Pred schválením musia byť podpísané obidva predpracovné podpisy (vydavateľ + dodávateľ).' =>
        'Both pre-work signatures (issuer + contractor) must be captured before approval.',
    'Pred schválením HSE'                     => 'Before HSE approval',
    'Nie je možné schváliť'                   => 'Cannot be approved',
    'chýba predpracovný podpis dodávateľa.'   => 'contractor\'s pre-work signature is missing.',
    'chýba predpracovný podpis vydavateľa.'   => 'issuer\'s pre-work signature is missing.',
    'Pri zamietnutí je dôvod povinný.'        => 'A reason is required when rejecting.',
    'Dôvod zamietnutia (povinné)'             => 'Reason for rejection (required)',
    'Pred začatím prác'                       => 'Before work begins',
    'Pred začatím prác musíte podpísať každý schválený subpermit.' =>
        'You must sign every approved subpermit before work begins.',

    // Signatures — common
    'Podpisy'                                 => 'Signatures',
    'Podpisy (legacy)'                        => 'Signatures (legacy)',
    'Podpisy pred prácou'                     => 'Pre-work signatures',
    'Podpisy pri uzavretí'                    => 'Closure signatures',
    'Pri uzavretí'                            => 'At closure',
    'Pri dokončení / zrušení prác'            => 'On completion / cancellation of work',
    'Nepodpísané'                             => 'Not signed',
    'Povinné podpisy'                         => 'Mandatory signatures',
    'Signatári subpermitu'                    => 'Subpermit signatories',
    'Podpísal vydavateľ'                      => 'Signed by issuer',
    'Vydavateľ — pred prácou'                 => 'Issuer — before work',
    'Vydavateľ — uzavretie'                   => 'Issuer — closure',
    'Vydavateľ (legacy)'                      => 'Issuer (legacy)',
    'Vydavateľ podpísal'                      => 'Issuer signed',
    'Vydavateľ podpísal pred prácou'          => 'Issuer signed before work',
    'Vydavateľ ešte nepodpísal.'              => 'The issuer has not signed yet.',
    'Vydavateľ ešte nepodpísal pred prácou.'  => 'The issuer has not signed pre-work yet.',
    'Vydavateľ musí najprv podpísať pred začatím prác.' =>
        'The issuer must sign before work begins first.',
    'Vydavateľ podpísal uzavretie. Pre dokončenie procesu pridajte svoj podpis.' =>
        'The issuer signed the closure. Add your signature to complete the process.',
    'Dodávateľ — pred prácou'                 => 'Contractor — before work',
    'Dodávateľ — uzavretie'                   => 'Contractor — closure',
    'Dodávateľ podpísal'                      => 'Contractor signed',
    'Dodávateľ podpísal pred prácou'          => 'Contractor signed before work',
    'Dodávateľ podpísal zrušenie'             => 'Contractor signed cancellation',
    'Dodávateľ potvrdil dokončenie'           => 'Contractor confirmed completion',
    'Dodávateľ podpísal uzavretie. Subpermit bude úplne uzavretý po Vašom podpise.' =>
        'The contractor signed the closure. The subpermit will be fully closed after your signature.',
    'Dodávateľ musí najprv podpísať uzavretie.' => 'The contractor must sign the closure first.',
    'Po podpise vydavateľa budete môcť podpísať aj vy.' =>
        'After the issuer signs, you will be able to sign too.',
    'Po dokončení prác dodávateľom budete vyzvaní k podpísaniu uzavretia subpermitu.' =>
        'After the contractor completes the work you will be asked to sign the subpermit closure.',
    'Najprv musia byť obidva podpisy pred začatím prác.' =>
        'Both pre-work signatures are required first.',
    'Najprv musia byť podpísané obidva podpisy pred začatím prác.' =>
        'Both pre-work signatures must be captured first.',
    'Čaká na podpis HSE'                      => 'Awaiting HSE signature',
    'Čaká na uzavretie vydavateľom'           => 'Awaiting closure by issuer',
    'Čaká sa na predpracovný podpis dodávateľa. HSE bude môcť schváliť subpermit až po jeho podpise.' =>
        'Awaiting contractor\'s pre-work signature. HSE will only be able to approve the subpermit after that signature.',

    // Signer prework / closure
    'Podpis dodávateľa'                       => 'Contractor signature',
    'Podpis dodávateľa je povinný.'           => 'Contractor signature is required.',
    'Podpis dodávateľa — pred prácou'         => 'Contractor signature — before work',
    'Podpis vydavateľa'                       => 'Issuer signature',
    'Podpis vydavateľa je povinný.'           => 'Issuer signature is required.',
    'Podpis vydavateľa — pred prácou'         => 'Issuer signature — before work',
    'Podpis vydavateľa — uzavretie'           => 'Issuer signature — closure',
    'Podpísať'                                => 'Sign',
    'Podpísať pred prácou'                    => 'Sign before work',
    'Podpísať subpermit'                      => 'Sign subpermit',
    'Podpísať uzavretie'                      => 'Sign closure',
    'Podpísať a odoslať'                      => 'Sign and submit',
    'Podpísať a uložiť'                       => 'Sign and save',
    'Podpísať a uložiť kontrolu'              => 'Sign and save inspection',
    'Podpísať a uložiť subpermit'             => 'Sign and save subpermit',
    'Podpísať a uzavrieť'                     => 'Sign and close',
    'Podpíšte pred začatím prác.'             => 'Sign before work begins.',
    'Podpíšte dokončenie / zrušenie subpermitu' => 'Sign the completion / cancellation of the subpermit',
    'Podpíšte subpermit ako dodávateľ pred začatím prác.' =>
        'Sign the subpermit as the contractor before work begins.',
    'Podpíšte subpermit ako vydavateľ povolenia. Po uložení bude odoslaný HSE na schválenie.' =>
        'Sign the subpermit as the permit issuer. After saving it will be sent to HSE for approval.',
    'Podpíšte záznam kontroly. Po uložení ho nebude možné upravovať.' =>
        'Sign the inspection record. After saving it cannot be edited.',
    'Podpisom potvrdzujete úplné uzavretie subpermitu po podpise dodávateľa.' =>
        'By signing you confirm the full closure of the subpermit after the contractor\'s signature.',
    'Podpisom potvrdzujete, že ste oboznámení s podmienkami subpermitu a zaviazali ste sa ich dodržiavať.' =>
        'By signing you confirm that you are familiar with the subpermit conditions and commit to following them.',
    'Podpisom potvrdzujete, že subpermit je pripravený a práca môže byť zahájená.' =>
        'By signing you confirm the subpermit is ready and the work may begin.',
    'Skontrolujte, že výsledok a poznámky sú správne vyplnené pred podpisom.' =>
        'Verify that the result and notes are filled in correctly before signing.',
    'Skontrolujte, že všetky polia sú správne vyplnené pred podpisom.' =>
        'Verify that all fields are filled in correctly before signing.',

    // Token signer page
    'Vyžaduje sa Váš podpis pred schválením'  => 'Your signature is required before approval',
    'Vyžaduje sa váš podpis — subpermit k povoleniu {n}' =>
        'Your signature is required — subpermit for permit {n}',
    'Ste požiadaný o podpis'                  => 'Your signature is requested',
    'Permit čaká na váš podpis'               => 'Permit is waiting for your signature',
    'Na uzavretie permitu je teraz potrebný váš podpis.' =>
        'Your signature is now required to close the permit.',
    'Bez Vášho predpracovného podpisu nebude môcť HSE permit schváliť.' =>
        'Without your pre-work signature HSE will not be able to approve the permit.',
    'Elektronický podpis subpermitu'          => 'Electronic signature of the subpermit',
    'Tento odkaz bol už použitý na podpis.'   => 'This link has already been used to sign.',
    'Kliknutím na tlačidlo nižšie otvoríte formulár na podpis. Odkaz je platný pre jedno použitie.' =>
        'Click the button below to open the signing form. The link is valid for a single use.',
    'Ak tlačidlo nefunguje, skopírujte tento odkaz do prehliadača:' =>
        'If the button does not work, copy this link into your browser:',
    'Podpis bol zaznamenaný'                  => 'Signature recorded',
    'Podpis bol úspešne zaznamenaný'          => 'Signature successfully recorded',
    'Podpis bol uložený. Permit čaká na podpis HSE.' =>
        'Signature saved. The permit is awaiting HSE signature.',
    'Túto záložku môžete zavrieť. Ďakujeme.'  => 'You may close this tab. Thank you.',
    'Uloženie podpisu zlyhalo. Skúste znova.' => 'Could not save the signature. Please try again.',
    'Podpis kontrolóra'                       => 'Inspector signature',

    // Predpracovný / uzávierkový signature messages
    'Predpracovný podpis dodávateľa bol zaznamenaný.' =>
        'Contractor\'s pre-work signature recorded.',
    'Predpracovný podpis dodávateľa už bol zaznamenaný.' =>
        'Contractor\'s pre-work signature has already been recorded.',
    'Predpracovný podpis vydavateľa bol zaznamenaný.' => 'Issuer\'s pre-work signature recorded.',
    'Predpracovný podpis vydavateľa už bol zaznamenaný.' =>
        'Issuer\'s pre-work signature has already been recorded.',
    'Predpracovný podpis je možný len pred schválením.' =>
        'Pre-work signature is only possible before approval.',
    'Predpracovný podpis je možný len pred schválením (stav „čaká na schválenie").' =>
        'Pre-work signature is only possible before approval (status "pending approval").',
    'Uzávierkový podpis vydavateľa bol zaznamenaný.' => 'Issuer\'s closure signature recorded.',
    'Uzávierkový podpis vydavateľa už bol zaznamenaný.' =>
        'Issuer\'s closure signature has already been recorded.',

    // Inspection / control
    'Kontrola'                                => 'Inspection',
    'Kontrola prevádzky'                      => 'Operational inspection',
    'Kontrola prevádzky (zamestnanec Mondelez)' => 'Operational inspection (Mondelez employee)',
    'Kontroly prevádzky'                      => 'Operational inspections',
    'Záznam o kontrole prevádzky'             => 'Operational inspection record',
    'Kontrolór'                               => 'Inspector',
    'Meno kontrolóra'                         => 'Inspector name',
    'Meno kontrolóra je povinné.'             => 'Inspector name is required.',
    'Dátum a čas kontroly'                    => 'Inspection date and time',
    'Dátum a čas kontroly je povinný.'        => 'Inspection date and time are required.',
    'Výsledok'                                => 'Result',
    'Výsledok kontroly'                       => 'Inspection result',
    'Vyberte výsledok kontroly.'              => 'Select the inspection result.',
    'V poriadku'                              => 'OK',
    'Zistené nedostatky'                      => 'Issues found',
    'Práce zastavené'                         => 'Work stopped',
    'Poznámky'                                => 'Notes',
    'Poznámky / zistenia'                     => 'Notes / findings',
    'Kontrola bola zaznamenaná.'              => 'Inspection recorded.',
    'Uloženie kontroly zlyhalo. Skúste znova.' => 'Could not save the inspection. Please try again.',
    'Predchádzajúce kontroly'                 => 'Previous inspections',
    'Vykonávať kontroly prevádzky'            => 'Perform operational inspections',
    'Nemáte oprávnenie na vykonávanie kontrol.' => 'You do not have permission to perform inspections.',
    'Ste zamestnanec Mondelez a chcete zaznamenať kontrolu prevádzky?' =>
        'Are you a Mondelez employee and want to record an operational inspection?',
    'Práce boli zastavené kontrolórom. Kontaktujte okamžite oddelenie HSE.' =>
        'Work has been stopped by the inspector. Contact the HSE department immediately.',
    'Práce na základe tohto permitu sú zakázané.' => 'Work under this permit is prohibited.',
    'V prípade otázok kontaktujte oddelenie HSE.' => 'If you have questions, contact the HSE department.',

    // PDF / generic
    'PDF nie je k dispozícii.'                => 'PDF is not available.',
    'PDF súbor nebol nájdený.'                => 'PDF file not found.',

    // Closure / permit lifecycle
    'Permit nie je v stave čakania na HSE.'   => 'Permit is not in the awaiting-HSE state.',
    'Permit bol oficiálne uzavretý'           => 'Permit officially closed',
    'Permit bol zrušený vydavateľom'          => 'Permit cancelled by issuer',
    'Permit {n} bol uzavretý.'                => 'Permit {n} has been closed.',
    'Permit expiruje do 24 hodín'             => 'Permit expires within 24 hours',
    'Finálne uzavretie HSE'                   => 'Final HSE closure',
    'Uzavrel'                                 => 'Closed by',
    'Audit'                                   => 'Audit',
    'Akcia'                                   => 'Action',
    'Akcie'                                   => 'Actions',
    'Dátum a čas'                             => 'Date and time',
    'Dátum / čas'                             => 'Date / time',
    'Dátum'                                   => 'Date',
    'Dátum je povinný.'                       => 'Date is required.',
    'Vyberte aspoň jeden stav po dokončení.'  => 'Select at least one closure status.',
    'Označte, či sa vyžaduje skúšobná prevádzka.' => 'Mark whether a trial operation is required.',
    'Zrušenie prác dodávateľom'               => 'Cancellation of work by contractor',
    'Dokončenie prác dodávateľom'             => 'Contractor work completion',

    // Email — expiry reminders
    'Upozornenie na expiráciu'                => 'Expiry reminder',
    'Upozornenie na expiráciu — subpermit'    => 'Expiry reminder — subpermit',
    'Toto je automatické upozornenie 24 hodín pred koncom platnosti permitu.' =>
        'This is an automated reminder 24 hours before the permit expires.',
    'Toto je automatické upozornenie 1 hodinu pred koncom platnosti subpermitu.' =>
        'This is an automated reminder 1 hour before the subpermit expires.',
    'Toto je automatická správa zo systému BOZP Permity. Neodpovedajte na tento e-mail.' =>
        'This is an automated message from the BOZP Permits system. Do not reply to this email.',
    'Ďakujeme za spoluprácu pri dodržiavaní bezpečnostných predpisov.' =>
        'Thank you for cooperating in complying with safety regulations.',
    'Váš permit bol schválený'                => 'Your permit has been approved',
    'Prístupové údaje k portálu'              => 'Portal access credentials',
    'Odkaz'                                   => 'Link',
    'Prípadne naskenujte QR kód mobilným zariadením:' =>
        'Or scan the QR code with a mobile device:',
    'Odkaz a heslo platia do dátumu skončenia platnosti permitu. Heslo je jednorazové — nepredávajte ho.' =>
        'The link and password are valid until the permit expires. The password is single-use — do not share it.',
    'Použite prosím prístupové údaje z predošlého e-mailu k tomuto permitu.' =>
        'Please use the access credentials from the previous email for this permit.',
    'Použite prosím prístupové údaje z predošlého e-mailu k tomuto permitu. Po dokončení prác je potrebné subpermit uzavrieť v portáli.' =>
        'Please use the access credentials from the previous email for this permit. After completing the work the subpermit must be closed in the portal.',
    'Požadované prílohy k permitu'            => 'Required permit attachments',

    // ===== Subpermit-specific labels — Hot Work =====
    'Povolenie na práce so zvýšeným požiarnym rizikom' => 'Permit for work with increased fire risk',
    'Povolenie na prácu'                      => 'Permit to work',
    'Povolenie na prácu — subpermit'          => 'Permit to work — subpermit',
    'Existujú bezpečnejšie spôsoby vykonania tejto práce? Vyhýbajte sa činnostiam so zvýšeným požiarnym rizikom.' =>
        'Are there safer ways to perform this work? Avoid activities with increased fire risk.',
    'Povolenie vydal (meno a priezvisko)'     => 'Permit issued by (name and surname)',
    'Práce so zvýšeným požiarnym rizikom vykonáva' => 'Work with increased fire risk is performed by',
    'Zamestnanec'                             => 'Employee',
    'Číslo pracovného príkazu / zmeny'        => 'Job order / shift number',
    'Časové údaje'                            => 'Timing',
    'Povolenie platí len 8 hodín / jednu zmenu.' => 'Permit is valid for only 8 hours / one shift.',
    'Čas začiatku prác'                       => 'Work start time',
    'Čas ukončenia prác'                      => 'Work completion time',
    'Dátum a čas expirácie sa nastavia automaticky pri schválení HSE (+ 8 hodín od schválenia).' =>
        'Expiry date and time are set automatically at HSE approval (+ 8 hours from approval).',
    'Kontrolný zoznam preventívnych opatrení' => 'Checklist of preventive measures',
    'Označte stav každej položky: ✓ OK = v poriadku, ✗ NOK = nie v poriadku, — N/A = neuplatňuje sa.' =>
        'Mark the state of each item: ✓ OK = in order, ✗ NOK = not in order, — N/A = not applicable.',
    'Sprinklery, hydranty (hadice) a hasiace prístroje sú na mieste a funkčné' =>
        'Sprinklers, hydrants (hoses) and fire extinguishers are in place and operational',
    'Pracovné vybavenie je v dobrom stave'    => 'Work equipment is in good condition',
    'Požiadavky v 11-metrovej pracovnej zóne' => 'Requirements within the 11-metre work zone',
    'Zariadenia produkujúce horľavý prach alebo vlákna sú vypnuté' =>
        'Equipment producing combustible dust or lint is shut down',
    'Dopravníky, vzduchotechnika, dúchadlá a iné zariadenia schopné prenášať iskry alebo horiace materiály sú izolované alebo vypnuté' =>
        'Conveyors, ducts, blowers and other equipment capable of transporting sparks or burning materials are isolated or shut down',
    'Horľavé materiály odstránené (prach, vlákna, olej, obalový materiál, horľavé kvapaliny). Inak prikryté nehorľavými plachtami.' =>
        'Combustible materials removed (dust, lint, oil, packaging, flammable liquids). Otherwise covered with fire-resistant sheets.',
    'Výbušná atmosféra v oblasti eliminovaná' => 'Explosive atmosphere in the area eliminated',
    'Podlaha riadne pozametaná'               => 'Floor swept clean',
    'Horľavá podlaha navlhčená, prikrytá vlhkým pieskom alebo nehorľavými plachtami' =>
        'Combustible floor wet down, covered with damp sand or fire-resistant sheets',
    'Všetky otvory v stenách a podlahe sú prekryté' => 'All wall and floor openings covered',
    'Nehorľavé plachty zavesené pod miestom práce' => 'Fire-resistant tarpaulins suspended beneath the work',
    'Práce na stenách, stropoch alebo uzavretých zariadeniach' =>
        'Work on walls, ceilings or enclosed equipment',
    'Konštrukcia je nehorľavá, bez horľavého obkladu alebo izolácie' =>
        'Construction is non-combustible, no combustible covering or insulation',
    'Horľavé materiály na druhej strane stien sú odstránené' =>
        'Combustibles on the other side of walls are removed',
    'Uzavreté zariadenie vyčistené od všetkých horľavín' =>
        'Enclosed equipment cleaned of all combustibles',
    'Nádoby zbavené horľavých kvapalín a výparov' =>
        'Containers purged of flammable liquids and vapors',
    'Požiadavky na požiarnu hliadku / monitoring' => 'Fire patrol / monitoring requirements',
    'Vedro vody'                              => 'Bucket of water',
    'Požiarna hliadka má vhodné hasiace prístroje alebo dostatočné množstvo vody alebo iných hasiacich látok' =>
        'Fire patrol has suitable fire extinguishers or sufficient water or other extinguishing agents',
    'Počet a typ hasiacich prístrojov'        => 'Number and type of fire extinguishers',
    'Požiarna hliadka je vyškolená na použitie hasiacich prístrojov a oboznámená s požiarnym poriadkom' =>
        'Fire patrol is trained in the use of fire extinguishers and familiar with fire alarm rules',
    'Zahrnutý dodatočný dozor nad priľahlými priestormi (nad, pod)' =>
        'Additional supervision included for adjacent areas (above, below)',
    'Hodín po ukončení prác bude pracovisko pravidelne kontrolované a monitorované (min. 2 h)' =>
        'Hours after work the workplace will be regularly inspected and monitored (min. 2 h)',
    'Ďalšie bezpečnostné a protipožiarne opatrenia' => 'Other safety and fire-precaution measures',
    'Povolenia pre uzavreté priestory (CSE) alebo LOTO vydané, ak sa vyžaduje' =>
        'Confined space (CSE) or LOTO permits issued if required',
    'Detekcia dymu alebo tepla deaktivovaná, ak je v lokalite prítomná' =>
        'Smoke or heat detection deactivated if present in the location',
    'Iné opatrenia'                           => 'Other measures',
    'Požiarna hliadka a požiarny technik'     => 'Fire patrol and fire-protection technician',
    'Záznam o kontrole a podpis sa vykonáva v papierovej forme. Pred uzavretím subpermitu je potrebné nahrať skenovaný / fotený dokument ako prílohu.' =>
        'The inspection record and signature are kept on paper. Before closing the subpermit a scanned / photographed document must be uploaded as an attachment.',
    '✓ OK'                                    => '✓ OK',
    '✗ NOK'                                   => '✗ NOK',
    '— N/A'                                   => '— N/A',

    // ===== Subpermit-specific labels — Heights (additions) =====
    'Nebezpečenstvo pádu'                     => 'Fall hazard',
    'Aplikované'                              => 'Applicable',
    'Kontrola na pracovisku'                  => 'Workplace inspection',
    'Pri každom riziku označte, či je aplikovateľné, a či bola vykonaná kontrola na pracovisku.' =>
        'For each hazard, mark whether it is applicable and whether a workplace inspection was performed.',
    'Ochrana pred pádom'                      => 'Fall protection',
    'Je možné vyhnúť sa riziku pádu (predĺžené nástroje, práca na úrovni podlahy)?' =>
        'Is it possible to avoid the fall risk (extended tools, work at floor level)?',
    'Použitý systém ochrany pred pádom'       => 'Fall-protection system used',
    'Zábradlový systém (zabraňuje pádom)'     => 'Guardrail system (prevents falls)',
    'Systém zachytenia pádu — 1 360 kg'       => 'Fall arrest system — 1,360 kg',
    'Systém zachytenia pádu — 2 260 kg'       => 'Fall arrest system — 2,260 kg',
    'Záchytná sieť'                           => 'Safety net',
    'Pracovník je vyškolený, oprávnený a kvalifikovaný na prácu vo výškach?' =>
        'Is the worker trained, authorised and qualified to work at heights?',
    'Konštrukčné podmienky sú vhodné pre prácu (dostatočná nosnosť)?' =>
        'Are construction conditions suitable for the work (sufficient load capacity)?',
    'Je potrebné ohradiť priestor kvôli pohybu osôb a zariadení?' =>
        'Is it necessary to fence off the area due to the movement of persons and equipment?',
    'Bol použitý kotevný bod?'                => 'Was an anchor point used?',
    'Popis kotevného bodu'                    => 'Anchor point description',
    'Použité zariadenia'                      => 'Equipment used',
    'Prenosné rebríky'                        => 'Portable ladders',
    'Lešenie'                                 => 'Scaffolding',
    'Mobilné pracovné plošiny'                => 'Mobile work platforms',
    'Mobilné pracovné plošiny.'               => 'Mobile work platforms.',
    'Zdvíhacie plošiny'                       => 'Lifting platforms',
    'Závesné plošiny'                         => 'Suspended platforms',
    'Použité zariadenie na ochranu pred pádom má platnú kontrolu?' =>
        'Does the fall-protection equipment have a valid inspection?',
    'Práca bola skontrolovaná a prerokovaná priamo na pracovisku?' =>
        'Was the work reviewed and discussed directly at the workplace?',
    'Vyvýšené prechodové/pracovné plochy 1,2 m alebo viac nad úrovňou podlahy.' =>
        'Elevated walkways / working surfaces 1.2 m or more above floor level.',
    'Vyvýšené prechodové/pracovné plochy 1,2 metra alebo viac nad úrovňou podlahy.' =>
        'Elevated walkways / working surfaces 1.2 metres or more above floor level.',
    'Práca na strechách, na ktoré pracovníci vstupujú, kde môžu spadnúť cez strešné okno, otvor, práca na okraji hrany pádu, kde hrozí spadnutie.' =>
        'Work on roofs that workers enter where they could fall through a skylight or opening; work on the edge of a fall edge where falling is possible.',
    'Práca na strechách, na ktoré pracovníci vstupujú, kde môžu spadnúť cez strešné okno, otvor, práca na okraji hrany pádu.' =>
        'Work on roofs that workers enter where they could fall through a skylight or opening; work on the edge of a fall edge.',
    'Stenové otvory (napríklad: okná alebo dvere, cez ktoré by pracovníci mohli spadnúť).' =>
        'Wall openings (e.g. windows or doors through which workers could fall).',
    'Výkopy a jamy, ktoré nie sú ľahko viditeľné a pracovníci by mohli do nich spadnúť.' =>
        'Excavations and pits that are not easily visible and workers could fall into them.',
    'Vyvýšené priestory, kde boli odstránené zábradlia.' =>
        'Elevated areas where handrails have been removed.',
    'Strany/okraje prechodových/pracovných plôch (podlahy, medziposchodia, balkóny, chodníky) 1,2 m alebo viac bez zábradlia.' =>
        'Sides/edges of walkways/working surfaces (floors, mezzanines, balconies, walkways) 1.2 m or more without handrails.',
    'Strany/okraje prechodových/pracovných plôch, ako sú podlahy, medziposchodia, balkóny a chodníky, ktoré sú 1,2 m alebo viac nad úrovňou podlahy a nie sú chránené zábradlím.' =>
        'Sides/edges of walkways/working surfaces such as floors, mezzanines, balconies and walkways that are 1.2 m or more above floor level and not protected by handrails.',
    'Rampy, prechody a cesty, ktoré nie sú chránené zábradlím.' =>
        'Ramps, walkways and paths not protected by handrails.',
    'Studne, podlahové otvory, jamy alebo šachty bez zábradlia/plotov/bariér/krytov.' =>
        'Wells, floor openings, pits or shafts without handrails/fences/barriers/covers.',
    'Studne, podlahové otvory, jamy alebo šachty, ktoré nie sú chránené zábradlím, plotmi, bariérami alebo krytmi.' =>
        'Wells, floor openings, pits or shafts not protected by handrails, fences, barriers or covers.',

    // ===== Subpermit-specific labels — Confined Space =====
    'EX zóna'                                 => 'EX zone',
    'EX zóna v danom priestore / zariadení'   => 'EX zone in the area / device',
    'Stav zabezpečenia pracoviska / priestoru / zariadenia proti výbuchu' =>
        'Securing state of the workplace / area / device against explosion',
    'Pracovisko bolo zabezpečené'             => 'Workplace has been secured',
    'Pracovisko bude zabezpečené'             => 'Workplace will be secured',
    'Pracovisko nie je možné zabezpečiť'      => 'Workplace cannot be secured',
    'Spôsob zabezpečenia (ak je zabezpečené)' => 'Securing method (if secured)',
    'Vyprázdnenie zariadenia / priestoru'     => 'Emptying the device / space',
    'Zvlhčenie výbušnej látky vodou'          => 'Wetting the explosive substance with water',
    'Inertizácia plynom'                      => 'Inertization with gas',
    'Ak pracovisko nie je možné zabezpečiť, je nutné prijať dodatočné opatrenia podľa HSE-7-1 a konzultovať činnosť s HSE koordinátorom alebo požiarnym technikom. Povolenie nie je možné vydať, kým nie sú potvrdené všetky dodatočné opatrenia. Vedúci prác je povinný kontrolovať vykonávanie dodatočných opatrení každú hodinu.' =>
        'If the workplace cannot be secured, additional measures must be taken in accordance with HSE-7-1 and the activity must be consulted with the HSE coordinator or fire-protection technician. The permit cannot be issued until all additional measures are confirmed. The works manager is required to verify the implementation of additional measures every hour.',
    'Použitie neiskrivého ručného náradia schváleného pre ATEX prostredie' =>
        'Use of non-sparking hand tools approved for ATEX environment',
    'Použitie prenosného elektrického náradia s ATEX certifikáciou pre danú zónu' =>
        'Use of portable power tools with ATEX certification for the given zone',
    'Použitie antistatického odevu (vrátane hygienickej čiapky, masky, odevu a obuvi v antistatickom prevedení)' =>
        'Use of antistatic clothing (including hygienic cap, mask, clothing and shoes in antistatic design)',
    'Plán a osobné ochranné pracovné prostriedky' => 'Plan and personal protective equipment',
    'Vypracovaný, prediskutovaný, zavedený a overený plán na obmedzenie nebezpečnej energie' =>
        'Established, discussed, implemented and verified plan to limit hazardous energy',
    'Použité OOPP'                            => 'PPE used',
    'Použité náradie'                         => 'Tools used',
    'Použité náradie a izolácia pracoviska'   => 'Tools used and workplace isolation',
    'Izolácia pracoviska'                     => 'Workplace isolation',
    'Izolácia zdroja energie'                 => 'Energy source isolation',
    'Lock Out Tag Out (LOTO) musí byť aplikovaný.' => 'Lock Out Tag Out (LOTO) must be applied.',
    'Čerpadlá / potrubia sú zaslepené, odpojené alebo zablokované' =>
        'Pumps / pipes are blinded, disconnected or blocked',
    'Ventilácia'                              => 'Ventilation',
    'Je potrebná úprava ventilácie?'          => 'Is ventilation modification required?',
    'Ak nie, popíšte dôvod'                   => 'If not, describe the reason',
    'Nútené vetranie — začiatok'              => 'Forced ventilation — started',
    'Nútené vetranie — koniec'                => 'Forced ventilation — stopped',
    'Kontrola atmosféry (iniciálne meranie)'  => 'Atmosphere check (initial reading)',
    'Vstup povolený: O₂ 19,5 – 23,5 %, LEL < 10 %, CO a H₂S = 0 PPM. Periodické testy (každých 30 minút) zaznamenajte na papieri a priložte ako prílohu pred uzatvorením subpermitu.' =>
        'Entry allowed: O₂ 19.5 – 23.5 %, LEL < 10 %, CO and H₂S = 0 PPM. Record periodic tests (every 30 minutes) on paper and attach as an attachment before closing the subpermit.',
    'Čas merania'                             => 'Measurement time',
    'Kyslík (O₂) %'                           => 'Oxygen (O₂) %',
    '% LEL (musí byť < 10 %)'                 => '% LEL (must be < 10 %)',
    'CO alebo H₂S (PPM)'                      => 'CO or H₂S (PPM)',
    'Iné (názov, hodnota)'                    => 'Other (name, value)',
    'Spôsob komunikácie'                      => 'Communication method',
    'Vstupujúci s dozorujúcim'                => 'Entrant with supervisor',
    'Dozorujúci s pohotovostnými službami'    => 'Supervisor with emergency services',
    'Mená členov pohotovostného tímu'         => 'Names of emergency team members',
    'Časové údaje vstupu'                     => 'Entry timing',
    'Čas prvého vstupu'                       => 'Time of first entry',
    'Čas posledného výstupu'                  => 'Time of last exit',
    'Vstupujúci (entrant)'                    => 'Entrant',
    'Dozorujúci pri vstupe (supervisor entrant)' => 'Supervisor entrant',
    'Vedúci vstupu do uzavretého priestoru (supervisor in CSE)' => 'Supervisor in CSE',
    'Objednávateľ MDLZ (review)'              => 'MDLZ orderant (review)',
    'HSE oddelenie (review)'                  => 'HSE department (review)',
    'Každej osobe bude po uložení odoslaný e-mail s odkazom na elektronický podpis. Bez podpisu všetkých osôb nie je možné subpermit schváliť HSE. Všetky e-maily sú povinné.' =>
        'Each person will receive an email with a link to sign electronically after saving. Without all signatures the subpermit cannot be approved by HSE. All emails are required.',
    'Každej osobe bude po uložení odoslaný e-mail s odkazom na elektronický podpis. Povinné polia sú označené *.' =>
        'Each person will receive an email with a link to sign electronically after saving. Required fields are marked with *.',
    'Ak vyplníte e-mail, danej osobe bude po uložení odoslaný odkaz na elektronický podpis. Polia môžete nechať prázdne, ak schválenie nie je potrebné.' =>
        'If you fill in an email, that person will receive a link to sign electronically after saving. Fields can be left blank if approval is not required.',
    'Popis havarijného / záchranného plánu (samostatný dokument)' =>
        'Description of the emergency / rescue plan (separate document)',
    'Oprávnená osoba (povolenie)'             => 'Authorized person (permit)',

    // PPE list (ATEX)
    'Antistatický odev'                       => 'Antistatic clothing',
    'Bezpečnostný postroj'                    => 'Safety harness',
    'Bezpečnostné označenie'                  => 'Safety markings',
    'Ochrana sluchu'                          => 'Hearing protection',
    'Ochranná obuv'                           => 'Safety shoes',
    'Ochranné okuliare'                       => 'Safety glasses',
    'Ochranné rukavice'                       => 'Safety gloves',
    'Respirátor'                              => 'Respirator',
    'Prilba'                                  => 'Hard hat',
    'Ručné náradie'                           => 'Hand tools',
    'Neiskrivé náradie'                       => 'Non-sparking tools',
    'Prenosné elektrické náradie'             => 'Portable power tools',
    'Zvárací stroj'                           => 'Welding machine',
    'Výstražná páska'                         => 'Warning tape',
    'Pevné zábrany'                           => 'Fixed barriers',
    'Vystuženie'                              => 'Reinforcement',
    'Kryty výkopov'                           => 'Trench covers',

    // ===== Subpermit-specific labels — Lifting =====
    'Príprava na činnosť — všetky pracovné činnosti' => 'Preparation for activity — all work activities',
    '(*vyhovujúce zaškrknite)'                => '(*tick if applicable)',
    'Je možné ohradiť pracovisko a zabrániť tak vstupu nepovolaných osôb (bariéry, výstražná páska, bezpečnostné piktogramy)' =>
        'It is possible to fence off the workplace to prevent entry by unauthorized persons (barriers, warning tape, safety pictograms)',
    'Vykonávateľ práce je školený — autorizovaný — spôsobilý na prácu' =>
        'The work performer is trained — authorised — qualified to work',
    'Špeciálne náradie, pracovné vybavenie, ktoré bude použité' =>
        'Special tools and equipment to be used',
    'Procesné podmienky sú vhodné pre prácu (dostatočná nosnosť)' =>
        'Process conditions are suitable for the work (sufficient load capacity)',
    'Vykonáva sa v súčasnosti iná práca, ktorá by mohla ovplyvniť toto povolenie?' =>
        'Is there any other work currently being carried out that could affect this permit?',
    'Bezpečnostné požiadavky, ktoré sa majú prijať' => 'Safety requirements to be adopted',
    'Je žeriav premiestnený do najbezpečnejšej možnej polohy' =>
        'Is the crane moved to the safest possible position',
    'Bezpečný prístup na pracovisko je zabezpečený' => 'Safe access to the workplace is ensured',
    'Je nebezpečná zóna žeriava ohradená'      => 'Is the crane danger zone fenced off',
    'Sú zamestnanci oboznámení s ohradením pracoviska a zákazom vstupu' =>
        'Are employees familiar with the workplace fencing and entry ban',
    'Ovládače žeriava sú umiestnené s dobrým výhľadom na viazača bremena' =>
        'Crane controls are located with a good view of the load binder',
    'Je žeriav v dobrom technickom stave'      => 'Is the crane in good technical condition',
    'Sú v oblasti iné žeriavy, vozidlá, inžinierske siete, osoby alebo iné kolízne riziká' =>
        'Are there other cranes, vehicles, utilities, persons or other collision risks in the area',
    'Je skontrolované, či sa v blízkosti pracoviska / pracovnej oblasti nevykonáva iná práca' =>
        'It is verified that no other work is taking place near the work area',
    'Sú viazacie prostriedky v dobrom stave a majú platnú revíziu' =>
        'Are the binding equipment in good condition and has a valid inspection',
    'Je dohodnutá komunikácia medzi viazačom bremena a obsluhou žeriava' =>
        'Communication is agreed between the load binder and the crane operator',
    'Vyžadujú sa počas práce prostriedky ochrany pred pádom' =>
        'Fall-protection equipment required during work',
    'Záverečné potvrdenia'                    => 'Final confirmations',
    'Vypracovaný, prediskutovaný, zavedený a overený plán na vykonávanie zdvíhacích a žeriavových prác' =>
        'Established, discussed, implemented and verified plan for carrying out lifting and crane work',
    'Práca / činnosť skontrolovaná a prediskutovaná na mieste' =>
        'Work / activity reviewed and discussed on site',

    // ===== Subpermit-specific labels — Excavation =====
    'FÁZA 1 — Vydavateľ povolenia (MDLZ)'     => 'PHASE 1 — Permit issuer (MDLZ)',
    'FÁZA 2 — Schválenie HSE oddelením'       => 'PHASE 2 — Approval by HSE department',
    'FÁZA 3 — Schválenie oprávneným orgánom'  => 'PHASE 3 — Approval by authorising body',
    'Spôsobilosť vykonávateľa'                => 'Performer competency',
    '(*vyhovujúce zaškrknite, bezpredmetné nechajte voľné pole)' =>
        '(*tick if applicable, leave irrelevant fields blank)',
    'Skontroloval som a potvrdil som, že boli splnené tieto bezpečnostné požiadavky.' =>
        'I have checked and confirmed that the following safety requirements have been met.',
    'vyžaduje sa izolácia elektrickej energie' => 'electrical isolation required',
    'vyžadujú sa bariéry (min. 2 m) a výstražné tabule' =>
        'barriers (min. 2 m) and warning signs required',
    'izolované zariadenie'                    => 'isolated equipment',
    'adekvátne osvetlenie'                    => 'adequate lighting',
    'vyhradený priestor'                      => 'designated area',
    'zákaz fajčenia alebo otvoreného ohňa'    => 'no smoking or open flame',
    'umiestnenie výstražných značení'         => 'placement of warning signs',
    'vyžaduje sa paženie'                     => 'shoring required',
    'dozor'                                   => 'supervision',
    'lekárnička'                              => 'first-aid kit',
    'kontrola výbušnosti'                     => 'explosivity check',
    'hasiaci prístroj'                        => 'fire extinguisher',
    'kontrola toxicity'                       => 'toxicity check',
    'vyžaduje sa záchranné lano s obsluhou'   => 'rescue line with operator required',
    'kyslík viac ako 19,5 %'                  => 'oxygen above 19.5 %',
    'bezpečný prístup k výkopom a výstup z nich' => 'safe access to and exit from excavations',
    'vyžaduje sa samostatný dýchací prístroj' => 'self-contained breathing apparatus required',
    'potvrdené, že sa na mieste nenachádzajú žiadne inžinierske siete, ako je plyn, vodovodné potrubia, elektrická sieť, telekomunikačné káble' =>
        'confirmed that there are no utilities on site such as gas, water pipes, electrical network, telecom cables',
    'vyžadujú sa záznamy o denných kontrolách kvalifikovanou osobou' =>
        'daily inspections by a qualified person are required',
    'vyžaduje sa podpera (viac ako 1,5 m)'    => 'support required (more than 1.5 m)',
    'Plánovaná činnosť'                       => 'Planned activity',
    'Stretnutie k plánovanej činnosti dňa'    => 'Meeting on planned activity day',
    'Barikády / Prechodové lávky'             => 'Barricades / Walkways',

    // ===== Subpermit-specific labels — ATEX =====
    // (additional, beyond confined-space overlap)
    // ===== Subpermit-specific labels — Command B =====
    'Pozor'                                   => 'Attention',
    'Hodnotenie rizík (RA) musí byť spracované krok po kroku — nesmie to byť všeobecné RA.' =>
        'The risk assessment (RA) must be prepared step by step — it must not be a general RA.',
    'Identifikácia Príkazu „B"'               => 'Order "B" identification',
    'Príkaz „B" (číslo príkazu)'              => 'Order "B" (order number)',
    'Pre vedúceho prác'                       => 'For the works supervisor',
    'S pracovnou skupinou (počet pracovníkov)' => 'With work group (number of workers)',
    'Pre dozor'                               => 'For supervision',
    'Termín a rozsah prác'                    => 'Schedule and scope of work',
    'Dňa'                                     => 'On day',
    'Od (čas)'                                => 'From (time)',
    'Do (čas)'                                => 'Until (time)',
    'Práca sa vykonáva'                       => 'Work is performed',
    'na elektrickom zariadení'                => 'on electrical equipment',
    'v blízkosti elektrického zariadenia'     => 'near electrical equipment',
    'Stav napätia'                            => 'Voltage state',
    'bez elektrického napätia'                => 'without electrical voltage',
    's elektrickým napätím'                   => 'with electrical voltage',
    'Zabezpečenie pracoviska'                 => 'Workplace securing',
    'Na zabezpečenie pracoviska je vypnuté a zabezpečené' =>
        'To secure the workplace the following is turned off and secured',
    'Pod napätím zostáva'                     => 'Remains under voltage',
    'Doručenie Príkazu „B"'                   => 'Delivery of Order "B"',
    'Príkaz „B" bol doručený'                 => 'Order "B" was delivered',
    'osobne'                                  => 'in person',
    'iná osoba'                               => 'other person',
    'e-mailom'                                => 'by email',
    'Mená a časy nižšie sú evidenčné. Samotné podpisy sa zachytávajú v štandardnom toku podpisov subpermitu.' =>
        'Names and times below are for record only. The signatures themselves are captured by the standard subpermit signature flow.',
    'Vydal alebo nahlásil'                    => 'Issued or reported by',
    'Prijal'                                  => 'Received by',
    'Evidencia v knihe príkazov „B"'          => 'Entry in Order "B" book',
    'Zapísaný v knihe príkazov „B" č.'        => 'Entered in Order "B" book no.',
    'Číslo príkazu'                           => 'Order number',
    'Opatrenia na zabezpečenie pracoviska'    => 'Workplace securing measures',
    'Vyplní alebo určí osoba vydávajúca príkaz „B". Stĺpec „Vykonané / nahlásené" doplní vykonávateľ.' =>
        'Filled in or determined by the person issuing the Order "B". The "Performed / reported" column is completed by the executor.',
    'Vypnúť'                                  => 'Turn off',
    'Odpojené'                                => 'Disconnected',
    'Ďalšie opatrenia'                        => 'Other measures',
    'Ako overiť, že inštalácia je bez napätia' => 'How to verify the installation is de-energized',
    'Uzemnenie a skratovanie'                 => 'Grounding and shorting',
    'Spôsob označenia pracoviska'             => 'Workplace marking method',
    'Vymedzenie pracoviska'                   => 'Workplace definition',
    'Doplnkové bezpečnostné opatrenia'        => 'Additional safety precautions',
    'Miesto'                                  => 'Location',
    'Úkon'                                    => 'Action',
    'Por. č.'                                 => 'Seq. no.',
    'Poradové č.'                             => 'Sequence no.',
    'Zodpovedný'                              => 'Responsible',
    'Okolie pracoviska'                       => 'Workplace surroundings',
    'Najbližšie časti elektrickej inštalácie (alebo iných elektrických inštalácií) pod napätím' =>
        'Nearest parts of the electrical installation (or other electrical installations) under voltage',
    'Atmosférické podmienky'                  => 'Atmospheric conditions',

    // ===== Subpermit-specific labels — Electrical =====
    '1. Povolenie na prácu'                   => '1. Permit to work',
    '2. Analýza rizík'                        => '2. Risk analysis',
    '5. Bezpečný systém práce — SSoW'         => '5. Safe System of Work — SSoW',
    '6. Prehľad elektrických prác pod napätím > 750 VAC' =>
        '6. Overview of live electrical work > 750 VAC',
    'Vypĺňa žiadateľ — kvalifikovaná MDLZ osoba ktorá vykonáva prácu definovanú v tomto povolení. Ak je práca vykonávaná treťou stranou, žiadateľ naďalej zostáva kvalifikovaná MDLZ osoba a vydáva toto povolenie v kooperácii s treťou stranou pod jeho dohľadom. Každý relevantný riadok označte „Áno".' =>
        'Filled in by the applicant — a qualified MDLZ person who performs the work defined in this permit. If the work is performed by a third party, the applicant still remains the qualified MDLZ person and issues this permit in cooperation with the third party under their supervision. Mark each relevant row "Yes".',
    'Potvrdenia kvalifikácie'                 => 'Qualification confirmations',
    'Áno, MDLZ žiadateľ je kvalifikovaný elektrikár (§21/22/23)' =>
        'Yes, the MDLZ applicant is a qualified electrician (§21/22/23)',
    'Áno, MDLZ žiadateľ je školený na vydanie povolenia (povinné)' =>
        'Yes, the MDLZ applicant is trained to issue the permit (mandatory)',
    'Áno, MDLZ žiadateľ bude vykonávať prácu' => 'Yes, the MDLZ applicant will perform the work',
    'Áno, požiadavka vydaná MDLZ žiadateľom pre tretiu stranu' =>
        'Yes, request issued by the MDLZ applicant for a third party',
    'Kto bude vykonávať prácu'                => 'Who will perform the work',
    'Interne MDLZ osobou'                     => 'Internally by a MDLZ person',
    'Interne MDLZ viacerími osobami'          => 'Internally by multiple MDLZ persons',
    'Treťou stranou'                          => 'By third party',
    'Napätie rozvádzača'                      => 'Switchboard voltage',
    'Menej ako 50 VAC (pokračujte s QRP)'     => 'Less than 50 VAC (proceed with QRP)',
    'Medzi 50 VAC a 750 VAC (pokračujte s povolením)' => 'Between 50 VAC and 750 VAC (proceed with permit)',
    'Viac ako 750 VAC — Špec. povolenie nutné!' => 'More than 750 VAC — Special permit required!',
    'Konštrukcia rozvádzača'                  => 'Switchboard construction',
    'IP2X COMP.'                              => 'IP2X COMP.',
    'IP2X NON COMP.'                          => 'IP2X NON COMP.',
    'Bezpečný systém práce (SSoW)'            => 'Safe System of Work (SSoW)',
    'SSoW je priložený ako príloha k povoleniu (inak vyplňte bod 5 nižšie)' =>
        'SSoW is attached to the permit (otherwise fill in section 5 below)',
    'Pracovná požiadavka / SAP zákazka'       => 'Work request / SAP order',
    'Plánovaný štart (dátum / čas)'           => 'Planned start (date / time)',
    'Plánovaný koniec (dátum / čas)'          => 'Planned end (date / time)',
    'Dôvod práce na živom zariadení (prečo nemôže byť práca vykonaná na vypnutom zariadení)' =>
        'Reason for live work (why the work cannot be performed with the equipment de-energized)',
    'Vypĺňa kvalifikovaná MDLZ osoba.'        => 'Filled in by a qualified MDLZ person.',
    'Analýza výboja / Prístupné hranice'      => 'Arc flash analysis / Access boundaries',
    'Arc Flash hranica rizika (m)'            => 'Arc Flash hazard boundary (m)',
    'Arc Flash hranica rizika (cm)'           => 'Arc Flash hazard boundary (cm)',
    'Hranica obmedzeného prístupu (m)'        => 'Limited approach boundary (m)',
    'Hranica obmedzeného prístupu (cm)'       => 'Limited approach boundary (cm)',
    'Hranica zakázaného prístupu (m)'         => 'Prohibited approach boundary (m)',
    'Hranica zakázaného prístupu (cm)'        => 'Prohibited approach boundary (cm)',
    'Je rozvádzač vybavený ARC FLASH nálepkou (práca bude vykonávaná v týchto hraniciach)?' =>
        'Is the switchboard fitted with an ARC FLASH sticker (work will be performed within those boundaries)?',
    'Ak Arc flash nie je dostupný, obmedzené hranice' =>
        'If Arc Flash is not available, restricted boundaries',
    '3 m (vždy > 750 VAC)'                    => '3 m (always > 750 VAC)',
    '< 3 m, priestor obmedzený a proof safe (1 m min!)' =>
        '< 3 m, restricted area and proof safe (1 m min!)',
    '*proof safe znamená že všetky vodiče sú uchytené a nie sú pohyblivé odhalené časti.' =>
        '*proof safe means all conductors are secured and there are no movable exposed parts.',
    'Nasadené prvky proti vniku nekvalifikovaných osôb' =>
        'Measures against entry by unqualified persons',
    'Značky'                                  => 'Signs',
    'Oplotenie'                               => 'Fencing',
    'Obsluha'                                 => 'Operator',
    'Bezpečnosť pracoviska'                   => 'Workplace safety',
    'Pre pokračovanie musia byť všetky odpovede „Áno".' => 'To proceed, all answers must be "Yes".',
    'Všetky horľavé riziká odstránené (nebezpečné materiály), hasiaci prístroj v dosahu' =>
        'All flammable hazards removed (hazardous materials), fire extinguisher within reach',
    'Dostatočné osvetlenie'                   => 'Adequate lighting',
    'Všetky pohyblivé dvere zabezpečené proti samovoľnému pohybu' =>
        'All movable doors secured against unintended movement',
    'Núdzový východ je voľný'                 => 'Emergency exit is clear',
    'Rozvádzač neobsahuje cudzí objekt'       => 'Switchboard contains no foreign objects',
    'Za správne vypracovanie SSoW je zodpovedná MDLZ osoba alebo tretia strana vykonávajúca prácu. Ak sa SSoW nedá bezpečne zadefinovať, práca sa nemôže začať.' =>
        'The MDLZ person or the third party performing the work is responsible for the correct preparation of the SSoW. If the SSoW cannot be defined safely, the work cannot start.',
    'Popis práce ako sa bude bezpečne vykonávať' => 'Description of how the work will be performed safely',
    'Definujte izoláciu živých častí, prevenciu pádu náradia do rozvádzača, chránený priestor, spôsob komunikácie pred vstupom, kto bude dohliadať na prácu, ochranu pred neočakávaným zatvorením dverí, odstránením ochrany a barikády. Adresujte mimoriadne riziká pre špecifickú prácu.' =>
        'Define isolation of live parts, prevention of tools falling into the switchboard, protected area, way of communication before entry, who will supervise the work, protection from unexpected door closure, removal of guards and barricades. Address extraordinary risks specific to the work.',
    'Plán núdzovej situácie'                  => 'Emergency plan',
    'Plán musí byť pripravený inou osobou ako tou, ktorá vykonáva prácu (oblastný dozor alebo člen skupiny prvej pomoci). Túto osobu zaregistrujte ako podpisovateľa v sekcii Signatári (Kvalifikovaná osoba MDLZ — havarijný plán).' =>
        'The plan must be prepared by a person other than the one performing the work (area supervisor or first-aid responder). Register that person as a signatory in the Signatories section (MDLZ qualified person — emergency plan).',
    'AED sa nachádza v oblasti (povinné)'     => 'AED is in the area (required)',
    'KPR školený'                             => 'CPR trained',
    'AED školený'                             => 'AED trained',
    'Vyplňte len ak sa pracuje na viac ako 750 VAC alebo ak je vydaných viac PTW v rovnakom čase v rovnakej oblasti / línii. Podpisy „Druhý technik (dohľad)", „Zástupca pre bezpečnosť" a „Vedúci údržby" sa zachytávajú cez systém signatárov nižšie — pri vyplnení ich e-mailov dostanú odkaz na elektronický podpis.' =>
        'Fill in only if work is over 750 VAC or if multiple PTWs are issued at the same time in the same area / line. The signatures "Second technician (supervisor)", "Safety representative" and "Maintenance manager" are captured via the signatory system below — entering their emails sends them a link to sign electronically.',
    'Kvalifikovaná osoba MDLZ — havarijný plán' => 'MDLZ qualified person — emergency plan',
    'Vedúci pracoviska'                       => 'Area supervisor',
    'Kvalifikovaná osoba MDLZ — schválenie SSoW' => 'MDLZ qualified person — SSoW approval',
    'Dodávateľ (tretia strana)'               => 'Contractor (third party)',
    'Druhý technik (>750 VAC)'                => 'Second technician (>750 VAC)',
    'Zástupca pre bezpečnosť (>750 VAC)'      => 'Safety representative (>750 VAC)',
    'Vedúci údržby (>750 VAC)'                => 'Maintenance manager (>750 VAC)',

    // ===== Subpermit signing flow header =====
    'Vyžaduje sa Váš podpis pred schválením'  => 'Your signature is required before approval',
];
