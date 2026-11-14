<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Designated SEADOs
    |--------------------------------------------------------------------------
    |
    | SEADOs designated by Office Order, listed even before they handle a
    | case or get a system account. Names are written as the RFA data
    | records them ("FIRST M. LAST", upper case) so they match existing
    | cases and accounts by exact name. The office is the code the cases
    | carry (rfas.office, see config/offices.php).
    |
    | Source: Office Order (Series of 2026) designating additional SEADOs
    | who completed the Basic SEADO Training on September 9-11, 2026.
    |
    */

    'designated' => [

        // Regional Office - TSSD
        ['name' => 'ERIC A. FETALCORIN', 'office' => 'RO-V', 'position' => 'Senior LEO', 'id_number' => null],
        ['name' => 'ISAAC FREDERICK RAMOS', 'office' => 'RO-V', 'position' => 'Administrative Assistant II', 'id_number' => null],
        ['name' => 'MARY DINGHIE D. LOMEDA', 'office' => 'RO-V', 'position' => 'Statistician II', 'id_number' => null],
        ['name' => 'SAMUEL VINCE FRANCIS E. MADRID', 'office' => 'RO-V', 'position' => 'Administrative Assistant III', 'id_number' => null],
        ['name' => 'LADY ANDREA D. VEGA', 'office' => 'RO-V', 'position' => 'LEO III', 'id_number' => null],
        ['name' => 'MARLON G. ASCUTIA', 'office' => 'RO-V', 'position' => 'LEO III', 'id_number' => 'SEADO-DOLE-RO5-2023-00008'],
        ['name' => 'JOSEPH N. AYALA', 'office' => 'RO-V', 'position' => 'LEO II', 'id_number' => null],

        // Regional Office - ORD
        ['name' => 'ANGELI LOIS A. MARANAN', 'office' => 'RO-V', 'position' => 'Mediator-Arbiter', 'id_number' => null],
        ['name' => 'DANA MAY A. RIVERO', 'office' => 'RO-V', 'position' => 'AA III', 'id_number' => null],
        ['name' => 'MERYJAY ANN V. ESCOBAL', 'office' => 'RO-V', 'position' => 'AA VI', 'id_number' => null],
        ['name' => 'RHEY VINCENT A. CAPAYAS', 'office' => 'RO-V', 'position' => 'LEO II', 'id_number' => null],

        // Regional Office - IMSD
        ['name' => 'RIZALIZ A. VARGAS', 'office' => 'RO-V', 'position' => 'Admin. Officer V', 'id_number' => null],
        ['name' => 'ANGELICA JOY L. NAPOLIS', 'office' => 'RO-V', 'position' => 'Accountant III', 'id_number' => null],
        ['name' => 'REBECA B. MUJAR', 'office' => 'RO-V', 'position' => 'AA VI', 'id_number' => null],
        ['name' => 'MARY GRACE A. MAGALONA', 'office' => 'RO-V', 'position' => 'LEO II', 'id_number' => null],
        ['name' => 'ANTHONETH M. MANGAMPO', 'office' => 'RO-V', 'position' => 'Administrative Aide VI', 'id_number' => null],

        // Albay Provincial Office
        ['name' => 'CINDY V. LOGRONIO', 'office' => 'RO-V-APFO', 'position' => 'LEO III', 'id_number' => null],
        ['name' => 'OLGA R. LUDOVICE', 'office' => 'RO-V-APFO', 'position' => 'Senior LEO', 'id_number' => null],
        ['name' => 'MARY ANN B. BERMUNDO', 'office' => 'RO-V-APFO', 'position' => 'Admin. Assistant III', 'id_number' => null],
        ['name' => 'ELAINNE B. MADRASO', 'office' => 'RO-V-APFO', 'position' => 'LEO III', 'id_number' => null],
        ['name' => 'LEONARD ALVIN P. DACALLOS', 'office' => 'RO-V-APFO', 'position' => 'LEO III', 'id_number' => null],
        ['name' => 'ALEENA MAE B. RODILA', 'office' => 'RO-V-APFO', 'position' => 'LEO III', 'id_number' => null],
        ['name' => 'JONAS N. ALCOY', 'office' => 'RO-V-APFO', 'position' => 'LEO II', 'id_number' => null],
        ['name' => 'JESICA N. NAVIO', 'office' => 'RO-V-APFO', 'position' => 'LEO II', 'id_number' => null],

        // Sorsogon Provincial Office
        ['name' => 'MARK FRANCIS LLANETA', 'office' => 'RO-V-SPO', 'position' => 'LEO III', 'id_number' => 'SEADO-DOLE-RO5-2026-0165'],
        ['name' => 'RICKY A. JUDIN', 'office' => 'RO-V-SPO', 'position' => 'LEO III', 'id_number' => null],

        // Camarines Sur Provincial Office
        ['name' => 'CHERRY J. BRUSOLA', 'office' => 'RO-V-CSPO', 'position' => 'Senior LEO', 'id_number' => 'SEADO-DOLE-RO5-2026-00158'],
        ['name' => 'EFREN N. ZOLAS JR.', 'office' => 'RO-V-CSPO', 'position' => 'LEO II', 'id_number' => null],
        ['name' => 'LICERIO D. ABEJURO', 'office' => 'RO-V-CSPO', 'position' => 'LEO III', 'id_number' => 'SEADO-DOLE-RO5-2023-00001'],
        ['name' => 'SHARMAINE G. MARBELLA', 'office' => 'RO-V-CSPO', 'position' => 'LEO III', 'id_number' => 'SEADO-DOLE-RO5-2023-00033'],

        // Camarines Norte Provincial Office
        ['name' => 'FRANCIS U. DOMANAIS', 'office' => 'RO-V-CNPO', 'position' => 'LEO II', 'id_number' => null],

        // Catanduanes Provincial Office
        ['name' => 'ROLDAN MARK R. REYES', 'office' => 'RO-V-CPO', 'position' => 'LEO III', 'id_number' => 'SEADO-DOLE-RO5-2023-00040'],

        // Masbate Provincial Office
        ['name' => 'KEVIN B. BRIONES', 'office' => 'RO-V-MPO', 'position' => 'LEO II', 'id_number' => 'SEADO-DOLE-RO5-2023-00022'],

    ],

];
