<?php
// File: vendor/con2net/contao-anti-spam-form-bundle/src/Resources/contao/languages/en/tl_module.php

declare(strict_types=1);

// ===== Legends =====
$GLOBALS['TL_LANG']['tl_module']['antispam_legend'] = 'Anti-SPAM protection';

// ===== Anti-SPAM fields (registration) =====

$GLOBALS['TL_LANG']['tl_module']['c2n_enable_antispam'] = [
    'Enable Anti-SPAM protection',
    'Enables the extended SPAM protection of the Anti-SPAM extension for this registration form (JavaScript token, honeypot, time-based check and optionally ALTCHA).'
];

$GLOBALS['TL_LANG']['tl_module']['c2n_min_submit_time'] = [
    'Minimum submit time (seconds)',
    'Registrations submitted faster than this are treated as SPAM. Recommended: 10-20 seconds.'
];

$GLOBALS['TL_LANG']['tl_module']['c2n_max_submit_time'] = [
    'Maximum submit time (seconds)',
    'Registrations taking longer than this are treated as SPAM (patient bots). Recommended: 600 seconds (10 min). 0 = no limit.'
];

$GLOBALS['TL_LANG']['tl_module']['c2n_spam_reaction'] = [
    'Reaction to SPAM',
    'Defines what happens to a registration detected as SPAM. In all cases NO member is created.'
];

$GLOBALS['TL_LANG']['tl_module']['c2n_spam_reaction_options'] = [
    'discard' => 'Discard silently (redirect as on success)',
    'error' => 'Show an error message in the form',
    'report' => 'Send a report by e-mail, then discard silently'
];

$GLOBALS['TL_LANG']['tl_module']['c2n_report_email'] = [
    'Report recipients',
    'One or more e-mail addresses (comma-separated). The sender is the system administrator\'s e-mail address. At most 10 reports per hour.'
];

$GLOBALS['TL_LANG']['tl_module']['c2n_enable_altcha'] = [
    'Use ALTCHA from the Anti-SPAM extension',
    'Shows the ALTCHA captcha of the Anti-SPAM extension and hides the Contao security question instead. On Contao 4.13 please read the notes in the help.'
];

$GLOBALS['TL_LANG']['tl_module']['c2n_enable_ip_blacklist'] = [
    'Enable IP blacklist check',
    'Checks the sender\'s IP address and e-mail against StopForumSpam.com. Both are transmitted to this external service (mind your privacy policy). The check runs only after all local checks and uses 24h caching. If the service is unreachable, the registration is not blocked. Whitelist IPs can be defined in config.yml.'
];

$GLOBALS['TL_LANG']['tl_module']['c2n_debug'] = [
    'Debug mode',
    'Writes detailed information about the SPAM check of this registration form to the Contao log.'
];
