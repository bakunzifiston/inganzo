<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\PricingRule;
use App\Models\Product;
use App\Models\ProductType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Initial merchandise catalogue, design categories and the default pricing rule.
 * Costs are illustrative starting points in RWF and should be confirmed with printing
 * partners before launch; everything here is editable in the admin console.
 */
class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $this->categories();
        $this->products();

        PricingRule::query()->firstOrCreate(['name' => 'Default marketplace pricing'], [
            'currency' => 'RWF',
            'platform_margin_bps' => 2500,
            'platform_fixed_amount' => 0,
            'artist_markup_min_bps' => 0,
            'artist_markup_max_bps' => 10000,
            'artist_markup_default_bps' => 2000,
            'artist_fixed_max_amount' => 20000,
            'rounding_increment' => 100,
            'is_active' => true,
        ]);
    }

    private function categories(): void
    {
        $categories = [
            ['culture', 'Culture & heritage', 'Umuco n\'amateka'],
            ['nature', 'Nature & wildlife', 'Ibidukikije n\'inyamaswa'],
            ['typography', 'Typography & words', 'Inyandiko n\'amagambo'],
            ['abstract', 'Abstract & pattern', 'Imiterere n\'ibishushanyo'],
            ['city-life', 'City life', 'Ubuzima bw\'umujyi'],
            ['music', 'Music & dance', 'Umuziki n\'imbyino'],
        ];

        foreach ($categories as $position => [$slug, $en, $rw]) {
            Category::query()->updateOrCreate(['slug' => $slug], [
                'name' => ['en' => $en, 'rw' => $rw],
                'position' => $position,
                'is_active' => true,
                'is_featured' => $position < 4,
            ]);
        }
    }

    private function products(): void
    {
        $tops = ['White' => ['#FFFFFF', false], 'Black' => ['#141414', true], 'Heather grey' => ['#B8BABD', false], 'Navy' => ['#1E2A44', true], 'Clay red' => ['#9C3A27', true], 'Sand' => ['#D9CBAE', false]];

        $catalog = [
            'T-shirts' => ['t-shirts', 'Imipira', [
                ['classic-tee', 'Classic T-shirt', 'Ishati y\'imipira isanzwe', 'Soft 180 gsm combed cotton with a regular fit. Printed in Rwanda with water-based DTG inks.', 6500, 'tshirt', 3, $tops, ['S' => 0, 'M' => 0, 'L' => 0, 'XL' => 0, 'XXL' => 500], [
                    ['front', 'Front', 300, 400, true, [0.330, 0.250, 0.340, 0.453]],
                    ['back', 'Back', 300, 400, false, [0.330, 0.200, 0.340, 0.453]],
                ]],
            ]],
            'Oversized T-shirts' => ['oversized-t-shirts', 'Imipira minini', [
                ['oversized-tee', 'Oversized T-shirt', 'Ishati nini', 'Heavyweight 240 gsm cotton with dropped shoulders and a boxy streetwear cut.', 8500, 'tshirt-oversized', 3, array_intersect_key($tops, array_flip(['White', 'Black', 'Sand', 'Clay red'])), ['S' => 0, 'M' => 0, 'L' => 0, 'XL' => 0, 'XXL' => 700], [
                    ['front', 'Front', 350, 450, true, [0.305, 0.240, 0.390, 0.501]],
                ]],
            ]],
            'Hoodies & sweatshirts' => ['hoodies-sweatshirts', 'Amakoti', [
                ['pullover-hoodie', 'Pullover hoodie', 'Ikoti rifite ingofero', 'Brushed-fleece cotton blend with a lined hood and kangaroo pocket.', 15000, 'hoodie', 4, ['Black' => ['#141414', true], 'Charcoal' => ['#3B3D40', true], 'Forest' => ['#2E4A3B', true], 'Cream' => ['#EEE5D1', false]], ['S' => 0, 'M' => 0, 'L' => 0, 'XL' => 0, 'XXL' => 1000], [
                    ['front', 'Front', 300, 300, true, [0.350, 0.300, 0.300, 0.300]],
                ]],
                ['crew-sweatshirt', 'Crewneck sweatshirt', 'Ikoti risanzwe', 'Midweight fleece crewneck with ribbed cuffs and hem.', 12000, 'sweatshirt', 4, ['Heather grey' => ['#B8BABD', false], 'Black' => ['#141414', true], 'Navy' => ['#1E2A44', true], 'Sand' => ['#D9CBAE', false]], ['S' => 0, 'M' => 0, 'L' => 0, 'XL' => 0, 'XXL' => 800], [
                    ['front', 'Front', 300, 350, true, [0.340, 0.260, 0.320, 0.373]],
                ]],
            ]],
            'Tote bags' => ['tote-bags', 'Imifuka', [
                ['canvas-tote', 'Canvas tote bag', 'Umufuka w\'igitambaro', '12 oz natural canvas with reinforced handles. Fits a laptop and the market run.', 4500, 'tote', 2, ['Natural' => ['#EDE3CC', false], 'Black' => ['#141414', true]], [], [
                    ['front', 'Front', 300, 330, true, [0.280, 0.400, 0.440, 0.484]],
                ]],
            ]],
            'Mugs' => ['mugs', 'Ibikombe', [
                ['ceramic-mug', 'Ceramic mug 11 oz', 'Igikombe cya seramike', 'Dishwasher-safe ceramic mug with a glossy sublimation print.', 4000, 'mug', 2, ['White' => ['#FFFFFF', false]], [], [
                    ['wrap', 'Wrap', 200, 85, true, [0.180, 0.390, 0.500, 0.2125]],
                ]],
            ]],
            'Stickers' => ['stickers', 'Udupapuro dufatana', [
                ['die-cut-sticker', 'Die-cut sticker', 'Agapapuro gafatana', 'Durable vinyl sticker cut to the shape of your design. Water resistant.', 600, 'sticker', 2, ['Glossy white' => ['#FFFFFF', false]], ['7.5 cm' => 0, '10 cm' => 200, '15 cm' => 500], [
                    ['front', 'Sticker', 100, 100, true, [0.200, 0.200, 0.600, 0.600]],
                ]],
            ]],
        ];

        $typePosition = 0;
        foreach ($catalog as $typeName => [$typeSlug, $typeRw, $products]) {
            $type = ProductType::query()->updateOrCreate(['slug' => $typeSlug], [
                'name' => ['en' => $typeName, 'rw' => $typeRw],
                'position' => $typePosition++,
                'is_active' => true,
            ]);

            foreach ($products as $position => [$slug, $name, $nameRw, $description, $cost, $template, $days, $colors, $sizes, $areas]) {
                $product = Product::query()->updateOrCreate(['slug' => $slug], [
                    'product_type_id' => $type->id,
                    'name' => ['en' => $name, 'rw' => $nameRw],
                    'description' => ['en' => $description],
                    'base_cost_amount' => $cost,
                    'currency' => 'RWF',
                    'production_days' => $days,
                    'mockup_template' => $template,
                    'is_active' => true,
                    'position' => $position,
                ]);

                $colorModels = [];
                $colorPosition = 0;
                foreach ($colors as $colorName => [$hex, $dark]) {
                    $colorModels[] = $product->colors()->updateOrCreate(['name' => $colorName], ['hex' => $hex, 'is_dark' => $dark, 'position' => $colorPosition++, 'is_active' => true]);
                }

                $sizeModels = [];
                $sizePosition = 0;
                foreach ($sizes as $label => $extra) {
                    $sizeModels[] = [$product->sizes()->updateOrCreate(['label' => $label], ['position' => $sizePosition++, 'is_active' => true]), $extra];
                }

                foreach ($colorModels as $color) {
                    foreach ($sizeModels ?: [[null, 0]] as [$size, $extra]) {
                        $sku = Str::upper(Str::limit(Str::slug($slug, ''), 8, '')).'-'.Str::upper(Str::slug($color->name, '')).($size ? '-'.Str::upper(Str::slug($size->label, '')) : '');
                        $product->variants()->updateOrCreate(
                            ['product_color_id' => $color->id, 'product_size_id' => $size?->id],
                            ['sku' => $sku, 'extra_cost_amount' => $extra, 'is_active' => true],
                        );
                    }
                }

                foreach ($areas as $areaPosition => [$key, $label, $width, $height, $required, [$x, $y, $w, $h]]) {
                    $area = $product->printAreas()->updateOrCreate(['key' => $key], [
                        'label' => $label,
                        'width_mm' => $width,
                        'height_mm' => $height,
                        'min_dpi' => config('marketplace.print_quality.minimum_dpi'),
                        'recommended_dpi' => config('marketplace.print_quality.sharp_dpi'),
                        'is_required' => $required,
                        'position' => $areaPosition,
                    ]);

                    $product->mockups()->updateOrCreate(
                        ['print_area_id' => $area->id, 'product_color_id' => null, 'view' => $key === 'back' ? 'back' : 'front'],
                        ['area_x' => $x, 'area_y' => $y, 'area_width' => $w, 'area_height' => $h, 'position' => $areaPosition],
                    );
                }
            }
        }
    }
}
