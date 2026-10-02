<?php

/**
 * T5 (v1.7.3) — curated disposable-email domain bundle (~150 entries).
 *
 * Source: a consolidated snapshot of the most common throwaway-inbox
 * domains from public blocklists (10minutemail/TempMail/guerrillamail
 * families and similar), maintained by hand — the admin extends it at
 * runtime via the blocked_domains_extra setting; controllers NEVER hold
 * their own hardcoded list (handoff §6 watch-out).
 *
 * Matching is exact-domain OR any subdomain, case-insensitive (the
 * NotDisposableEmail rule handles the comparison).
 */
return [

    '10minutemail.com', '10minutemail.net', '10minutemail.co.uk', '10minutemail.de',
    '20minutemail.com', '33mail.com', 'anonbox.net', 'anonymbox.com',
    'armyspy.com', 'banit.club', 'banit.me', 'bccto.me',
    'binkmail.com', 'bio-muesli.net', 'bobmail.info', 'burnermail.io',
    'casualdx.com', 'chammy.info', 'cheatmail.de', 'childish regards',
    'coolandwacky.us', 'courrieltemporaire.com', 'cuvox.de', 'dayrep.com',
    'deadaddress.com', 'despam.it', 'discard.email', 'discardmail.com',
    'dispostable.com', 'dodgeit.com', 'dodgit.com', 'dudmail.com',
    'dumpandjunk.com', 'dumpmail.com', 'e4ward.com', 'email-fake.com',
    'email temporanea', 'emailondeck.com', 'emailsensei.com', 'emailtemporanea.net',
    'emailtemporar.ro', 'fakeinbox.com', 'fakemail.net', 'fakemailgenerator.com',
    'fleckens.hu', 'gcmail.top', 'gehensiemirnicht.com', 'getairmail.com',
    'getnada.com', 'grr.la', 'guerrillamail.com', 'guerrillamail.biz',
    'guerrillamail.de', 'guerrillamail.info', 'guerrillamail.net', 'guerrillamail.org',
    'guerrillamailblock.com', 'harakirimail.com', 'hat-geld.de', 'hochsitze.com',
    'inboxbear.com', 'inboxkitten.com', 'incognitomail.com', 'jetsni.com',
    'jetable.org', 'jourrapide.com', 'kurzepost.de', 'lroid.com',
    'l planted.net', 'mail-temporaire.fr', 'mail7.io', 'mailcatch.com',
    'mailde.de', 'mailde.info', 'maildrop.cc', 'maildu.de',
    'mailexpire.com', 'mailforspam.com', 'mailfreeonline.com', 'mailimate.com',
    'mailinator.com', 'mailinator.net', 'mailinator2.com', 'mailismagic.com',
    'mailmetrash.com', 'mailnesia.com', 'mailnull.com', 'mailsac.com',
    'mailtemp.net', 'mailtothis.com', 'mintemail.com', 'mohmal.com',
    'mvrht.net', 'mytemp.email', 'mytrashmail.com', 'nada.email',
    'no-spam.ws', 'nomail.xl.cx', 'nospam.ze.tc', 'notmailinator.com',
    'nowmymail.com', 'objectmail.com', 'onetimemail.org', 'pacmail.org',
    'pokemail.net', 'proxymail.eu', 'rcpt.at', 'reallymymail.com',
    'rhyta.com', 'safe-mail.net', 'safetymail.info', 'sharklasers.com',
    'shieldedmail.com', 'shitmail.me', 'smashmail.de', 'spam4.me',
    'spambog.com', 'spamavert.com', 'spambox.us', 'spamfree24.org',
    'spamgourmet.com', 'spamhole.com', 'spaml.com', 'spamobox.com',
    'superrito.com', 'supermailer.jp', 'teleworm.us', 'tempail.com',
    'tempemail.net', 'tempemail.co.za', 'tempinbox.com', 'tempmail.plus',
    'tempmail.com', 'tempmail.dev', 'tempmail.email', 'tempmail.live',
    'tempmail.net', 'tempmailo.com', 'tempomail.fr', 'tempmailplus.com',
    'temporaryemail.net', 'temporaryinbox.com', 'temporary-mail.net', 'tempsky.com',
    'thankyou2010.com', 'throwawaymail.com', 'tmail.ws', 'tmailinator.com',
    'trash-mail.at', 'trash-mail.com', 'trashmail.at', 'trashmail.com',
    'trashmail.de', 'trashmail.me', 'trashmail.net', 'trashmails.com',
    'trbvm.com', 'trbvn.com', 'vomoto.com', 'vpn.st',
    'wegwerfmail.de', 'wegwerfmail.net', 'wegwerfmail.org', 'wh4f.org',
    'willselfdestruct.com', 'wuzup.net', 'yopmail.com', 'yopmail.fr',
    'yopmail.net', 'yopmail.org', 'zetmail.com', 'zipcat.net',

];
