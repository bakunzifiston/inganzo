<?php

namespace App\Services\Design;

use App\Models\ArtistProfile;
use App\Models\ArtworkVersion;
use App\Models\DesignAsset;
use App\Services\Artwork\PlacementValidator;
use Illuminate\Validation\ValidationException;

/**
 * Validates and sanitises the editable Design Studio document sent by the browser.
 *
 * The document is a list of Fabric.js objects. Only an allow-listed set of object types
 * and properties is kept; everything else (event handlers, gradients, filters, clip paths,
 * image src URLs, data URIs) is dropped. Image layers carry an assetRef – "asset:<id>"
 * (a studio upload) or "version:<id>" (an artwork file) – that must belong to the artist,
 * so no image data is ever stored in the database and no foreign URL is ever loaded.
 *
 * It also computes the lowest real resolution of the image layers at their designed size:
 * an image shown at scale s on an artboard rendered at D DPI has D ÷ s real DPI. That value
 * travels with the exported print file so enlarging a small photo in the studio never makes
 * it look print-ready (see PlacementValidator).
 */
class DesignDocumentValidator
{
    public const MAX_OBJECTS = 300;

    public const MAX_DEPTH = 3;

    public const MAX_TEXT = 5000;

    public const MAX_POINTS = 200;

    public const TYPES = ['rect', 'ellipse', 'circle', 'line', 'triangle', 'polygon', 'textbox', 'image', 'group'];

    public const FONTS = ['Inter', 'Bricolage Grotesque', 'Playfair Display', 'Bebas Neue', 'Pacifico', 'Space Mono', 'Caveat', 'Oswald', 'Montserrat', 'Arial', 'Georgia', 'Courier New'];

    private const COMMON = ['left', 'top', 'width', 'height', 'scaleX', 'scaleY', 'angle', 'opacity', 'strokeWidth'];

    private const SHAPE_NUMBERS = [
        'rect' => ['rx', 'ry'],
        'ellipse' => ['rx', 'ry'],
        'circle' => ['radius'],
        'line' => ['x1', 'y1', 'x2', 'y2'],
        'textbox' => ['fontSize', 'charSpacing', 'lineHeight', 'curve'],
        'image' => ['cropX', 'cropY'],
    ];

    private int $count = 0;

    /**
     * @var array<string, array{width: int, height: int, factor: float}>
     */
    private array $sources = [];

    /**
     * @var list<int>
     */
    private array $rasterDpis = [];

    public function __construct(private ArtistProfile $artist, private int $designDpi) {}

