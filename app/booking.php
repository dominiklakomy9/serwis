<?php
/**
 * booking.php — logika rezerwacji: generowanie slotów, dostępność,
 * tworzenie rezerwacji z ochroną przed podwójną rezerwacją.
 *
 * Kluczowa zasada biznesowa: klient rezerwuje jedynie GODZINĘ DOSTARCZENIA
 * sprzętu. Nie rezerwuje czasu trwania naprawy.
 */

declare(strict_types=1);

/**
 * Generuje listę godzin (HH:MM:00) dla przedziału [start, end) co `interval` minut.
 */
function generate_times(string $start, string $end, int $interval): array
{
    $times = [];
    if ($interval < 5) {
        $interval = 30;
    }
    $t = DateTimeImmutable::createFromFormat('!H:i:s', $start)
        ?: DateTimeImmutable::createFromFormat('!H:i', $start);
    $e = DateTimeImmutable::createFromFormat('!H:i:s', $end)
        ?: DateTimeImmutable::createFromFormat('!H:i', $end);
    if (!$t || !$e || $e <= $t) {
        return [];
    }
    while ($t < $e) {
        $times[] = $t->format('H:i:s');
        $t = $t->modify("+{$interval} minutes");
    }
    return $times;
}

/**
 * Zwraca zajęte godziny (HH:MM:00) w danym dniu — aktywne rezerwacje.
 */
function booked_times_for_date(string $date): array
{
    $pdo = db();
    $stmt = $pdo->prepare(
        "SELECT slot_time FROM appointments
         WHERE slot_date = :d AND status NOT IN ('cancelled','no_show')"
    );
    $stmt->execute([':d' => $date]);
    return array_map(
        static fn($t) => substr((string) $t, 0, 8),
        $stmt->fetchAll(PDO::FETCH_COLUMN)
    );
}

/**
 * Zwraca ręcznie zablokowane godziny (HH:MM:00) w danym dniu.
 */
function blocked_times_for_date(string $date): array
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT slot_time FROM blocked_slots WHERE slot_date = :d');
    $stmt->execute([':d' => $date]);
    return array_map(
        static fn($t) => substr((string) $t, 0, 8),
        $stmt->fetchAll(PDO::FETCH_COLUMN)
    );
}

/**
 * Zwraca listę WOLNYCH godzin dla danej daty (widok klienta).
 * Uwzględnia: bloki dostępności, zajęte terminy, blokady i przeszłość.
 */
function free_slots_for_date(string $date): array
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT start_time, end_time, interval_minutes FROM availability WHERE slot_date = :d');
    $stmt->execute([':d' => $date]);
    $blocks = $stmt->fetchAll();

    if (!$blocks) {
        return [];
    }

    $all = [];
    foreach ($blocks as $b) {
        foreach (generate_times((string) $b['start_time'], (string) $b['end_time'], (int) $b['interval_minutes']) as $t) {
            $all[$t] = true;
        }
    }

    // Usuń zajęte i zablokowane.
    foreach (booked_times_for_date($date) as $t) {
        unset($all[$t]);
    }
    foreach (blocked_times_for_date($date) as $t) {
        unset($all[$t]);
    }

    // Usuń godziny z przeszłości, jeśli to dzisiejszy dzień.
    $today = (new DateTimeImmutable('today'))->format('Y-m-d');
    if ($date === $today) {
        $now = (new DateTimeImmutable('now'))->format('H:i:s');
        foreach (array_keys($all) as $t) {
            if ($t <= $now) {
                unset($all[$t]);
            }
        }
    }

    $times = array_keys($all);
    sort($times);
    // Zwracamy w formacie HH:MM (przyjazny dla frontendu).
    return array_map(static fn($t) => substr($t, 0, 5), $times);
}

/**
 * Zwraca listę dat (YYYY-MM-DD) w danym miesiącu, które mają >=1 wolny slot.
 * Wykorzystywane do podświetlenia dostępnych dni w kalendarzu.
 */
function available_dates_in_month(int $year, int $month): array
{
    $pdo = db();
    $first = sprintf('%04d-%02d-01', $year, $month);
    $firstDt = new DateTimeImmutable($first);
    $last = $firstDt->format('Y-m-t');

    $stmt = $pdo->prepare(
        'SELECT DISTINCT slot_date FROM availability
         WHERE slot_date BETWEEN :a AND :b ORDER BY slot_date'
    );
    $stmt->execute([':a' => $first, ':b' => $last]);
    $candidateDates = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $result = [];
    foreach ($candidateDates as $d) {
        if (count(free_slots_for_date((string) $d)) > 0) {
            $result[] = (string) $d;
        }
    }
    return $result;
}

