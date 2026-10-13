<?php

namespace Database\Seeders;

use App\Models\RustdeskDevice;
use Illuminate\Database\Seeder;

class RustdeskDevicesSeeder extends Seeder
{
    public function run(): void
    {
        $rawLines = [
            "491-601\t\t443 710 821\tMiloszK",
            "491-637\t\t270 512 957\tPlocharczykK",
            "491-643\t\t270 513 001\tWojtowiczJ",
            "491-657\t\t694 537 81\tCiupkaW",
            "491-662\t\t918 932 98\tWyrzykowskiE",
            "491-666\t\t510 974 618\tWojtowiczJ Narzedziownia",
            "491-672\t\t511 323 270\tDymnickiR",
            "\t\t167 793 870\tHiszpania",
            "491-480\t\t433 348 119\tMPlotka",
            "SPIDER\t\t486 567 429\tRJagiello",
            "491-647\t\t105 487 726\tMWG",
            "487-002\t\t194 323 895\tOsten-Sacken",
            "491-595\t\t767 400 28\tKatarzyna Wrosz",
            "491-608\t\t444 427 812",
            "491-599\t\t480 275 906",
            "487-019\t\t584 462 34\tSkrzypinskiO",
            "487-003\t\t193 090 644\tSwiatekT",
            "491-602\t\t422 273 117",
            "487-031\t\t356 668 161\tLiszewskiM",
            "491-621\t\t447 149 059\tKowalczykJ",
            "491-670\t\t193 871 029\tJefimowP",
            "491-661\t\t192 646 321\tOrlowskiP",
            "487-023\t\t 89 169 285\tSzafranM",
            "491-584\t\t431 879\t087\tPalaszW",
            "487-003\t\t193 090 644",
            "491-633\t\t102 859 246\tJakubowskiM",
            "491-624\t\t309 230 527\tRytlewskaM",
            "491-517\t\t359 761 911\tStrażnikMocy",
            "491-618\t\t309 228 238\tMikosR (Elektroniczna)",
            "491-610 \t444 428 721 \tSkrzypkowskiJ",
            "491-628\t\t309 228 949\tSajkoD",
            "491-612\t\t444 430 971\tKorsztunM (hasło: Prezentacja2026)",
            "491-663\t\t192 646 692\tRoboczy Dell Marian",
            "491-660\t\t184 445 627\tLaptop SkibickaJ",
            "487-032\t\t502 232 937\tJoanna Bielawska",
            "491-570\t\t167 793 870\tSzyamńskaB",
            "491-669\t\t390 331 683\tRejmusS",
            "487-033\t\t208 144 004\tBielawskiRa",
            "491-632\t\t102 859 487\tKiedys MichonA",
            "491-682\t\t137 012 748 \tGetac",
            "487-004\t\t439 027 924\tPrzybytK",
            "491-597\t\t479 870 942\tChmielewskaP"
        ];

        foreach ($rawLines as $line) {
            // Podział według tabulacji lub wielu spacji
            $parts = preg_split('/\t+/', trim($line));

            $compNo = isset($parts[0]) ? trim($parts[0]) : '-';
            $rustId = isset($parts[1]) ? trim($parts[1]) : '';
            $rawUser = isset($parts[2]) ? trim($parts[2]) : '';

            // W przypadku rozbicia numeru przez tabulacje (np. 491-584)
            if (count($parts) > 3 && preg_match('/^\d+$/', $parts[2])) {
                $rustId .= ' ' . $parts[2];
                $rawUser = isset($parts[3]) ? trim($parts[3]) : '';
            }

            // Standaryzacja spacji w RustDesk ID (usuwamy wielokrotne spacje)
            $rustId = preg_replace('/\s+/', ' ', $rustId);

            // Wyciąganie nazwy użytkownika i uwag z ostatniej kolumny
            $userName = $rawUser ?: 'Nieprzypisany';
            $notes = null;

            if (empty($rawUser)) {
                $userName = 'Brak opisu';
            } elseif (str_contains($rawUser, '(')) {
                // Wyciąganie wartości w nawiasie do uwag, np. (hasło: ...)
                preg_match('/^(.*?)\s*\((.*?)\)$/', $rawUser, $matches);
                if (!empty($matches)) {
                    $userName = trim($matches[1]);
                    $notes = trim($matches[2]);
                }
            }

            RustdeskDevice::create([
                'computer_number' => $compNo ?: '-',
                'rustdesk_id'     => $rustId,
                'user_name'       => $userName,
                'notes'           => $notes,
            ]);
        }
    }
}