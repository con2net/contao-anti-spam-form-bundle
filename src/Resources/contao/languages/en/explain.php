<?php
// File: src/Resources/contao/languages/en/explain.php

declare(strict_types=1);

/**
 * Explanations for the Helpwizard (?)
 */

$GLOBALS['TL_LANG']['XPL']['c2n_antispam'] = [
    [
        'Anti-SPAM protection - How it works',
        '<strong>The anti-SPAM protection works with several mechanisms:</strong><br><br>

        <strong>1. Honeypot field (required!):</strong><br>
        Add a honeypot field to the form (text, textarea or checkbox).
        This field is invisible to normal visitors, but bots fill it in.<br><br>

        <strong>2. Time-based validation:</strong><br>
        - <strong>Minimum time:</strong> submitting too fast (< X seconds) = bot<br>
        - <strong>Maximum time:</strong> submitting too slowly (> X seconds) = delayed bot<br><br>

        <strong>3. JavaScript token:</strong><br>
        A hidden token is generated via JavaScript. Bots without JS execution fail this check.<br><br>

        <strong>4. SPAM handling:</strong><br>
        - <strong>Mark:</strong> e-mail is sent with "*** SPAM ***" (default)<br>
        - <strong>Block:</strong> e-mail is NOT sent, error message shown to the user<br><br>

        <strong>Variable for e-mail subject:</strong><br>
        Use in NotificationCenter: <code>##form_spam_marker##Contact form: new enquiry</code><br>
        For SPAM this becomes: <strong>*** SPAM *** Contact form: new enquiry</strong><br><br>

        <strong>Debug mode:</strong><br>
        Only enable debug mode during development!
        It writes verbose logs to the system log (time checks, honeypot status, etc.).'
    ]
];

$GLOBALS['TL_LANG']['XPL']['c2n_honeypot'] = [
    [
        'Honeypot field - Best practices',
        '<strong>What is a honeypot?</strong><br>
        A hidden form field that is invisible to humans but filled in by bots.<br><br>

        <strong>Recommended field names:</strong><br>
        - <code>website</code> - Very inconspicuous<br>
        - <code>company</code> - Attracts business bots<br>
        - <code>newsletter_subscribe</code> - Checkbox variant<br>
        - <code>local_office_address</code> - Proven to work well!<br>
        - <code>additional_info</code> - For textarea<br><br>

        <strong>DO NOT use:</strong><br>
        - <code>honeypot</code> - Too obvious!<br>
        - <code>spam</code> - Bots know this one<br>
        - <code>trap</code> - Reveals the purpose<br><br>

        <strong>Label recommendations:</strong><br>
        - Text: "Website", "Company", "Phone number"<br>
        - Textarea: "Additional information", "Comments"<br>
        - Checkbox: "Subscribe to newsletter", "Receive updates"<br><br>

        <strong>How it works:</strong><br>
        1. The field is hidden via CSS (<code>opacity: 0</code>)<br>
        2. It is still present in the HTML code<br>
        3. Bots fill in ALL fields → SPAM detected!<br>
        4. Humans do not see the field → it stays empty<br><br>

        <strong>Note:</strong><br>
        Add at least ONE honeypot field to your form
        for the anti-SPAM protection to work!'
    ]
];

$GLOBALS['TL_LANG']['XPL']['c2n_block_spam'] = [
    [
        'Do not send SPAM messages vs. abuse e-mail',
        '<strong>Do not send SPAM messages (this checkbox):</strong><br>
        If this option is enabled, a submission detected as SPAM is
        blocked completely. NO notification goes out at all - neither to the
        normal recipients nor to an abuse address.<br><br>

        <strong>Abuse e-mail (only effective if this checkbox is NOT enabled):</strong><br>
        SPAM is still only marked (see SPAM marker), but all
        notifications - including via NotificationCenter - are then sent
        exclusively to this abuse address instead of the originally configured
        recipients. This way your regular recipients no longer see SPAM mails,
        without the submission being lost completely.<br><br>

        <strong>Important:</strong><br>
        If the abuse e-mail field is empty, nothing changes compared to the
        previous behaviour - SPAM-marked mails are still sent to the normal
        recipients as before. No migration required, existing forms keep
        working unchanged.'
    ]
];

$GLOBALS['TL_LANG']['XPL']['c2n_spam_reaction'] = [
    [
        'Discard silently',
        'The registration is discarded and the visitor is redirected as after a
        successful registration. Bots get no hint that they were detected.'
    ],
    [
        'Show an error message in the form',
        'The form is shown again with an error message, the input is kept. Recommended,
        because a real visitor immediately notices if a registration was wrongly
        detected as SPAM. Exception: if the e-mail address belongs to a member who has
        not been activated yet, the registration is discarded silently, because Contao
        would otherwise resend the activation mail.'
    ],
    [
        'Send a report by e-mail, then discard silently',
        'Like "Discard silently", but additionally a report about the discarded
        registration (without password) is sent to the configured address(es).'
    ],
    [
        'Important',
        'In all cases NO member is created and no e-mail is sent to the visitor.'
    ]
];

$GLOBALS['TL_LANG']['XPL']['c2n_registration_altcha'] = [
    [
        'ALTCHA instead of the Contao security question',
        'If enabled, the registration form shows the ALTCHA captcha of the Anti-SPAM
        extension. The Contao security question is hidden automatically, the
        "Disable spam protection" setting itself is not changed.'
    ],
    [
        '"Disable spam protection" (Contao)',
        'This Contao setting in the "Module configuration" section ONLY disables the
        Contao security question, NOT the protection of the Anti-SPAM extension. That
        is controlled solely by "Enable Anti-SPAM protection".'
    ],
    [
        'Contao 4.13: embedding via a content element',
        'If the registration module is embedded into an article via a content element of
        type "Module" on Contao 4.13, the Contao security question cannot be hidden
        automatically and both captchas are shown. In this case additionally tick
        "Disable spam protection" in the module. Not needed on Contao 5.'
    ]
];
