<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
public function run(): void
    {
        $products = [
            [
                'name' => 'Elegant Evening Dress',
                'name_ar' => 'فستان سهرة أنيق',
                'description' => 'A stylish evening dress perfect for special occasions.',
                'description_ar' => 'فستان أنيق مثالي للمناسبات الخاصة.',
                'price' => 120.00,
                'discount_price' => 95.00,
                'stock_quantity' => 15,
                'condition' => 'new',
                'status' => true,
                'category' => 'Dresses',
            ],
            [
                'name' => 'Casual Summer Dress',
                'name_ar' => 'فستان صيفي كاجوال',
                'description' => 'Lightweight cotton dress for daily wear.',
                'description_ar' => 'فستان قطني خفيف مناسب للاستخدام اليومي.',
                'price' => 60.00,
                'discount_price' => null,
                'stock_quantity' => 25,
                'condition' => 'new',
                'status' => true,
                'category' => 'Dresses',
            ],
            [
                'name' => 'Silk Chemise',
                'name_ar' => 'قميص نوم حرير',
                'description' => 'Luxurious silk chemise for comfort and elegance.',
                'description_ar' => 'قميص نوم حريري فاخر يمنح الراحة والأناقة.',
                'price' => 80.00,
                'discount_price' => 70.00,
                'stock_quantity' => 20,
                'condition' => 'new',
                'status' => true,
                'category' => 'Chemises',
            ],
            [
                'name' => 'Lace Chemise',
                'name_ar' => 'قميص نوم دانتيل',
                'description' => 'Beautiful lace chemise with a soft touch.',
                'description_ar' => 'قميص نوم دانتيل جميل بملمس ناعم.',
                'price' => 65.00,
                'discount_price' => null,
                'stock_quantity' => 10,
                'condition' => 'new',
                'status' => true,
                'category' => 'Chemises',
            ],
        ];

        foreach ($products as $item) {
            $category = Category::where('slug', Str::slug($item['category']))->first();

            if ($category) {
                Product::updateOrCreate(
                    ['slug' => Str::slug($item['name'])],
                    [
                        'name' => $item['name'],
                        'name_ar' => $item['name_ar'],
                        'slug' => Str::slug($item['name']),
                        'sku' => strtoupper(Str::random(8)),
                        'description' => $item['description'],
                        'description_ar' => $item['description_ar'],
                        'price' => $item['price'],
                        'discount_price' => $item['discount_price'],
                        'stock_quantity' => $item['stock_quantity'],
                        'condition' => $item['condition'],
                        'status' => $item['status'],
                        'category_id' => $category->id,
                    ]
                );
            }
        }
    }
}
