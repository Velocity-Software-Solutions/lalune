<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Faker\Factory as Faker;

class ProductReviewSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create();

        // Ensure the folder exists (images are optional; you can drop files here later)
        Storage::disk('public')->makeDirectory('review-images');

        // If you want to sometimes attach a placeholder image, set this to an existing file under storage/app/public
        $maybeImage = function () use ($faker) {
            // ~30% of reviews get an image; adjust to taste
            if (mt_rand(1, 100) <= 30) {
                // Put a real file at: storage/app/public/review-images/placeholder.jpg
                $placeholder = 'review-images/placeholder.jpg';
                return Storage::disk('public')->exists($placeholder) ? $placeholder : null;
            }
            return null;
        };

        $halfStep = function () {
            // 0.5 … 5.0 in 0.5 increments
            return mt_rand(1, 10) / 2;
        };

        $statusPick = function () {
            // 70% approved, 30% pending
            return mt_rand(1, 100) <= 70 ? 'approved' : 'pending';
        };

        $users = \App\Models\User::query()->inRandomOrder()->get(['id', 'name', 'email']);

        Product::with('reviews')->chunk(100, function ($products) use ($faker, $maybeImage, $halfStep, $statusPick, $users) {
            foreach ($products as $product) {
                // 3–10 reviews per product
                $count = mt_rand(3, 10);

                $payload = [];
                for ($i = 0; $i < $count; $i++) {
                    $attachedUser = $users->isNotEmpty() && mt_rand(0, 1) ? $users->random() : null;

                    $payload[] = [
                        'product_id' => "9",
                        'author_name' => $attachedUser->name ?? $faker->name(),
                        'author_email' => $attachedUser->email ?? $faker->safeEmail(),
                        'rating' => $halfStep(),
                        'comment' => $faker->sentences(mt_rand(1, 3), true),
                        'image_path' => $maybeImage(),                           // e.g. 'review-images/placeholder.jpg' or null
                        'status' => $statusPick(),                           // 'approved' | 'pending'
                        'created_at' => $faker->dateTimeBetween('-11 months', 'now'),
                        'updated_at' => now(),
                    ];
                }

                // Use the relation so we don't need to reference a specific Review model class
                $product->reviews()->insert($payload);
            }
        });
    }
}
