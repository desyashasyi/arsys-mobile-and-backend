<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;

class MigrateLaratrustToSpatie extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // --- Step 1: Rename old Laratrust tables (if they exist and haven't been renamed) ---
        if (Schema::hasTable('roles') && !Schema::hasTable('laratrust_roles')) {
            Schema::rename('roles', 'laratrust_roles');
        }
        if (Schema::hasTable('permissions') && !Schema::hasTable('laratrust_permissions')) {
            Schema::rename('permissions', 'laratrust_permissions');
        }
        if (Schema::hasTable('role_user') && !Schema::hasTable('laratrust_role_user')) {
            Schema::rename('role_user', 'laratrust_role_user');
        }
        if (Schema::hasTable('permission_role') && !Schema::hasTable('laratrust_permission_role')) {
            Schema::rename('permission_role', 'laratrust_permission_role');
        }
        if (Schema::hasTable('permission_user') && !Schema::hasTable('laratrust_permission_user')) {
            Schema::rename('permission_user', 'laratrust_permission_user');
        }

        // --- Step 2: Create new Spatie tables (if they don't exist) ---
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');

        if (!Schema::hasTable($tableNames['permissions'])) {
            Schema::create($tableNames['permissions'], function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name');
                $table->string('guard_name');
                $table->timestamps();
                $table->unique(['name', 'guard_name']);
            });
        }

        if (!Schema::hasTable($tableNames['roles'])) {
            Schema::create($tableNames['roles'], function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name');
                $table->string('guard_name');
                $table->timestamps();
                $table->unique(['name', 'guard_name']);
            });
        }

        if (!Schema::hasTable($tableNames['model_has_permissions'])) {
            Schema::create($tableNames['model_has_permissions'], function (Blueprint $table) use ($tableNames, $columnNames) {
                $table->unsignedBigInteger(config('permission.column_names.permission_pivot_key') ?? 'permission_id');
                $table->string('model_type');
                $table->unsignedBigInteger($columnNames['model_morph_key']);
                $table->index([$columnNames['model_morph_key'], 'model_type'], 'model_has_permissions_model_id_model_type_index');
                $table->foreign(config('permission.column_names.permission_pivot_key') ?? 'permission_id')->references('id')->on($tableNames['permissions'])->onDelete('cascade');
                $table->primary([config('permission.column_names.permission_pivot_key') ?? 'permission_id', $columnNames['model_morph_key'], 'model_type'], 'model_has_permissions_permission_model_type_primary');
            });
        }

        if (!Schema::hasTable($tableNames['model_has_roles'])) {
            Schema::create($tableNames['model_has_roles'], function (Blueprint $table) use ($tableNames, $columnNames) {
                $table->unsignedBigInteger(config('permission.column_names.role_pivot_key') ?? 'role_id');
                $table->string('model_type');
                $table->unsignedBigInteger($columnNames['model_morph_key']);
                $table->index([$columnNames['model_morph_key'], 'model_type'], 'model_has_roles_model_id_model_type_index');
                $table->foreign(config('permission.column_names.role_pivot_key') ?? 'role_id')->references('id')->on($tableNames['roles'])->onDelete('cascade');
                $table->primary([config('permission.column_names.role_pivot_key') ?? 'role_id', $columnNames['model_morph_key'], 'model_type'], 'model_has_roles_role_model_type_primary');
            });
        }

        if (!Schema::hasTable($tableNames['role_has_permissions'])) {
            Schema::create($tableNames['role_has_permissions'], function (Blueprint $table) use ($tableNames) {
                $table->unsignedBigInteger(config('permission.column_names.permission_pivot_key') ?? 'permission_id');
                $table->unsignedBigInteger(config('permission.column_names.role_pivot_key') ?? 'role_id');
                $table->foreign(config('permission.column_names.permission_pivot_key') ?? 'permission_id')->references('id')->on($tableNames['permissions'])->onDelete('cascade');
                $table->foreign(config('permission.column_names.role_pivot_key') ?? 'role_id')->references('id')->on($tableNames['roles'])->onDelete('cascade');
                $table->primary([config('permission.column_names.permission_pivot_key') ?? 'permission_id', config('permission.column_names.role_pivot_key') ?? 'role_id'], 'role_has_permissions_permission_id_role_id_primary');
            });
        }

        app('cache')->forget(config('permission.cache.key'));

        // --- Step 3: Migrate data (Clear tables first for idempotency) ---
        Schema::disableForeignKeyConstraints();
        DB::table($tableNames['model_has_permissions'])->truncate();
        DB::table($tableNames['model_has_roles'])->truncate();
        DB::table($tableNames['role_has_permissions'])->truncate();
        DB::table($tableNames['roles'])->truncate();
        DB::table($tableNames['permissions'])->truncate();
        Schema::enableForeignKeyConstraints();

        $roleMap = [];
        if (Schema::hasTable('laratrust_roles')) {
            $laratrustRoles = DB::table('laratrust_roles')->get();
            foreach ($laratrustRoles as $laratrustRole) {
                $newRole = Role::create(['name' => $laratrustRole->name, 'guard_name' => 'web']);
                $roleMap[$laratrustRole->id] = $newRole;
            }
        }

        $permissionMap = [];
        if (Schema::hasTable('laratrust_permissions')) {
            $laratrustPermissions = DB::table('laratrust_permissions')->get();
            foreach ($laratrustPermissions as $laratrustPermission) {
                $newPermission = Permission::create(['name' => $laratrustPermission->name, 'guard_name' => 'web']);
                $permissionMap[$laratrustPermission->id] = $newPermission;
            }
        }

        if (Schema::hasTable('laratrust_role_user')) {
            $laratrustRoleUser = DB::table('laratrust_role_user')->get();
            foreach ($laratrustRoleUser as $roleUser) {
                $user = User::find($roleUser->user_id);
                $role = $roleMap[$roleUser->role_id] ?? null;
                if ($user && $role) {
                    $user->assignRole($role);
                }
            }
        }

        if (Schema::hasTable('laratrust_permission_role')) {
            $laratrustPermissionRole = DB::table('laratrust_permission_role')->get();
            foreach ($laratrustPermissionRole as $permissionRole) {
                $role = $roleMap[$permissionRole->role_id] ?? null;
                $permission = $permissionMap[$permissionRole->permission_id] ?? null;
                if ($role && $permission) {
                    $role->givePermissionTo($permission);
                }
            }
        }

        if (Schema::hasTable('laratrust_permission_user')) {
            $laratrustPermissionUser = DB::table('laratrust_permission_user')->get();
            foreach ($laratrustPermissionUser as $permissionUser) {
                $user = User::find($permissionUser->user_id);
                $permission = $permissionMap[$permissionUser->permission_id] ?? null;
                if ($user && $permission) {
                    $user->givePermissionTo($permission);
                }
            }
        }

        // --- Step 4: Drop old Laratrust tables ---
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('laratrust_permission_role');
        Schema::dropIfExists('laratrust_permission_user');
        Schema::dropIfExists('laratrust_role_user');
        Schema::dropIfExists('laratrust_roles');
        Schema::dropIfExists('laratrust_permissions');
        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Reversing this complex migration is not recommended.
        // For safety, we'll only drop the Spatie tables.
        $tableNames = config('permission.table_names');
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists($tableNames['role_has_permissions']);
        Schema::dropIfExists($tableNames['model_has_roles']);
        Schema::dropIfExists($tableNames['model_has_permissions']);
        Schema::dropIfExists($tableNames['roles']);
        Schema::dropIfExists($tableNames['permissions']);
        Schema::enableForeignKeyConstraints();
    }
}
