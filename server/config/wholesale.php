<?php

return [
    // Prototype OTP: every code is shared.otp.prototypeCode and returned as a hint. Turn off in production.
    'otp_prototype' => env('OTP_PROTOTYPE', true),
    'otp_max_attempts' => 5,
    'invoice_prefix' => 'INV',
    'low_stock_default' => 20,
];
