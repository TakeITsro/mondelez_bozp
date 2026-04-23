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
    'Stav' => 'Status',
    'Vytvorené' => 'Created',
    'Zatiaľ ste nevytvorili žiadny permit.' => 'You haven\'t created any permits yet.',

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
];
