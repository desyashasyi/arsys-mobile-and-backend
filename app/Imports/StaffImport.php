<?php

namespace App\Imports;

use App\Models\ArSys\Staff;
use App\Models\ArSys\StaffType;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class StaffImport implements ToCollection, WithHeadingRow
{
    public array $errors  = [];
    public int   $updated = 0;
    public int   $created = 0;

    public function collection(Collection $rows): void
    {
        foreach ($rows as $i => $row) {
            $rowNum = $i + 2; // header = baris 1

            $sso = trim($row['sso'] ?? '');
            if (!$sso) {
                $this->errors[] = "Row {$rowNum}: kolom 'sso' kosong, dilewati.";
                continue;
            }

            $firstName = trim($row['first_name'] ?? '');
            if (!$firstName) {
                $this->errors[] = "Row {$rowNum}: kolom 'first_name' kosong, dilewati.";
                continue;
            }

            $typeCode = strtoupper(trim($row['staff_type'] ?? ''));
            $type     = $typeCode ? StaffType::where('code', $typeCode)->first() : null;

            $data = array_filter([
                'first_name'    => $firstName,
                'last_name'     => trim($row['last_name']    ?? '') ?: null,
                'front_title'   => trim($row['front_title']  ?? '') ?: null,
                'rear_title'    => trim($row['rear_title']   ?? '') ?: null,
                'employee_id'   => trim($row['employee_id']  ?? '') ?: null,
                'code'          => strtoupper(trim($row['code']      ?? '')) ?: null,
                'univ_code'     => strtoupper(trim($row['univ_code'] ?? '')) ?: null,
                'email'         => trim($row['email'] ?? '') ?: null,
                'phone'         => trim($row['phone'] ?? '') ?: null,
                'staff_type_id' => $type?->id,
            ], fn($v) => $v !== null);

            $existing = Staff::where('sso', $sso)->first();

            if ($existing) {
                $existing->update($data);
                $this->updated++;
            } else {
                Staff::create(array_merge($data, ['sso' => $sso]));
                $this->created++;
            }
        }
    }
}
