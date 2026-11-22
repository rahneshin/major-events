<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Budget module (Step 09)
    |--------------------------------------------------------------------------
    |
    | overrun_threshold_percent: expense lines (and the expense total) raise an
    |   alert when actual spend is MORE than planned + this percentage.
    |   Organizational accounts can override it per event.
    | max_amount: upper bound (in rial) for one planned amount / transaction.
    |   Keeps sums safely inside a signed 64-bit integer.
    | receipt_max_kb / receipt_mimes: receipt upload rules (private disk).
    |
    */

    'overrun_threshold_percent' => 10,

    'max_amount' => 10_000_000_000_000,

    'receipt_max_kb' => 5120,

    'receipt_mimes' => ['pdf', 'jpg', 'jpeg', 'png'],

];
