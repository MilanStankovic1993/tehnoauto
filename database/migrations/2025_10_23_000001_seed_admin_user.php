<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ➜ podesivo: iz .env ili fallback na podrazumevane vrednosti
        $email = env('ADMIN_EMAIL', 'admin@tehnoauto.rs');
        $name  = env('ADMIN_NAME',  'Administrator');
        $pass  = env('ADMIN_PASSWORD', 'admin1234');

        DB::transaction(function () use ($email, $name, $pass) {
            // 1) Kreiraj admin user-a ako ne postoji
            $user = DB::table('users')->where('email', $email)->first();

            if (! $user) {
                $userId = DB::table('users')->insertGetId([
                    'name' => $name,
                    'email' => $email,
                    'password' => Hash::make($pass),
                    'email_verified_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $user = (object) ['id' => $userId];
            }

            // 2) Ako postoje Spatie Permission tabele, kreiraj rolu i dodeli
            if (
                Schema::hasTable('roles') &&
                Schema::hasTable('model_has_roles')
            ) {
                // kreiraj "Super Admin" ako ne postoji
                $role = DB::table('roles')->where('name', 'Super Admin')->first();

                if (! $role) {
                    // guard_name obično "web"
                    $roleId = DB::table('roles')->insertGetId([
                        'name'       => 'Super Admin',
                        'guard_name' => config('auth.defaults.guard', 'web'),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $role = (object) ['id' => $roleId];
                }

                // poveži user ↔ role ako već nije povezano
                $already = DB::table('model_has_roles')
                    ->where('role_id', $role->id)
                    ->where('model_id', $user->id)
                    ->where('model_type', config('auth.providers.users.model', 'App\\Models\\User'))
                    ->exists();

                if (! $already) {
                    DB::table('model_has_roles')->insert([
                        'role_id'    => $role->id,
                        'model_type' => config('auth.providers.users.model', 'App\\Models\\User'),
                        'model_id'   => $user->id,
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        // Namerno ne brišemo korisnika/rolu pri rollback-u.
    }
};
