<?php

namespace Database\Seeders\Support;

use GdImage;
use RuntimeException;

/**
 * Procedurally generated DEMO artwork (original geometric designs) so the development
 * marketplace has realistic, print-resolution images without third-party assets.
 */
final class DemoArtwork
{
    private const SIZE = 3000;

    /**
     * @return list<string> available design keys
     */
    public static function keys(): array
    {
        return ['imigongo', 'agaseke', 'hills', 'volcanoes', 'kivu', 'coffee', 'canopy', 'sunburst', 'murakoze', 'skyline'];
    }

    public static function render(string $key, string $path): void
    {
        $image = imagecreatetruecolor(self::SIZE, self::SIZE);
        imagesavealpha($image, true);
        imagealphablending($image, false);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
        imagealphablending($image, true);
        imageantialias($image, true);

        match ($key) {
            'imigongo' => self::imigongo($image),
            'agaseke' => self::agaseke($image),
            'hills' => self::hills($image),
            'volcanoes' => self::volcanoes($image),
            'kivu' => self::kivu($image),
            'coffee' => self::coffee($image),
            'canopy' => self::canopy($image),
            'sunburst' => self::sunburst($image),
            'murakoze' => self::pixelWord($image, 'MURAKOZE', [194, 84, 52]),
            'skyline' => self::skyline($image),
            default => throw new RuntimeException("Unknown demo artwork [{$key}]"),
        };

        imagepng($image, $path, 6);
        imagedestroy($image);
    }

    private static function color(GdImage $image, array $rgb, int $alpha = 0): int
    {
        return imagecolorallocatealpha($image, $rgb[0], $rgb[1], $rgb[2], $alpha);
    }

    /**
     * Keep only pixels inside a circle (alpha mask) for badge-style designs.
     */
    private static function circleMask(GdImage $image, int $radius): void
    {
        $c = self::SIZE / 2;
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagealphablending($image, false);
        for ($y = 0; $y < self::SIZE; $y += 1) {
            $dy = $y - $c;
            if (abs($dy) >= $radius) {
                imageline($image, 0, $y, self::SIZE, $y, $transparent);

                continue;
            }
            $dx = (int) sqrt($radius * $radius - $dy * $dy);
            imageline($image, 0, $y, (int) ($c - $dx), $y, $transparent);
            imageline($image, (int) ($c + $dx), $y, self::SIZE, $y, $transparent);
        }
        imagealphablending($image, true);
    }

    private static function imigongo(GdImage $image): void
    {
        $black = self::color($image, [23, 20, 15]);
        $white = self::color($image, [245, 238, 226]);
        $clay = self::color($image, [180, 71, 42]);
        $m = 150;
        imagefilledrectangle($image, $m, $m, self::SIZE - $m, self::SIZE - $m, $black);
        $c = self::SIZE / 2;

        // Nested diamonds alternating white and clay, the classic Imigongo spiral motif.
        for ($i = 0; $i < 11; $i++) {
            $r = 1250 - $i * 112;
            if ($r <= 30) {
                break;
            }
            $color = $i % 2 === 0 ? $white : ($i % 4 === 1 ? $clay : $black);
            imagefilledpolygon($image, [$c, $c - $r, $c + $r, $c, $c, $c + $r, $c - $r, $c], $color);
            $inner = $r - 56;
            imagefilledpolygon($image, [$c, $c - $inner, $c + $inner, $c, $c, $c + $inner, $c - $inner, $c], $black);
        }

        // Zig-zag borders.
        $step = 150;
        foreach ([$m + 30, self::SIZE - $m - 130] as $y) {
            for ($x = $m; $x < self::SIZE - $m; $x += $step) {
                imagefilledpolygon($image, [$x, $y + 100, $x + $step / 2, $y, $x + $step, $y + 100], $white);
            }
        }
    }

    private static function agaseke(GdImage $image): void
    {
        $c = self::SIZE / 2;
        $natural = self::color($image, [222, 199, 160]);
        $black = self::color($image, [23, 20, 15]);
        $clay = self::color($image, [180, 71, 42]);
        imagefilledellipse($image, $c, $c, 2700, 2700, $natural);

        // Rings of triangles as seen on the lid of an Agaseke peace basket.
        foreach ([[1300, 1050, 24, $black], [1000, 760, 18, $clay], [720, 480, 12, $black], [440, 220, 8, $clay]] as [$outer, $inner, $count, $color]) {
            for ($k = 0; $k < $count; $k++) {
                $a1 = 2 * M_PI * $k / $count;
                $a2 = 2 * M_PI * ($k + 0.5) / $count;
                $a3 = 2 * M_PI * ($k + 1) / $count;
                imagefilledpolygon($image, [
                    $c + $inner * cos($a1), $c + $inner * sin($a1),
                    $c + $outer * cos($a2), $c + $outer * sin($a2),
                    $c + $inner * cos($a3), $c + $inner * sin($a3),
                ], $color);
            }
        }
        imagefilledellipse($image, $c, $c, 260, 260, $black);
        imagefilledellipse($image, $c, $c, 120, 120, $natural);
    }

