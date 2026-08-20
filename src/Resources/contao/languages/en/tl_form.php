<?php
// File: vendor/con2net/contao-anti-spam-form-bundle/src/Resources/contao/languages/en/tl_form.php

declare(strict_types=1);

// ===== Legends =====
$GLOBALS['TL_LANG']['tl_form']['antispam_legend'] = 'Anti-SPAM protection';
$GLOBALS['TL_LANG']['tl_form']['content_analysis_legend'] = 'Content analysis';

// ===== Anti-SPAM fields =====

$GLOBALS['TL_LANG']['tl_form']['c2n_enable_antispam'] = [
    'Enable anti-SPAM protection',
    'Enables advanced SPAM protection with honeypot fields, time-based validation and optional ALTCHA captcha.'
];

$GLOBALS['TL_LANG']['tl_form']['c2n_min_submit_time'] = [
    'Minimum submit time (seconds)',
    'Forms submitted faster than this time are considered SPAM. Recommended: 5-10 seconds for short forms, 15-40 seconds for longer forms.'
];

$GLOBALS['TL_LANG']['tl_form']['c2n_max_submit_time'] = [
    'Maximum submit time (seconds)',
    'Forms taking longer than this time are considered SPAM (patient bots). Recommended: 300 seconds (5 min). Value 0 = no limit.'
];

$GLOBALS['TL_LANG']['tl_form']['c2n_spam_prefix'] = [
    'SPAM marker',
    'Text inserted into the ##form_spam_marker## variable when SPAM is detected (for e-mail subject). Tip: use &amp;nbsp; for a trailing space.'
];

$GLOBALS['TL_LANG']['tl_form']['c2n_block_spam'] = [
    'Do not send SPAM messages',
    'If enabled, submissions detected as SPAM are NOT sent by e-mail.'
];

$GLOBALS['TL_LANG']['tl_form']['c2n_abuse_email'] = [
    'Abuse e-mail address',
    'Only used if "Do not send SPAM messages" is NOT enabled. If this field is filled in, form notifications flagged as SPAM (including via NotificationCenter) are sent exclusively to this address instead of the configured recipients. Leave empty to keep the previous behaviour.'
];

$GLOBALS['TL_LANG']['tl_form']['c2n_enable_altcha'] = [
    'Enable ALTCHA captcha',
    'Enables a modern, accessible captcha system. IMPORTANT: add an "ALTCHA" form field to the form! Configuration (difficulty, algorithm, etc.) is done in config.yml.'
];

$GLOBALS['TL_LANG']['tl_form']['c2n_enable_ip_blacklist'] = [
    'Enable IP blacklist check',
    'Checks the sender\'s IP address and e-mail against StopForumSpam.com. Known SPAM IPs and e-mail addresses are blocked automatically. This check runs BEFORE all other checks and uses 24h caching. Whitelist IPs can be defined in config.yml.'
];

$GLOBALS['TL_LANG']['tl_form']['c2n_debug'] = [
    'Enable debug mode',
    'Enables verbose logging in the system log for developers. IMPORTANT: disable in production!'
];

// ===== Content analysis fields =====

// Main switch
$GLOBALS['TL_LANG']['tl_form']['c2n_enable_content_analysis'] = [
    'Enable content analysis',
    '<strong>⚠️ For experts:</strong> Analyses form data for SPAM patterns. Uses intelligent pattern detection without external APIs. All tests are DISABLED by default - only enable the tests you actually need. Recommended: test in debug mode first, then go live.'
];

// General
$GLOBALS['TL_LANG']['tl_form']['c2n_content_spam_threshold'] = [
    'SPAM threshold (points)',
    'A message is considered SPAM from this score upwards. Default: 50 points. The individual tests add up their scores. Higher value = stricter (less SPAM let through), lower value = looser (more genuine enquiries let through).'
];

// ===== Test 1: URLs in text =====

$GLOBALS['TL_LANG']['tl_form']['c2n_content_check_urls'] = [
    'Check for URLs in text',
    'Detects http://, https://, www. and domain names in the text. Almost all SPAM messages contain URLs. Very effective! Default: +50 points.'
];

$GLOBALS['TL_LANG']['tl_form']['c2n_content_score_urls'] = [
    'Score for URLs',
    'Points added when URLs are found. Default: 50 (very high, as URLs are a strong SPAM indicator).'
];

$GLOBALS['TL_LANG']['tl_form']['c2n_content_fields_urls'] = [
    'Check in fields',
    'Select the form fields to search for URLs. IMPORTANT: do NOT select the "Website" field (if present), as URLs are expected there. Typical: message, text, comment.'
];

// ===== Test 2: Special characters only =====

$GLOBALS['TL_LANG']['tl_form']['c2n_content_check_special_chars'] = [
    'Check for special characters only',
    'Detects messages consisting only of special characters (e.g. "!!!###$$$"). Typical for bot-generated messages. Default: +40 points.'
];

$GLOBALS['TL_LANG']['tl_form']['c2n_content_score_special_chars'] = [
    'Score for special characters',
    'Points added for messages consisting purely of special characters. Default: 40.'
];

