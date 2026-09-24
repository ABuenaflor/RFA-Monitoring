<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Offices
    |--------------------------------------------------------------------------
    |
    | The Regional Office and the six Provincial Field Offices — the only
    | values a user account's office may hold. The key is what is
    | stored in users.office and shown in the Add/Edit User dropdown; the
    | value is the code the same office carries in the imported RFA data
    | (rfas.office), for features that need to match users to cases.
    |
    | Edit this list to add, rename or remove an office. Renaming a key
    | does not update accounts that already hold the old name.
    |
    */

    'list' => [

        'Regional Office' => 'RO-V',

        'Albay PFO' => 'RO-V-APFO',

        'Camarines Sur PFO' => 'RO-V-CSPO',

        'Camarines Norte PFO' => 'RO-V-CNPO',

        'Sorsogon PFO' => 'RO-V-SPO',

        'Masbate PFO' => 'RO-V-MPO',

        'Catanduanes PFO' => 'RO-V-CPO',

    ],

];
