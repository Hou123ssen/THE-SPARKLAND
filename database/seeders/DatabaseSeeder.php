<?php

namespace Database\Seeders;

use App\Models\Collection;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $collections = [
            [
                'name' => 'Essential Forms',
                'slug' => 'essential-forms',
                'description' => 'Quietly considered silhouettes built for everyday clarity.',
                'display_order' => 1,
            ],
            [
                'name' => 'Afterlight Editions',
                'slug' => 'afterlight-editions',
                'description' => 'Expressive frames with a sharper point of view.',
                'display_order' => 2,
            ],
        ];

        $products = [
            [
                ['name' => 'Axis 01', 'slug' => 'axis-01', 'price' => 245, 'color' => 'Ink Black', 'material' => 'Acetate', 'lens' => 'Clear CR-39', 'frame' => 'Soft square', 'dimensions' => '49-20-145', 'description' => 'A balanced everyday frame with a clean architectural brow.'],
                ['name' => 'Line 02', 'slug' => 'line-02', 'price' => 265, 'color' => 'Warm Tortoise', 'material' => 'Bio-acetate', 'lens' => 'Clear CR-39', 'frame' => 'Rounded rectangle', 'dimensions' => '48-21-145', 'description' => 'A softly rounded profile with a precise, lightweight fit.'],
                ['name' => 'Still 03', 'slug' => 'still-03', 'price' => 230, 'color' => 'Smoke Grey', 'material' => 'Acetate', 'lens' => 'Blue-light filter', 'frame' => 'Oval', 'dimensions' => '47-22-145', 'description' => 'An understated oval designed for long days and clear focus.'],
                ['name' => 'Frame 04', 'slug' => 'frame-04', 'price' => 280, 'color' => 'Deep Olive', 'material' => 'Bio-acetate', 'lens' => 'Clear CR-39', 'frame' => 'Panto', 'dimensions' => '50-20-145', 'description' => 'A compact panto shape with a calm, tactile finish.'],
                ['name' => 'North 05', 'slug' => 'north-05', 'price' => 250, 'color' => 'Sand Crystal', 'material' => 'Acetate', 'lens' => 'Clear CR-39', 'frame' => 'Angular square', 'dimensions' => '51-19-145', 'description' => 'A confident square silhouette softened by translucent color.'],
                ['name' => 'Field 06', 'slug' => 'field-06', 'price' => 295, 'color' => 'Brushed Graphite', 'material' => 'Titanium', 'lens' => 'Clear CR-39', 'frame' => 'Slim rectangle', 'dimensions' => '50-19-140', 'description' => 'A minimal metal frame tuned for an effortless daily uniform.'],
            ],
            [
                ['name' => 'Halo 07', 'slug' => 'halo-07', 'price' => 320, 'color' => 'Midnight Blue', 'material' => 'Acetate', 'lens' => 'Gradient smoke', 'frame' => 'Butterfly', 'dimensions' => '53-18-140', 'description' => 'A lifted shape that brings a little ceremony to the everyday.'],
                ['name' => 'Trace 08', 'slug' => 'trace-08', 'price' => 310, 'color' => 'Honey Fade', 'material' => 'Bio-acetate', 'lens' => 'Gradient amber', 'frame' => 'Cat-eye', 'dimensions' => '52-18-140', 'description' => 'A warm translucent frame with a deliberate, sculpted line.'],
                ['name' => 'Arc 09', 'slug' => 'arc-09', 'price' => 340, 'color' => 'Polished Black', 'material' => 'Titanium', 'lens' => 'Clear CR-39', 'frame' => 'Navigator', 'dimensions' => '52-19-145', 'description' => 'A fine metal navigator with graphic edges and a quiet shine.'],
                ['name' => 'Eclipse 10', 'slug' => 'eclipse-10', 'price' => 360, 'color' => 'Carbon Grey', 'material' => 'Acetate', 'lens' => 'Polarized smoke', 'frame' => 'Oversized square', 'dimensions' => '55-18-140', 'description' => 'A generous silhouette made for bright streets and long horizons.'],
                ['name' => 'Muse 11', 'slug' => 'muse-11', 'price' => 335, 'color' => 'Rose Quartz', 'material' => 'Bio-acetate', 'lens' => 'Gradient rose', 'frame' => 'Soft cat-eye', 'dimensions' => '51-19-140', 'description' => 'A refined cat-eye with a subtle color story and polished balance.'],
                ['name' => 'Orbit 12', 'slug' => 'orbit-12', 'price' => 380, 'color' => 'Silver Mist', 'material' => 'Titanium', 'lens' => 'Polarized green', 'frame' => 'Round panto', 'dimensions' => '50-20-145', 'description' => 'A weightless round frame with a technical edge and soft presence.'],
            ],
        ];

        foreach ($collections as $collectionIndex => $collectionData) {
            $collection = Collection::create($collectionData);

            foreach ($products[$collectionIndex] as $productIndex => $productData) {
                $product = Product::create([
                    ...$productData,
                    'collection_id' => $collection->id,
                    'display_order' => $productIndex + 1,
                ]);

                ProductImage::create([
                    'product_id' => $product->id,
                    'image_path' => '/images/glasses-front.png',
                    'type' => 'front',
                    'display_order' => 1,
                ]);

                ProductImage::create([
                    'product_id' => $product->id,
                    'image_path' => '/images/glasses-angle.png',
                    'type' => 'angle',
                    'display_order' => 2,
                ]);
            }
        }
    }
}
