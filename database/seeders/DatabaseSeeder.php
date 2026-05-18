<?php

namespace Database\Seeders;

use App\Models\Pengguna;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $administrator = Pengguna::firstOrNew([
            'username' => 'administrator',
        ]);

        $administrator->fill([
            'nama' => 'Administrator NUSA',
            'peran' => 'administrator',
            'aktif' => true,
            'akun_sistem' => true,
        ]);

        if (! $administrator->exists) {
            $administrator->kata_sandi = Hash::make('administrator');
        }

        $administrator->save();
    }
}