/**
 * Generuje unikalny numer zgłoszenia w formacie PREFIX-ROK-NNNN.
 */
function generate_appointment_number(PDO $pdo): string
{
    $prefix = (string) config('app.order_prefix', 'PK');
    $year = date('Y');
    $like = $prefix . '-' . $year . '-%';

    $stmt = $pdo->prepare(
        'SELECT appointment_number FROM appointments
         WHERE appointment_number LIKE :like
         ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([':like' => $like]);
    $lastNum = $stmt->fetchColumn();

    $next = 1;
    if ($lastNum && preg_match('/-(\d+)$/', (string) $lastNum, $m)) {
        $next = (int) $m[1] + 1;
    }
    return sprintf('%s-%s-%04d', $prefix, $year, $next);
}

/**
 * Tworzy rezerwację w sposób bezpieczny względem podwójnej rezerwacji.
 *
 * Zwraca tablicę:
 *   ['ok' => true, 'number' => ..., 'token' => ..., 'date' => ..., 'time' => ..., 'status' => 'pending']
 * lub
 *   ['ok' => false, 'error' => '...', 'code' => 'slot_taken'|'invalid'|'server']
 *
 * @param array $data znormalizowane, ZWALIDOWANE dane (patrz create_booking.php)
 */
function create_booking(array $data): array
{
    $pdo = db();

    $date = $data['date'];       // 'Y-m-d'
    $time = $data['time'];       // 'HH:MM:00'
    $timeShort = substr($time, 0, 5);

    try {
        $pdo->beginTransaction();

        // 1) Czy administrator udostępnił blok obejmujący ten slot?
        //    Blokujemy wiersze dostępności FOR UPDATE (serializacja).
        $stmt = $pdo->prepare(
            'SELECT start_time, end_time, interval_minutes FROM availability
             WHERE slot_date = :d FOR UPDATE'
        );
        $stmt->execute([':d' => $date]);
        $blocks = $stmt->fetchAll();

        $slotOffered = false;
        foreach ($blocks as $b) {
            $times = generate_times((string) $b['start_time'], (string) $b['end_time'], (int) $b['interval_minutes']);
            if (in_array($time, $times, true)) {
                $slotOffered = true;
                break;
            }
        }
        if (!$slotOffered) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'Wybrany termin nie jest dostępny.', 'code' => 'invalid'];
        }

        // 2) Czy slot został ręcznie zablokowany?
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM blocked_slots WHERE slot_date = :d AND slot_time = :t');
        $stmt->execute([':d' => $date, ':t' => $time]);
        if ((int) $stmt->fetchColumn() > 0) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'Ten termin został właśnie zajęty. Wybierz inną godzinę.', 'code' => 'slot_taken'];
        }

        // 3) Czy slot jest już zajęty przez aktywną rezerwację? (blokada odczytu)
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM appointments
             WHERE slot_date = :d AND slot_time = :t
               AND status NOT IN ('cancelled','no_show')
             FOR UPDATE"
        );
        $stmt->execute([':d' => $date, ':t' => $time]);
        if ((int) $stmt->fetchColumn() > 0) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'Ten termin został właśnie zajęty. Wybierz inną godzinę.', 'code' => 'slot_taken'];
        }

        // 4) Utwórz klienta.
        $stmt = $pdo->prepare(
            'INSERT INTO customers (full_name, phone, email, created_at, updated_at)
             VALUES (:name, :phone, :email, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
        );
        $stmt->execute([
            ':name'  => $data['full_name'],
            ':phone' => $data['phone'],
            ':email' => $data['email'],
        ]);
        $customerId = (int) $pdo->lastInsertId();

        // 5) Numer zgłoszenia + token statusu.
        $number = generate_appointment_number($pdo);
        $token = bin2hex(random_bytes(32)); // 64 znaki hex, nieprzewidywalny

        // 6) Wstaw rezerwację. UNIQUE(active_slot_key) to twarda ochrona
        //    przed wyścigiem — równoległy INSERT dla tego samego slotu poleci
        //    wyjątkiem duplikatu klucza (obsłużonym niżej).
        $stmt = $pdo->prepare(
            'INSERT INTO appointments
                (appointment_number, customer_id, slot_date, slot_time, device_type,
                 device_manufacturer, device_model, problem_description, status,
                 status_token, privacy_accepted, source_ip, created_at, updated_at)
             VALUES
                (:num, :cid, :d, :t, :dtype, :man, :model, :prob, "pending",
                 :token, :priv, :ip, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
        );
        $stmt->execute([
            ':num'   => $number,
            ':cid'   => $customerId,
            ':d'     => $date,
            ':t'     => $time,
            ':dtype' => $data['device_type'],
            ':man'   => $data['device_manufacturer'],
            ':model' => $data['device_model'],
            ':prob'  => $data['problem_description'],
            ':token' => $token,
            ':priv'  => 1,
            ':ip'    => client_ip(),
        ]);
        $appointmentId = (int) $pdo->lastInsertId();

        // 7) Wpis w historii statusów.
        $stmt = $pdo->prepare(
            'INSERT INTO appointment_status_history (appointment_id, old_status, new_status, note, created_at)
             VALUES (:aid, NULL, "pending", "Zgłoszenie utworzone przez klienta", UTC_TIMESTAMP())'
        );
        $stmt->execute([':aid' => $appointmentId]);

        $pdo->commit();

        return [
            'ok'     => true,
            'number' => $number,
            'token'  => $token,
            'date'   => $date,
            'time'   => $timeShort,
            'status' => 'pending',
            'appointment_id' => $appointmentId,
            'customer_email' => $data['email'],
            'customer_name'  => $data['full_name'],
        ];
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        // 23000 = naruszenie ograniczenia integralności (np. UNIQUE active_slot_key).
        if ($e->getCode() === '23000') {
            return ['ok' => false, 'error' => 'Ten termin został właśnie zajęty. Wybierz inną godzinę.', 'code' => 'slot_taken'];
        }
        app_log('booking', 'Błąd tworzenia rezerwacji: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Nie udało się zapisać zgłoszenia. Spróbuj ponownie.', 'code' => 'server'];
    }
}

