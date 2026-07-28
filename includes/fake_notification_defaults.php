<?php
declare(strict_types=1);

/**
 * Türkiye isimleri — ağırlıklı Doğu / Güneydoğu Anadolu; yabancı isim/şehir yok.
 *
 * @return list<array{0: string, 1: string, 2: string, 3: int, 4: int}>
 */
function fake_notification_default_rows(): array
{
    $times = ['az önce', 'biraz önce', '2 dk önce', '3 dk önce', '4 dk önce', '5 dk önce', '6 dk önce', '7 dk önce', '8 dk önce', '10 dk önce'];
    $rows = [
        ['Kübra A.', 'Erzurum', $times[0], 18, 40],
        ['Rohat Y.', 'Van', $times[1], 17, 39],
        ['Berfin K.', 'Diyarbakır', $times[2], 17, 38],
        ['Baran Ş.', 'Mardin', $times[0], 16, 37],
        ['Zilan M.', 'Şanlıurfa', $times[3], 16, 36],
        ['Serhat D.', 'Batman', $times[4], 15, 35],
        ['Dilan T.', 'Elazığ', $times[1], 15, 34],
        ['Azad G.', 'Bitlis', $times[5], 14, 33],
        ['Leyla H.', 'Ağrı', $times[2], 14, 32],
        ['Hevi P.', 'Muş', $times[0], 14, 31],
        ['Mirza C.', 'Bingöl', $times[6], 13, 30],
        ['Cemile Ö.', 'Tunceli', $times[3], 13, 29],
        ['Ferhat B.', 'Siirt', $times[1], 13, 28],
        ['Gülizar N.', 'Şırnak', $times[7], 12, 27],
        ['Şehmus E.', 'Hakkari', $times[4], 12, 26],
        ['Remziye S.', 'Iğdır', $times[2], 12, 25],
        ['Medeni U.', 'Kars', $times[0], 11, 24],
        ['Halime V.', 'Gümüşhane', $times[5], 11, 23],
        ['Rojhat İ.', 'Erzincan', $times[3], 11, 22],
        ['Satı L.', 'Malatya', $times[1], 10, 21],
        ['Nurettin R.', 'Gaziantep', $times[8], 10, 20],
        ['Fadile K.', 'Adıyaman', $times[6], 10, 19],
        ['Yılmaz Ç.', 'Trabzon', $times[2], 9, 18],
        ['Ayşe M.', 'Ankara', $times[4], 9, 17],
        ['Mehmet Y.', 'İzmir', $times[0], 9, 16],
        ['Elif S.', 'Bursa', $times[3], 8, 15],
        ['Emre D.', 'Antalya', $times[5], 8, 14],
        ['Fatma G.', 'Samsun', $times[1], 8, 13],
        ['Oğuzhan P.', 'Konya', $times[7], 7, 12],
        ['Deniz T.', 'Eskişehir', $times[2], 7, 11],
        ['Canan Ö.', 'Mersin', $times[0], 7, 10],
        ['Hüseyin B.', 'Adana', $times[6], 6, 9],
        ['Selin K.', 'Kayseri', $times[4], 6, 8],
        ['Murat C.', 'Sivas', $times[3], 6, 7],
        ['Gül N.', 'Tokat', $times[1], 5, 6],
        ['Ahmet K.', 'İstanbul', $times[0], 5, 5],
        ['Zeynep A.', 'Erzurum', $times[2], 5, 4],
        ['Burak Ş.', 'Van', $times[5], 4, 3],
        ['Havin T.', 'Diyarbakır', $times[0], 4, 2],
        ['Jiyan M.', 'Mardin', $times[3], 4, 1],
    ];

    return $rows;
}

function fake_notifications_seed_defaults(PDO $pdo, bool $replace = true): int
{
    if ($replace) {
        $pdo->exec('DELETE FROM fake_notifications');
    }

    $ins = $pdo->prepare(
        'INSERT INTO fake_notifications (customer_name, city_name, time_label, is_active, weight, sort_order)
         VALUES (?,?,?,1,?,?)'
    );

    $count = 0;
    foreach (fake_notification_default_rows() as $row) {
        $ins->execute([$row[0], $row[1], $row[2], $row[3], $row[4]]);
        ++$count;
    }

    return $count;
}

/** Mevcut DB kayıtlarından yabancı isim/şehirleri temizler */
function fake_notifications_purge_foreign(PDO $pdo): int
{
    $foreignCities = ['Berlin', 'London', 'Amsterdam', 'Paris', 'Münih', 'Munich', 'New York', 'Dubai'];
    $placeholders = implode(',', array_fill(0, count($foreignCities), '?'));
    $st = $pdo->prepare(
        "DELETE FROM fake_notifications WHERE city_name IN ({$placeholders})
         OR customer_name REGEXP '^(James|Emma|Michael|Sophia|David|John|Anna|Liam|Olivia)'"
    );
    $st->execute($foreignCities);

    return $st->rowCount();
}
