<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\SplFileInfo;
use Tests\TestCase;

/**
 * Panel nie ma buildera — biblioteki i własne assety to statyczne pliki
 * w public/assets, ładowane przez asset() i wersjonowane filemtime().
 *
 * Test pilnuje dwóch pułapek tego układu: odwołania do nieistniejącego pliku
 * (literówka w ścieżce = 404 i zepsuty widok) oraz resztek kodu Blade/HTML
 * w wydzielonych plikach JS/CSS (np. doklejone `</script>` = SyntaxError
 * i wszystkie funkcje strony przestają istnieć).
 */
class StaticAssetIntegrityTest extends TestCase
{
    /**
     * Znaczniki, których nie może zawierać statyczny plik JS/CSS.
     */
    private const BLADE_LEFTOVERS = ['{{', '}}', '@push', '@endpush', '@include', '@if', '<script', '</script>', '<style', '</style>'];

    public function test_assets_referenced_in_views_exist(): void
    {
        $missing = [];

        foreach (File::allFiles(resource_path('views')) as $view) {
            foreach ($this->referencedAssets($view) as $path) {
                if (! is_file(public_path($path))) {
                    $missing[] = "{$path} ({$view->getRelativePathname()})";
                }
            }
        }

        $this->assertSame([], $missing, 'Widoki odwołują się do nieistniejących plików w public/.');
    }

    public function test_own_js_and_css_have_no_blade_or_html_leftovers(): void
    {
        $offenders = [];

        foreach (File::allFiles(resource_path('views')) as $view) {
            foreach ($this->referencedAssets($view) as $path) {
                $contents = $this->versionedAssetContents($view, $path);

                if ($contents === null) {
                    continue;
                }

                foreach (self::BLADE_LEFTOVERS as $needle) {
                    if (str_contains($contents, $needle)) {
                        $offenders[] = "{$path} zawiera '{$needle}'";
                    }
                }
            }
        }

        $this->assertSame([], array_values(array_unique($offenders)), 'Wydzielone pliki JS/CSS zawierają resztki kodu Blade/HTML.');
    }

    /**
     * Ścieżki assetów (względem public/) użyte w danym widoku przez asset().
     *
     * @return array<int, string>
     */
    private function referencedAssets(SplFileInfo $view): array
    {
        preg_match_all("/asset\('([^']+\.(?:css|js|woff2|svg|png))'\)/", $view->getContents(), $matches);

        return $matches[1];
    }

    /**
     * Zawartość pliku tylko wtedy, gdy widok wersjonuje go filemtime() — to
     * odróżnia własne pliki z tego repo od skopiowanych bibliotek (Bootstrap,
     * jQuery, FontAwesome), które mogą zawierać dowolny tekst.
     */
    private function versionedAssetContents(SplFileInfo $view, string $path): ?string
    {
        if (! str_contains($view->getContents(), "filemtime(public_path('{$path}'))")) {
            return null;
        }

        return is_file(public_path($path)) ? file_get_contents(public_path($path)) : null;
    }
}
