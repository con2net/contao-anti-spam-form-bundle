<?php

$GLOBALS['TL_LANG']['ERR']['c2nSpamBlocked'] =
    'Your request could not be processed. Please try again later.';

$GLOBALS['TL_LANG']['MSC']['c2n_registration_report'] = [
    'subject' => 'Anti-SPAM: spam registration discarded (%s, module ID %d)',
    'intro' => 'The Anti-SPAM extension detected a registration as spam and discarded it. No member was created.',
    'module' => 'Module',
    'reason' => 'Reason',
    'time' => 'Time',
    'ip' => 'IP address',
    'url' => 'Page',
    'userAgent' => 'Browser',
    'fields' => 'Submitted data',
    'suppressed' => 'Since the last report, %d more spam registrations were discarded without a report.',
    'footer' => 'Passwords are never included. The data comes from the sender and is unverified.'
];
