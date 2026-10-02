{{--
    A3 (v1.7.6) — Admin / Email "Send test email" (plain-text part).

    @var string $mailer
    @var string $host
    @var int    $port
    @var string $siteName
--}}
The mail rail is alive.

{{ $siteName }} sent this test message to your own address from Admin / Email.
Nothing else depends on it; it is a probe.

  Mailer: {{ $mailer }}
  Host:   {{ $host !== '' ? $host : 'local sendmail' }}
  Port:   {{ $port }}

— {{ $siteName }}