<?php

namespace App\Http\Controllers\Accountant;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ReportDownloadController extends Controller
{
    public function __invoke(Request $request, string $file)
    {
        abort_unless($request->hasValidSignature(), 403, 'Invalid or expired download link.');

        $user = $request->user();
        abort_unless($user, 401);

        $hasAccountantAccess = ($user->role === UserRole::ACCOUNTANT)
            || $user->hasAnyRole(['Accountant', 'accountant', 'ACCOUNTANT']);

        abort_unless($hasAccountantAccess, 403);

        $file = ltrim($file, '/');

        if (str_contains($file, '..')) {
            abort(403, 'Invalid file path.');
        }

        $expectedPrefix = 'accountant-reports/'.$user->id.'/';
        if (! str_starts_with($file, $expectedPrefix)) {
            abort(403, 'You can only download your own report files.');
        }

        $absolutePath = storage_path('app/'.$file);

        abort_unless(is_file($absolutePath), 404, 'Report file not found.');

        return response()->download($absolutePath, basename($file));
    }
}