$GLOBALS['TL_LANG']['tl_form']['c2n_content_fields_special_chars'] = [
    'Check in fields',
    'Select the form fields to check for special characters. Typical: name, message, subject.'
];

// ===== Test 3: Temporary e-mail addresses =====

$GLOBALS['TL_LANG']['tl_form']['c2n_content_check_tempmail'] = [
    'Check for temporary e-mail addresses',
    'Detects disposable e-mail addresses (e.g. 10minutemail.com, guerrillamail.com). Spammers often use such addresses. Default: +30 points.'
];

$GLOBALS['TL_LANG']['tl_form']['c2n_content_score_tempmail'] = [
    'Score for temporary e-mail',
    'Points added for temporary e-mail addresses. Default: 30.'
];

$GLOBALS['TL_LANG']['tl_form']['c2n_content_tempmail_domains'] = [
    'Temporary e-mail domains',
    'List of disposable e-mail domains (one per line). You can add or remove your own domains here. The e-mail address is checked automatically against this list.'
];

// ===== Test 4: Message too short =====

$GLOBALS['TL_LANG']['tl_form']['c2n_content_check_short_message'] = [
    'Check for messages that are too short',
    'Detects very short messages such as "test", "hi", "asd". Genuine enquiries are usually longer. Default: +25 points.'
];

$GLOBALS['TL_LANG']['tl_form']['c2n_content_score_short_message'] = [
    'Score for short message',
    'Points added for messages that are too short. Default: 25.'
];

$GLOBALS['TL_LANG']['tl_form']['c2n_content_min_message_length'] = [
    'Minimum message length (characters)',
    'Messages shorter than this value are considered suspicious. Default: 10 characters. Adjust this value to your form: short contact form = lower (5-10), enquiry form = higher (15-30).'
];

$GLOBALS['TL_LANG']['tl_form']['c2n_content_fields_short_message'] = [
    'Check in fields',
    'Select the message fields to check for length. IMPORTANT: only select large text fields (textarea) here, NOT short fields like name or subject. Typical: message, text, your enquiry, comment.'
];

// ===== Test 5: Repetitive characters =====

$GLOBALS['TL_LANG']['tl_form']['c2n_content_check_repetitive'] = [
    'Check for repetitive characters',
    'Detects repeated characters such as "aaaaaaa", "111111", "!!!!!!". Typical for bot messages. Default: +20 points.'
];

$GLOBALS['TL_LANG']['tl_form']['c2n_content_score_repetitive'] = [
    'Score for repetition',
    'Points added for repetitive patterns (6+ identical characters in a row). Default: 20.'
];

$GLOBALS['TL_LANG']['tl_form']['c2n_content_fields_repetitive'] = [
    'Check in fields',
    'Select the form fields to check for repetition. Typical: name, message, subject.'
];

// ===== Test 6: Uppercase letters =====

$GLOBALS['TL_LANG']['tl_form']['c2n_content_check_uppercase'] = [
    'Check for uppercase letters',
    'Detects excessive use of uppercase letters (e.g. "HELLO THIS IS IMPORTANT!!!"). Typical for SPAM. Default: +15 points.'
];

$GLOBALS['TL_LANG']['tl_form']['c2n_content_score_uppercase'] = [
    'Score for uppercase letters',
    'Points added for excessive uppercase letters. Default: 15.'
];

$GLOBALS['TL_LANG']['tl_form']['c2n_content_max_uppercase_ratio'] = [
    'Maximum uppercase ratio (%)',
    'A message is considered suspicious from this percentage upwards. Default: 60% (more than half in uppercase). WARNING: do NOT select fields that contain codes (such as country codes "DE", "ES")! Typically checked: message, text, comment.'
];

$GLOBALS['TL_LANG']['tl_form']['c2n_content_fields_uppercase'] = [
    'Check in fields',
    'Select the form fields to check for uppercase letters. IMPORTANT: do NOT select fields with codes/abbreviations (such as country, postcode, etc.)! Typical: message, name, subject.'
];

// ===== Test 7: SPAM keywords =====

$GLOBALS['TL_LANG']['tl_form']['c2n_content_check_keywords'] = [
    'Check for SPAM keywords',
    '<strong>⚠️ For experts:</strong> Searches for typical SPAM words. WARNING: can lead to many false positives! Only enable if you have carefully adapted the keyword list to your industry. Example: a tech company should remove "seo, backlink", a pharmacy should keep "viagra". Default: DISABLED. Score: +10 per keyword (max. 30).'
];

$GLOBALS['TL_LANG']['tl_form']['c2n_content_score_keywords'] = [
    'Score per keyword',
    'Points added PER keyword found (max. 30 points in total). Default: 10 points per keyword.'
];

$GLOBALS['TL_LANG']['tl_form']['c2n_content_spam_keywords'] = [
    'SPAM keywords',
    'Comma-separated list of SPAM words. IMPORTANT: make sure to adapt this list to your industry! Default keywords such as "viagra, casino, crypto" do not fit every industry. Be careful with generic words!'
];

$GLOBALS['TL_LANG']['tl_form']['c2n_content_fields_keywords'] = [
    'Check in fields',
    'Select the form fields to search for keywords. Typical: message, subject, comment. NOT: name (could accidentally contain a keyword).'
];
