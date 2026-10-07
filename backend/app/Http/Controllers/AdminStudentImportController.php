<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AdminStudentImportController extends Controller
{
    public function import(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'csv' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $imported = [];
        $skipped = [];

        foreach ($this->parseCsv($validated['csv']) as $row) {
            // The CSV's 5th column ("password") is stored as a hashed local-password fallback,
            // used when LDAP is disabled for students or the directory server is unreachable.
            [$lastName, $firstName, $class, $username, $password] = $row;

            $rowValidator = Validator::make(
                ['last_name' => $lastName, 'first_name' => $firstName, 'class' => $class, 'username' => $username],
                [
                    'last_name'  => ['required', 'string', 'max:100'],
                    'first_name' => ['required', 'string', 'max:100'],
                    'class'      => ['required', 'string', 'max:50'],
                    'username'   => ['required', 'string', 'max:100', 'unique:users,ldap_username'],
                ]
            );

            if ($rowValidator->fails()) {
                $skipped[] = ['username' => $username, 'reason' => implode(' ', $rowValidator->errors()->all())];

                continue;
            }

            User::create([
                'name'          => $firstName . ' ' . $lastName,
                'first_name'    => $firstName,
                'last_name'     => $lastName,
                'ldap_username' => $username,
                'class'         => $class,
                'role'          => User::ROLE_STUDENT,
                'password'      => $password !== '' ? Hash::make($password) : null,
            ]);

            $imported[] = $username;
        }

        return response()->json([
            'imported_count' => count($imported),
            'imported'       => $imported,
            'skipped'        => $skipped,
        ]);
    }

    /** @return array<int, array{0: string, 1: string, 2: string, 3: string, 4: string}> */
    private function parseCsv(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');
        $rows = [];

        fgetcsv($handle); // skip header row (lastname, firstname, class, username, password)

        while (($row = fgetcsv($handle)) !== false) {
            if (count(array_filter($row, fn ($cell) => trim((string) $cell) !== '')) === 0) {
                continue;
            }

            $rows[] = [
                trim($row[0] ?? ''),
                trim($row[1] ?? ''),
                trim($row[2] ?? ''),
                trim($row[3] ?? ''),
                trim($row[4] ?? ''),
            ];
        }

        fclose($handle);

        return $rows;
    }
}