    private static function hills(GdImage $image): void
    {
        $c = self::SIZE / 2;
        imagefilledellipse($image, $c, $c, 2800, 2800, self::color($image, [246, 214, 160]));
        imagefilledellipse($image, $c, 1180, 900, 900, self::color($image, [221, 120, 62]));
        $layers = [[47, 107, 79], [37, 86, 65], [30, 70, 54], [23, 55, 42], [18, 42, 32]];
        foreach ($layers as $i => $rgb) {
            $base = 1350 + $i * 260;
            $points = [0, self::SIZE];
            for ($x = 0; $x <= self::SIZE; $x += 60) {
                $points[] = $x;
                $points[] = (int) ($base + 140 * sin(($x / 520) + $i * 1.7) + 70 * sin($x / 190 + $i));
            }
            array_push($points, self::SIZE, self::SIZE);
            imagefilledpolygon($image, $points, self::color($image, $rgb));
        }
        self::circleMask($image, 1400);
    }

    private static function volcanoes(GdImage $image): void
    {
        $c = self::SIZE / 2;
        imagefilledellipse($image, $c, $c, 2800, 2800, self::color($image, [214, 226, 232]));
        imagefilledrectangle($image, 0, 1900, self::SIZE, self::SIZE, self::color($image, [47, 107, 79]));
        $peaks = [[700, 1100, 520], [1250, 820, 640], [1800, 980, 560], [2300, 1180, 480], [1500, 1350, 700]];
        foreach ($peaks as $i => [$x, $top, $half]) {
            $shade = [[60, 70, 80], [43, 50, 58], [70, 78, 86], [52, 60, 68], [33, 38, 44]][$i];
            imagefilledpolygon($image, [$x - $half, 2000, $x - 90, $top, $x + 90, $top, $x + $half, 2000], self::color($image, $shade));
            imagefilledpolygon($image, [$x - 150, $top + 120, $x - 90, $top, $x + 90, $top, $x + 150, $top + 120], self::color($image, [235, 238, 240]));
        }
        for ($i = 0; $i < 4; $i++) {
            imagefilledellipse($image, 600 + $i * 600, 1880 + ($i % 2) * 60, 1100, 140, self::color($image, [255, 255, 255], 70));
        }
        self::circleMask($image, 1400);
    }

    private static function kivu(GdImage $image): void
    {
        $blues = [[18, 62, 99], [29, 92, 143], [64, 132, 180], [120, 176, 210], [190, 220, 236]];
        imagefilledrectangle($image, 200, 200, 2800, 2800, self::color($image, [242, 237, 226]));
        for ($band = 0; $band < 16; $band++) {
            $rgb = $blues[$band % count($blues)];
            $points = [];
            for ($x = 200; $x <= 2800; $x += 40) {
                $points[] = $x;
                $points[] = (int) (420 + $band * 140 + 60 * sin($x / 160 + $band * 0.9));
            }
            for ($x = 2800; $x >= 200; $x -= 40) {
                $points[] = $x;
                $points[] = (int) (420 + $band * 140 + 70 + 60 * sin($x / 160 + $band * 0.9));
            }
            imagefilledpolygon($image, $points, self::color($image, $rgb));
        }
        imagesetthickness($image, 40);
        imagerectangle($image, 220, 220, 2780, 2780, self::color($image, [18, 62, 99]));
    }

    private static function coffee(GdImage $image): void
    {
        $c = self::SIZE / 2;
        $browns = [[59, 36, 24], [96, 58, 36], [138, 90, 56], [186, 140, 96], [226, 200, 160]];
        for ($i = 0; $i < 10; $i++) {
            imagefilledellipse($image, $c, $c, 2800 - $i * 270, 2800 - $i * 270, self::color($image, $browns[$i % 5]));
        }
        for ($k = 0; $k < 12; $k++) {
            $a = 2 * M_PI * $k / 12;
            self::leaf($image, $c + 1150 * cos($a), $c + 1150 * sin($a), 260, $a, [47, 107, 79]);
        }
        imagefilledellipse($image, $c - 70, $c, 220, 300, self::color($image, [180, 71, 42]));
        imagefilledellipse($image, $c + 70, $c, 220, 300, self::color($image, [180, 71, 42]));
    }

    private static function canopy(GdImage $image): void
    {
        mt_srand(42);
        $greens = [[30, 70, 54], [47, 107, 79], [78, 140, 96], [120, 170, 110], [212, 162, 58]];
        for ($i = 0; $i < 85; $i++) {
            $x = mt_rand(450, 2550);
            $y = mt_rand(450, 2550);
            if (hypot($x - 1500, $y - 1500) > 1150) {
                continue;
            }
            self::leaf($image, $x, $y, mt_rand(200, 420), mt_rand(0, 628) / 100, $greens[mt_rand(0, 4)]);
        }
    }

