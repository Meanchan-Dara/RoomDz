<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Private Room',
                'slug' => 'private-room',
                'image' => 'https://images.unsplash.com/photo-1598928506311-c55ded91a20c?auto=format&fit=crop&w=400&q=80',
                'description' => 'Comfortable private rooms perfect for single occupancy or students.',
            ],
            [
                'name' => 'Studio Apartment',
                'slug' => 'studio-apartment',
                'image' => 'https://images.unsplash.com/photo-1502672260266-1c1ef2d93688?auto=format&fit=crop&w=400&q=80',
                'description' => 'Self-contained open layout studios featuring private kitchen and bath.',
            ],
            [
                'name' => 'Shared Room',
                'slug' => 'shared-room',
                'image' => 'https://images.unsplash.com/photo-1555854877-bab0e564b8d5?auto=format&fit=crop&w=400&q=80',
                'description' => 'Budget-friendly shared spaces for travelers and roommates.',
            ],
            [
                'name' => 'Condo',
                'slug' => 'condo',
                'image' => 'https://images.unsplash.com/photo-1545324418-cc1a3fa10c00?auto=format&fit=crop&w=400&q=80',
                'description' => 'Modern luxury condominium units with swimming pool and gym access.',
            ],
        ];

        foreach ($categories as $cat) {
            Category::updateOrCreate(
                ['slug' => $cat['slug']],
                $cat
            );
        }
    }
}
