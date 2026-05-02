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
];
