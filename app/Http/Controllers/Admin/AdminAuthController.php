<?php
/*
 * HLStatsX Community Edition - Laravel Rebase
 * A modern Laravel 13 rewrite of the HLStatsX:CE web frontend, preserving the original MySQL schema.
 *
 * A long lineage of open-source stats for Half-Life & Source engine games:
 *   HLstats (Simon Garner, 2001) -> HLstatsX (Tobias Oetzel, 2005)
 *   -> HLstatsX:CE (Nicholas Hastings, 2008) -> This rebase (Royal-Multi-Gamers, 2026)
 *
 * Perl daemon sourced from SnipeZilla/HLSTATS-2.
 *
 * Copyright (C) 2025-2026 Royal-Multi-Gamers
 * Licensed under the GNU General Public License v2.0
 * https://www.gnu.org/licenses/gpl-2.0.html
 *
 * https://github.com/Royal-Multi-Gamers/hlstatsx-community-edition-laravel
 */

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminAuthController extends Controller
{
    public function showLogin()
    {
        return view('admin.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => ['required'],
            'password' => ['required'],
        ]);

        // Normal bcrypt login (hlstats_Admins)
        if (Auth::guard('admin')->attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            return redirect()->intended(route('admin.dashboard'));
        }

        // Legacy fallback (hlstats_Users): bcrypt for accounts created from the
        // admin panel, MD5 only for rows inherited from the Perl daemon.
        $legacy = DB::table('hlstats_Users')
            ->where('username', $credentials['username'])
            ->first();

        if ($legacy && $this->legacyPasswordMatches($credentials['password'], (string) $legacy->password)) {
            $request->session()->put('admin_migrate_user', $legacy->username);
            return redirect()->route('admin.migrate-password');
        }

        return back()->withErrors(['username' => 'Identifiants invalides.'])->onlyInput('username');
    }

    /**
     * A stored hash is a legacy MD5 digest only when it is exactly 32 hex
     * characters; anything longer is a modern hash and must go through Hash.
     */
    private function legacyPasswordMatches(string $plain, string $stored): bool
    {
        if ($stored === '') {
            return false;
        }

        if (strlen($stored) === 32 && ctype_xdigit($stored)) {
            return hash_equals($stored, md5($plain));
        }

        return Hash::check($plain, $stored);
    }

    public function showMigratePassword(Request $request)
    {
        if (! $request->session()->has('admin_migrate_user')) {
            return redirect()->route('admin.login');
        }

        return view('admin.migrate-password', [
            'username' => $request->session()->get('admin_migrate_user'),
        ]);
    }

    public function migratePassword(Request $request)
    {
        $username = $request->session()->get('admin_migrate_user');

        if (! $username) {
            return redirect()->route('admin.login');
        }

        $request->validate([
            'password'              => ['required', 'min:8', 'confirmed'],
            'password_confirmation' => ['required'],
        ]);

        $legacy = DB::table('hlstats_Users')->where('username', $username)->first();

        if (! $legacy) {
            return redirect()->route('admin.login');
        }

        $accessLevel = $legacy->acclevel >= 100 ? 'superadmin' : 'admin';

        DB::table('hlstats_Admins')->updateOrInsert(
            ['username' => $username],
            ['password' => Hash::make($request->password), 'accessLevel' => $accessLevel]
        );

        $request->session()->forget('admin_migrate_user');

        $admin = Admin::where('username', $username)->first();
        Auth::guard('admin')->login($admin);
        $request->session()->regenerate();

        return redirect()->route('admin.dashboard');
    }

    public function logout(Request $request)
    {
        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('admin.login');
    }
}
