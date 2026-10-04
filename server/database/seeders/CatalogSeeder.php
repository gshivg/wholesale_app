<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Hub;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\StockService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        // category => [icon, [ [name, brand, description, gst, hsn, accent, featured, [ [label, packs, case price, case mrp, min qty] ... ] ] ]]
        $catalog = [
            'Chips & Crisps' => ['bubble_chart', [
                ['Classic Salted Chips', 'Royal Crunch', 'Thin-sliced potatoes, lightly salted.', 12, '20052000', '#F4C542', true, [['20g × 60 packs', 60, 540, 600, 2], ['52g × 36 packs', 36, 720, 800, 2]]],
                ['Masala Magic Chips', 'Royal Crunch', 'Desi masala kick in every bite.', 12, '20052000', '#E4572E', true, [['20g × 60 packs', 60, 540, 600, 2], ['52g × 36 packs', 36, 720, 800, 2]]],
                ['Cream & Onion Chips', 'Royal Crunch', 'Tangy cream and onion seasoning.', 12, '20052000', '#4C9F70', false, [['52g × 36 packs', 36, 720, 800, 2]]],
                ['Tomato Tango Chips', 'Royal Crunch', 'Sweet-sour tomato flavour.', 12, '20052000', '#C0392B', false, [['20g × 60 packs', 60, 540, 600, 2]]],
                ['Ridge Cut Chilli Lime', 'Royal Crunch', 'Ridged chips with a chilli-lime punch.', 12, '20052000', '#2E8B57', false, [['45g × 40 packs', 40, 680, 760, 2]]],
            ]],
            'Namkeen & Mixtures' => ['restaurant', [
                ['Aloo Bhujia', 'Shahi Namkeen', 'Crispy potato-gram noodles with spices.', 12, '19059090', '#E9A23B', true, [['40g × 48 packs', 48, 720, 800, 2], ['200g × 24 packs', 24, 1920, 2160, 1]]],
                ['Moong Dal', 'Shahi Namkeen', 'Crunchy fried moong dal, lightly salted.', 12, '19059090', '#D4A017', false, [['40g × 48 packs', 48, 720, 800, 2], ['200g × 24 packs', 24, 1800, 2000, 1]]],
                ['Khatta Meetha Mixture', 'Shahi Namkeen', 'Sweet-and-sour Indian snack mix.', 12, '19059090', '#B5651D', false, [['35g × 50 packs', 50, 650, 750, 2]]],
                ['Bikaneri Bhujia', 'Shahi Namkeen', 'Bikaner-style spicy sev.', 12, '19059090', '#CC5500', true, [['40g × 48 packs', 48, 740, 820, 2], ['400g × 12 packs', 12, 2000, 2250, 1]]],
                ['Peanut Masala', 'Shahi Namkeen', 'Masala-coated roasted peanuts.', 12, '20081100', '#A0522D', false, [['35g × 50 packs', 50, 700, 800, 2]]],
            ]],
            'Biscuits & Cookies' => ['cookie', [
                ['Glucose Biscuits', 'Royal Bakes', 'Everyday energy biscuits.', 18, '19053100', '#F2C94C', true, [['60g × 60 packs', 60, 600, 660, 3], ['120g × 36 packs', 36, 780, 860, 2]]],
                ['Cream Sandwich Biscuits', 'Royal Bakes', 'Vanilla cream-filled sandwich biscuits.', 18, '19053100', '#8E6BBF', false, [['55g × 48 packs', 48, 720, 800, 2]]],
                ['Butter Cookies', 'Royal Bakes', 'Rich, buttery cookies.', 18, '19053100', '#E0A458', true, [['75g × 40 packs', 40, 880, 980, 2]]],
                ['Marie Tea Biscuits', 'Royal Bakes', 'Light biscuits made for chai.', 18, '19053100', '#D9B382', false, [['150g × 30 packs', 30, 810, 900, 2]]],
                ['Choco Chip Cookies', 'Royal Bakes', 'Loaded with choco chips.', 18, '19053100', '#5D4037', false, [['60g × 48 packs', 48, 960, 1080, 2]]],
            ]],
            'Instant Noodles & Pasta' => ['ramen_dining', [
                ['Masala Instant Noodles', 'Royal Bowl', 'Classic masala 2-minute noodles.', 12, '19023010', '#F57C00', true, [['70g × 48 packs', 48, 576, 648, 3]]],
                ['Chicken-Style Veg Noodles', 'Royal Bowl', 'Veg noodles with savoury seasoning.', 12, '19023010', '#EF6C00', false, [['70g × 48 packs', 48, 600, 672, 3]]],
                ['Macaroni Masala', 'Royal Bowl', 'Cheesy-masala macaroni pasta.', 12, '19023090', '#FBC02D', false, [['65g × 48 packs', 48, 648, 720, 2]]],
            ]],
            'Beverages' => ['local_drink', [
                ['Mango Fruit Drink', 'Royal Sip', 'Ready-to-drink mango fruit beverage.', 12, '22029990', '#FFB300', true, [['200ml × 24 packs', 24, 360, 420, 3], ['1L × 12 packs', 12, 720, 840, 2]]],
                ['Jeera Soda', 'Royal Sip', 'Spiced cumin sparkling soda.', 28, '22021090', '#8BC34A', false, [['250ml × 24 packs', 24, 400, 480, 3]]],
                ['Lemon Fizz', 'Royal Sip', 'Zesty lemon carbonated drink.', 28, '22021090', '#C0CA33', false, [['250ml × 24 packs', 24, 400, 480, 3]]],
                ['Masala Chaas', 'Royal Sip', 'Spiced buttermilk, ready to drink.', 12, '04039090', '#FFF8E1', false, [['200ml × 30 packs', 30, 330, 390, 2]]],
            ]],
            'Chocolates & Sweets' => ['icecream', [
                ['Milk Choco Bar', 'Royal Treats', 'Creamy milk chocolate bar.', 18, '18069010', '#6D4C41', true, [['25g × 72 packs', 72, 1080, 1260, 2]]],
                ['Wafer Roll Chocolate', 'Royal Treats', 'Crispy wafer rolls with choco filling.', 18, '19053200', '#A1887F', false, [['30g × 48 packs', 48, 840, 960, 2]]],
                ['Fruit Gummies', 'Royal Treats', 'Chewy assorted fruit gummies.', 18, '17049090', '#E91E63', false, [['20g × 80 packs', 80, 760, 880, 2]]],
                ['Kaju Katli Box', 'Royal Treats', 'Classic cashew fudge gift boxes.', 5, '17049090', '#CFD8DC', false, [['250g × 12 boxes', 12, 3000, 3360, 1]]],
            ]],
            'Popcorn & Roasted Snacks' => ['sports_bar', [
                ['Butter Popcorn', 'Royal Pop', 'Ready-to-eat buttery popcorn.', 12, '19041010', '#FFCA28', false, [['30g × 50 packs', 50, 600, 700, 2]]],
                ['Roasted Makhana Peri-Peri', 'Royal Pop', 'Light roasted fox nuts, peri-peri.', 12, '20089900', '#D84315', true, [['30g × 40 packs', 40, 1040, 1200, 2]]],
                ['Roasted Chana', 'Royal Pop', 'Dry-roasted chickpeas.', 5, '20081100', '#8D6E63', false, [['100g × 30 packs', 30, 900, 1020, 2]]],
            ]],
            'Rusk, Papad & Others' => ['bakery_dining', [
                ['Elaichi Rusk', 'Royal Bakes', 'Cardamom tea-time rusk.', 18, '19059010', '#CDA434', false, [['200g × 24 packs', 24, 1200, 1320, 2]]],
                ['Urad Papad', 'Shahi Namkeen', 'Classic spiced urad dal papad.', 5, '19059040', '#BCAAA4', false, [['200g × 30 packs', 30, 1350, 1500, 2]]],
            ]],
        ];

        $sort = 0;
        $variants = [];
        foreach ($catalog as $catName => [$icon, $products]) {
            $cat = Category::updateOrCreate(['slug' => Str::slug($catName)], ['name' => $catName, 'icon' => $icon, 'sort_order' => $sort++]);
            foreach ($products as [$name, $brand, $desc, $gst, $hsn, $accent, $featured, $vs]) {
                $p = Product::updateOrCreate(['slug' => Str::slug($name)], [
                    'category_id' => $cat->id, 'name' => $name, 'brand' => $brand, 'description' => $desc,
                    'gst_rate' => $gst, 'hsn_code' => $hsn, 'accent' => $accent, 'is_featured' => $featured,
                ]);
                foreach ($vs as [$label, $packs, $price, $mrp, $min]) {
                    $variants[] = ProductVariant::updateOrCreate(['sku' => strtoupper(Str::slug($name . '-' . $packs . '-' . Str::before($label, ' '), '-'))], [
                        'product_id' => $p->id, 'label' => $label, 'packs_per_case' => $packs,
                        'price' => $price, 'mrp' => $mrp, 'min_qty' => $min,
                    ]);
                }
            }
        }

        // Stock: the main warehouse is deepest; DCs hold a working quantity.
        $stock = app(StockService::class);
        $hubs = Hub::all();
        foreach ($variants as $v) {
            foreach ($hubs as $h) {
                $qty = $h->isMain() ? random_int(300, 600) : random_int(80, 200);
                $stock->adjust($h->id, $v->id, $qty, null, 'Opening stock');
            }
        }
    }
}
