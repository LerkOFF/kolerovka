<?php
declare(strict_types=1);

namespace AgeCMS\Modules\catalog;

/** Транслитерация русских названий в адрес: латиница, цифры, дефис, до 64 знаков. */
final class Slug
{
    private const MAP = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e', 'ж' => 'zh', 'з' => 'z', 'и' => 'i',
        'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't',
        'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch', 'ъ' => '', 'ы' => 'y', 'ь' => '',
        'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
    ];

    public static function make(string $text): string
    {
        $text = strtr(mb_strtolower(trim($text)), self::MAP);
        $text = trim((string)preg_replace('/[^a-z0-9]+/', '-', $text), '-');
        return substr($text !== '' ? $text : 'tovar', 0, 64);
    }

    /** Делает адрес свободным: добавляет -2, -3 и так далее. $taken получает кандидата и говорит, занят ли он. */
    public static function unique(string $base, callable $taken): string
    {
        $base = rtrim(substr($base, 0, 60), '-');
        $slug = $base;
        for ($n = 2; $taken($slug) && $n < 1000; $n++) {
            $slug = $base . '-' . $n;
        }
        return $slug;
    }
}