    private static function leaf(GdImage $image, float $cx, float $cy, float $length, float $angle, array $rgb): void
    {
        $points = [];
        for ($t = 0; $t <= M_PI; $t += M_PI / 16) {
            $points[] = [cos($t) * $length / 2, sin($t) * $length / 5];
        }
        for ($t = M_PI; $t <= 2 * M_PI; $t += M_PI / 16) {
            $points[] = [cos($t) * $length / 2, sin($t) * $length / 5];
        }
        $flat = [];
        foreach ($points as [$px, $py]) {
            $flat[] = (int) ($cx + $px * cos($angle) - $py * sin($angle));
            $flat[] = (int) ($cy + $px * sin($angle) + $py * cos($angle));
        }
        imagefilledpolygon($image, $flat, self::color($image, $rgb));
    }

    private static function sunburst(GdImage $image): void
    {
        $c = self::SIZE / 2;
        imagefilledellipse($image, $c, $c, 2800, 2800, self::color($image, [23, 20, 15]));
        $gold = self::color($image, [212, 162, 58]);
        $clay = self::color($image, [180, 71, 42]);
        for ($k = 0; $k < 36; $k++) {
            $a = 2 * M_PI * $k / 36;
            $b = 2 * M_PI * ($k + 0.42) / 36;
            imagefilledpolygon($image, [
                $c + 380 * cos($a), $c + 380 * sin($a),
                $c + 1300 * cos(($a + $b) / 2), $c + 1300 * sin(($a + $b) / 2),
                $c + 380 * cos($b), $c + 380 * sin($b),
            ], $k % 2 ? $gold : $clay);
        }
        imagefilledellipse($image, $c, $c, 640, 640, $gold);
        imagefilledellipse($image, $c, $c, 420, 420, self::color($image, [23, 20, 15]));
    }

    private static function pixelWord(GdImage $image, string $word, array $rgb): void
    {
        $font = 5;
        $w = imagefontwidth($font) * strlen($word);
        $h = imagefontheight($font);
        $small = imagecreatetruecolor($w, $h);
        imagesavealpha($small, true);
        imagealphablending($small, false);
        imagefill($small, 0, 0, imagecolorallocatealpha($small, 0, 0, 0, 127));
        imagestring($small, $font, 0, 0, $word, imagecolorallocate($small, $rgb[0], $rgb[1], $rgb[2]));
        $scale = (int) floor(2700 / $w);
        imagecopyresized($image, $small, (int) ((self::SIZE - $w * $scale) / 2), (int) ((self::SIZE - $h * $scale) / 2), 0, 0, $w * $scale, $h * $scale, $w, $h);
        imagedestroy($small);

        // Imigongo zig-zag underline.
        $y = (int) ((self::SIZE + $h * $scale) / 2) + 80;
        $ink = self::color($image, [23, 20, 15]);
        for ($x = 150; $x < 2850; $x += 180) {
            imagefilledpolygon($image, [$x, $y + 120, $x + 90, $y, $x + 180, $y + 120], $ink);
        }
    }

    private static function skyline(GdImage $image): void
    {
        $c = self::SIZE / 2;
        imagefilledellipse($image, $c, $c, 2800, 2800, self::color($image, [29, 42, 68]));
        mt_srand(7);
        for ($i = 0; $i < 60; $i++) {
            imagefilledellipse($image, mt_rand(300, 2700), mt_rand(250, 1300), 18, 18, self::color($image, [245, 238, 226]));
        }
        imagefilledellipse($image, 2100, 700, 360, 360, self::color($image, [246, 214, 160]));
        $buildings = [[500, 1600], [700, 1300], [880, 1500], [1100, 1050], [1360, 1250], [1900, 1350], [2150, 1450], [2400, 1650]];
        $ink = self::color($image, [12, 18, 30]);
        foreach ($buildings as [$x, $top]) {
            imagefilledrectangle($image, $x - 110, $top, $x + 110, 2300, $ink);
            for ($wy = $top + 60; $wy < 2200; $wy += 110) {
                imagefilledrectangle($image, $x - 60, $wy, $x - 20, $wy + 50, self::color($image, [212, 162, 58], mt_rand(0, 1) ? 0 : 90));
                imagefilledrectangle($image, $x + 20, $wy, $x + 60, $wy + 50, self::color($image, [212, 162, 58], mt_rand(0, 1) ? 0 : 90));
            }
        }
        // Domed convention-centre silhouette.
        imagefilledarc($image, 1640, 1320, 600, 900, 180, 360, $ink, IMG_ARC_PIE);
        imagefilledrectangle($image, 1340, 1320, 1940, 2300, $ink);
        imagefilledrectangle($image, 0, 2250, self::SIZE, self::SIZE, self::color($image, [47, 107, 79]));
        self::circleMask($image, 1400);
    }
}