    /**
     * @return array{data: array{objects: list<array<string, mixed>>, background: string|null}, min_raster_dpi: int|null, refs: list<string>}
     *
     * @throws ValidationException
     */
    public function validate(mixed $data): array
    {
        if (! is_array($data) || ! isset($data['objects']) || ! is_array($data['objects']) || ! array_is_list($data['objects'])) {
            $this->fail(__('The design could not be read.'));
        }

        $background = $data['background'] ?? null;
        if ($background !== null && ! $this->isColor($background)) {
            $this->fail(__('The background colour is not valid.'));
        }

        $this->count = 0;
        $this->rasterDpis = [];
        $objects = array_map(fn ($object) => $this->object($object, 1, 1.0, 1.0), $data['objects']);

        return [
            'data' => ['objects' => $objects, 'background' => $background === '' ? null : $background],
            'min_raster_dpi' => $this->rasterDpis === [] ? null : min($this->rasterDpis),
            'refs' => array_keys($this->sources),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function object(mixed $object, int $depth, float $parentScaleX, float $parentScaleY): array
    {
        if (++$this->count > self::MAX_OBJECTS) {
            $this->fail(__('A design can contain at most :max layers.', ['max' => self::MAX_OBJECTS]));
        }
        if (! is_array($object) || ! is_string($object['type'] ?? null)) {
            $this->fail(__('The design contains an unreadable layer.'));
        }

        $type = mb_strtolower($object['type']);
        if (! in_array($type, self::TYPES, true)) {
            $this->fail(__('Layers of type “:type” are not supported.', ['type' => mb_substr($object['type'], 0, 20)]));
        }

        $clean = ['type' => $type];

        foreach ([...self::COMMON, ...(self::SHAPE_NUMBERS[$type] ?? [])] as $key) {
            if (array_key_exists($key, $object) && $object[$key] !== null) {
                $clean[$key] = $this->number($object[$key], $key);
            }
        }
        foreach (['flipX', 'flipY', 'visible', 'locked', 'lockAspect', 'strokeUniform'] as $key) {
            if (array_key_exists($key, $object)) {
                $clean[$key] = (bool) $object[$key];
            }
        }
        foreach (['fill', 'stroke'] as $key) {
            if (array_key_exists($key, $object) && $object[$key] !== null && $object[$key] !== '') {
                if (! $this->isColor($object[$key])) {
                    $this->fail(__('A layer uses an unsupported colour.'));
                }
                $clean[$key] = $object[$key];
            }
        }
        if (isset($object['id']) && is_string($object['id']) && preg_match('/^[A-Za-z0-9_-]{1,40}$/', $object['id'])) {
            $clean['id'] = $object['id'];
        }
        if (isset($object['name']) && is_string($object['name'])) {
            $clean['name'] = mb_substr(strip_tags($object['name']), 0, 80);
        }
        if (isset($object['shadow']) && is_array($object['shadow'])) {
            $clean['shadow'] = $this->shadow($object['shadow']);
        }
        if (isset($object['strokeDashArray']) && is_array($object['strokeDashArray'])) {
            $clean['strokeDashArray'] = array_map(fn ($v) => $this->number($v, 'strokeDashArray'), array_slice(array_values($object['strokeDashArray']), 0, 8));
        }
        $clean['originX'] = in_array($object['originX'] ?? 'center', ['left', 'center', 'right'], true) ? $object['originX'] ?? 'center' : 'center';
        $clean['originY'] = in_array($object['originY'] ?? 'center', ['top', 'center', 'bottom'], true) ? $object['originY'] ?? 'center' : 'center';
        if (($clean['scaleX'] ?? 1) == 0 || ($clean['scaleY'] ?? 1) == 0) {
            $this->fail(__('A layer has zero size.'));
        }

        $scaleX = $parentScaleX * abs($clean['scaleX'] ?? 1);
        $scaleY = $parentScaleY * abs($clean['scaleY'] ?? 1);

        return match ($type) {
            'textbox' => $this->textbox($object, $clean),
            'polygon' => $this->polygon($object, $clean),
            'image' => $this->image($object, $clean, $scaleX, $scaleY),
            'group' => $this->group($object, $clean, $depth, $scaleX, $scaleY),
            default => $clean,
        };
    }

    /**
     * @param  array<string, mixed>  $object
     * @param  array<string, mixed>  $clean
     * @return array<string, mixed>
     */
    private function textbox(array $object, array $clean): array
    {
        $text = $object['text'] ?? '';
        if (! is_string($text) || mb_strlen($text) > self::MAX_TEXT) {
            $this->fail(__('Text layers can hold at most :max characters.', ['max' => self::MAX_TEXT]));
        }
        $font = $object['fontFamily'] ?? 'Inter';
        if (! in_array($font, self::FONTS, true)) {
            $this->fail(__('The font “:font” is not available.', ['font' => mb_substr((string) $font, 0, 40)]));
        }

        return $clean + [
            'text' => $text,
            'fontFamily' => $font,
            'fontWeight' => in_array((string) ($object['fontWeight'] ?? 'normal'), ['normal', 'bold', '300', '400', '500', '600', '700', '800', '900'], true) ? (string) ($object['fontWeight'] ?? 'normal') : 'normal',
            'fontStyle' => ($object['fontStyle'] ?? 'normal') === 'italic' ? 'italic' : 'normal',
            'underline' => (bool) ($object['underline'] ?? false),
            'linethrough' => (bool) ($object['linethrough'] ?? false),
            'textAlign' => in_array($object['textAlign'] ?? 'left', ['left', 'center', 'right', 'justify'], true) ? $object['textAlign'] ?? 'left' : 'left',
        ];
    }

    /**
     * @param  array<string, mixed>  $object
     * @param  array<string, mixed>  $clean
     * @return array<string, mixed>
     */
    private function polygon(array $object, array $clean): array
    {
        $points = $object['points'] ?? null;
        if (! is_array($points) || count($points) < 3 || count($points) > self::MAX_POINTS) {
            $this->fail(__('A shape has an invalid outline.'));
        }

        $clean['points'] = array_map(fn ($p) => is_array($p) ? ['x' => $this->number($p['x'] ?? null, 'x'), 'y' => $this->number($p['y'] ?? null, 'y')] : $this->fail(__('A shape has an invalid outline.')), array_values($points));

        foreach (['pathOffset'] as $key) {
            if (isset($object[$key]) && is_array($object[$key])) {
                $clean[$key] = ['x' => $this->number($object[$key]['x'] ?? 0, $key), 'y' => $this->number($object[$key]['y'] ?? 0, $key)];
            }
        }

        return $clean;
    }

    /**
     * @param  array<string, mixed>  $object
     * @param  array<string, mixed>  $clean
     * @return array<string, mixed>
     */
    private function image(array $object, array $clean, float $scaleX, float $scaleY): array
    {
        $ref = $object['assetRef'] ?? null;
        if (! is_string($ref) || ! preg_match('/^(asset|version):(\d{1,12})$/', $ref)) {
            $this->fail(__('An image layer is missing its file.'));
        }

        $source = $this->source($ref);
        $cropX = $clean['cropX'] ?? 0;
        $cropY = $clean['cropY'] ?? 0;
        $width = $clean['width'] ?? $source['width'];
        $height = $clean['height'] ?? $source['height'];

        // Crop is non-destructive (cropX/cropY/width/height select a window of the original) and must stay inside it.
        if ($cropX < 0 || $cropY < 0 || $width < 1 || $height < 1 || $cropX + $width > $source['width'] + 1 || $cropY + $height > $source['height'] + 1) {
            $this->fail(__('An image crop lies outside the image.'));
        }

        if (($clean['visible'] ?? true) && ($clean['opacity'] ?? 1) > 0) {
            $this->rasterDpis[] = (int) floor($this->designDpi * $source['factor'] / max($scaleX, $scaleY) + 1e-9);
        }

        return $clean + ['assetRef' => $ref, 'cropX' => $cropX, 'cropY' => $cropY, 'width' => $width, 'height' => $height];
    }

    /**
     * @param  array<string, mixed>  $object
     * @param  array<string, mixed>  $clean
     * @return array<string, mixed>
     */
    private function group(array $object, array $clean, int $depth, float $scaleX, float $scaleY): array
    {
        if ($depth >= self::MAX_DEPTH) {
            $this->fail(__('Groups can be nested at most :max levels deep.', ['max' => self::MAX_DEPTH]));
        }
        $children = $object['objects'] ?? null;
        if (! is_array($children) || ! array_is_list($children) || $children === []) {
            $this->fail(__('A group has no layers.'));
        }

        // Children are positioned relative to the group centre and inherit its scale.
        $clean['objects'] = array_map(fn ($child) => $this->object($child, $depth + 1, $scaleX, $scaleY), $children);

        return $clean;
    }

    /**
     * @return array{width: int, height: int, factor: float}
     */
    private function source(string $ref): array
    {
        if (isset($this->sources[$ref])) {
            return $this->sources[$ref];
        }

        [$kind, $id] = explode(':', $ref);

        if ($kind === 'asset') {
            $asset = DesignAsset::query()->whereKey((int) $id)->where('artist_profile_id', $this->artist->id)->first();
            $source = $asset ? ['width' => $asset->width, 'height' => $asset->height, 'factor' => 1.0] : null;
        } else {
            $version = ArtworkVersion::query()->whereKey((int) $id)
                ->whereHas('artwork', fn ($q) => $q->where('artist_profile_id', $this->artist->id))
                ->first();
            $source = $version?->width && $version->height ? ['width' => $version->width, 'height' => $version->height, 'factor' => self::versionFactor($version)] : null;
        }

        if ($source === null) {
            $this->fail(__('An image in this design is no longer available. Remove it and try again.'));
        }

        return $this->sources[$ref] = $source;
    }

    /**
     * A previous studio export that contained enlarged images has less real detail than its pixel count suggests.
     */
    public static function versionFactor(ArtworkVersion $version): float
    {
        return PlacementValidator::sourceFactor($version);
    }

    /**
     * @param  array<string, mixed>  $shadow
     * @return array{color: string, blur: float, offsetX: float, offsetY: float}
     */
    private function shadow(array $shadow): array
    {
        $color = $shadow['color'] ?? 'rgba(0,0,0,0.4)';
        if (! $this->isColor($color)) {
            $this->fail(__('A layer uses an unsupported colour.'));
        }

        return [
            'color' => $color,
            'blur' => $this->number($shadow['blur'] ?? 0, 'blur'),
            'offsetX' => $this->number($shadow['offsetX'] ?? 0, 'offsetX'),
            'offsetY' => $this->number($shadow['offsetY'] ?? 0, 'offsetY'),
        ];
    }

    private function number(mixed $value, string $key): float
    {
        if (! is_int($value) && ! is_float($value)) {
            $this->fail(__('The design contains an invalid value (:key).', ['key' => $key]));
        }
        if (! is_finite((float) $value) || abs($value) > 1_000_000) {
            $this->fail(__('The design contains an invalid value (:key).', ['key' => $key]));
        }

        return round((float) $value, 4);
    }

    private function isColor(mixed $value): bool
    {
        return is_string($value) && (
            preg_match('/^#[0-9a-fA-F]{3,8}$/', $value)
            || preg_match('/^rgba?\(\s*[\d.]+%?\s*,\s*[\d.]+%?\s*,\s*[\d.]+%?\s*(,\s*[\d.]+\s*)?\)$/', $value)
            || $value === 'transparent'
        );
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['data' => $message]);
    }
}
