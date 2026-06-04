<?php

namespace Core;

use RuntimeException;

/**
 * Liest Konfigurationswerte aus der zentralen config.ini.
 *
 * Verwendet einen eigenen INI-Parser statt parse_ini_file(),
 * weil PHPs eingebaute Funktion bestimmte Sonderzeichen (z.B. !)
 * in unquoteten Werten nicht akzeptiert. Das Delphi-Modul liest
 * dieselbe Datei mit dem nativen Windows-INI-Format, ohne
 * Anfuehrungszeichen — und das soll so bleiben.
 *
 * Verwendung:
 *   $driver = Config::get('MAIL', 'driver', 'file');
 *   $section = Config::section('MAIL');
 */
class Config
{
    /** relativer Pfad zur config.ini */


    private const INI_PATH = __DIR__ . '../../../../apache24/cgi-config/config.ini';


    /** Cache der geladenen Werte (pro Request) */
    private static ?array $data = null;

    /**
     * Laedt die config.ini beim ersten Zugriff.
     */
    private static function load(): void
    {


        if (self::$data !== null) {
            return;
        }

        if (!is_readable(self::INI_PATH)) {
            throw new RuntimeException(
                'Konfigurationsdatei nicht lesbar: ' . self::INI_PATH
            );
        }

        $content = file_get_contents(self::INI_PATH);
        if ($content === false) {
            throw new RuntimeException(
                'Konfigurationsdatei konnte nicht gelesen werden: ' . self::INI_PATH
            );
        }

        self::$data = self::parseIni($content);
    }

    /**
     * Eigener INI-Parser — toleranter als parse_ini_file().
     *
     * Erkennt:
     *   [section]            ← Section-Header
     *   key = value          ← Schluessel/Wert-Paar
     *   ; Kommentar          ← Kommentarzeile (auch # als Praefix)
     *
     * Werte werden so genommen wie sie sind, inklusive Sonderzeichen.
     * Anfuehrungszeichen am Anfang/Ende werden NICHT entfernt — wer
     * sie braucht, bekommt sie. Das passt zum Verhalten der meisten
     * Delphi-INI-Reader.
     */
    private static function parseIni(string $content): array
    {
        $result        = [];
        $currentSection = null;

        // Zeilen splitten — sowohl Windows- als auch Unix-Zeilenenden
        $lines = preg_split('/\r\n|\r|\n/', $content);

        foreach ($lines as $line) {
            $line = trim($line);

            // Leere Zeilen und Kommentare ueberspringen
            if ($line === '' || $line[0] === ';' || $line[0] === '#') {
                continue;
            }

            // Section-Header erkennen: [SECTION]
            if ($line[0] === '[' && substr($line, -1) === ']') {
                $currentSection = trim(substr($line, 1, -1));
                if (!isset($result[$currentSection])) {
                    $result[$currentSection] = [];
                }
                continue;
            }

            // Schluessel/Wert: alles vor und nach dem ersten '='
            $eqPos = strpos($line, '=');
            if ($eqPos === false) {
                continue;  // Ungueltiges Format, ueberspringen
            }

            $key   = trim(substr($line, 0, $eqPos));
            $value = trim(substr($line, $eqPos + 1));

            // Inline-Kommentare entfernen, aber nur wenn der Wert NICHT
            // in Anfuehrungszeichen steht
            // (z.B. "key=wert ; das ist ein Kommentar" → "wert")
            if ($value !== '' && $value[0] !== '"') {
                $semicolonPos = strpos($value, ';');
                if ($semicolonPos !== false) {
                    $value = trim(substr($value, 0, $semicolonPos));
                }
            }

            // Werte ohne Section landen in einem speziellen "_root"-Bereich
            // (kommt in deiner config.ini nicht vor, aber zur Sicherheit)
            if ($currentSection === null) {
                $result['_root'][$key] = $value;
            } else {
                $result[$currentSection][$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Liefert einen Wert aus der Konfiguration.
     */
    public static function get(string $section, string $key, $default = null)
    {
        self::load();
        return self::$data[$section][$key] ?? $default;
    }

    /**
     * Liefert eine ganze Section als Array.
     */
    public static function section(string $section): array
    {
        self::load();
        return self::$data[$section] ?? [];
    }

    /**
     * Prueft, ob ein Schluessel in einer Section existiert.
     */
    public static function has(string $section, string $key): bool
    {
        self::load();
        return isset(self::$data[$section][$key]);
    }
}
