<?php

namespace App\Helpers;

class StatusHelper
{
    /**
     * @param string $type Tipe data yang ingin ditampilkan warnanya
     * @param string $data Data untuk difilter
     */
    public static function getColor(string $type, string $data)
    {
        try {
            $payload = [
                'status_pelayanan' => [
                    'Sudah' => 'bg-green-100 text-green-700',
                    'Belum' => 'bg-yellow-100 text-yellow-700',
                    'Batal' => 'bg-indigo-100 text-indigo-700',
                    'Dirujuk' => 'bg-cyan-100 text-cyan-700',
                    'Berkas Diterima' => 'bg-pink-100 text-pink-700',
                    'Dirawat' => 'bg-violet-100 text-violet-700',
                    'Meninggal' => 'bg-red-100 text-red-700',
                    'Pulang Paksa' => 'bg-black/10 text-black',
                ]
            ];

            return $payload[$type][$data];
        } catch (\Exception $e) {
            return 'bg-gray-100 text-gray-700';
        }
    }
}
