<?php

namespace Database\Seeders;

use App\Models\role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [
                'name' => 'admin',
                'description' => 'Administrator with full access to manage the platform',
            ],
            [
                'name' => 'owner',
                'description' => 'Property or room owner who can list and manage rooms',
            ],
            [
                'name' => 'customer',
                'description' => 'Customer / Tenant who can browse and rent rooms',
            ],
        ];

        foreach ($roles as $r) {
            role::updateOrCreate(
                ['name' => $r['name']],
                $r
            );
        }
    }
}