/**
 * Zmiana statusu rezerwacji przez administratora (z zapisem historii).
 */
function change_appointment_status(int $appointmentId, string $newStatus, ?int $adminId, ?string $note = null): bool
{
    $allowed = ['pending','confirmed','in_progress','waiting_for_customer','completed','cancelled','no_show'];
    if (!in_array($newStatus, $allowed, true)) {
        return false;
    }
    $pdo = db();
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT status FROM appointments WHERE id = :id FOR UPDATE');
        $stmt->execute([':id' => $appointmentId]);
        $old = $stmt->fetchColumn();
        if ($old === false) {
            $pdo->rollBack();
            return false;
        }
        $upd = $pdo->prepare('UPDATE appointments SET status = :s, updated_at = UTC_TIMESTAMP() WHERE id = :id');
        $upd->execute([':s' => $newStatus, ':id' => $appointmentId]);

        $hist = $pdo->prepare(
            'INSERT INTO appointment_status_history (appointment_id, old_status, new_status, note, changed_by, created_at)
             VALUES (:aid, :old, :new, :note, :by, UTC_TIMESTAMP())'
        );
        $hist->execute([
            ':aid'  => $appointmentId,
            ':old'  => $old,
            ':new'  => $newStatus,
            ':note' => $note,
            ':by'   => $adminId,
        ]);
        $pdo->commit();
        app_log('appointment', "Zmiana statusu #{$appointmentId}: {$old} -> {$newStatus} (admin {$adminId})");
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        app_log('appointment', 'Błąd zmiany statusu: ' . $e->getMessage());
        return false;
    }
}

/**
 * Wysyła powiadomienie e-mail po zmianie statusu (potwierdzenie / anulowanie /
 * zakończenie). Nie przerywa procesu w razie błędu. Do maila dołączany jest
 * magiczny link do śledzenia statusu (numer + token w adresie).
 */
function notify_status_change(int $appointmentId, string $newStatus): void
{
    try {
        $pdo = db();
        $q = $pdo->prepare(
            'SELECT a.appointment_number, a.slot_date, a.slot_time, a.status_token, c.email, c.full_name
             FROM appointments a JOIN customers c ON c.id = a.customer_id WHERE a.id = :id'
        );
        $q->execute([':id' => $appointmentId]);
        $row = $q->fetch();
        if (!$row || empty($row['email'])) {
            return;
        }
        $payload = [
            'number' => $row['appointment_number'],
            'date'   => $row['slot_date'],
            'time'   => substr((string) $row['slot_time'], 0, 5),
            'token'  => $row['status_token'],
        ];
        if ($newStatus === 'confirmed') {
            $t = mail_booking_confirmed($payload);
        } elseif ($newStatus === 'cancelled') {
            $t = mail_booking_cancelled($payload);
        } elseif ($newStatus === 'completed') {
            $t = mail_booking_completed($payload);
        } else {
            return;
        }
        Mailer::send($row['email'], $row['full_name'], $t['subject'], $t['html']);
    } catch (Throwable $e) {
        app_log('mail', 'notify_status_change: ' . $e->getMessage());
    }
}
