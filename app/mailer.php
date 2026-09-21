<?php
/**
 * mailer.php — warstwa wysyłki e-maili przez własny serwer SMTP.
 *
 * Lekki klient SMTP bez zewnętrznych zależności (obsługuje AUTH LOGIN,
 * STARTTLS na porcie 587 oraz połączenie SSL na porcie 465).
 * Dane SMTP pochodzą wyłącznie z konfiguracji — nigdy z kodu.
 *
 * Jeśli mail.enabled = false, wiadomości są tylko logowane (tryb bezpieczny
 * do developmentu). Błędy wysyłki nie przerywają procesu rezerwacji.
 */

declare(strict_types=1);

class Mailer
{
    /**
     * Publiczne API — wysyła wiadomość (tekst + HTML).
     */
    public static function send(string $toEmail, string $toName, string $subject, string $htmlBody, string $textBody = ''): bool
    {
        $toEmail = trim($toEmail);
        if ($toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        if (!config('mail.enabled')) {
            app_log('mail', "[WYŁĄCZONE] Do: {$toEmail} | Temat: {$subject}");
            return true; // w trybie wyłączonym traktujemy jako "obsłużone"
        }

        if ($textBody === '') {
            $textBody = trim(html_entity_decode(strip_tags($htmlBody), ENT_QUOTES, 'UTF-8'));
        }

        try {
            return (new self())->smtpSend($toEmail, $toName, $subject, $htmlBody, $textBody);
        } catch (Throwable $e) {
            app_log('mail', 'Błąd wysyłki: ' . $e->getMessage());
            return false;
        }
    }

    private function smtpSend(string $toEmail, string $toName, string $subject, string $html, string $text): bool
    {
        $host = (string) config('mail.smtp.host');
        $port = (int) config('mail.smtp.port', 587);
        $enc  = (string) config('mail.smtp.encryption', 'tls');
        $user = (string) config('mail.smtp.username');
        $pass = (string) config('mail.smtp.password');
        $timeout = (int) config('mail.smtp.timeout', 15);

        $fromEmail = (string) config('mail.from_email');
        $fromName  = (string) config('mail.from_name');

        $transport = ($enc === 'ssl') ? "ssl://{$host}" : $host;

        $ctx = stream_context_create([
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'allow_self_signed' => false],
        ]);

        $conn = @stream_socket_client(
            "{$transport}:{$port}",
            $errno,
            $errstr,
            $timeout,
            STREAM_CLIENT_CONNECT,
            $ctx
        );
        if (!$conn) {
            throw new RuntimeException("Połączenie SMTP nieudane: {$errstr} ({$errno})");
        }
        stream_set_timeout($conn, $timeout);

        $this->expect($conn, 220);
        $ehloName = $this->ehloName();

        $this->cmd($conn, "EHLO {$ehloName}", 250);

        if ($enc === 'tls') {
            $this->cmd($conn, 'STARTTLS', 220);
            if (!stream_socket_enable_crypto($conn, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('Nie udało się nawiązać szyfrowania STARTTLS.');
            }
            $this->cmd($conn, "EHLO {$ehloName}", 250);
        }

        // Uwierzytelnienie AUTH LOGIN
        if ($user !== '') {
            $this->cmd($conn, 'AUTH LOGIN', 334);
            $this->cmd($conn, base64_encode($user), 334);
            $this->cmd($conn, base64_encode($pass), 235);
        }

        $this->cmd($conn, "MAIL FROM:<{$fromEmail}>", 250);
        $this->cmd($conn, "RCPT TO:<{$toEmail}>", [250, 251]);
        $this->cmd($conn, 'DATA', 354);

        $message = $this->buildMime($fromEmail, $fromName, $toEmail, $toName, $subject, $html, $text);
        // Zakończenie danych: kropka w osobnej linii.
        fwrite($conn, $message . "\r\n.\r\n");
        $this->expect($conn, 250);

        $this->cmd($conn, 'QUIT', [221], false);
        fclose($conn);

        app_log('mail', "Wysłano do {$toEmail} | Temat: {$subject}");
        return true;
    }

    private function ehloName(): string
    {
        $host = parse_url((string) config('app.base_url'), PHP_URL_HOST);
        return $host ?: 'localhost';
    }

    private function buildMime(string $fromEmail, string $fromName, string $toEmail, string $toName, string $subject, string $html, string $text): string
    {
        $boundary = 'b_' . bin2hex(random_bytes(12));
        $encSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $encFromName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
        $encToName = '=?UTF-8?B?' . base64_encode($toName) . '?=';

        $headers = [];
        $headers[] = "Date: " . date('r');
        $headers[] = "From: {$encFromName} <{$fromEmail}>";
        $headers[] = "To: {$encToName} <{$toEmail}>";
        $headers[] = "Subject: {$encSubject}";
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "Content-Type: multipart/alternative; boundary=\"{$boundary}\"";

        $body = [];
        $body[] = "--{$boundary}";
        $body[] = "Content-Type: text/plain; charset=UTF-8";
        $body[] = "Content-Transfer-Encoding: base64";
        $body[] = "";
        $body[] = chunk_split(base64_encode($text));
        $body[] = "--{$boundary}";
        $body[] = "Content-Type: text/html; charset=UTF-8";
        $body[] = "Content-Transfer-Encoding: base64";
        $body[] = "";
        $body[] = chunk_split(base64_encode($html));
        $body[] = "--{$boundary}--";

        return implode("\r\n", $headers) . "\r\n\r\n" . implode("\r\n", $body);
    }

    /**
     * Wysyła komendę i sprawdza kod odpowiedzi.
     */
    private function cmd($conn, string $command, $expected, bool $check = true): string
    {
        fwrite($conn, $command . "\r\n");
        if (!$check) {
            return $this->readResponse($conn);
        }
        return $this->expect($conn, $expected);
    }

    private function expect($conn, $expected): string
    {
        $response = $this->readResponse($conn);
        $code = (int) substr($response, 0, 3);
        $expected = (array) $expected;
        if (!in_array($code, $expected, true)) {
            throw new RuntimeException("Nieoczekiwana odpowiedź SMTP: {$response}");
        }
        return $response;
    }

    private function readResponse($conn): string
    {
        $data = '';
        while (($line = fgets($conn, 515)) !== false) {
            $data .= $line;
            // Odpowiedzi wieloliniowe mają '-' na 4. znaku; ostatnia ma spację.
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return trim($data);
    }
}

/* ---------------------------------------------------------------------
 *  Gotowe szablony powiadomień (treść po polsku).
 * ------------------------------------------------------------------- */

function mail_layout(string $title, string $contentHtml): string
{
    $service = e((string) config('app.name'));
    $phone = e((string) config('contact.phone'));
    return "<!DOCTYPE html><html lang=\"pl\"><head><meta charset=\"UTF-8\"></head>
    <body style=\"font-family:Arial,Helvetica,sans-serif;background:#0f0f0f;color:#f5f5f5;margin:0;padding:24px;\">
      <div style=\"max-width:560px;margin:0 auto;background:#1a1a1a;border:1px solid #2a2a2a;border-radius:12px;overflow:hidden;\">
        <div style=\"background:#facc15;color:#111;padding:16px 24px;font-weight:700;\">{$service}</div>
        <div style=\"padding:24px;line-height:1.6;\">
          <h1 style=\"font-size:18px;margin:0 0 16px;color:#facc15;\">{$title}</h1>
          {$contentHtml}
          <p style=\"margin-top:24px;color:#9ca3af;font-size:13px;\">Kontakt: {$phone}</p>
        </div>
      </div>
    </body></html>";
}

/**
 * Przycisk z magicznym linkiem do śledzenia statusu (numer + token w URL).
 * Klient nie musi nic przepisywać.
 */
function mail_track_button(array $b): string
{
    if (empty($b['number']) || empty($b['token'])) {
        return '';
    }
    $url = e(base_url('/status.php?nr=' . urlencode((string) $b['number']) . '&token=' . urlencode((string) $b['token'])));
    return "<p style=\"margin:20px 0;\"><a href=\"{$url}\" style=\"display:inline-block;background:#facc15;color:#111;padding:12px 22px;border-radius:8px;font-weight:700;text-decoration:none;\">Sprawdź status zlecenia</a></p>";
}

function mail_booking_received(array $booking): array
{
    $num = e($booking['number']);
    $date = e($booking['date']);
    $time = e($booking['time']);
    $html = mail_layout('Otrzymaliśmy Twoje zgłoszenie', "
        <p>Dziękujemy za umówienie terminu dostarczenia sprzętu.</p>
        <p><strong>Numer zgłoszenia:</strong> {$num}<br>
           <strong>Termin dostarczenia:</strong> {$date}, godz. {$time}</p>
        <p><strong>Status:</strong> Oczekuje na potwierdzenie</p>
        <p>Rezerwacja dotyczy wyłącznie terminu dostarczenia sprzętu, a nie czasu trwania naprawy.</p>
        <p>Status zlecenia sprawdzisz w każdej chwili — kliknij poniższy przycisk albo podaj numer zlecenia i adres e-mail na stronie statusu.</p>
    " . mail_track_button($booking) . "
    ");
    return ['subject' => "Otrzymaliśmy Twoje zgłoszenie {$booking['number']}", 'html' => $html];
}

function mail_booking_confirmed(array $booking): array
{
    $num = e($booking['number']);
    $date = e($booking['date']);
    $time = e($booking['time']);
    $html = mail_layout('Termin dostarczenia potwierdzony', "
        <p>Termin dostarczenia sprzętu został potwierdzony.</p>
        <p><strong>Numer zgłoszenia:</strong> {$num}<br>
           <strong>Termin:</strong> {$date}, godz. {$time}</p>
        <p>Prosimy o dostarczenie sprzętu w umówionym czasie.</p>
    " . mail_track_button($booking) . "
    ");
    return ['subject' => "Termin dostarczenia potwierdzony — {$booking['number']}", 'html' => $html];
}

function mail_booking_cancelled(array $booking): array
{
    $num = e($booking['number']);
    $html = mail_layout('Termin został anulowany', "
        <p>Termin dostarczenia sprzętu dla zgłoszenia <strong>{$num}</strong> został anulowany.</p>
        <p>W razie pytań prosimy o kontakt.</p>
    ");
    return ['subject' => "Termin anulowany — {$booking['number']}", 'html' => $html];
}

function mail_booking_completed(array $booking): array
{
    $num = e($booking['number']);
    $review = trim((string) config('app.google_review_url', ''));

    $reviewBlock = '';
    if ($review !== '') {
        $r = e($review);
        $reviewBlock = "
        <p>Jeśli jesteś zadowolony/a z usługi, będę wdzięczny za krótką opinię w Google — zajmuje to chwilę, a bardzo mi pomaga.</p>
        <p style=\"margin:22px 0;\">
            <a href=\"{$r}\" style=\"display:inline-block;background:#facc15;color:#111;padding:13px 24px;border-radius:8px;font-weight:700;text-decoration:none;\">Wystaw opinię w Google</a>
        </p>
        <p style=\"font-size:13px;color:#9ca3af;\">Jeśli przycisk nie działa, skopiuj i wklej ten link w przeglądarce:<br>{$r}</p>";
    }

    $html = mail_layout('Zlecenie zakończone', "
        <p>Twoje zlecenie <strong>{$num}</strong> zostało zakończone, a sprzęt jest gotowy do odbioru.</p>
        {$reviewBlock}
        <p>Podgląd wykonanych prac znajdziesz na stronie statusu zlecenia.</p>
    " . mail_track_button($booking) . "
        <p>Dziękuję za zaufanie!</p>
    ");
    return ['subject' => "Zlecenie zakończone — {$booking['number']}", 'html' => $html];
}
